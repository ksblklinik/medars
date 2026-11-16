<?php

declare(strict_types=1);

namespace App\Application\Registration\DTO;

final readonly class CheckInMobileJknCommand
{
    public function __construct(
        public string $noRkmMedis,
        public ?\DateTimeInterface $today = null,
        public ?string $jamCheckIn = null
    ) {}
}
