<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Repository;

interface GudangFarmasiRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listBarang(string $q = ''): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function saveBarang(array $payload): void;

    public function receiveStock(
        string $kodeBrng,
        string $kdBangsal,
        float $qty,
        string $noBatch,
        string $noFaktur,
        string $petugas,
        string $keterangan
    ): void;

    public function mutateStock(
        string $kodeBrng,
        string $fromBangsal,
        string $toBangsal,
        float $qty,
        string $petugas,
        string $keterangan
    ): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listKartuStok(string $kodeBrng = '', string $kdBangsal = ''): array;
}
