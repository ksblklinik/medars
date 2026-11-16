<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Entity;

use App\Domain\Pharmacy\ValueObject\NoResep;

final readonly class ResepDokterNonRacikan
{
    public function __construct(
        public NoResep $noResep,
        public string $kodeBrng,
        public float $jml,
        public string $aturanPakai = '-',
    ) {
        if ($this->jml <= 0) {
            throw new \InvalidArgumentException('Jumlah obat resep tidak boleh <= 0.');
        }
    }
}
