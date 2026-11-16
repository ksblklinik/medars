<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Entity;

use App\Domain\Pharmacy\ValueObject\NoResep;

final readonly class ResepDokterRacikan
{
    /**
     * @param list<ResepDokterRacikanDetail> $details
     */
    public function __construct(
        public NoResep $noResep,
        public string $noRacik,
        public string $namaRacik,
        public string $kdRacik,
        public int $jmlDr,
        public string $aturanPakai = '-',
        public string $keterangan = '-',
        public array $details = [],
    ) {
        if ($this->jmlDr <= 0) {
            throw new \InvalidArgumentException('Jumlah racikan (jml_dr) harus > 0.');
        }
    }
}
