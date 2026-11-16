<?php

declare(strict_types=1);

namespace App\Application\Registration\Exception;

final class UnpaidArrearsException extends \DomainException
{
    public function __construct(string $noRkmMedis)
    {
        parent::__construct(
            "Pasien '{$noRkmMedis}' masih memiliki tagihan kunjungan sebelumnya yang belum diselesaikan di kasir."
        );
    }
}
