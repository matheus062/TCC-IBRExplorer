<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Database\PostgreSQL;
use Psr\Http\Message\ResponseInterface as Response;

class PcapStatsAction extends PcapFileAction {

    protected function run(): Response {
        $fileId = (int)($this->arguments['id'] ?? 0);

        if (empty($fileId)) {
            return $this->respond('ID inválido.', StatusCode::BadRequest);
        }

        $userId = PostgreSQL::$instance->getUser()?->id ?? 0;
        $stats = $this->entityService->getStats($fileId, $userId);

        if ($stats === false) {
            return $this->respondWithServiceError();
        }

        return $this->respond($stats);
    }

}
