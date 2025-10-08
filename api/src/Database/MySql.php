<?php

declare(strict_types=1);

namespace IBRExplorer\Database;

use BackedEnum;
use Exception;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\User\User;
use mysqli;
use mysqli_stmt;
use RuntimeException;

class MySql {

    public static ?MySql $instance;

    private mysqli $mysqli;
    private ?mysqli_stmt $lastStatement = null;
    private RepositoryConfig $config;
    private ?User $userLogged;
    private bool $inTransaction = false;
    private bool $tablesLocked = false;

    public function __construct(RepositoryConfig $config) {
        $this->config = $config;
    }

    public function initDatabase(): void {
        self::$instance = $this;
        $this->mysqli = new mysqli(
            $this->config->mysqlHost,
            $this->config->mysqlUser,
            $this->config->mysqlPassword,
            $this->config->mysqlDatabase,
            $this->config->mysqlPort,
        );
        $this->mysqli->set_charset('utf8mb4');
        $this->mysqli->options(MYSQLI_OPT_LOCAL_INFILE, true);

    }

    /**
     * @throws Exception
     */
    public function tableExists(string $table): bool {
        $this->execute('SHOW TABLES LIKE \'' . $this->mysqli->real_escape_string($table) . '\'');

        return ($this->lastStatement->get_result()->num_rows > 0);
    }

    /**
     * @throws Exception
     */
    public function execute(string|mysqli_stmt $sqlOrStatement, array $params = []): bool {
        $this->lastStatement = is_string($sqlOrStatement)
            ? $this->prepareStatement($sqlOrStatement)
            : $sqlOrStatement;

        if (!empty($params)) {
            $types = implode('', array_map(function ($param) {
                return match ($param) {
                    is_int($param) => 'i',
                    is_float($param) => 'd',
                    default => 's'
                };
            }, $params));

            foreach ($params as $key => $param) {
                if ($param instanceof BackedEnum) {
                    $params[$key] = $param->value;
                }
            }

            $this->lastStatement->bind_param($types, ...array_values($params));
        }

        return $this->lastStatement->execute();
    }

    /**
     *
     * @throws Exception
     */
    public function prepareStatement(string $sql): mysqli_stmt {
        $statement = $this->mysqli->prepare($sql);

        if ($statement === false) {
            throw new Exception($this->mysqli->error);
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
            (fn($field) => '`' . $this->mysqli->real_escape_string($field) . '`' . ' = ?'),
            array_keys($row)
        ));
        $sql = 'UPDATE `' . $this->mysqli->real_escape_string($table) . '` SET ' . $fields . ' WHERE (id = ?)';
        $row['id'] = $id;

