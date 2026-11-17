<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface SmartAgentRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listKnowledge(string $search = '', string $category = ''): array;

    /** @param array<string, mixed> $data */
    public function saveKnowledge(array $data): array;

    public function deleteKnowledge(int $id): bool;

    /** @return list<array<string, mixed>> */
    public function listProfiles(): array;

    /** @param array<string, mixed> $data */
    public function saveProfile(array $data): array;
}
