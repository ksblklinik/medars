<?php

declare(strict_types=1);

namespace App\Domain\Support\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class DetailPeriksaLab
{
    public function __construct(
        public NoRawat $noRawat,
        public string $kdJenisPrw,
        public string $tglPeriksa,
        public string $jam,
        public string $pemeriksaan,
        public string $nilai,
        public string $nilaiRujukan,
        public string $keterangan,
    ) {
        if (trim($this->pemeriksaan) === '') {
            throw new \InvalidArgumentException('Nama parameter pemeriksaan lab wajib diisi.');
        }
    }

    /**
     * Evaluates numeric value against reference range string (e.g. "13.0 - 17.0").
     */
    public static function evaluateReferenceRange(string $nilai, string $nilaiRujukan): string
    {
        $cleanValue = str_replace(',', '.', trim($nilai));
        if (!is_numeric($cleanValue)) {
            return 'Normal';
        }
        $num = (float) $cleanValue;

        if (preg_match('/(-?\d+(?:[.,]\d+)?)\s*[-–]\s*(-?\d+(?:[.,]\d+)?)/', $nilaiRujukan, $matches) === 1) {
            $min = (float) str_replace(',', '.', $matches[1]);
            $max = (float) str_replace(',', '.', $matches[2]);
            if ($num < $min) {
                return 'Rendah';
            }
            if ($num > $max) {
                return 'Tinggi';
            }
            return 'Normal';
        }

        if (preg_match('/^<\s*(-?\d+(?:[.,]\d+)?)/', trim($nilaiRujukan), $matches) === 1) {
            $max = (float) str_replace(',', '.', $matches[1]);
            return $num >= $max ? 'Tinggi' : 'Normal';
        }

        if (preg_match('/^>\s*(-?\d+(?:[.,]\d+)?)/', trim($nilaiRujukan), $matches) === 1) {
            $min = (float) str_replace(',', '.', $matches[1]);
            return $num <= $min ? 'Rendah' : 'Normal';
        }

        return 'Normal';
    }
}
