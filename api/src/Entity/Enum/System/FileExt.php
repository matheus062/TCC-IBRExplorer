<?php

declare(strict_types=1);

namespace IBRExplorer\Entity\Enum\System;

enum FileExt: string {

    case PDF = 'pdf';
    case XLSX = 'xlsx';
    case TXT = 'txt';
    case JPEG = 'jpeg';
    case PNG = 'png';
    case WEBP = 'webp';
    case MP4 = 'mp4';
    case PCAP = 'pcap';
    case PCAPNG = 'pcapng';

}
