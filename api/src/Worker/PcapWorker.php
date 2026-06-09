<?php

declare(strict_types=1);

namespace IBRExplorer\Worker;

use DateTime;
use Exception;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\PcapFile\PcapFile;
use IBRExplorer\Service\Pcap\PcapFileService;
use IBRExplorer\Service\Pcap\PcapProcessingService;
use Throwable;

class PcapWorker {

    private PcapFileService $pcapFileService;
    private PcapProcessingService $processingService;

    public function __construct(
        private readonly int  $sleepSeconds = PCAP_WORKER_POLL_SECONDS,
        private readonly bool $once = false,
    ) {
        $api = IBRExplorerApi::getInstance();
        /** @var PcapFileService $pcapFileService */
        $pcapFileService = $api->getEntityService(PcapFile::class);

        $this->pcapFileService = $pcapFileService;
        $this->processingService = new PcapProcessingService($api);
    }

    /**
     * @throws Exception
     */
    public function run(): void {
        $this->processingService->assertRuntimeReady();
        $this->log('Worker iniciado.');

        do {
            $this->recoverStalledFiles();
            $processedJob = $this->processNextPendingFile();

            if (!$processedJob && !$this->once) {
                sleep($this->sleepSeconds);
            }
        } while (!$this->once);
    }

    private function log(string $message): void {
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        fwrite(STDOUT, '[' . $timestamp . '] ' . $message . PHP_EOL);
    }

    private function recoverStalledFiles(): void {
        $recovered = $this->pcapFileService->markStalledProcessingAsError(PCAP_WORKER_STALL_MINUTES);

        if ($recovered > 0) {
            $this->log('Recuperado(s) ' . $recovered . ' arquivo(s) travado(s) em processamento há mais de ' . PCAP_WORKER_STALL_MINUTES . ' minuto(s).');
        }
    }

    private function processNextPendingFile(): bool {
        $claimedJob = $this->pcapFileService->claimNextForWorker();

        if ($claimedJob === false) {
            if (DEBUG) {
                $this->log('Nenhum arquivo pendente na fila.');
            }

            return false;
        }

        try {
            $this->log('Processando arquivo #' . $claimedJob->id . ' (' . $claimedJob->key . ').');
            $this->processingService->process($claimedJob);
            $this->pcapFileService->markWorkerProcessed($claimedJob->id);
            $this->log('Arquivo #' . $claimedJob->id . ' finalizado.');
        } catch (Throwable $e) {
            $this->pcapFileService->markWorkerError($claimedJob->id, $e->getMessage());
            $this->log('Falha no arquivo #' . $claimedJob->id . ': ' . $e->getMessage());
        }

        return true;
    }

}
