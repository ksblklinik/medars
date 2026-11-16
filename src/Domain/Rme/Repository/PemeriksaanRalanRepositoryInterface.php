<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\PemeriksaanRalan;
use App\Domain\Rme\Entity\RmeMetadata;

interface PemeriksaanRalanRepositoryInterface
{
    public function findByTriplet(NoRawat $noRawat, string $tglPerawatan, string $jamRawat): ?PemeriksaanRalan;

    public function findLatestByNoRawat(NoRawat $noRawat): ?PemeriksaanRalan;

    /**
     * @return list<PemeriksaanRalan>
     */
    public function findByNoRawat(NoRawat $noRawat): array;

    public function save(PemeriksaanRalan $pemeriksaan): void;

    public function saveMetadata(RmeMetadata $metadata): void;
}
