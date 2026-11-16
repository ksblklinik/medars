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

    public function saveRujukanInternal(RujukanInternal $rujukan): void;
}
