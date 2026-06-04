<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Enrichment;

use Psr\Http\Message\ResponseInterface as Response;

class EnrichmentFlowCapabilitiesAction extends EnrichmentFlowAction {

    protected function run(): Response {
        $flow = $this->getFlow();

        if ($flow === false) {
            return $this->flowNotFoundResponse();
        }

        $targetObservations = $this->targetService->targetObservationsFromFlow($flow);
        $targets = $this->targetService->uniqueTargetsFromObservations($targetObservations);

        return $this->respond([
            'flow' => $flow,
            'targets' => $this->targetsPayload($targetObservations),
            'capabilities' => $this->capabilitiesPayload(
                $this->capabilityService->capabilitiesForTargets($targets),
                $targetObservations
            ),
        ]);
    }

}
