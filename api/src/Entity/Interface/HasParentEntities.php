<?php

namespace IBRExplorer\Entity\Interface;

interface HasParentEntities {

    /**
     * Retorna os mapeamentos dos campos pais para as entidades.
     * Cada chave representa o nome do campo e o valor a classe da entidade pai.
     *
     * @return array<string, string>
     */
    public function getParentEntities(): array;

}