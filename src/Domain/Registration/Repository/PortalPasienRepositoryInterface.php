<?php

declare(strict_types=1);

namespace App\Domain\Registration\Repository;

interface PortalPasienRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function verifyAndLoadPortal(string $noRkmMedis, string $tglLahir): ?array;

    /**
     * @return array{no_reg: string, tanggal_periksa: string}
     */
    public function createOnlineBooking(
        string $noRkmMedis,
        string $tglLahir,
        string $tanggalPeriksa,
        string $kdPoli,
        string $kdDokter,
        string $kdPj = 'A09'
    ): array;
}
