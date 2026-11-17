<?php

declare(strict_types=1);

namespace App\Domain\Ranap\Repository;

interface RawatInapRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listKamar(string $status = ''): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function saveKamar(array $payload): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveInpatients(string $q = ''): array;

    public function findKamar(string $kdKamar): ?array;

    public function insertKamarInap(
        string $noRawat,
        string $kdKamar,
        string $tglMasuk,
        string $jamMasuk,
        float $trfKamar
    ): void;

    public function setKamarStatus(string $kdKamar, string $status): void;

    public function markRegRanap(string $noRawat): void;

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveStay(string $noRawat, string $kdKamar): ?array;

    public function updateCheckoutStay(
        string $noRawat,
        string $kdKamar,
        string $tglMasuk,
        string $jamMasuk,
        string $tglKeluar,
        string $jamKeluar,
        int $lama,
        float $ttlBiaya,
        string $sttsPulang
    ): void;
}
