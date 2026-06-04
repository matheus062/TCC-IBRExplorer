<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use IBRExplorer\Entity\Enrichment\EnrichmentIntegration;
use IBRExplorer\Entity\Enrichment\EnrichmentTarget;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class MaxMindGeoLite2Service {

    private ?Reader $cityReader = null;
    private ?string $cityDatabasePath = null;

    public function lookupCity(EnrichmentIntegration $integration, EnrichmentTarget $target): array {
        $databasePath = $this->resolveCityDatabasePath($integration);

        if ($databasePath === null) {
            return [
                'found' => false,
                'error' => 'Base GeoLite2 City não localizada em geoip.',
            ];
        }

        try {
            $reader = $this->cityReader($databasePath);
            $record = $reader->city($target->normalizedValue);
        } catch (AddressNotFoundException) {
            return [
                'found' => false,
                'error' => 'IP não localizado na base GeoLite2 City.',
            ];
        } catch (Throwable $exception) {
            return [
                'found' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $data = [
            'ip' => $target->normalizedValue,
            'database' => [
                'path' => $databasePath,
                'updatedAt' => date('Y-m-d H:i:s', filemtime($databasePath) ?: time()),
            ],
            'network' => $record->traits->network,
            'continent' => [
                'code' => $record->continent->code,
                'name' => $record->continent->name,
                'geonameId' => $record->continent->geonameId,
            ],
            'country' => [
                'isoCode' => $record->country->isoCode,
                'name' => $record->country->name,
                'geonameId' => $record->country->geonameId,
                'isInEuropeanUnion' => $record->country->isInEuropeanUnion,
            ],
            'registeredCountry' => [
                'isoCode' => $record->registeredCountry->isoCode,
                'name' => $record->registeredCountry->name,
                'geonameId' => $record->registeredCountry->geonameId,
                'isInEuropeanUnion' => $record->registeredCountry->isInEuropeanUnion,
            ],
            'city' => [
                'name' => $record->city->name,
                'geonameId' => $record->city->geonameId,
            ],
            'subdivisions' => array_map(
                fn($subdivision) => [
                    'isoCode' => $subdivision->isoCode,
                    'name' => $subdivision->name,
                    'geonameId' => $subdivision->geonameId,
                ],
                iterator_to_array($record->subdivisions)
            ),
            'location' => [
                'latitude' => $record->location->latitude,
                'longitude' => $record->location->longitude,
                'accuracyRadius' => $record->location->accuracyRadius,
                'timeZone' => $record->location->timeZone,
                'metroCode' => $record->location->metroCode,
                'postalCode' => $record->postal->code,
            ],
        ];

        return [
            'found' => true,
            'data' => $data,
            'summary' => [
                'country' => $record->country->isoCode,
                'countryName' => $record->country->name,
                'city' => $record->city->name,
                'subdivision' => $record->mostSpecificSubdivision->name,
                'latitude' => $record->location->latitude,
                'longitude' => $record->location->longitude,
                'accuracyRadius' => $record->location->accuracyRadius,
                'databasePath' => $databasePath,
            ],
        ];
    }

    private function resolveCityDatabasePath(EnrichmentIntegration $integration): ?string {
        $configuredPath = $integration->config?->getValue()['cityDatabasePath'] ?? null;

        if (!empty($configuredPath)) {
            $path = $this->normalizePath((string)$configuredPath);

            if (is_file($path)) {
                return $path;
            }
        }

        return $this->latestDatabasePath();
    }

    private function normalizePath(string $path): string {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRoot() . '/' . ltrim($path, '/');
    }

    private function projectRoot(): string {
        return dirname(__DIR__, 4);
    }

    private function latestDatabasePath(): ?string {
        $geoIpDirectory = $this->projectRoot() . '/geoip';

        if (!is_dir($geoIpDirectory)) {
            return null;
        }

        $candidates = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($geoIpDirectory));

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'mmdb') {
                continue;
            }

            $path = $file->getPathname();
            $basename = strtolower($file->getBasename());

            if (!str_contains($basename, 'city')) {
                continue;
            }

            $candidates[] = [
                'path' => $path,
                'mtime' => $file->getMTime(),
            ];
        }

        usort(
            $candidates,
            fn(array $a, array $b) => [$b['mtime'], $b['path']] <=> [$a['mtime'], $a['path']]
        );

        return $candidates[0]['path'] ?? null;
    }

    private function cityReader(string $databasePath): Reader {
        if ($this->cityReader === null || $this->cityDatabasePath !== $databasePath) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->cityReader = new Reader($databasePath);
            $this->cityDatabasePath = $databasePath;
        }

        return $this->cityReader;
    }

}
