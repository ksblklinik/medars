<?php

declare(strict_types=1);

namespace App\Domain\Rme\ValueObject;

final readonly class TandaVital
{
    public string $suhuTubuh;
    public string $tensi;
    public string $sistole;
    public string $diastole;
    public string $nadi;
    public string $respirasi;
    public string $tinggi;
    public string $berat;
    public string $spo2;
    public string $gcs;
    public string $lingkarPerut;

    public function __construct(
        string $suhuTubuh = '',
        string $tensi = '',
        string $sistole = '',
        string $diastole = '',
        string $nadi = '',
        string $respirasi = '',
        string $tinggi = '',
        string $berat = '',
        string $spo2 = '',
        string $gcs = '',
        string $lingkarPerut = '',
    ) {
        $this->suhuTubuh = self::sanitize($suhuTubuh);
        $this->tensi = self::sanitize($tensi);
        $this->sistole = self::sanitize($sistole);
        $this->diastole = self::sanitize($diastole);
        $this->nadi = self::sanitize($nadi);
        $this->respirasi = self::sanitize($respirasi);
        $this->tinggi = self::sanitize($tinggi);
        $this->berat = self::sanitize($berat);
        $this->spo2 = self::sanitize($spo2);
        $this->gcs = self::sanitize($gcs);
        $this->lingkarPerut = self::sanitize($lingkarPerut);
    }

    private static function sanitize(string $val): string
    {
        $trimmed = trim($val);
        return $trimmed === '' ? '-' : $trimmed;
    }
}
