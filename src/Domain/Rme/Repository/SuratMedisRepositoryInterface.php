<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

use App\Domain\Rme\Entity\RujukanInternal;
use App\Domain\Rme\Entity\SuratBebasNarkoba;
use App\Domain\Rme\Entity\SuratHamil;
use App\Domain\Rme\Entity\SuratSakit;

interface SuratMedisRepositoryInterface
{
    public function getNextSuratNumber(string $prefix, string $table): string;

    public function saveSuratSakit(SuratSakit $surat): void;

    public function saveSuratHamil(SuratHamil $surat): void;

    public function saveSuratBebasNarkoba(SuratBebasNarkoba $surat): void;

    /** @param array<string, mixed> $data */
    public function saveSuratSehat(array $data): string;

    /** @param array<string, mixed> $data */
    public function saveSuratCutiHamil(array $data): string;

    public function saveRujukanInternal(RujukanInternal $rujukan): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listSurat(string $type, string $q = ''): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findSuratForPrint(string $type, string $noSurat): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listRujukanInternal(string $q = ''): array;
}
