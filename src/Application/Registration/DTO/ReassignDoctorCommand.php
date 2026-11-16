<?php

declare(strict_types=1);

namespace App\Application\Registration\DTO;

final readonly class ReassignDoctorCommand
{
    public function __construct(
        public string $noRawat,
        public string $newKdDokter,
        public ?string $newShift = null
    ) {}
}
