<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoRkmMedis;

final readonly class Odontogram
{
    public const VALID_FDI_TEETH = [
        '11', '12', '13', '14', '15', '16', '17', '18',
        '21', '22', '23', '24', '25', '26', '27', '28',
        '31', '32', '33', '34', '35', '36', '37', '38',
        '41', '42', '43', '44', '45', '46', '47', '48',
        '51', '52', '53', '54', '55',
        '61', '62', '63', '64', '65',
        '71', '72', '73', '74', '75',
        '81', '82', '83', '84', '85',
    ];

    public function __construct(
        public ?int $id,
        public NoRkmMedis $noRkmMedis,
        public NoRawat $noRawat,
        public string $tglPemeriksaan,
        public string $nomorGigi,
        public string $kondisi,
        public string $catatan = '',
        public int $idUser = 0,
    ) {
        if (!in_array($this->nomorGigi, self::VALID_FDI_TEETH, true)) {
            throw new \InvalidArgumentException("Nomor gigi FDI tidak valid: '{$this->nomorGigi}'.");
        }

        if (trim($this->kondisi) === '') {
            throw new \InvalidArgumentException('Kondisi pemeriksaan odontogram tidak boleh kosong.');
        }
    }
}
