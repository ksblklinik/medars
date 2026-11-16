<?php

declare(strict_types=1);

namespace App\Domain\Support\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class HasilRadiologi
{
    public function __construct(
        public NoRawat $noRawat,
        public string $tglPeriksa,
        public string $jam,
        public string $hasil,
    ) {
        if (trim($this->hasil) === '') {
            throw new \InvalidArgumentException('Expertise hasil radiologi tidak boleh kosong.');
        }
    }
}
