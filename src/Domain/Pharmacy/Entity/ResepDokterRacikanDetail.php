<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Entity;

use App\Domain\Pharmacy\ValueObject\NoResep;

final readonly class ResepDokterRacikanDetail
{
    public function __construct(
        public NoResep $noResep,
        public string $noRacik,
        public string $kodeBrng,
        public float $p1 = 1.0,
        public float $p2 = 1.0,
        public string $kandungan = '',
        public float $jml = 1.0,
    ) {
        if ($this->jml <= 0) {
            throw new \InvalidArgumentException('Jumlah bahan racikan harus > 0.');
        }
    }
}
