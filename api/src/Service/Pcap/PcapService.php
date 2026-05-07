<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use Exception;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Pcap\Pcap;
use IBRExplorer\Entity\PcapFile\PcapFile;
use IBRExplorer\Service\EntityService;

class PcapService extends EntityService {

    public function __construct() {
        parent::__construct(Pcap::class);
    }

    /**
     * @param array|Pcap $data
     * @return int|false
     */
    public function create(array|Entity $data): int|false {
        return parent::create($data);
    }

    public function createInitialStructure(PcapFile $file, array $pcapData, array $headerData): bool {
        $this->setError([]);
        /** @var PcapFileService $pcapFileService */
        $pcapFileService = IBRExplorerApi::getInstance()->getEntityService(PcapFile::class);

        return $pcapFileService->update($file->id, [
                'pcap' => [
                    'startTimestamp' => $pcapData['startTimestamp'],
                    'endTimestamp' => $pcapData['endTimestamp'],
                    'packetsTotal' => $pcapData['packetsTotal'],
                    'capturedBytes' => $pcapData['capturedBytes'],
                    'flows' => [],
                    'packets' => [],
                    'header' => [
                        'magicNumber' => $headerData['magicNumber'],
                        'headerType' => $headerData['headerType'],
                        'versionMajor' => $headerData['versionMajor'],
                        'versionMinor' => $headerData['versionMinor'],
                        'linkType' => $headerData['linkType'],
                        'snapLen' => $headerData['snapLen'],
                    ],
                ],
            ]) || $this->setError($pcapFileService->getError(), $pcapFileService->getCode());
    }

    public function finalizeParsedCapture(PcapFile $file, array $summary): bool {
        $this->setError([]);

        try {
            $data = [
                'startTimestamp' => $summary['startTimestamp'],
                'endTimestamp' => $summary['endTimestamp'],
                'packetsTotal' => $summary['packetsTotal'],
                'capturedBytes' => $summary['capturedBytes'],
            ];

            if (!empty($summary['protocols'])) {
                $data['protocols'] = array_values($summary['protocols']);
            }

            if (array_key_exists('checksum', $summary)) {
                $data['checksum'] = $summary['checksum'];
            }

            if (array_key_exists('flowsTotal', $summary)) {
                $data['flowsTotal'] = $summary['flowsTotal'];
            }

            return $this->update($file->id, $data);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }
    }

    public function cleanupPartialByFileId(int $fileId): bool {
        $this->setError([]);

        try {
            PostgreSQL::$instance->execute('DELETE FROM "pcap_packet" WHERE "pcap" = ?', [$fileId]);
            PostgreSQL::$instance->execute('DELETE FROM "pcap_flow" WHERE "pcap" = ?', [$fileId]);
            PostgreSQL::$instance->execute('DELETE FROM "pcap_header" WHERE "pcap" = ?', [$fileId]);
            PostgreSQL::$instance->execute('DELETE FROM "pcap" WHERE "file" = ?', [$fileId]);
        } catch (Exception $e) {
            return $this->setError($e->getMessage(), $e->getCode());
        }

        return true;
    }

}
