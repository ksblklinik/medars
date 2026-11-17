<?php

declare(strict_types=1);

namespace App\Domain\Master\Repository;

interface MasterDataRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $entity, string $q = ''): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function save(string $entity, array $payload): void;

    /**
     * @param array<string, mixed> $keys
     */
    public function delete(string $entity, array $keys): void;
}
