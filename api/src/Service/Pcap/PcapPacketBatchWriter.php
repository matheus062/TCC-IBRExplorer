<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use DateTime;
use Exception;
use IBRExplorer\Database\PostgreSQL;

class PcapPacketBatchWriter {

    private const array INSERT_COLUMNS = [
        'entityStatus',
        'key',
        'createdAt',
        'createdBy',
        'updatedAt',
        'updatedBy',
        'pcap',
        'flow',
        'packetNumber',
        'timestamp',
        'offset',
        'capturedLen',
        'originalLen',
        'srcIp',
        'dstIp',
        'srcPort',
        'dstPort',
        'protocol',
        'ipVersion',
        'ttl',
        'ipLength',
        'payloadSize',
        'tcpFlags',
        'icmpType',
        'icmpCode',
    ];

    public function __construct(
        private readonly int $batchSize = PCAP_PACKET_BATCH_SIZE,
    ) {
    }

    public function getBatchSize(): int {
        return $this->batchSize;
    }

    /**
     * @throws Exception
     */
    public function insertBatch(int $pcapId, array $packets): void {
        if (empty($packets)) {
            return;
        }

        $db = PostgreSQL::$instance;
        $userId = $db->getUser()->id;
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $columnsSql = '"' . implode('", "', self::INSERT_COLUMNS) . '"';
        $valuesSql = [];
        $params = [];

        foreach ($packets as $packet) {
            $valuesSql[] = '(' . implode(', ', array_fill(0, count(self::INSERT_COLUMNS), '?')) . ')';
            $params = array_merge($params, [
                1,
                $this->buildPacketKey($pcapId, (int)$packet['packetNumber']),
                $now,
                $userId,
                $now,
                $userId,
                $pcapId,
                null,
                $packet['packetNumber'],
                $packet['timestamp'],
                $packet['offset'],
                $packet['capturedLen'],
                $packet['originalLen'],
                $packet['srcIp'],
                $packet['dstIp'],
                $packet['srcPort'],
                $packet['dstPort'],
                $packet['protocol'],
                $packet['ipVersion'],
                $packet['ttl'],
                $packet['ipLength'],
                $packet['payloadSize'],
                $packet['tcpFlags'],
                $packet['icmpType'],
                $packet['icmpCode'],
            ]);
        }

        /** @noinspection SqlInsertValues */
        $db->execute(
            'INSERT INTO "pcap_packet" (' . $columnsSql . ') VALUES ' . implode(', ', $valuesSql),
            $params
        );
    }

    private function buildPacketKey(int $pcapId, int $packetNumber): string {
        return substr(sha1($pcapId . ':' . $packetNumber), 0, 16);
    }

}
