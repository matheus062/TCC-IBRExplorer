<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

class MonthName {
    public static function getName(int $month): string {
        return match ($month) {
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        };
    }

}
