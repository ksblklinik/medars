<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface VeronisaRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listClaims(string $from = '', string $to = '', string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function saveClaim(array $data): array;

    public function updateStatus(int $id, string $status, string $catatan = ''): bool;

    public function deleteClaim(int $id): bool;

    /** @return array<string, mixed>|null */
    public function findClaimById(int $id): ?array;

    /** @return list<array<string, mixed>> */
    public function listObatMapping(string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function saveObatMapping(array $data): array;
}
