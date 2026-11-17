<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface PenjualanRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listBarang(string $search = ''): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveBarang(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createOrder(array $data): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrders(string $from = '', string $to = ''): array;

    /**
     * @return array<string, mixed>
     */
    public function addItem(int $idPenjualan, int $idBarang, int $jumlah, string $user = 'system'): array;

    public function removeItem(int $detailId): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function getOrderDetail(int $idPenjualan): ?array;

    /**
     * @return array<string, mixed>
     */
    public function settleOrder(int $idPenjualan, float $potongan, float $jumlahBayar, string $caraBayar, string $user = 'system'): array;

    /**
     * @return array{total_transaksi: int, total_omzet: float, top_items: list<array<string, mixed>>}
     */
    public function getTurnoverReport(string $from, string $to): array;
}
