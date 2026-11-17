<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface UtdRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listKomponen(): array;

    /** @param array<string, mixed> $data */
    public function saveKomponen(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listPendonor(string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function savePendonor(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listStokDarah(string $golongan = ''): array;

    /** @param array<string, mixed> $data */
    public function saveStokDarah(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listPermintaan(string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function savePermintaan(array $data): array;

    public function updateCrossmatch(int $id, string $noKantong, string $hasil, string $status): bool;
}
