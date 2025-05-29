<?php

namespace IBRExplorer\Api\Enum;

enum ContentType: string {

    case Json = 'application/json';
    case Pdf = 'application/pdf';
    case Xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case Txt = 'text/plain';
    case Jpeg = 'image/jpeg';
    case Png = 'image/png';
    case Webp = 'image/webp';
    case Mp4 = 'video/mp4';
    case Pcap = 'application/vnd.tcpdump.pcap';
    case Pcapng = 'application/octet-stream';

}