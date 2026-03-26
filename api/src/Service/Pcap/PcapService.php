<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Pcap;

use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\Pcap\Pcap;
use IBRExplorer\Service\EntityService;

class PcapService extends EntityService {

    public function __construct() {
        parent::__construct(Pcap::class);
    }

    public function create(array|Entity $data): int|false {
        // 1. Primeiro passo é salvar o arquivo no banco, com alguns dados iniciais, extraídos do cabeçalho
        // 2. Segundo passo é colocar o arquivo na fila de processamento.
        // 3. O processamento será realizado para os pacotes capturados

        // TODO: Só vou receber o base64


        return parent::create($data);
    }

}