<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface ManajemenAnalyticsRepositoryInterface
{
    /**
     * @return array{
     *     total_kunjungan: int,
     *     total_pendapatan: float,
     *     avg_lead_time_minutes: int,
     *     overtime_visits_count: int
     * }
     */
    public function getExecutiveKpi(string $from, string $to): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getLeanPoliMetrics(string $from, string $to): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getDailyTrend(string $from, string $to): array;
}
