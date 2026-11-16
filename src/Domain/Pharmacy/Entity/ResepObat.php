<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Entity;

use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Domain\Registration\ValueObject\NoRawat;

final readonly class ResepObat
{
    public function __construct(
        public NoResep $noResep,
        public string $tglPeresepan,
        public string $jamPeresepan,
        public NoRawat $noRawat,
        public string $kdDokter,
        public string $tglPerawatan = '0000-00-00',
        public string $jam = '00:00:00',
        public string $status = 'ralan',
    ) {
    }

    public function isDispensed(): bool
    {
        return $this->tglPerawatan !== '' && $this->tglPerawatan !== '0000-00-00';
    }
}
