<?php /** @noinspection SqlNoDataSourceInspection */

declare(strict_types=1);

namespace IBRExplorer\Database\Structure;

use Exception;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Database\RepositoryConfig;
use IBRExplorer\Entity\Entity;

class Structure {

    private PostgreSQL $db;

    public function __construct(RepositoryConfig $config) {
        $this->db = new PostgreSQL($config);
    }

    /**
     * @throws Exception
     */
    public function updateStart(): void {
        $this->db->initDatabase();

        if ($this->isNewDatabase()) {
            $this->createSystemConfigTable();
        }

        $this->setProcessPid(getmypid());
        $this->processStructureFiles($this->getUpdateFiles());
        $this->setProcessPid(null, true);
    }

    /**
     * @throws Exception
     */
    private function isNewDatabase(): bool {
        return !$this->db->tableExists('system_config');
    }

    /**
     * @throws Exception
     */
    private function createSystemConfigTable(): void {
        $this->db->prepareStatement('
        CREATE TABLE system_config (
            "id" SERIAL PRIMARY KEY,
            "dbVersion" INT DEFAULT 0,
            "s3Storage" BOOLEAN NOT NULL DEFAULT FALSE,
            "updatePid" INT NULL,
            "lastUpdate" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            "backupPid" INT NULL,
            "lastBackup" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ')->execute();

        $this->db->prepareStatement("INSERT INTO system_config DEFAULT VALUES;")->execute();
    }

    /**
     * @throws Exception
     */
    private function setProcessPid(int $pid = null, bool $setLastUpdate = false): void {
        $row = ['updatePid' => $pid];

        if ($setLastUpdate) {
            $row['lastUpdate'] = date('Y-m-d H:i:s');
        }

        $this->db->updateRow('system_config', $row, 1);
    }

    /**
     * @throws Exception
     */
    private function processStructureFiles(array $updates): void {
        foreach ($updates as $update) {
            $version = (int)explode('.', $update)[0];
            $this->updateApply($update);
            $this->setDbVersion($version);
        }
    }

    /**
     * @throws Exception
     */
    private function updateApply(string $updateFile): void {
        $contents = json_decode(
            file_get_contents($this->db->getConfig()->structurePath . $updateFile), true
        );

        foreach ($contents as $updateContent) {
            switch ($updateContent['type'] ?? null) {
                case 'table':
                    $this->createTable($updateContent);

                    break;
                case 'columns':
                    $this->alterTable($updateContent);

                    break;
                case 'entities':
                    $this->insertEntities($updateContent);

                    break;
                case 'sql':
                    if ($this->shouldSkipSql($updateContent['sql'])) {
                        break;
                    }

                    $this->db->prepareStatement($updateContent['sql'])->execute();

                    break;
                default:
                    throw new Exception('Tipo da atualização não encontrado.');
            }
        }
    }

    /**
     * @throws Exception
     */
    private function createTable(array $create): void {
        $table = $create['name'];
        $quotedTable = $this->quoteIdentifier($table);

        $sql = '
            CREATE TABLE ' . $quotedTable . ' (
                "id" SERIAL PRIMARY KEY,
                "entityStatus" SMALLINT DEFAULT 1 NOT NULL CHECK ("entityStatus" IN (1,2,3)),
                "key" VARCHAR(16) UNIQUE NOT NULL,
                "createdAt" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "createdBy" INT NOT NULL,
                "updatedAt" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updatedBy" INT NOT NULL,
                "internalNotes" TEXT NULL
        ';

        $foreignKeys = [
            'CONSTRAINT fk_' . $table . '_user_createdBy FOREIGN KEY ("createdBy") REFERENCES "user"("id")',
            'CONSTRAINT fk_' . $table . '_user_updatedBy FOREIGN KEY ("updatedBy") REFERENCES "user"("id")'
        ];

        if (!empty($create['columns'])) {
            foreach ($create['columns'] as $column) {
                $sql .= ",\n" . $this->processColumn($table, $column, $foreignKeys);


            }
        }


        if (!empty($foreignKeys)) {
            $sql .= implode('', array_map(fn($key) => ",\n$key", $foreignKeys));
        }

        $sql .= "\n);";

        $indexStatements = [];

        if (!empty($create['indexes'])) {
            foreach ($create['indexes'] as $index) {
                $indexStatements[] = $this->processIndex($table, $index);
            }
        }

        $this->db->prepareStatement($sql)->execute();

        if (!empty($indexStatements)) {
            foreach ($indexStatements as $indexSql) {
                $this->db->prepareStatement($indexSql)->execute();
            }
        }
    }

    private function quoteIdentifier(string $identifier): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function processColumn(string $table, array $column, array &$foreignKeys): string {
        $columnType = isset($column['parentTable']) ? 'INT' : strtoupper($column['type']);

        if ($columnType === 'TINYINT') {
            $columnType = 'SMALLINT';
        }

        if ($columnType === 'JSON') {
            $columnType = 'JSONB';
        }

        $sql = '"' . $column['name'] . '" ' . $columnType;

        if (isset($column['default'])) {
            $sql .= ' DEFAULT ' . $column['default'];
        }

        $sql .= isset($column['null']) ? ' NULL' : ' NOT NULL';

        if (isset($column['unique'])) {
            $sql .= ' UNIQUE';
        }

        if (!empty($column['parentTable'])) {
            $constraint = 'CONSTRAINT fk_' . $table . '_' . $column['parentTable'] . '_' . $column['name'];
            $foreignKey = sprintf(
                'FOREIGN KEY (%s) REFERENCES %s(%s)',
                $this->quoteIdentifier($column['name']),
                $this->quoteIdentifier($column['parentTable']),
                $this->quoteIdentifier('id')
            );
            $foreignKeys[] = $constraint . ' ' . $foreignKey;
        }

        return $sql;
    }

    private function processIndex(string $table, array $index): string {

        $indexType = strtoupper($index['type']);
        $columns = '"' . implode('", "', $index['columns']) . '"';
        $columns = '(' . $columns . ')';

        if (empty($index['identifier'])) {
            $prefix = ($indexType === 'UNIQUE') ? 'uc' : 'ix';
            $identifier = $prefix . '_' . $table . '_' . strtolower(implode('_', $index['columns']));
        } else {
            $identifier = $index['identifier'];
        }

        if ($indexType === 'UNIQUE') {
            return 'CREATE UNIQUE INDEX "' . $identifier . '" ON "' . $table . '" ' . $columns . ';';
        }

        return 'CREATE INDEX "' . $identifier . '" ON "' . $table . '" ' . $columns . ';';
    }

    /**
     * @throws Exception
     */
    private function alterTable(mixed $alterTable): void {
        $table = $alterTable['table'];

        if (!$this->db->tableExists($table)) {
            throw new Exception('Tabela não encontrada: ' . $table);
        }

        $sql = 'ALTER TABLE "' . $table . '" ';
        $foreignKeys = [];
        $columns = [];

        if (!empty($alterTable['columns'])) {
            foreach ($alterTable['columns'] as $column) {
                $columns[] = "ADD COLUMN " . $this->processColumn($table, $column, $foreignKeys);
            }
        }

        $sql .= implode(",\n", $columns);

        if (!empty($foreignKeys)) {
            $sql .= implode('', array_map(fn($key) => ",\n ADD $key", $foreignKeys));
        }

        $this->db->prepareStatement($sql)->execute();

        if (!empty($alterTable['indexes'])) {
            foreach ($alterTable['indexes'] as $index) {
                $indexSql = $this->processIndex($table, $index);
                $this->db->prepareStatement($indexSql)->execute();
            }
        }
    }

    /**
     * @throws Exception
     */
    private function insertEntities(mixed $updateContent): void {
        foreach ($updateContent['entities'] as $entity) {
            foreach ($entity['values'] as $row) {
                $row = array_combine($entity['columns'], $row);
                $row['key'] = Entity::generateKey();
                $row['createdBy'] ??= 1;
                $row['updatedBy'] ??= 1;
                $this->db->insertRow($entity['table'], $row);
            }
        }
    }

    private function shouldSkipSql(string $sql): bool {
        $normalizedSql = strtoupper(trim($sql));

        return str_starts_with($normalizedSql, 'SET FOREIGN_KEY_CHECKS');
    }

    /**
     * @throws Exception
     */
    private function setDbVersion(int $version): void {
        $this->db->updateRow('system_config', ['dbVersion' => $version], 1);
    }

    /**
     * @throws Exception
     */
    private function getUpdateFiles(): array {
        if (!is_dir($this->db->getConfig()->structurePath)) {
            throw new Exception('Diretório da estrutura do banco não existente.');
        }

        $files = glob($this->db->getConfig()->structurePath . '*.json');
        $files = array_map(function ($file) {
            $file = explode('/', $file);
            return array_pop($file);
        }, $files);
        usort($files, function ($first, $second) {
            $first = (int)explode('.', $first)[0];
            $second = (int)explode('.', $second)[0];

            return ($first <=> $second);
        });

        if (empty($files)) {
            throw new Exception('Não foi possível encontrar arquivos de estrutura do banco.');
        }

        $currentVersion = $this->db->columnById('system_config', 'dbVersion', 1);
        $updates = array_filter($files, function ($value) use ($currentVersion) {
            $version = (int)explode('.', $value)[0];

            return ($version > $currentVersion);
        });

        if (empty($updates)) {
            throw new Exception('Não há atualizações para serem realizadas na estrutura do banco.');
        }

        return $updates;
    }

}
