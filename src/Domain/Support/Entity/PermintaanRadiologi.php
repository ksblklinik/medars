<?php

declare(strict_types=1);

namespace App\Domain\Support\Entity;

use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Registration\ValueObject\NoRawat;

final readonly class PermintaanRadiologi
{
    public function __construct(
        public string $noOrder,
        public NoRawat $noRawat,
        public string $tglPermintaan,
        public string $jamPermintaan,
        public string $dokterPerujuk,
        public ClaimType $jenisKlaim,
        public string $status = 'ralan',
        public ?string $tglHasil = null,
        public ?string $jamHasil = null,
    ) {
        if (trim($this->noOrder) === '' || trim($this->dokterPerujuk) === '') {
            throw new \InvalidArgumentException('Nomor order dan dokter perujuk radiologi wajib diisi.');
        }
    }
}
