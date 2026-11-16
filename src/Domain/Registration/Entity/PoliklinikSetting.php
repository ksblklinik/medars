<?php

declare(strict_types=1);

namespace App\Domain\Registration\Entity;

final readonly class PoliklinikSetting
{
    public const KODE_PENJAMIN_BPJS = 'BPJ';

    /**
     * @param string $kdPoli Kode Poliklinik (misal: 'U01', 'PEX', 'IGDK')
     * @param string $nmPoli Nama Poliklinik
     * @param float $registrasiBaru Tarif pendaftaran pasien baru
     * @param float $registrasiLama Tarif pendaftaran pasien lama
     * @param bool $allowBpjs Apakah poliklinik ini melayani penjamin BPJS (pengaturan dinamis per poli)
     * @param string $bpjsPenjaminCode Kode penjamin BPJS yang berlaku (default 'BPJ')
     */
    public function __construct(
        public string $kdPoli,
        public string $nmPoli,
        public float $registrasiBaru,
        public float $registrasiLama,
        public bool $allowBpjs = true,
        public string $bpjsPenjaminCode = self::KODE_PENJAMIN_BPJS
    ) {}

    public function isPenjaminAllowed(string $kdPj): bool
    {
        if (!$this->allowBpjs && strtoupper($kdPj) === strtoupper($this->bpjsPenjaminCode)) {
            return false;
        }

        return true;
    }

    public function getRegistrationFee(string $sttsDaftar): float
    {
        return $sttsDaftar === 'Lama' ? $this->registrasiLama : $this->registrasiBaru;
    }
}
