<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Entity;

use App\Domain\Pharmacy\ValueObject\UniformPricing;

final readonly class DataBarang
{
    public function __construct(
        public string $kodeBrng,
        public string $namaBrng,
        public string $kodeSat,
        public UniformPricing $pricing,
        public string $status = '1',
    ) {
        if (trim($this->kodeBrng) === '' || trim($this->namaBrng) === '') {
            throw new \InvalidArgumentException('Kode dan nama barang tidak boleh kosong.');
        }
    }

    public function isActive(): bool
    {
        return $this->status === '1';
    }
}
