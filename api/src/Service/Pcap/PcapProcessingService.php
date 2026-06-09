<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use DateTime;
use Exception;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\Pcap\Pcap;
use IBRExplorer\Entity\PcapFile\PcapFile;
use IBRExplorer\Storage\FileSystem;
use RuntimeException;

class PcapProcessingService {

    private PcapFileService $pcapFileService;
    private PcapService $pcapService;
    private TsharkPcapParser $parser;
    private PcapHeaderReader $headerReader;
    private PcapPacketBatchWriter $packetBatchWriter;
    private PcapFlowGenerationService $flowGenerationService;

    public function __construct(IBRExplorerApi $api) {
        /** @var PcapFileService $pcapFileService */
        $pcapFileService = $api->getEntityService(PcapFile::class);
        /** @var PcapService $pcapService */
        $pcapService = $api->getEntityService(Pcap::class);

        $this->pcapFileService = $pcapFileService;
        $this->pcapService = $pcapService;
        $this->parser = new TsharkPcapParser();
        $this->headerReader = new PcapHeaderReader();
        $this->packetBatchWriter = new PcapPacketBatchWriter();
        $this->flowGenerationService = new PcapFlowGenerationService();
    }

    private function log(string $message): void {
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        fwrite(STDOUT, '[' . $timestamp . '] ' . $message . PHP_EOL);
    }

    /**
     * @throws Exception
     */
    public function assertRuntimeReady(): void {
        $this->parser->assertAvailable();
    }

    /**
     * @throws Exception
     */
    public function process(PcapFile $pcapFile): void {
        if (empty($pcapFile->file)) {
            throw new RuntimeException('Arquivo sem metadados persistidos.');
        }

        $filePath = FileSystem::getInstance()->getAbsolutePath($pcapFile->fileEntityPath(), $pcapFile->file);

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Arquivo não localizado ou sem permissão de leitura no storage.');
        }

        $actualSize = filesize($filePath);

        if ($actualSize === false) {
            throw new RuntimeException('Não foi possível obter o tamanho do arquivo em processamento.');
        } elseif (!empty($pcapFile->fileSize) && ((int)$pcapFile->fileSize !== $actualSize)) {
            throw new RuntimeException('O tamanho do arquivo em disco diverge do valor persistido.');
        }

        $this->log('Arquivo #' . $pcapFile->id . ' validado. Tamanho: ' . $actualSize . ' bytes.');

        if (!$this->pcapService->cleanupPartialByFileId($pcapFile->id)) {
            throw new RuntimeException($this->pcapService->getErrorAsString());
        }

        if (DEBUG) {
            $this->log('Arquivo #' . $pcapFile->id . ': resíduos anteriores removidos.');
        }

        $header = $this->headerReader->read($filePath);

        if (DEBUG) {
            $this->log(
                'Arquivo #' . $pcapFile->id . ': header lido'
                . ' — tipo=' . ($header['headerType'] ?? '?')
                . ', linkType=' . ($header['linkType'] ?? '?')
                . '.'
            );
        }

        $batch = [];
        $pcapInitialized = false;
        $summary = [
            'startTimestamp' => null,
            'endTimestamp' => null,
            'packetsTotal' => 0,
            'capturedBytes' => 0,
            'protocols' => [],
        ];
        $offsetValidation = [
            'checked' => 0,
            'lastOffset' => null,
            'lastPacketNumber' => null,
        ];

        $this->log('Arquivo #' . $pcapFile->id . ': iniciando streaming com tshark.');

        $processStartTime = time();

