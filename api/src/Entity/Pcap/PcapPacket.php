<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Pcap;

class PcapPacket extends PcapChild {

    // PCAP Packet
    //  - Timestamp segundos (32 bits)
    //  - Timestamp Micro ou Nano (32 bits)
    //  - Tamanho do pacote (32 bits)
    //  - Tamanho do pacote original (32 bits)
    //  - Payload (Ethernet header, IP Packet e Checksum...)

    public float $timestamp;
    public int $capturedLen;
    public int $originalLen;
    public string $srcIp;
    public string $dstIp;
    public ?int $srcPort = null;
    public ?int $dstPort = null;
    public int $protocol;
    public int $ttl;
    public int $ipLength;
    public ?int $tcpFlags = null;
    public ?int $icmpType = null;
    public ?int $icmpCode = null;


    //frame.time_epoch ou frame.time: timestamp do pacote (UTC) – permite filtros por intervalo.
    //ip.src e ip.dst: endereços IP de origem e destino.
    //ip.len: comprimento total do datagrama IP.
    //ip.ttl: TTL (Time To Live) do pacote – útil para analisar origem ou detectar varreduras.
    //ip.proto: protocolo IP (6=TCP,17=UDP,1=ICMP etc.).
    //tcp.srcport, tcp.dstport: portas TCP de origem/destino (se aplicável).
    //tcp.flags: flags TCP (valor inteiro ou hexa); ou separá-las em booleanos (SYN, ACK, etc.).
    //udp.srcport, udp.dstport: portas UDP (se aplicável).
    //icmp.type, icmp.code: tipo e código ICMP (se for protocolo ICMP).

    // Implementar o isEntity...

}