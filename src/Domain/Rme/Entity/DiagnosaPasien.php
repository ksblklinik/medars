<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class DiagnosaPasien
{
    public function __construct(
        public NoRawat $noRawat,
        public string $kdPenyakit,
        public string $status = 'Ralan',
        public int $prioritas = 1,
        public string $statusPenyakit = 'Baru',
    ) {
        if (trim($this->kdPenyakit) === '') {
            throw new \InvalidArgumentException('Kode penyakit ICD-10 tidak boleh kosong.');
        }
        if ($this->prioritas < 1) {
            throw new \InvalidArgumentException('Prioritas diagnosa minimal 1.');
        }
    }

    public function isPrimer(): bool
    {
        return $this->prioritas === 1;
    }
}
