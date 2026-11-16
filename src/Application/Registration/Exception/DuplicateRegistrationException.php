<?php

declare(strict_types=1);

namespace App\Application\Registration\Exception;

final class DuplicateRegistrationException extends \DomainException
{
    public function __construct(string $noRkmMedis, string $kdPoli, string $kdDokter, string $tgl)
    {
        parent::__construct(
            "Pasien '{$noRkmMedis}' sudah memiliki kunjungan aktif pada tanggal {$tgl} di poli '{$kdPoli}' dokter '{$kdDokter}'."
        );
    }
}
