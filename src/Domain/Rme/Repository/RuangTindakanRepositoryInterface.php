<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

interface RuangTindakanRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listFollowup(string $from = '', string $to = '', string $search = ''): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveFollowup(array $data, string $actorRole = 'medis'): array;

    public function deleteFollowup(int $id): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;
}
