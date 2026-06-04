<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use Exception;
use IBRExplorer\Database\PostgreSQL;
use RuntimeException;

class PcapFlowGenerationService {

    private const int MAX_ATTEMPTS = 2;

    /**
     * @throws Exception
     */
    public function generateForPcap(int $pcapId): int {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->generateOnce($pcapId);
            } catch (Exception $e) {
                $lastException = $e;
            }
        }

        throw new RuntimeException(
            'Falha ao gerar flows da captura após ' . self::MAX_ATTEMPTS . ' tentativas.'
            . ($lastException !== null ? ' ' . $lastException->getMessage() : '')
        );
    }

    /**
     * @throws Exception
     */
    private function generateOnce(int $pcapId): int {
        $db = PostgreSQL::$instance;
        $startedTransaction = false;

        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $startedTransaction = true;
            }

            $db->execute('DELETE FROM "pcap_flow" WHERE "pcap" = ?', [$pcapId]);

            $db->execute($this->buildInsertFlowsSql(), [
                $pcapId,
                $pcapId,
                $db->getUser()->id,
                $db->getUser()->id,
                $pcapId,
            ]);

            $insertedFlows = $db->getLastStatement()?->fetchColumn();
            $flowsTotal = ($insertedFlows !== false && $insertedFlows !== null)
                ? (int)$insertedFlows
                : 0;

            if ($startedTransaction) {
                $db->commit();
            }

            return $flowsTotal;
        } catch (Exception $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollback();
            }

            throw $e;
        }
    }

    private function buildInsertFlowsSql(): string {
        return '
            WITH aggregated AS (
                SELECT
                    "flowKey",
                    "srcIp",
                    "dstIp",
                    "srcPort",
                    "dstPort",
                    "protocol",
                    "icmpType",
                    "icmpCode",
                    COUNT(*) AS "packetCount",
                    COALESCE(SUM("capturedLen"), 0) AS "bytesTotal",
                    MIN("timestamp") AS "startTimestamp",
                    MAX("timestamp") AS "endTimestamp"
                FROM "pcap_packet"
                WHERE "pcap" = ?
                  AND "entityStatus" = 1
                  AND "srcIp" IS NOT NULL
                  AND "dstIp" IS NOT NULL
                  AND "protocol" IS NOT NULL
                  AND "flowKey" IS NOT NULL
                GROUP BY
                    "flowKey",
                    "srcIp",
                    "dstIp",
                    "srcPort",
                    "dstPort",
                    "protocol",
                    "icmpType",
                    "icmpCode"
            ),
            inserted AS (
                INSERT INTO "pcap_flow" (
                    "entityStatus",
                    "key",
                    "createdAt",
                    "createdBy",
                    "updatedAt",
                    "updatedBy",
                    "pcap",
                    "flowKey",
                    "srcIp",
                    "dstIp",
                    "srcPort",
                    "dstPort",
                    "protocol",
                    "icmpType",
                    "icmpCode",
                    "packetCount",
                    "bytesTotal",
                    "startTimestamp",
                    "endTimestamp"
                )
                SELECT
                    1,
                    substr(md5(?::text || \':\' || "flowKey"), 1, 16),
                    CURRENT_TIMESTAMP,
                    ?,
                    CURRENT_TIMESTAMP,
                    ?,
                    ?,
                    "flowKey",
                    "srcIp",
                    "dstIp",
                    "srcPort",
                    "dstPort",
                    "protocol",
                    "icmpType",
                    "icmpCode",
                    "packetCount",
                    "bytesTotal",
                    "startTimestamp",
                    "endTimestamp"
                FROM aggregated
                RETURNING "id"
            )
            SELECT COUNT(*) FROM inserted
        ';
    }

}
