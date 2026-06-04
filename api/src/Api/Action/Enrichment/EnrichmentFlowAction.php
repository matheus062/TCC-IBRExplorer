<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Enrichment;

use IBRExplorer\Api\Action\Action;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentResult;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;
use IBRExplorer\Entity\Pcap\PcapFlow;
use IBRExplorer\Service\Enrichment\EnrichmentCapabilityService;
use IBRExplorer\Service\Enrichment\EnrichmentExecutionService;
use IBRExplorer\Service\Enrichment\EnrichmentTargetService;
use Psr\Http\Message\ResponseInterface as Response;

abstract class EnrichmentFlowAction extends Action {

    protected EnrichmentTargetService $targetService;
    protected EnrichmentCapabilityService $capabilityService;
    protected EnrichmentExecutionService $executionService;

    protected function prepare(): void {
        parent::prepare();

        $this->targetService = new EnrichmentTargetService();
        $this->capabilityService = new EnrichmentCapabilityService();
        $this->executionService = new EnrichmentExecutionService();
    }

    protected function getFlow(): PcapFlow|false {
        $id = (int)($this->arguments['id'] ?? 0);

        if (empty($id)) {
            return false;
        }

        $flowService = IBRExplorerApi::getInstance()->getEntityService(PcapFlow::class);

        /** @var PcapFlow|false $flow */
        $flow = $flowService->getById($id, [
            'id',
            'key',
            'pcap',
            'srcIp',
            'dstIp',
            'srcPort',
            'dstPort',
            'protocol',
            'icmpType',
            'icmpCode',
            'packetCount',
            'bytesTotal',
            'startTimestamp',
            'endTimestamp',
        ]);

        return $flow;
    }

    protected function flowPayload(PcapFlow $flow): array {
        $targetObservations = $this->targetService->targetObservationsFromFlow($flow);
        $targets = $this->targetService->uniqueTargetsFromObservations($targetObservations);

        return [
            'flow' => $flow,
            'targets' => $this->targetsPayload($targetObservations),
            'capabilities' => $this->capabilitiesPayload(
                $this->capabilityService->capabilitiesForTargets($targets),
                $targetObservations
            ),
        ];
    }

    /**
     * @param array<int, array{target: EnrichmentTarget, fields: string[], roles: string[]}> $targetObservations
     */
    protected function targetsPayload(array $targetObservations): array {
        $payload = [];

        foreach ($targetObservations as $observation) {
            $target = $observation['target'] ?? null;

            if (!$target instanceof EnrichmentTarget) {
                continue;
            }

            $payload[] = [
                'fields' => $observation['fields'] ?? [],
                'roles' => $observation['roles'] ?? [],
                'target' => $target,
                'results' => $this->resultsForTarget($target),
            ];
        }

        return $payload;
    }

    protected function resultsForTarget(EnrichmentTarget $target): array {
        $resultService = IBRExplorerApi::getInstance()->getEntityService(EnrichmentResult::class);
        $list = $resultService->list(
            [
                'id',
                'key',
                'target',
                'integration' => [
                    'id',
                    'key',
                    'provider',
                    'identifier',
                    'name',
                    'enabled',
                    'alwaysExecute'
                ],
                'status',
                'data',
                'summary',
                'confidence',
                'error',
                'fetchedAt',
                'expiresAt',
            ],
            ['target' => $target->id],
            ['id ASC'],
            100
        );

        return $list['entities'] ?? [];
    }

    protected function capabilitiesPayload(array $capabilities, array $targetObservations = []): array {
        $observationIndex = $this->targetObservationIndex($targetObservations);

        return array_map(
            fn(array $capability) => [
                'integration' => $this->integrationPayload($capability['integration']),
                'target' => $capability['target'],
                'fields' => $observationIndex[$capability['target']->id]['fields'] ?? [],
                'roles' => $observationIndex[$capability['target']->id]['roles'] ?? [],
                'capable' => $capability['capable'],
                'alwaysExecute' => $capability['alwaysExecute'],
                'reason' => $capability['reason'],
            ],
            $capabilities
        );
    }

    protected function targetObservationIndex(array $targetObservations): array {
        $index = [];

        foreach ($targetObservations as $observation) {
            $target = $observation['target'] ?? null;

            if (!$target instanceof EnrichmentTarget) {
                continue;
            }

            $index[$target->id] = [
                'fields' => $observation['fields'] ?? [],
                'roles' => $observation['roles'] ?? [],
            ];
        }

        return $index;
    }

    protected function integrationPayload(EnrichmentIntegration $integration): array {
        return [
            'id' => $integration->id,
            'key' => $integration->key,
            'provider' => $integration->provider->value,
            'identifier' => $integration->identifier,
            'name' => $integration->name,
            'enabled' => $integration->enabled,
            'alwaysExecute' => $integration->alwaysExecute,
            'requiresApiKey' => $integration->requiresApiKey,
            'dailyLimit' => $integration->dailyLimit,
            'weeklyLimit' => $integration->weeklyLimit,
            'monthlyLimit' => $integration->monthlyLimit,
            'dailyUsed' => $integration->dailyUsed,
            'weeklyUsed' => $integration->weeklyUsed,
            'monthlyUsed' => $integration->monthlyUsed,
        ];
    }

    protected function flowNotFoundResponse(): Response {
        return $this->respond('Flow não localizado.', StatusCode::NotFound);
    }

}
