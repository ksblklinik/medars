<?php

declare(strict_types=1);

namespace App\Domain\Support\Entity;

use App\Domain\Billing\ValueObject\ClaimType;

final readonly class PermintaanPemeriksaanLab
{
    public function __construct(
        public string $noOrder,
        public string $kdJenisPrw,
        public ClaimType $jenisKlaim,
        public string $sttsBayar = 'Belum',
    ) {
        if (trim($this->noOrder) === '' || trim($this->kdJenisPrw) === '') {
            throw new \InvalidArgumentException('Nomor order dan kode jenis perawatan lab wajib diisi.');
        }
    }
}