        try {
            $this->parser->streamPackets($filePath, function (array $packet) use (
                &$batch,
                &$pcapInitialized,
                &$offsetValidation,
                &$summary,
                $header,
                $pcapFile,
                $actualSize,
                $processStartTime
            ): void {
                if (!$pcapInitialized) {
                    if (
                        empty($header['headerType']) ||
                        ($header['magicNumber'] ?? null) === null ||
                        ($header['versionMajor'] ?? null) === null ||
                        ($header['versionMinor'] ?? null) === null ||
                        ($header['linkType'] ?? null) === null ||
                        ($header['snapLen'] ?? null) === null
                    ) {
                        throw new RuntimeException('Não foi possível identificar o cabeçalho do arquivo PCAP/PCAPNG.');
                    }

                    if (!$this->pcapService->createInitialStructure($pcapFile, [
                        'startTimestamp' => $packet['timestamp'],
                        'endTimestamp' => $packet['timestamp'],
                        'packetsTotal' => 0,
                        'capturedBytes' => 0,
                    ], [
                        'magicNumber' => $header['magicNumber'],
                        'headerType' => $header['headerType'],
                        'versionMajor' => $header['versionMajor'],
                        'versionMinor' => $header['versionMinor'],
                        'linkType' => $header['linkType'],
                        'snapLen' => $header['snapLen'],
                    ])) {
                        throw new RuntimeException($this->pcapService->getErrorAsString());
                    }

                    $pcapInitialized = true;
                    $this->log('Arquivo #' . $pcapFile->id . ': estrutura PCAP criada.');
                }

                $this->validateOffsetSequence($packet, $offsetValidation);

                $summary['startTimestamp'] ??= $packet['timestamp'];
                $summary['endTimestamp'] = $packet['timestamp'];
                $summary['packetsTotal']++;

                if ((time() - $processStartTime) > PCAP_WORKER_MAX_PROCESS_SECONDS) {
                    throw new RuntimeException(
                        'Tempo máximo de processamento excedido (' . PCAP_WORKER_MAX_PROCESS_SECONDS . 's).'
                        . ' Ajuste PCAP_WORKER_MAX_PROCESS_SECONDS se necessário.'
                    );
                }
                $summary['capturedBytes'] += $packet['capturedLen'];

                if (!empty($packet['protocolLabel'])) {
                    $summary['protocols'][$packet['protocolLabel']] = $packet['protocolLabel'];
                }

                $batch[] = [
                    'packetNumber' => $packet['packetNumber'],
                    'timestamp' => $packet['timestamp'],
                    'offset' => $packet['offset'],
                    'capturedLen' => $packet['capturedLen'],
                    'originalLen' => $packet['originalLen'],
                    'srcIp' => $packet['srcIp'],
                    'dstIp' => $packet['dstIp'],
                    'srcPort' => $packet['srcPort'],
                    'dstPort' => $packet['dstPort'],
                    'protocol' => $packet['protocol'],
                    'flowKey' => $this->buildFlowKey($packet),
                    'ipVersion' => $packet['ipVersion'],
                    'ttl' => $packet['ttl'],
                    'ipLength' => $packet['ipLength'],
                    'payloadSize' => $packet['payloadSize'],
                    'tcpFlags' => $packet['tcpFlags'],
                    'icmpType' => $packet['icmpType'],
                    'icmpCode' => $packet['icmpCode'],
                ];

                if (count($batch) >= $this->packetBatchWriter->getBatchSize()) {
                    $this->flushPackets($pcapFile, $batch, $actualSize);
                    $batch = [];
                }
            });

            if (!$pcapInitialized) {
                throw new RuntimeException('A captura não possui pacotes processáveis nesta etapa.');
            }

            if (!empty($batch)) {
                $this->flushPackets($pcapFile, $batch, $actualSize);
            }

            $this->log('Arquivo #' . $pcapFile->id . ': streaming concluído — ' . $summary['packetsTotal'] . ' pacotes.');

            $summary['protocols'] = array_values($summary['protocols']);

            $this->log('Arquivo #' . $pcapFile->id . ': gerando flows.');
            $summary['flowsTotal'] = $this->flowGenerationService->generateForPcap($pcapFile->id);
            $this->log('Arquivo #' . $pcapFile->id . ': ' . $summary['flowsTotal'] . ' flows gerados.');

            if (!$this->pcapService->finalizeParsedCapture($pcapFile, $summary)) {
                throw new RuntimeException($this->pcapService->getErrorAsString());
            }

            $this->log('Arquivo #' . $pcapFile->id . ': captura finalizada.');
            $this->pcapFileService->updateWorkerProgress($pcapFile->id, 99.00);
        } catch (Exception $e) {
            if ($pcapInitialized) {
                $this->pcapService->cleanupPartialByFileId($pcapFile->id);
            }

            throw $e;
        }
    }

    private function validateOffsetSequence(array $packet, array &$state): void {
        $offset = (int)$packet['offset'];
        $packetNumber = (int)$packet['packetNumber'];

        if ($offset < 0) {
            throw new RuntimeException('Offset inválido no pacote #' . $packetNumber . '.');
        }

        if (($state['lastOffset'] !== null) && ($offset < $state['lastOffset'])) {
            throw new RuntimeException(
                'Sequência de offsets inválida entre os pacotes #'
                . $state['lastPacketNumber']
                . ' e #'
                . $packetNumber
                . '.'
            );
        }

        $state['checked']++;
        $state['lastOffset'] = $offset;
        $state['lastPacketNumber'] = $packetNumber;
    }

    private function buildFlowKey(array $packet): string {
        return md5(
            ($packet['srcIp'] ?? '') . '|' .
            ($packet['dstIp'] ?? '') . '|' .
            ($packet['srcPort'] ?? '') . '|' .
            ($packet['dstPort'] ?? '') . '|' .
            ($packet['protocol'] ?? '') . '|' .
            ($packet['icmpType'] ?? '') . '|' .
            ($packet['icmpCode'] ?? '')
        );
    }

    /**
     * @throws Exception
     */
    private function flushPackets(PcapFile $pcapFile, array $batch, int $fileSize): void {
        $this->packetBatchWriter->insertBatch($pcapFile->id, $batch);

        $lastPacket = $batch[array_key_last($batch)];
        $processedBytes = (int)$lastPacket['offset'] + (int)$lastPacket['capturedLen'];
        $progress = min(99, max(1, ($processedBytes / max(1, $fileSize)) * 100));
        $this->pcapFileService->updateWorkerProgress($pcapFile->id, round($progress, 2));
    }

}
