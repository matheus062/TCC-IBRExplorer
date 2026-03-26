<?php

declare(strict_types=1);

namespace IBRExplorer\Validator\PcapFile;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Enum\System\FileExt;
use IBRExplorer\Entity\PcapFile\PcapFile;
use IBRExplorer\Validator\EntityValidator;

class PcapFileValidator extends EntityValidator {

    /**
     * @param PcapFile $entity
     * @return bool
     */
    public function isValid(Entity $entity): bool {
        parent::isValid($entity);

        if (!empty($entity->file) && !in_array($entity->file->ext ?? '', [FileExt::PCAP, FileExt::PCAPNG])) {
            $this->addMessage('file', 'Somente arquivos de extensão `pcap` e `pcapng` são aceitos.');
        }

        return empty($this->messages);
    }

}