<?php

declare(strict_types=1);

namespace App\Domain\Master\Entity;

final class JenisPerawatan
{
    public function __construct(
        private readonly string $kdJenisPrw,
        private readonly string $nmPerawatan,
        private readonly string $kdKategori,
        private readonly float $material = 0.0,
        private readonly float $tarifTindakanDr = 0.0,
        private readonly float $tarifTindakanPr = 0.0,
        private readonly float $totalByrDr = 0.0,
        private readonly float $totalByrPr = 0.0,
        private readonly string $status = '1',
    ) {
    }

    public function getKdJenisPrw(): string
    {
        return $this->kdJenisPrw;
    }

    public function getNmPerawatan(): string
    {
        return $this->nmPerawatan;
    }

    public function getKdKategori(): string
    {
        return $this->kdKategori;
    }

    public function getMaterial(): float
    {
        return $this->material;
    }

    public function getTarifTindakanDr(): float
    {
        return $this->tarifTindakanDr;
    }

    public function getTarifTindakanPr(): float
    {
        return $this->tarifTindakanPr;
    }

    public function getTotalByrDr(): float
    {
        return $this->totalByrDr;
    }

    public function getTotalByrPr(): float
    {
        return $this->totalByrPr;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isAktif(): bool
    {
        return $this->status === '1';
    }
}
