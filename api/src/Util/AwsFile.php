<?php

declare(strict_types=1);

namespace IBRExplorer\Util;

class AwsFile extends File {

    public function __construct(array|string|null $value = null) {
        parent::__construct($value);

        $this->s3Store = true;
    }

}