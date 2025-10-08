<?php

namespace IBRExplorer\Service\Interface;

use IBRExplorer\Entity\Entity;

interface HasProcessAfterSave {

    public function processAfterSave(Entity $entity): void;

}