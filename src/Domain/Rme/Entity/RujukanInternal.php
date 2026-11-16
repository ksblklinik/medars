<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class RujukanInternal
{
    public function __construct(
        public NoRawat $noRawat,
        public string $kdDokter,
        public string $kdPoli,
        public string $catatan = '',
    ) {
        if (trim($this->kdDokter) === '' || trim($this->kdPoli) === '') {
            throw new \InvalidArgumentException('Dokter dan poliklinik tujuan rujukan internal wajib diisi.');
        }
    }
}
