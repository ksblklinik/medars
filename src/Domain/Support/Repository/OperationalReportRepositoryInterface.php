<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface OperationalReportRepositoryInterface
{
    /**
     * @return list<array{kd_poli: string, nm_poli: string, total_kunjungan: int}>
     */
    public function getVisitSummaryByPoli(string $fromDate, string $toDate): array;

    /**
     * @return list<array{kd_penyakit: string, nm_indonesia: string, total: int}>
     */
    public function getTopDiseases(string $fromDate, string $toDate, int $limit = 10): array;

    /**
     * @return list<array{claim_type: string, total_items: int, total_amount: float}>
     */
    public function getRevenueByClaimType(string $fromDate, string $toDate): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getOrderReconciliationReport(string $fromDate, string $toDate): array;
}
