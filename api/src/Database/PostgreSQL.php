<?php

declare(strict_types=1);

namespace IBRExplorer\Database;

use BackedEnum;
use Exception;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\User\User;
use PDO;
use PDOStatement;
use RuntimeException;

class PostgreSQL {

    public static ?PostgreSQL $instance;

    private PDO $pdo;
    private ?PDOStatement $lastStatement = null;
    private ?int $lastInsertedId = null;
    private RepositoryConfig $config;
    private ?User $userLogged;
    private bool $inTransaction = false;
    private bool $tablesLocked = false;

    public function __construct(RepositoryConfig $config) {
        $this->config = $config;
    }

    public function initDatabase(): void {
        $dsn = 'pgsql:host=' . $this->config->host .
            ';port=' . $this->config->port .
            ';dbname=' . $this->config->database;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        $this->pdo = new PDO(
            $dsn,
            $this->config->user,
            $this->config->password,
            $options
        );

        self::$instance = $this;
    }

    /**
     * @throws Exception
     */
    public function tableExists(string $table): bool {
        $sql = '
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = current_schema()
              AND table_name = ?
            LIMIT 1
        ';
        $this->execute($sql, [$table]);

        return ($this->lastStatement->fetchColumn() !== false);
    }

    /**
     * @throws Exception
     */
    public function execute(string|PDOStatement $sqlOrStatement, array $params = []): bool {
        $this->lastStatement = is_string($sqlOrStatement)
            ? $this->prepareStatement($sqlOrStatement)
            : $sqlOrStatement;

        if (!empty($params)) {
            foreach ($params as $key => $param) {
                if ($param instanceof BackedEnum) {
                    $params[$key] = $param->value;
                }
            }
        }

        return $this->lastStatement->execute(array_values($params));
    }

    /**
     * @throws Exception
     */
    public function prepareStatement(string $sql): PDOStatement {
        $statement = $this->pdo->prepare($sql);

        if ($statement === false) {
            throw new Exception($this->pdo->errorInfo()[2]);
        }

        return $statement;
    }

    public function getConfig(): RepositoryConfig {
        return $this->config;
    }

    /**
     * @throws Exception
     */
    public function updateRow(string $table, array $row, int $id): bool {
        $fields = implode(',', array_map(
            fn($field) => '"' . $field . '" = ?',
            array_keys($row)
        ));

        $sql = 'UPDATE ' . $this->quoteIdentifier($table) . ' SET ' . $fields . ' WHERE ("id" = ?)';
        $row['id'] = $id;

        return $this->execute($sql, $row);
    }

    private function quoteIdentifier(string $identifier): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * @throws Exception
     */
    public function insertRow(string $table, array $row): bool {
        $fields = implode(', ', array_map(
            fn($field) => '"' . $field . '"',
            array_keys($row)
        ));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . $fields . ') VALUES (' . $placeholders . ') RETURNING "id"';

        $executed = $this->execute($sql, $row);

        if (!$executed) {
            $this->lastInsertedId = null;

            return false;
        }

        $insertedId = $this->lastStatement?->fetchColumn();
        $this->lastInsertedId = ($insertedId !== false) ? (int)$insertedId : null;

        return true;
    }

    public function getLastInsertId(): int|false {
        return $this->lastInsertedId ?? false;
    }

