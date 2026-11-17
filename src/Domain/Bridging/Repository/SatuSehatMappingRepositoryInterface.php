<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface SatuSehatMappingRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listMappings(string $entityType = '', string $search = ''): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveMapping(array $data): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function enqueueFhirResource(string $resourceType, string $noRawat, array $payload): array;
}
