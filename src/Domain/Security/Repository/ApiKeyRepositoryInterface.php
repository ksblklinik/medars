<?php

declare(strict_types=1);

namespace App\Domain\Security\Repository;

interface ApiKeyRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listKeys(string $search = ''): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed> Includes 'raw_key' once on creation
     */
    public function createKey(array $data): array;

    public function toggleActive(int $id, bool $active): bool;

    public function deleteKey(int $id): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function verifyKey(string $rawKey): ?array;
}
