<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entity;

final readonly class JurnalDetail
{
    public function __construct(
        public string $kdRek,
        public float $debet = 0.0,
        public float $kredit = 0.0,
    ) {
        if (trim($this->kdRek) === '') {
            throw new \InvalidArgumentException('Kode rekening akuntansi tidak boleh kosong.');
        }
        if ($this->debet < 0 || $this->kredit < 0) {
            throw new \InvalidArgumentException('Nilai debet/kredit jurnal tidak boleh negatif.');
        }
    }
}
