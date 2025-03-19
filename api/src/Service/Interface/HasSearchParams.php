<?php

namespace IBRExplorer\Service\Interface;

interface HasSearchParams {

    public function getSearchParams(string $search): array;

}