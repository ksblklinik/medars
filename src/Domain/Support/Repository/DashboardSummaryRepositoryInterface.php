<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface DashboardSummaryRepositoryInterface
{
    /**
     * @return list<array{tanggal: string, kunjungan: int, pendapatan: float}>
     */
    public function getSevenDayTrends(?string $endDate = null): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listAnnouncements(int $limit = 10): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createAnnouncement(array $data): array;

    public function deleteAnnouncement(int $id): bool;
}
