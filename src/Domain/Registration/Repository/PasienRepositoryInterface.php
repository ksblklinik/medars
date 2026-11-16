<?php

declare(strict_types=1);

namespace App\Domain\Registration\Repository;

use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\ValueObject\NoRkmMedis;

interface PasienRepositoryInterface
{
    public function findByNoRkmMedis(NoRkmMedis $noRkmMedis): ?Pasien;

    public function findByNik(string $nik): ?Pasien;

    public function findByNoPeserta(string $noPeserta): ?Pasien;

    /**
     * @return array<Pasien>
     */
    public function search(string $query, int $limit = 20): array;

    public function save(Pasien $pasien): void;

    /**
     * Mengambil nomor rekam medis baru tertinggi dari gabungan basis data legacy dan next.
     */
    public function getNextNoRkmMedis(): NoRkmMedis;
}
