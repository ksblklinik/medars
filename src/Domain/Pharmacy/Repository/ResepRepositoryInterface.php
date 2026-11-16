<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Repository;

use App\Domain\Pharmacy\Entity\DetailPemberianObat;
use App\Domain\Pharmacy\Entity\ResepDokterNonRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikan;
use App\Domain\Pharmacy\Entity\ResepObat;
use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Domain\Registration\ValueObject\NoRawat;

interface ResepRepositoryInterface
{
    public function getNextNoResep(\DateTimeInterface $date): NoResep;

    public function findByNoResep(NoResep $noResep): ?ResepObat;

    /**
     * @return list<ResepObat>
     */
    public function findByNoRawat(NoRawat $noRawat): array;

    /**
     * @return list<ResepDokterNonRacikan>
     */
    public function findNonRacikan(NoResep $noResep): array;

    /**
     * @return list<ResepDokterRacikan>
     */
    public function findRacikan(NoResep $noResep): array;

    public function saveHeader(ResepObat $resep): void;

    public function saveNonRacikan(ResepDokterNonRacikan $item): void;

    public function saveRacikan(ResepDokterRacikan $racikan): void;

    public function markDispensed(NoResep $noResep, string $tglPerawatan, string $jam): void;

    public function saveObatRacikan(
        string $tglPerawatan,
        string $jam,
        NoRawat $noRawat,
        ResepDokterRacikan $racikan
    ): void;

    public function saveDetailPemberianObat(DetailPemberianObat $detail): void;

    public function deleteHeaderAndItems(NoResep $noResep): void;
}
