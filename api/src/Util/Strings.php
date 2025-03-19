<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

class Strings {

    public static function getEntityTableName(string $entityClass): string {
        $namespaces = explode('\\', $entityClass);
        $className = array_pop($namespaces);

        return Strings::pascalToSnakeCase($className);
    }

    public static function pascalToSnakeCase(string $string): string {
        return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
    }

}