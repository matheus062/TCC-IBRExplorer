<?php

namespace IBRExplorer\Api\Trait;

use DateMalformedStringException;
use DateTime;

trait StartEndDateParamsTrait {

    public DateTime $startDate;
    public DateTime $endDate;

    protected function getStartEndDate(int $dayLimits = null): true|string {
        $startDate = $this->body['startDate'] ?? $this->params['startDate'] ?? null;
        $endDate = $this->body['endDate'] ?? $this->params['endDate'] ?? 'now';

        if (empty($startDate)) {
            return 'Data de início não informada.';
        }

        try {
            $startDate = new DateTime($startDate);
            $endDate = new DateTime($endDate);

            if (($startDate->diff($endDate)->days < 1) || ($startDate >= $endDate)) {
                return 'A data de início deve ser pelo menos 1 dia antes da data de término.';
            }
        } catch (DateMalformedStringException) {
            return 'Datas de início e fim fora do padrão `Y-m-d`.';
        }

        $this->startDate = $startDate;
        $this->endDate = $endDate;

        if (!empty($dayLimits)) {
            $diffDays = $this->startDate->diff($this->endDate)->days;

            if ($diffDays > $dayLimits) {
                return 'O intervalo entre as datas não pode ser superior a ' . $dayLimits . ' dia(s).';
            }
        }

        return true;
    }

}