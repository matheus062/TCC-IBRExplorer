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

    public static function onlyNumbers(string $string): string {
        return preg_replace('/\D+/', '', $string);
    }

    public static function onlyNumbersAndLetters(string $string): string {
        return preg_replace('/[^a-zA-Z0-9]+/', '', $string);
    }

}