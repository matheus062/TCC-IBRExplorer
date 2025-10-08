<?php

declare(strict_types=1);

namespace IBRExplorer\Storage\AwsS3\Config;

readonly class AwsS3FileSystemConfig {

    public string $AwsRegion;
    public string $AwsBucket;
    public string $AwsAccessKeyId;
    public string $AwsSecretAccessKey;

    public function __construct(
        string $AwsRegion,
        string $AwsBucket,
        string $AwsAccessKeyId,
        string $AwsSecretAccessKey,
    ) {
        $this->AwsRegion = $AwsRegion;
        $this->AwsBucket = $AwsBucket;
        $this->AwsAccessKeyId = $AwsAccessKeyId;
        $this->AwsSecretAccessKey = $AwsSecretAccessKey;
    }

}