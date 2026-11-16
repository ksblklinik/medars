<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;

final readonly class PemeriksaanRanap
{
    public string $keluhan;
    public string $pemeriksaan;
    public string $alergi;
    public string $rtl;
    public string $penilaian;
    public string $instruksi;
    public string $evaluasi;

    public function __construct(
        public NoRawat $noRawat,
        public string $tglPerawatan,
        public string $jamRawat,
        public TandaVital $tandaVital,
        public Kesadaran $kesadaran,
        string $keluhan = '',
        string $pemeriksaan = '',
        string $alergi = '',
        string $rtl = '',
        string $penilaian = '',
        string $instruksi = '',
        string $evaluasi = '',
        public string $nip = '',
    ) {
        $this->keluhan = self::sanitize($keluhan);
        $this->pemeriksaan = self::sanitize($pemeriksaan);
        $this->alergi = self::sanitize($alergi);
        $this->rtl = self::sanitize($rtl);
        $this->penilaian = self::sanitize($penilaian);
        $this->instruksi = self::sanitize($instruksi);
        $this->evaluasi = self::sanitize($evaluasi);
    }

    private static function sanitize(string $val): string
    {
        $trimmed = trim($val);
        return $trimmed === '' ? '-' : $trimmed;
    }
}
