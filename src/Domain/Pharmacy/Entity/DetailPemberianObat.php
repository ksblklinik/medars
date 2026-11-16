<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Entity;

use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use App\Domain\Pharmacy\ValueObject\JenisKlaim;
use App\Domain\Registration\ValueObject\NoRawat;

final readonly class DetailPemberianObat
{
    public function __construct(
        public string $tglPerawatan,
        public string $jam,
        public NoRawat $noRawat,
        public string $kodeBrng,
        public string $noBatch,
        public string $noFaktur,
        public float $hBeli,
        public float $biayaObat,
        public float $jml,
        public float $embalase,
        public float $tuslah,
        public float $total,
        public string $status,
        public DepoBangsal $kdBangsal,
        public JenisKlaim $jenisKlaim,
    ) {
    }
}
