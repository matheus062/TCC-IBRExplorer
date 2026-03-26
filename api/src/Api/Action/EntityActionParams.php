<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action;

use Exception;
use IBRExplorer\Cache\Entity\EntityMetadata;

readonly class EntityActionParams {

    public string $entityClass;
    public array $fields;
    public array $orderBy;
    public int $limit;
    public int $page;
    public string $search;
    public array $filters;
    public bool $getFileData;

    private ?EntityMetadata $metadata;

    public function __construct(string $entityClass, array $params) {
        $this->entityClass = $entityClass;
        $this->metadata = EntityMetadata::of($this->entityClass);

        $this->setFields($params);
        $this->setOrderBy($params);
        $this->setLimit($params);
        $this->setPage($params);
        $this->setSearch($params);
        $this->setFilters($params);
    }

    private function setFields(array &$params): void {
        if (empty($params['fields'])) {
            $this->fields = [];
            unset($params['fields']);
            $this->getFileData = false;

            return;
        }

        $fields = preg_split('/,(?![^{]*})/', $params['fields'], flags: PREG_SPLIT_NO_EMPTY);
        $this->getFileData = in_array('getFileData', $fields);

        foreach ($fields as $key => $field) {
            $fieldName = str_contains($field, '{') ?
                substr($field, 0, strpos($field, '{'))
                : $field;

            if (!in_array($fieldName, $this->metadata->fields)) {
                unset($fields[$key]);

                continue;
            }

            if (str_contains($field, '{')) {
                preg_match('/\{([^}]+)}/', $field, $subFields);
                $subFields = $subFields[1] ?? '';
                $fields[$fieldName] = explode(',', $subFields);
                unset($fields[$key]);
            }
        }

        $this->fields = $fields;
        unset($params['fields']);
    }

    private function setOrderBy(array &$params): void {
        if (empty($params['orderBy'])) {
            $this->orderBy = [];
            unset($params['orderBy']);

            return;
        }

        $orderBy = explode(',', $params['orderBy']);

        foreach ($orderBy as $key => $field) {
            if (str_contains($field, '.')) {
                $field = explode('.', $field)[0];
            } elseif (str_contains($field, ' ')) {
                $field = explode(' ', $field)[0];
            }

            if (!in_array($field, $this->metadata->fields)) {
                unset($orderBy[$key]);
            }
        }


        $this->orderBy = $orderBy;
        unset($params['orderBy']);
    }

    private function setLimit(array &$params): void {
        $limit = (int)($params['limit'] ?? 15);

        if (empty($limit) || !is_numeric($limit) || ($limit < 1)) {
            $limit = 15;
        } elseif ($limit > 100) {
            $limit = 100;
        }

        $this->limit = $limit;
        unset($params['limit']);
    }

    private function setPage(array &$params): void {
        $page = (int)($params['page'] ?? 1);

        if (empty($page) || ($page < 1)) {
            $page = 1;
        }

        $this->page = $page;
        unset($params['page']);
    }

    private function setSearch(array &$params): void {
        $this->search = $params['search'] ?? '';
        unset($params['search']);
    }


    private function setFilters(array $params): void {
        $filters = [];

        if (empty($params)) {
            $this->filters = $filters;

            return;
        }

        foreach ($params as $field => $value) {
            try {
                if (str_contains($field, '{')) {
                    $mainField = explode('{', $field)[0];

                    if (
                        !in_array($mainField, $this->metadata->fields)
                        || empty($this->metadata->relations[$mainField])
                    ) {
                        continue;
                    }

                    preg_match('/\{([^}]+)}/', $field, $subFields);
                    $childField = $subFields[1];

                    $field = $mainField . '.' . $childField;
                } elseif (!in_array($field, $this->metadata->fields)) {
                    continue;
                }


                if (str_starts_with($value, 'operator:')) {
                    $value = $this->parseOperatorFilterValue($value);
                } elseif (str_contains($value, ',')) {
                    $value = explode(',', $value);
                } elseif (str_contains($value, ';')) {
                    $value = [
                        'operator' => 'BETWEEN',
                        'values' => explode(';', $value)
                    ];
                }

                $filters[$field] = $value;
            } catch (Exception) {
                continue;
            }
        }

        $this->filters = $filters;
    }

    private function parseOperatorFilterValue(string $raw): ?array {
        $raw = trim($raw);

        // aceita "operator[...]" com ou sem prefixo "operator:"
        if (str_starts_with($raw, 'operator:')) {
            $raw = trim(substr($raw, strlen('operator:')));
        }

        if (!str_starts_with($raw, 'operator[') || !preg_match('/^operator\[([^]]+)](?:;(.*))?$/', $raw, $m)) {
            return null;
        }

        $operator = strtoupper(trim($m[1] ?? ''));

        $allowed = [
            'IS NULL',
            'IS NOT NULL',
            'IN',
            'NOT IN',
            'BETWEEN',
            '<>',
            '>',
            '>=',
            '<',
            '<='
        ];

        if (!in_array($operator, $allowed, true)) {
            return null;
        } elseif ($operator === 'IS NULL') {
            return null;
        }

        if ($operator === 'IS NOT NULL') {
            return [
                'operator' => $operator,
                'values' => []
            ];
        }

        $tail = trim($m[2] ?? '');
        $valuesRaw = '';

        if ($tail !== '' && preg_match('/values:(.*)$/', $tail, $vm)) {
            $valuesRaw = trim($vm[1] ?? '');
        }

        if ($valuesRaw === '' || $valuesRaw === '[]') {
            $values = [];
        } else {
            // remove colchetes caso venham (ex: [1,2,3])
            if (str_starts_with($valuesRaw, '[') && str_ends_with($valuesRaw, ']')) {
                $valuesRaw = trim(substr($valuesRaw, 1, -1));
            }

            if (str_contains($valuesRaw, ';')) {
                $values = array_values(array_filter(array_map('trim', explode(';', $valuesRaw)), static fn($v) => $v !== ''));
            } elseif (str_contains($valuesRaw, ',')) {
                $values = array_values(array_filter(array_map('trim', explode(',', $valuesRaw)), static fn($v) => $v !== ''));
            } else {
                $values = [trim($valuesRaw)];
            }
        }

        return [
            'operator' => $operator,
            'values' => $values
        ];
    }

}