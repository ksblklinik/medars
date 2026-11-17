<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repository;

interface KeuanganRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listRekening(): array;

    public function saveRekening(string $kdRek, string $nmRek, string $tipe = 'Aset', string $balance = 'D'): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listJurnal(string $from, string $to): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getBukuBesar(string $from, string $to): array;

    /**
     * @return array{total_pendapatan: float, total_beban: float, laba_bersih: float}
     */
    public function getLabaRugi(string $from, string $to): array;

    public function postManualJurnal(
        string $noBukti,
        string $keterangan,
        string $kdDebet,
        string $kdKredit,
        float $nominal,
        ?\DateTimeImmutable $at = null
    ): string;
}
