<?php

declare(strict_types=1);

namespace App\Domain\Security\Repository;

interface PresensiRepositoryInterface
{
    public function clockIn(
        string $nip,
        string $shift = 'Pagi',
        string $lokasi = 'Klinik',
        string $keterangan = '',
        ?string $tgl = null,
        ?string $jam = null
    ): void;

    public function clockOut(string $nip, ?string $tgl = null, ?string $jam = null): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listPresensi(string $from, string $to, string $nip = ''): array;
}
