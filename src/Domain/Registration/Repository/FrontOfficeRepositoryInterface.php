<?php

declare(strict_types=1);

namespace App\Domain\Registration\Repository;

interface FrontOfficeRepositoryInterface
{
    /**
     * @return array{
     *     total_kunjungan: int,
     *     belum_periksa: int,
     *     sudah_periksa: int,
     *     batal: int,
     *     booking_menunggu: int,
     *     belum_bayar: int
     * }
     */
    public function getTodayStats(string $date): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listTodayBookings(string $date): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listTodayVisits(string $date): array;
}
