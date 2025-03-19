<?php /** @noinspection SqlNoDataSourceInspection */

declare(strict_types=1);

namespace IBRExplorer\Database\Structure;

use Exception;
use IBRExplorer\Database\MySql;
use IBRExplorer\Database\RepositoryConfig;
use IBRExplorer\Entity\Entity;

class Structure {

    private MySql $mySql;

    public function __construct(RepositoryConfig $config) {
        $this->mySql = new MySql($config);
    }

    /**
     * @throws Exception
     */
    public function updateStart(): void {
        $this->mySql->initDatabase();

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
        return !$this->mySql->tableExists('system_config');
    }

    /**
     * @throws Exception
     */
    private function createSystemConfigTable(): void {
        $this->mySql->prepareStatement("
            CREATE TABLE system_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                dbVersion INT DEFAULT 0 COMMENT 'Versão atual do banco de dados.',
                updatePid INT NULL COMMENT 'PID do processo de atualização.',
                lastUpdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da última atualização.',
                backupPid INT NULL COMMENT 'PID do processo de backup.',
                lastBackup TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data do último backup.'
            );
        ")->execute();
        $this->mySql->prepareStatement('INSERT INTO system_config () VALUES ();')->execute();
    }

    /**
     * @throws Exception
     */
    private function setProcessPid(int $pid = null, bool $setLastUpdate = false): void {
        $row = ['updatePid' => $pid];

        if ($setLastUpdate) {
            $row['lastUpdate'] = date('Y-m-d H:i:s');
        }

        $this->mySql->updateRow('system_config', $row, 1);
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
            file_get_contents($this->mySql->getConfig()->structurePath . $updateFile), true
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
                    $this->mySql->prepareStatement($updateContent['sql'])->execute();

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
        $sql = '
            CREATE TABLE `' . $table . '` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entityStatus` TINYINT DEFAULT 1 NOT NULL CHECK (`entityStatus` IN (1, 2, 3)),
                `key` VARCHAR(16) UNIQUE NOT NULL,
                `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `createdBy` INT NOT NULL,
                `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `updatedBy` INT NOT NULL,
                `webMetadata` JSON NULL,
                `internalNotes` TEXT NULL';
        $foreignKeys = [
            'CONSTRAINT fk_' . $table . '_user_createdBy FOREIGN KEY (`createdBy`) REFERENCES `user`(`id`)',
            'CONSTRAINT fk_' . $table . '_user_updatedBy FOREIGN KEY (`updatedBy`) REFERENCES `user`(`id`)'
        ];

        if (!empty($create['columns'])) {
            $columns = [];

            foreach ($create['columns'] as $column) {
                $columns[] = ",\n" . $this->processColumn($table, $column, $foreignKeys);
            }

            $sql .= implode('', $columns);
        }

        if (!empty($create['indexes'])) {
            $indexes = [];

            foreach ($create['indexes'] as $index) {
                $indexes[] = ",\n" . $this->processIndex($table, $index);
            }

            $sql .= implode('', $indexes);
        }

        if (!empty($foreignKeys)) {
            $sql .= implode('', array_map(fn($key) => ",\n" . $key, $foreignKeys));
        }

        $sql .= "\n);";
        $this->mySql->prepareStatement($sql)->execute();
    }

    private function processColumn(string $table, array $column, array &$foreignKeys): string {
        $columnType = isset($column['parentTable']) ? 'int' : $column['type'];
        $sql = '`' . $column['name'] . '` ' . strtoupper($columnType);
        $sql .= isset($column['default']) ? ' DEFAULT ' . $column['default'] : '';
        $sql .= isset($column['null']) ? ' NULL' : ' NOT NULL';
        $sql .= isset($column['unique']) ? ' UNIQUE' : '';
        $sql .= isset($column['comment']) ? ' COMMENT \'' . addslashes($column['comment']) . '\'' : '';
        $sql .= ' ' . ($column['position'] ?? '');

        if (!empty($column['parentTable'])) {
            $constraint = 'CONSTRAINT ' . 'fk_' . $table . '_' . $column['parentTable'] . '_' . $column['name'];
            $foreignKey = 'FOREIGN KEY (`' . $column['name'] . '`) REFERENCES `' . $column['parentTable'] . '`(`id`)';
            $foreignKeys[] = $constraint . ' ' . $foreignKey;
        }

        return $sql;
    }

    private function processIndex(string $table, array $index): string {
        $indexType = strtoupper($index['type']);
        $columns = '(`' . implode('`, `', $index['columns']) . '`)';

        if (empty($index['identifier'])) {
            $prefix = ($indexType === 'UNIQUE') ? 'uc' : 'ix';
            $identifier = $prefix . '_' . $table . '_' . strtolower(implode('_', $index['columns']));
        } else {
            $identifier = $index['identifier'];
        }

        return ($indexType === 'INDEX')
            ? $indexType . ' ' . $identifier . ' ' . $columns
            : 'CONSTRAINT ' . $identifier . ' ' . $indexType . ' ' . $columns;
    }

    /**
     * @throws Exception
     */
    private function alterTable(mixed $alterTable): void {
        $table = $alterTable['table'];

        if (!$this->mySql->tableExists($table)) {
            throw new Exception('Tabela não encontrada: ' . $table);
        }

        $sql = 'ALTER TABLE `' . $table . '` ';
        $foreignKeys = [];

        if (!empty($alterTable['columns'])) {
            $columns = [];

            foreach ($alterTable['columns'] as $column) {
                $columns[] = "ADD COLUMN " . $this->processColumn($table, $column, $foreignKeys);
            }

            $sql .= implode(",\n", $columns);
        }

        if (!empty($alterTable['indexes'])) {
            $indexes = [];

            foreach ($alterTable['indexes'] as $index) {
                $indexes[] = 'ADD ' . $this->processIndex($table, $index);
            }

            if (!empty($columns)) {
                $sql .= ",\n";
            }

            $sql .= implode(",\n", $indexes);
        }

        if (!empty($foreignKeys)) {
            $sql .= implode('', array_map(fn($key) => ",\n ADD " . $key, $foreignKeys));
        }

        $this->mySql->prepareStatement($sql)->execute();
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
                $this->mySql->insertRow($entity['table'], $row);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function setDbVersion(int $version): void {
        $this->mySql->updateRow('system_config', ['dbVersion' => $version], 1);
    }

    /**
     * @throws Exception
     */
    private function getUpdateFiles(): array {
        if (!is_dir($this->mySql->getConfig()->structurePath)) {
            throw new Exception('Diretório da estrutura do banco não existente.');
        }

        $files = glob($this->mySql->getConfig()->structurePath . '*.json');
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

        $currentVersion = $this->mySql->columnById('system_config', 'dbVersion', 1);
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