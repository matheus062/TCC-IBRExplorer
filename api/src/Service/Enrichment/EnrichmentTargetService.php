<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment;

use DateTime;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentTargetType;
use IBRExplorer\Entity\Pcap\PcapFlow;
use IBRExplorer\Service\EntityService;

class EnrichmentTargetService extends EntityService {

    public function __construct() {
        parent::__construct(EnrichmentTarget::class);
    }

    /**
     * @return array<int, array{target: EnrichmentTarget, type: string, value: string, normalizedValue: string, fields: string[], roles: string[]}>
     */
    public function targetObservationsFromFlow(PcapFlow $flow): array {
        $observations = [];

        foreach ($this->flowIpCandidates($flow) as $candidate) {
            $ip = trim((string)$candidate['value']);

            if (!$this->isValidIp($ip)) {
                continue;
            }

            $target = $this->getOrCreateTarget(EnrichmentTargetType::Ip, $ip);

            if ($target === false) {
                continue;
            }

            $key = sprintf('%s:%s', $target->type->value, $target->normalizedValue);

            if (!isset($observations[$key])) {
                $observations[$key] = [
                    'target' => $target,
                    'type' => $target->type->value,
                    'value' => $target->value,
                    'normalizedValue' => $target->normalizedValue,
                    'fields' => [],
                    'roles' => [],
                ];
            }

            $observations[$key]['fields'][] = $candidate['field'];
            $observations[$key]['roles'][] = $candidate['role'];
            $observations[$key]['fields'] = array_values(array_unique($observations[$key]['fields']));
            $observations[$key]['roles'] = array_values(array_unique($observations[$key]['roles']));
        }

        return array_values($observations);
    }

    private function flowIpCandidates(PcapFlow $flow): array {
        return [
            [
                'field' => 'srcIp',
                'role' => 'source',
                'value' => $flow->srcIp ?? null,
            ],
            [
                'field' => 'dstIp',
                'role' => 'destination',
                'value' => $flow->dstIp ?? null,
            ],
        ];
    }

    private function isValidIp(string $value): bool {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public function getOrCreateTarget(EnrichmentTargetType $type, string $value): EnrichmentTarget|false {
        $this->setError([]);

        $normalizedValue = $this->normalizeValue($type, $value);
        $existing = $this->list(
            ['id', 'type', 'value', 'normalizedValue', 'firstSeenAt', 'lastSeenAt', 'lastEnrichedAt'],
            [
                'type' => $type->value,
                'normalizedValue' => $normalizedValue
            ],
            limit: 1
        );

        if ($existing === false) {
            return false;
        }

        if (!empty($existing['entities'][0])) {
            return $existing['entities'][0];
        }

        $now = (new DateTime())->format('Y-m-d H:i:s');
        $id = $this->create([
            'type' => $type->value,
            'value' => $value,
            'normalizedValue' => $normalizedValue,
            'firstSeenAt' => $now,
            'lastSeenAt' => $now,
        ]);

        if ($id === false) {
            return false;
        }

        /** @var EnrichmentTarget|false $target */
        $target = $this->getById($id, [
            'id',
            'type',
            'value',
            'normalizedValue',
            'firstSeenAt',
            'lastSeenAt',
            'lastEnrichedAt'
        ]);

        return $target;
    }

    private function normalizeValue(EnrichmentTargetType $type, string $value): string {
        $value = trim($value);

        return match ($type) {
            EnrichmentTargetType::Ip, EnrichmentTargetType::Domain, EnrichmentTargetType::Url => strtolower($value),
            EnrichmentTargetType::Hash => strtolower(preg_replace('/\s+/', '', $value)),
            EnrichmentTargetType::Asn => strtoupper(ltrim($value, 'asAS')),
        };
    }

    /**
     * @return EnrichmentTarget[]
     */
    public function uniqueTargetsFromObservations(array $observations): array {
        $targets = [];

        foreach ($observations as $observation) {
            $target = $observation['target'] ?? null;

            if (!$target instanceof EnrichmentTarget) {
                continue;
            }

            $targets[$target->id] = $target;
        }

        return array_values($targets);
    }

}
