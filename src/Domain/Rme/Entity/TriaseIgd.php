<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\ValueObject\TandaVital;

final readonly class TriaseIgd
{
    public const VALID_SCALES = [
        'Resusitasi',
        'Emergensi',
        'Urgensi',
        'Semi-Urgensi',
        'Non-Urgent',
    ];

    public function __construct(
        public NoRawat $noRawat,
        public string $tglTriase,
        public string $jamTriase,
        public string $skalaTriase,
        public string $keluhanUtama,
        public TandaVital $tandaVital,
        public string $nipPetugas,
    ) {
        if (!in_array($this->skalaTriase, self::VALID_SCALES, true)) {
            throw new \InvalidArgumentException("Skala triase IGD tidak valid: '{$this->skalaTriase}'.");
        }
    }
}
