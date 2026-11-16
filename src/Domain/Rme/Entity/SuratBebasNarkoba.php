<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class SuratBebasNarkoba
{
    public function __construct(
        public string $noSurat,
        public NoRawat $noRawat,
        public \DateTimeImmutable $tanggal,
        public string $kategori,
        public string $kdDokter,
        public string $keperluan,
        public string $opiat = 'NEGATIF',
        public string $ganja = 'NEGATIF',
        public string $amphetamin = 'NEGATIF',
        public string $methamphetamin = 'NEGATIF',
        public string $benzodiazepin = 'NEGATIF',
        public string $cocain = 'NEGATIF',
    ) {
        if (trim($this->keperluan) === '') {
            throw new \InvalidArgumentException('Keperluan surat bebas narkoba wajib diisi.');
        }
    }
}
