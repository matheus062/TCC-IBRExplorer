<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment;

use DateTime;
use Exception;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;

class EnrichmentLimitService {

    public function canUse(EnrichmentIntegration $integration): array {
        foreach ([
                     'daily' => ['limit' => 'dailyLimit', 'used' => 'dailyUsed'],
                     'weekly' => ['limit' => 'weeklyLimit', 'used' => 'weeklyUsed'],
                     'monthly' => ['limit' => 'monthlyLimit', 'used' => 'monthlyUsed'],
                 ] as $period => $fields) {
            $limit = $integration->{$fields['limit']} ?? null;
            $used = $integration->{$fields['used']} ?? 0;

            if ($limit !== null && $used >= $limit) {
                return [
                    'allowed' => false,
                    'reason' => 'Limite ' . $period . ' esgotado.',
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * @throws Exception
     */
    public function registerUse(EnrichmentIntegration $integration, int $amount = 1): bool {
        return PostgreSQL::$instance->execute(
            '
                UPDATE "enrichment_integration"
                SET "dailyUsed" = "dailyUsed" + ?,
                    "weeklyUsed" = "weeklyUsed" + ?,
                    "monthlyUsed" = "monthlyUsed" + ?,
                    "lastUsedAt" = ?
                WHERE "id" = ?
            ',
            [
                $amount,
                $amount,
                $amount,
                (new DateTime())->format('Y-m-d H:i:s'),
                $integration->id
            ]
        );
    }

}
