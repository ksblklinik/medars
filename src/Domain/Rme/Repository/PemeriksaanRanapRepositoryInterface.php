<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\PemeriksaanRanap;

interface PemeriksaanRanapRepositoryInterface
{
    /**
     * @return list<PemeriksaanRanap>
     */
    public function findByNoRawat(NoRawat $noRawat): array;

    public function save(PemeriksaanRanap $pemeriksaan): void;
}
