<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

use DateTime;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\PcapFile\PcapFile;

class Pcap extends Entity {

    public PcapFile $file;
    public DateTime $startTimestamp;    // timestamp do primeiro pacote capturado.
    public DateTime $endTimestamp;      // timestamp do último pacote capturado.
    public ?int $packetsTotal;           // número total de pacotes no PCAP. Pode ser obtido do próprio capinfos.
    /**
     * @var PcapProtocol
     */
    public ?array $protocols;            // lista de protocolos detectados (por exemplo, TCP, UDP, ICMP), que pode ser armazenada como texto estruturado (JSON ou CSV) ou em tabela auxiliar de protocolos.
    public ?string $checksum;            // checksum do arquivo?
    public PcapHeader $header;
    /**
     * @var PcapPacket[]
     */
    public array $packets;

    // Implementar o isEntity...

}
