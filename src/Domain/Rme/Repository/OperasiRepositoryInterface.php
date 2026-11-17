<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

interface OperasiRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listPaket(string $search = ''): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function savePaket(array $data): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listBookings(string $from = '', string $to = ''): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveBooking(array $data): array;

    public function updateBookingStatus(int $id, string $status): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function listLaporan(string $noRawat = ''): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveLaporan(array $data): array;
}
