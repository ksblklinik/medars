<?php

declare(strict_types=1);

namespace App\Application\Registration\DTO;

final readonly class RegisterInSiteCommand
{
    public function __construct(
        public string $noRkmMedis,
        public string $kdPoli,
        public string $kdDokter,
        public string $kdPj,
        public ?\DateTimeInterface $tglRegistrasi = null,
        public ?string $jamReg = null,
        public string $shift = '',
        public string $prefik = '',
        public string $room = '',
        public string $pJawab = '-',
        public string $almtPj = '-',
        public string $hubunganPj = '-'
    ) {}
}
