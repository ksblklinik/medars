<?php

declare(strict_types=1);

namespace App\Application\Registration\Exception;

final class ClinicalGuardBlockedException extends \DomainException
{
    public function __construct(string $noRawat, string $reason)
    {
        parent::__construct(
            "Perubahan atau pembatalan nomor antrean/dokter pada kunjungan '{$noRawat}' diblokir oleh Clinical Guard: {$reason}"
        );
    }
}
