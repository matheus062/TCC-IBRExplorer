<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Enrichment;

use Psr\Http\Message\ResponseInterface as Response;

class EnrichmentFlowReadAction extends EnrichmentFlowAction {

    protected function run(): Response {
        $flow = $this->getFlow();

        if ($flow === false) {
            return $this->flowNotFoundResponse();
        }

        return $this->respond($this->flowPayload($flow));
    }

}
