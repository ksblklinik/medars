<?php

declare(strict_types=1);

namespace App\Application\Registration\DTO;

final readonly class ShiftQueueCommand
{
    public function __construct(
        public string $noRawat,
        public string $newNoReg,
        public ?string $newShift = null
    ) {}
}