        return $this->execute($sql, $row);
    }

    /**
     * @throws Exception
     */
    public function insertRow(string $table, array $row): bool {
        $fields = implode(', ', array_map((fn($field) => '`' . $field . '`'), array_keys($row)));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $sql = 'INSERT INTO `' . $table . '` (' . $fields . ') VALUES (' . $placeholders . ')';

        return $this->execute($sql, $row);
    }

    public function getLastInsertId(): int|false {
        return $this->mysqli->insert_id ?: false;
    }

    /**
     * @throws Exception
     */
    public function columnById(string $table, string $column, int $id): mixed {
        if (!$this->columnExists($table, $column)) {
            throw new Exception('Coluna não existente na tabela.');
        }

        return $this->column($table, $column, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function columnExists(string $table, string $column): bool {
        // TODO: Fazer cache dos campos já consultados, caso não encontre, consulte no banco.
        $this->execute('SHOW COLUMNS FROM `' . $this->mysqli->real_escape_string($table) . '` LIKE \'' . $this->mysqli->real_escape_string($column) . '\'');

        return ($this->lastStatement->get_result()->num_rows > 0);
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
    public function row(
        string $table,
        array $fields = ['id', 'key', 'entityStatus'],
        array $where = [],
        array $orderBy = []
    ): array|false {
        return $this->rows($table, $fields, $where, [], $orderBy, 1)['entities'][0] ?? false;
    }

    /**
     * @throws Exception
     */
    public function rows(
        string $table,
        array $fields = ['id'],
        array  $where = [],
        array $groupBy = [],
        array  $orderBy = [],
        int    $limit = 15,
        int   $page = 1,
        array $searchParams = []
    ): array {
        $params = [];

        $fieldsLine = $this->getFieldsLine($table, $fields, $orderBy);
        $innerJoinLine = $this->getInnerJoinLine($table, $where, $orderBy, $searchParams);
        $whereLine = $this->getWhereLine($table, $where, $searchParams, $params);
        $groupByLine = $this->getGroupByLine($table, $groupBy);
        $orderByLine = $this->getOrderByLine($table, $orderBy);
        $limitLine = $this->getLimitLine($limit, $page);

        $sql = 'SELECT DISTINCT ' . $fieldsLine . ' FROM `' . $this->mysqli->real_escape_string($table) . '` ' .
            $innerJoinLine . ' ' .
            $whereLine . ' ' .
            $groupByLine . ' ' .
            $orderByLine . ' ' .
            $limitLine;
        $this->execute($sql, $params);
        $rows = $this->lastStatement->get_result()->fetch_all(MYSQLI_ASSOC);

        $this->execute('SELECT COUNT(*) FROM `' . $this->mysqli->real_escape_string($table) . '`' . $innerJoinLine . ' ' . $whereLine, $params);

        return [
            'entities' => $rows,
            'total' => $this->lastStatement->get_result()->fetch_column(),
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
                    return '`' . $this->mysqli->real_escape_string($table) . '`.`' . $this->mysqli->real_escape_string($field) . '`';
                },
                $fields
            );

            if (!empty($orderBy)) {
                foreach ($orderBy as $field) {
                    if (!in_array($field, $fields)) {
                        $field = explode(' ', $field)[0];

                        if (str_contains($field, '.')) {
                            $fieldExplode = explode('.', $field);
                            $arrayMap[] = '`' . $this->mysqli->real_escape_string($fieldExplode[0]) . '`.`' . $this->mysqli->real_escape_string($fieldExplode[1]) . '`';
                        } else {
                            $arrayMap[] = '`' . $this->mysqli->real_escape_string($table) . '`.`' . $this->mysqli->real_escape_string($field) . '`';
                        }
                    }
                }
            }

            $fieldsLine = implode(', ', $arrayMap);
        } else {
            $fieldsLine = $table . '.*';
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
        ], fn($tableField) => str_contains($tableField, '.'));

        if (empty($joinTables)) {
            return $innerJoinLine;
        }

        foreach ($joinTables as $tableField) {
            $childTable = explode('.', $tableField)[0];

            if (in_array($childTable, $tablesJoined)) {
                continue;
            }

            $isFromWhere = array_key_exists($tableField, $where);
            $isFromOrder = in_array($tableField, $orderBy);
            $isFromSearch = array_key_exists($tableField, $searchParams);
            $joinType = ($isFromSearch && !$isFromWhere && !$isFromOrder)
                ? 'LEFT JOIN'
                : 'INNER JOIN';
            $query = "
                SELECT CONCAT('`', REFERENCED_TABLE_NAME, '`.`', REFERENCED_COLUMN_NAME, '` = `', TABLE_NAME, '`.`', COLUMN_NAME, '`') AS joinCondition
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE COLUMN_NAME NOT IN ('createdBy', 'updatedBy') AND ((TABLE_NAME = ? AND REFERENCED_TABLE_NAME = ?) OR (TABLE_NAME = ? AND REFERENCED_TABLE_NAME = ?))
                LIMIT 1;
            ";
            $params = [
                $table,
                $childTable,
                $childTable,
                $table
            ];

            if (!$this->execute($query, $params)) {
                continue;
            }

            $joinCondition = $this->lastStatement->get_result()->fetch_column();

            if (empty($joinCondition)) {
                continue;
            }

            $innerJoinLine .= $joinType . ' `' . $this->mysqli->real_escape_string($childTable) . '` ON ' . $joinCondition . PHP_EOL;
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
                 */
                    function ($field, $value) use ($table, &$params) {
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
                                    $params[] = $item;
                                }
                            } else {
                                $params[] = $value;
                            }
                        } elseif (is_null($value)) {
                            $operator = 'IS NULL';
                            $param = '';
                        } else {
                            $operator = '=';
                            $param = '?';
                            $params[] = $value;
                        }

                        if (str_contains($field, '.')) {
                            $field = explode('.', $field);
                            $field = '`' . $this->mysqli->real_escape_string($field[0]) . '`.`' . $this->mysqli->real_escape_string($field[1]) . '`';
                        } else {
                            $field = '`' . $this->mysqli->real_escape_string($table) . '`.`' . $this->mysqli->real_escape_string($field) . '`';
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
                            $searchParams[] .= '(`' . $this->mysqli->real_escape_string($tableToEscape) . '`.`' . $this->mysqli->real_escape_string($fieldToEscape) . '` LIKE ?)';
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
                        return '`' . $table . '`.`' . $this->mysqli->real_escape_string($field) . '`';
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

                        return '`' . $this->mysqli->real_escape_string($table) . '`.`' . $this->mysqli->real_escape_string($field) . '` ' . ($hasDesc ? 'DESC' : 'ASC');
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

        if (!empty($this->mysqli)) {
            $this->mysqli->close();
        }
    }

    public function beginTransaction(): void {
        if ($this->inTransaction) {
            throw new RuntimeException('Não é possível iniciar uma nova transação.');
        } elseif (!$this->mysqli->begin_transaction()) {
            throw new RuntimeException('Falha ao iniciar a transação: ' . $this->mysqli->error);
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

        if (!$this->mysqli->commit()) {
            throw new RuntimeException('Falha ao confirmar a transação: ' . $this->mysqli->error);
        }

        $this->inTransaction = false;
    }

    public function unlockTables(): void {
        if (!$this->tablesLocked) {
            throw new RuntimeException('Não é possível desbloquear tabelas, pois não foram bloqueadas previamente.');
        }

        try {
            $this->tablesLocked = false;
            $this->mysqli->query('UNLOCK TABLES');
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

        if (!$this->mysqli->rollback()) {
            throw new RuntimeException('Falha ao reverter a transação: ' . $this->mysqli->error);
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
            $tables = array_map(
                function (string $table) {
                    $array = explode(' ', $table);
                    return '`' . $array[0] . '` ' . ($array[1] ?? 'READ');
                },
                $tables
            );

            $lockQuery = 'LOCK TABLES ' . implode(', ', $tables);

            $this->tablesLocked = true;
            $this->mysqli->query($lockQuery);
        } catch (Exception) {
            throw new RuntimeException('Falha ao bloquear as tabelas.');
        }
    }

    public function getLastStatement(): ?mysqli_stmt {
        return $this->lastStatement;
    }

    /**
     * @throws Exception
     */
    public function deleteRow(string $table, int $id): bool {
        $sql = 'DELETE FROM `' . $this->mysqli->real_escape_string($table) . '` WHERE (`id` = ?);';

        return $this->execute($sql, ['id' => $id]);
    }

}
