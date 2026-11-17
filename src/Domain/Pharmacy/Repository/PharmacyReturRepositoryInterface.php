<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Repository;

interface PharmacyReturRepositoryInterface
{
    /** @return array<string, mixed> */
    public function returDispensedObat(
        string $noResep,
        string $kodeBrng,
        float $qtyRetur,
        string $kdBangsal,
        string $petugasNip,
        string $alasan
    ): array;
}