    /**
     * @throws Exception
     */
    public function columnById(string $table, string $column, int $id): mixed {
        return $this->column($table, $column, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function column(string $table, string $column, array $where): mixed {
        if (!$this->columnExists($table, $column)) {
            throw new Exception('Coluna não existente na tabela.');
        }

        $row = $this->row($table, [$column], $where);

        if (isset($row[$column]) && ($row[$column] === false)) {
            return false;
        }

        return $row[$column] ?? null;
    }

    /**
     * @throws Exception
     */
    public function columnExists(string $table, string $column): bool {
        $sql = '
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ';

        $this->execute($sql, [$table, $column]);

        return ($this->lastStatement->fetchColumn() !== false);
    }

    /**
     * @throws Exception
     */
    public function row(
        string $table,
        array  $fields = ['id', 'key', 'entityStatus'],
        array  $where = [],
        array  $orderBy = []
    ): array|false {
        return $this->rows($table, $fields, $where, [], $orderBy, 1)['entities'][0] ?? false;
    }

    /**
     * @throws Exception
     */
    public function rows(
        string $table,
        array  $fields = ['id'],
        array  $where = [],
        array  $groupBy = [],
        array  $orderBy = [],
        int    $limit = 15,
        int    $page = 1,
        array  $searchParams = []
    ): array {
        $params = [];

        $fieldsLine = $this->getFieldsLine($table, $fields, $orderBy);
        $innerJoinLine = $this->getInnerJoinLine($table, $where, $orderBy, $searchParams);
        $whereLine = $this->getWhereLine($table, $where, $searchParams, $params);
        $groupByLine = $this->getGroupByLine($table, $groupBy);
        $orderByLine = $this->getOrderByLine($table, $orderBy);
        $limitLine = $this->getLimitLine($limit, $page);
        $quotedTable = $this->quoteIdentifier($table);

        $sql = 'SELECT DISTINCT ' . $fieldsLine . ' FROM ' . $quotedTable . ' ' .
            $innerJoinLine . ' ' .
            $whereLine . ' ' .
            $groupByLine . ' ' .
            $orderByLine . ' ' .
            $limitLine;

        $this->execute($sql, $params);
        $rows = $this->lastStatement->fetchAll(PDO::FETCH_ASSOC);

        $countSql = 'SELECT COUNT(DISTINCT ' . $quotedTable . '."id") FROM ' . $quotedTable . ' ' . $innerJoinLine . ' ' . $whereLine;
        $this->execute($countSql, $params);

        return [
            'entities' => $rows,
            'total' => (int)$this->lastStatement->fetchColumn(),
        ];
    }

    private function getFieldsLine(
        string $table,
        array  $fields,
        array  $orderBy,
    ): string {
        if (empty($fields)) {
            $fields = ['id'];
        }

        if ($fields[0] !== '*') {
            $arrayMap = array_map(
                function ($field) use ($table) {
                    return $this->quoteIdentifier($table) . '."' . $field . '"';
                },
                $fields
            );

            if (!empty($orderBy)) {
                foreach ($orderBy as $field) {
                    $fieldName = explode(' ', $field)[0];

                    if (!in_array($fieldName, $fields, true)) {
                        if (str_contains($fieldName, '.')) {
                            $fieldExplode = explode('.', $fieldName);
                            $arrayMap[] = $this->quoteIdentifier($fieldExplode[0]) . '."' . $fieldExplode[1] . '"';
                        } else {
                            $arrayMap[] = $this->quoteIdentifier($table) . '."' . $fieldName . '"';
                        }
                    }
                }
            }

            $fieldsLine = implode(', ', array_unique($arrayMap));
        } else {
            $fieldsLine = $this->quoteIdentifier($table) . '.*';
        }

        return $fieldsLine;
    }

    /**
     * @throws Exception
     */
    private function getInnerJoinLine(string $table, array $where, array $orderBy, array $searchParams): string {
        $innerJoinLine = '';
        $tablesJoined = [];

        $joinTables = array_filter([
            ...array_keys($where),
            ...array_values($orderBy),
            ...array_keys($searchParams)
        ], fn($tableField) => is_string($tableField) && str_contains($tableField, '.'));

        if (empty($joinTables)) {
            return $innerJoinLine;
        }

        foreach ($joinTables as $tableField) {
            $childTable = explode('.', $tableField)[0];

            if (in_array($childTable, $tablesJoined, true)) {
                continue;
            }

            $isFromWhere = array_key_exists($tableField, $where);
            $isFromOrder = in_array($tableField, $orderBy, true);
            $isFromSearch = array_key_exists($tableField, $searchParams);

            $joinType = ($isFromSearch && !$isFromWhere && !$isFromOrder)
                ? 'LEFT JOIN'
                : 'INNER JOIN';

            $query = '
                SELECT
                    kcu.table_name,
                    kcu.column_name,
                    ccu.table_name AS referenced_table_name,
                    ccu.column_name AS referenced_column_name
                FROM information_schema.table_constraints tc
                INNER JOIN information_schema.key_column_usage kcu
                    ON tc.constraint_name = kcu.constraint_name
                   AND tc.table_schema = kcu.table_schema
                INNER JOIN information_schema.constraint_column_usage ccu
                    ON ccu.constraint_name = tc.constraint_name
                   AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = \'FOREIGN KEY\'
                  AND kcu.column_name NOT IN (\'createdBy\', \'updatedBy\')
                  AND (
                        (kcu.table_name = ? AND ccu.table_name = ?)
                     OR (kcu.table_name = ? AND ccu.table_name = ?)
                  )
                LIMIT 1
            ';

            $params = [
                $table,
                $childTable,
                $childTable,
                $table
            ];

            if (!$this->execute($query, $params)) {
                continue;
            }

            $joinRow = $this->lastStatement->fetch(PDO::FETCH_ASSOC);

            if (empty($joinRow)) {
                continue;
            }

            if ($joinRow['table_name'] === $table) {
                $joinCondition =
                    $this->quoteIdentifier($joinRow['referenced_table_name']) . '."' . $joinRow['referenced_column_name'] . '" = ' . $this->quoteIdentifier($joinRow['table_name']) . '."' . $joinRow['column_name'] . '"';
            } else {
                $joinCondition =
                    $this->quoteIdentifier($joinRow['table_name']) . '."' . $joinRow['column_name'] . '" = ' . $this->quoteIdentifier($joinRow['referenced_table_name']) . '."' . $joinRow['referenced_column_name'] . '"';
            }

            $innerJoinLine .= $joinType . ' ' . $this->quoteIdentifier($childTable) . ' ON ' . $joinCondition . PHP_EOL;
            $tablesJoined[] = $childTable;
        }

        return $innerJoinLine;
    }

    private function getWhereLine(string $table, array $where, array $search, array &$params): string {
        $whereLine = '';

        if (empty($where) && empty($search)) {
            return $whereLine;
        }

        if (!empty($where)) {
            $whereLine = 'WHERE ' . implode(' AND ', array_map(
                /**
                 * @throws Exception
                 */ function ($field, $value) use ($table, &$params) {
                    if (is_array($value)) {
                        $operator = $value['operator'] ?? 'IN';

                        switch ($operator) {
                            case 'IN':
                            case 'NOT IN':
                                $value = $value['values'] ?? $value;
                                $param = '(' . implode(',', array_fill(0, count($value), '?')) . ')';
                                break;

                            case 'BETWEEN':
                                $value = $value['values'];
                                $param = implode(' AND ', array_fill(0, count($value), '?'));
                                break;

                            case '<>':
                            case '>':
                            case '>=':
                            case '<':
                            case '<=':
                                $value = $value['value'];
                                $param = '?';
                                break;

                            default:
                                throw new Exception('Operador inválido no WHERE.');
                        }

                        if (is_array($value)) {
                            foreach ($value as $item) {
                                $params[] = $item instanceof BackedEnum ? $item->value : $item;
                            }
                        } else {
                            $params[] = $value instanceof BackedEnum ? $value->value : $value;
                        }
                    } elseif (is_null($value)) {
                        $operator = 'IS NULL';
                        $param = '';
                    } else {
                        $operator = '=';
                        $param = '?';
                        $params[] = $value instanceof BackedEnum ? $value->value : $value;
                    }

                    if (str_contains($field, '.')) {
                        $field = explode('.', $field);
                        $field = $this->quoteIdentifier($field[0]) . '."' . $field[1] . '"';
                    } else {
                        $field = $this->quoteIdentifier($table) . '."' . $field . '"';
                    }

                    return '(' . $field . ' ' . $operator . ' ' . $param . ')';
                },
                    array_keys($where),
                    array_values($where)
                ));
        }

        $search = array_filter($search, fn($value) => is_array($value));

        if (!empty($search)) {
            $whereLine .= !empty($whereLine) ? ' AND (' : 'WHERE (';
            $whereLine .= implode(
                ' OR ',
                array_map(
                    function (string $field, array $terms) use ($table, &$params) {
                        $searchParams = [];

                        if (str_contains($field, '.')) {
                            [$tableToEscape, $fieldToEscape] = explode('.', $field);
                        } else {
                            $tableToEscape = $table;
                            $fieldToEscape = $field;
                        }

                        foreach ($terms as $term) {
                            $searchParams[] =
                                '(' . $this->quoteIdentifier($tableToEscape) . '."' . $fieldToEscape . '" LIKE ?)';
                            $params[] = $term;
                        }

                        return implode(' OR ', $searchParams);
                    },
                    array_keys($search),
                    array_values($search)
                )
            );
            $whereLine .= ')';
        }

        return $whereLine;
    }

    private function getGroupByLine(string $table, array $groupBy): string {
        if (empty($groupBy)) {
            return '';
        }

        return 'GROUP BY ' . implode(
                ', ',
                array_map(
                    function ($field) use ($table) {
                        return $this->quoteIdentifier($table) . '."' . $field . '"';
                    },
                    $groupBy
                )
            );
    }

    private function getOrderByLine(string $table, array $orderBy): string {
        if (empty($orderBy)) {
            return '';
        }

        return 'ORDER BY ' . implode(
                ', ',
                array_map(
                    function ($field) use ($table) {
                        $hasDesc = str_contains($field, 'DESC');
                        $field = trim(str_replace(['DESC', 'ASC'], '', $field));

                        if (str_contains($field, '.')) {
                            $field = explode('.', $field);
                            $table = $field[0];
                            $field = $field[1];
                        }

                        return $this->quoteIdentifier($table) . '."' . $field . '" ' . ($hasDesc ? 'DESC' : 'ASC');
                    },
                    $orderBy
                )
            );
    }

    private function getLimitLine(int $limit, int $page): string {
        if ($limit === 0) {
            return '';
        }

        return 'LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit);
    }

    /**
     * @throws Exception
     */
    public function initUser(int $id): void {
        $userRow = $this->rowById('user', $id);

        if ($userRow === false) {
            throw new Exception('Usuário não localizado.');
        }

        $user = new User($userRow);

        if (($user->entityStatus ?? null) !== EntityStatus::Active) {
            throw new Exception('Usuário não ativo, favor entrar em contato com o suporte.');
        }

        $roles = $this->rows(
            'user_role',
            ['user', 'type'],
            [
                'entityStatus' => EntityStatus::Active,
                'user' => $user->id
            ],
            [],
            ['type'],
            0
        );

        if (!empty($roles['entities'])) {
            $user->setData(['roles' => $roles['entities']]);
        }

        $this->userLogged = $user;
    }

    /**
     * @throws Exception
     */
    public function rowById(
        string $table,
        int    $id,
        array  $fields = ['*']
    ): array|false {
        return $this->row($table, $fields, ['id' => $id]);
    }

    public function getUser(): ?User {
        return $this->userLogged ?? null;
    }

    public function __destruct() {
        self::$instance = null;
    }

    public function beginTransaction(): void {
        if ($this->inTransaction) {
            throw new RuntimeException('Não é possível iniciar uma nova transação.');
        } elseif (!$this->pdo->beginTransaction()) {
            throw new RuntimeException('Falha ao iniciar a transação.');
        }

        $this->inTransaction = true;
    }

    public function commit(): void {
        if (!$this->inTransaction) {
            throw new RuntimeException('Não há transação em andamento.');
        }

        if ($this->tablesLocked) {
            $this->unlockTables();
        }

        if (!$this->pdo->commit()) {
            throw new RuntimeException('Falha ao confirmar a transação.');
        }

        $this->inTransaction = false;
    }

    public function unlockTables(): void {
        if (!$this->tablesLocked) {
            throw new RuntimeException('Não é possível desbloquear tabelas, pois não foram bloqueadas previamente.');
        }

        try {
            $this->tablesLocked = false;
        } catch (Exception) {
            throw new RuntimeException('Falha ao desbloquear as tabelas.');
        }
    }

    public function rollback(): void {
        if (!$this->inTransaction) {
            throw new RuntimeException('Não há transação em andamento.');
        }

        if ($this->tablesLocked) {
            $this->unlockTables();
        }

        if (!$this->pdo->rollBack()) {
            throw new RuntimeException('Falha ao reverter a transação.');
        }

        $this->inTransaction = false;
    }

    public function inTransaction(): bool {
        return $this->inTransaction;
    }

    public function lockTables(array $tables): void {
        if (empty($tables)) {
            throw new RuntimeException('Nenhuma tabela informada para bloquear.');
        } elseif ($this->tablesLocked) {
            throw new RuntimeException('Ja foram informadas tabelas para bloqueio.');
        }

        try {
            $locks = array_map(
                function (string $table) {
                    $array = explode(' ', trim($table));
                    $tableName = $array[0];
                    $mode = strtoupper($array[1] ?? 'READ');

                    $mode = match ($mode) {
                        'WRITE' => 'ACCESS EXCLUSIVE',
                        default => 'SHARE',
                    };

                    return 'LOCK TABLE ' . $this->quoteIdentifier($tableName) . ' IN ' . $mode . ' MODE';
                },
                $tables
            );

            foreach ($locks as $lockQuery) {
                $this->pdo->exec($lockQuery);
            }

            $this->tablesLocked = true;
        } catch (Exception) {
            throw new RuntimeException('Falha ao bloquear as tabelas.');
        }
    }

    public function getLastStatement(): ?PDOStatement {
        return $this->lastStatement;
    }

    /**
     * @throws Exception
     */
    public function deleteRow(string $table, int $id): bool {
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ("id" = ?);';

        return $this->execute($sql, ['id' => $id]);
    }

}
