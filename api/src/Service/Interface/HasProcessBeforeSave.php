<?php

namespace IBRExplorer\Service\Interface;

use IBRExplorer\Entity\Entity;

interface HasProcessBeforeSave {

    public function processBeforeSave(Entity $entity): void;

}