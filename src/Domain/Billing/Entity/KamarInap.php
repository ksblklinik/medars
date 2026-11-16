<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class KamarInap
{
    public function __construct(
        public NoRawat $noRawat,
        public string $kdKamar,
        public string $tglMasuk,
        public string $jamMasuk,
        public string $tglKeluar = '0000-00-00',
        public string $jamKeluar = '00:00:00',
        public int $lama = 1,
        public float $trfKamar = 0.0,
        public float $ttlBiaya = 0.0,
        public string $sttsPulang = '-',
    ) {
    }

    public function isStillAdmitted(): bool
    {
        return $this->tglKeluar === '' || $this->tglKeluar === '0000-00-00' || $this->sttsPulang === '-';
    }
}
