<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\ValueObject;

final readonly class UniformPricing
{
    public function __construct(
        public float $hargaTunai = 0.0,
        public float $hargaKapitasi = 0.0,
        public float $hargaNonKapitasi = 0.0,
        public float $jualBebas = 0.0,
        public float $dasar = 0.0,
        public float $hBeli = 0.0,
    ) {
    }

    public function getPriceForClaim(JenisKlaim $klaim): float
    {
        return match ($klaim->value()) {
            'Tunai' => $this->hargaTunai,
            'Kapitasi' => $this->hargaKapitasi,
            'Non-Kapitasi' => $this->hargaNonKapitasi,
            default => $this->hargaTunai,
        };
    }

    /**
     * @return list<array{claim_type: string, price: float}>
     */
    public function getAvailableClaimOptions(bool $isBpjs = false): array
    {
        $options = [];

        if ($isBpjs) {
            if ($this->hargaKapitasi > 0) {
                $options[] = ['claim_type' => 'Kapitasi', 'price' => $this->hargaKapitasi];
            }
            if ($this->hargaNonKapitasi > 0) {
                $options[] = ['claim_type' => 'Non-Kapitasi', 'price' => $this->hargaNonKapitasi];
            }
            if ($this->hargaTunai > 0) {
                $options[] = ['claim_type' => 'Tunai', 'price' => $this->hargaTunai];
            }
        } else {
            if ($this->hargaTunai > 0) {
                $options[] = ['claim_type' => 'Tunai', 'price' => $this->hargaTunai];
            }
            if ($this->hargaKapitasi > 0) {
                $options[] = ['claim_type' => 'Kapitasi', 'price' => $this->hargaKapitasi];
            }
            if ($this->hargaNonKapitasi > 0) {
                $options[] = ['claim_type' => 'Non-Kapitasi', 'price' => $this->hargaNonKapitasi];
            }
        }

        return $options;
    }
}
