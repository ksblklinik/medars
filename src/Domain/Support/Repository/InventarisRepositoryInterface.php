<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface InventarisRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listAset(string $q = ''): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function saveAset(array $payload): void;

    public function deleteAset(string $kodeAset): void;
}
