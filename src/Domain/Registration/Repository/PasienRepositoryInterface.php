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
     * @param array<string, mixed> $extraFields
     */
    public function saveOrUpdate(Pasien $pasien, array $extraFields = []): void;

    /**
     * @return array<string, mixed>|null
     */
    public function findRawByNoRkmMedis(NoRkmMedis $noRkmMedis): ?array;

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listPaginated(string $query = '', int $limit = 20, int $offset = 0): array;

    public function hasRegistrations(NoRkmMedis $noRkmMedis): bool;

    public function delete(NoRkmMedis $noRkmMedis): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function getVisitHistory(NoRkmMedis $noRkmMedis): array;

    /**
     * Mengambil nomor rekam medis baru tertinggi dari gabungan basis data legacy dan next.
     */
    public function getNextNoRkmMedis(): NoRkmMedis;
}
