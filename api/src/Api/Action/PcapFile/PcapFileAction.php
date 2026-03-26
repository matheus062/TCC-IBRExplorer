<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\PcapFile;

use IBRExplorer\Api\Action\Entity\EntityAction;
use IBRExplorer\Entity\PcapFile\PcapFile;
use IBRExplorer\Service\EntityService;
use IBRExplorer\Service\Pcap\PcapFileService;

abstract class PcapFileAction extends EntityAction {
    /**
     * @var PcapFileService
     */
    protected EntityService $entityService;

    public function __construct() {
        $this->entityClass = PcapFile::class;
    }

}