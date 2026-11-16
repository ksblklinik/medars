<?php

declare(strict_types=1);

namespace App\Domain\Communication\Repository;

use App\Domain\Registration\ValueObject\NoRawat;

interface HomeCareRepositoryInterface
{
    public function hasActivePreceptorSk(string $nip, string $institusi): bool;

    /**
     * @return array<string, mixed>
     */
    public function assignPreceptor(
        NoRawat $noRawat,
        string $nip,
        string $institusi,
        string $nim,
        string $supervisorId,
    ): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listKunjungan(string $fromDate, string $toDate): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createKunjungan(array $data): array;
}
