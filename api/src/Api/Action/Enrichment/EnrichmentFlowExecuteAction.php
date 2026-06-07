<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Enrichment;

use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enum\Enrichment\EnrichmentStatus;
use Psr\Http\Message\ResponseInterface as Response;

class EnrichmentFlowExecuteAction extends EnrichmentFlowAction {

    protected function run(): Response {
        $flow = $this->getFlow();

        if ($flow === false) {
            return $this->flowNotFoundResponse();
        }

        $targetObservations = $this->targetService->targetObservationsFromFlow($flow);
        $targets = $this->targetService->uniqueTargetsFromObservations($targetObservations);
        $capabilities = $this->capabilityService->capabilitiesForTargets($targets);
        $observationIndex = $this->targetObservationIndex($targetObservations);
        $requestedProviders = $this->requestedProviders();
        $executions = [];

        $requestedTargets = $this->requestedTargets();

        foreach ($capabilities as $capability) {
            /** @var EnrichmentIntegration $integration */
            $integration = $capability['integration'];

            if (!empty($requestedTargets) && !in_array($capability['target']->id, $requestedTargets, true)) {
                continue;
            }

            $shouldExecute = empty($requestedProviders)
                ? $capability['alwaysExecute'] && $capability['capable']
                : in_array($integration->identifier, $requestedProviders, true);

            if (!$shouldExecute) {
                continue;
            }

            if (!$capability['capable']) {
                $executions[] = [
                    'integration' => $this->integrationPayload($integration),
                    'target' => $capability['target'],
                    'fields' => $observationIndex[$capability['target']->id]['fields'] ?? [],
                    'roles' => $observationIndex[$capability['target']->id]['roles'] ?? [],
                    'result' => [
                        'success' => false,
                        'status' => EnrichmentStatus::Skipped->value,
                        'error' => $capability['reason'] ?? 'Integração não apta para execução.',
                    ],
                ];

                continue;
            }

            $executions[] = [
                'integration' => $this->integrationPayload($integration),
                'target' => $capability['target'],
                'fields' => $observationIndex[$capability['target']->id]['fields'] ?? [],
                'roles' => $observationIndex[$capability['target']->id]['roles'] ?? [],
                'result' => $this->executionService->execute($integration, $capability['target']),
            ];
        }

        return $this->respond([
            'flow' => $flow,
            'targets' => $this->targetsPayload($targetObservations),
            'capabilities' => $this->capabilitiesPayload($capabilities, $targetObservations),
            'executions' => $executions,
        ]);
    }

    private function requestedTargets(): array {
        $targets = $this->body['targets'] ?? [];

        if (!is_array($targets)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($id) => (int)$id,
            $targets
        )));
    }

    private function requestedProviders(): array {
        $providers = $this->body['providers'] ?? [];

        if (is_string($providers)) {
            $providers = explode(',', $providers);
        }

        if (!is_array($providers)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($provider) => strtolower(trim((string)$provider)),
            $providers
        )));
    }

}
