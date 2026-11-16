<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Entity;

use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;

final readonly class GudangBarang
{
    public function __construct(
        public string $kodeBrng,
        public DepoBangsal $kdBangsal,
        public string $noBatch,
        public string $noFaktur,
        public float $stok,
    ) {
        if ($this->stok < 0) {
            throw new InsufficientStockException("Saldo stok awal tidak boleh negatif untuk barang {$this->kodeBrng}.");
        }
    }

    public function deduct(float $qty): self
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah pengurangan stok harus lebih besar dari 0.');
        }

        $remaining = $this->stok - $qty;
        if ($remaining < 0) {
            throw new InsufficientStockException(
                "Stok tidak mencukupi di depo {$this->kdBangsal->value()} untuk barang {$this->kodeBrng} (tersedia: {$this->stok}, diminta: {$qty})."
            );
        }

        return new self(
            kodeBrng: $this->kodeBrng,
            kdBangsal: $this->kdBangsal,
            noBatch: $this->noBatch,
            noFaktur: $this->noFaktur,
            stok: $remaining
        );
    }

    public function add(float $qty): self
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah penambahan stok harus lebih besar dari 0.');
        }

        return new self(
            kodeBrng: $this->kodeBrng,
            kdBangsal: $this->kdBangsal,
            noBatch: $this->noBatch,
            noFaktur: $this->noFaktur,
            stok: $this->stok + $qty
        );
    }
}
