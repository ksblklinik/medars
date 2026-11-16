<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Repository;

use App\Domain\Pharmacy\ValueObject\DepoBangsal;

interface GudangBarangRepositoryInterface
{
    public function getStock(string $kodeBrng, DepoBangsal $depo): float;

    /**
     * Mengurangi stok secara atomik pada depo tertentu dan mencatat riwayat_barang_medis.
     *
     * @return list<array{no_batch: string, no_faktur: string, qty: float}>
     */
    public function deductStock(
        string $kodeBrng,
        DepoBangsal $depo,
        float $qty,
        string $tanggal,
        string $jam,
        string $petugas,
        string $keterangan = ''
    ): array;
}
