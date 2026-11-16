<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class SuratSakit
{
    public string $lamaSakit;

    public function __construct(
        public string $noSurat,
        public NoRawat $noRawat,
        public \DateTimeImmutable $tanggalAwal,
        public \DateTimeImmutable $tanggalAkhir,
    ) {
        if ($this->tanggalAkhir < $this->tanggalAwal) {
            throw new \InvalidArgumentException('Tanggal akhir istirahat tidak boleh mendahului tanggal awal.');
        }

        $days = (int) $this->tanggalAwal->diff($this->tanggalAkhir)->format('%a') + 1;
        $this->lamaSakit = "{$days} Hari";
    }
}
