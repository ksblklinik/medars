<?php

declare(strict_types=1);

namespace App\Domain\Clinical\Repository;

interface RiwayatSimgosRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function search(string $query = '', string $from = '', string $to = ''): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function importRecord(array $data): array;
}
