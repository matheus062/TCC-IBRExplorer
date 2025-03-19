<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action;

use Exception;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Util\Strings;
use ReflectionClass;

readonly class EntityActionParams {

    public string $entityClass;
    public array $fields;
    public array $orderBy;
    public int $limit;
    public int $page;
    public string $search;
    public array $filters;

    private ReflectionClass $reflection;

    public function __construct(string $entityClass, array $params) {
        $this->entityClass = $entityClass;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->reflection = new ReflectionClass($this->entityClass);

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

            return;
        }

        $fields = preg_split('/,(?![^{]*})/', $params['fields'], flags: PREG_SPLIT_NO_EMPTY);

        foreach ($fields as $key => $field) {
            $fieldName = str_contains($field, '{') ?
                substr($field, 0, strpos($field, '{'))
                : $field;

            if (!$this->reflection->hasProperty($fieldName)) {
                unset($fields[$key]);

                continue;
            }

            if (str_contains($field, '{')) {
                preg_match('/\{([^}]+)}/', $field, $subFields);
                $subFields = $subFields[1];
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
            if (str_contains($field, ' ')) {
                $field = explode(' ', $field)[0];
            }

            if (!$this->reflection->hasProperty($field)) {
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

        /** @var Entity $entityTemplate */
        $entityTemplate = new $this->entityClass(0);

        foreach ($params as $field => $value) {
            try {
                if (str_contains($field, '{')) {
                    $mainField = explode('{', $field)[0];
                    $childClass = $entityTemplate->isEntity($mainField);

                    if (!$this->reflection->hasProperty($mainField) || empty($childClass)) {
                        continue;
                    }

                    preg_match('/\{([^}]+)}/', $field, $subFields);
                    $childTable = Strings::getEntityTableName($childClass);
                    $childField = $subFields[1];

                    $field = $childTable . '.' . $childField;
                } elseif (!$this->reflection->hasProperty($field)) {
                    continue;
                }

                if (str_contains($value, ',')) {
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

}