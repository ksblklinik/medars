<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface OrthancRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listStudies(string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function saveStudy(array $data): array;

    public function updateHasilBaca(int $id, string $hasilBaca, string $dokterRadiologi): bool;

    public function deleteStudy(int $id): bool;
}
