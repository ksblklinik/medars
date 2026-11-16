<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class SuratHamil
{
    public function __construct(
        public string $noSurat,
        public NoRawat $noRawat,
        public \DateTimeImmutable $tanggalPeriksa,
        public string $hasilPeriksa,
    ) {
        if (trim($this->hasilPeriksa) === '') {
            throw new \InvalidArgumentException('Hasil pemeriksaan kehamilan tidak boleh kosong.');
        }
    }
}
