<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entity;

use App\Domain\Billing\ValueObject\PosPaymentMethod;

final readonly class PosApotekUmumSale
{
    public function __construct(
        public string $noNota,
        public string $tglJual,
        public string $jamJual,
        public PosPaymentMethod $paymentMethod,
        public float $total,
        public string $petugasNip,
        public string $keterangan = '',
    ) {
        if (!str_starts_with($this->noNota, 'PO-APT-')) {
            throw new \InvalidArgumentException("Nomor nota Apotek Umum wajib berprefiks 'PO-APT-': '{$this->noNota}'.");
        }
        if ($this->total < 0) {
            throw new \InvalidArgumentException('Total penjualan POS tidak boleh negatif.');
        }
    }
}
