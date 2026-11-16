<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Support\Entity\DetailPeriksaLab;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveLabResultUseCase
{
    public function __construct(
        private LabRadiologyRepositoryInterface $labRepo,
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private TransactionManagerInterface $tx,
    ) {}

    public function isValidationUnlocked(string $noOrder): bool
    {
        $order = $this->labRepo->findLabOrder($noOrder);
        if ($order === null) {
            return false;
        }

        if ($order->jenisKlaim->isKapitasi()) {
            return true;
        }

        $ledgerItems = $this->ledgerRepo->findByNoRawat($order->noRawat);
        $matchingItems = [];
        foreach ($ledgerItems as $item) {
            if ($item->providerId === $noOrder) {
                $matchingItems[] = $item;
            }
        }

        if ($matchingItems === []) {
            // Fallback check by category/claimType when providerId wasn't stamped
            foreach ($ledgerItems as $item) {
                if ($item->category === 'Laboratorium' && $item->claimType->value() === $order->jenisKlaim->value()) {
                    $matchingItems[] = $item;
                }
            }
        }

        if ($matchingItems === []) {
            return false;
        }

        foreach ($matchingItems as $item) {
            if (!in_array($item->status->value(), ['Paid', 'Approved', 'Bundled'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{kd_jenis_prw: string, pemeriksaan: string, nilai: string, nilai_rujukan: string}> $results
     * @return list<DetailPeriksaLab>
     */
    public function execute(
        string $noOrder,
        array $results,
        ?\DateTimeImmutable $resultAt = null,
    ): array {
        $order = $this->labRepo->findLabOrder($noOrder);
        if ($order === null) {
            throw new \DomainException("Order lab '{$noOrder}' tidak ditemukan.");
        }

        if (!$this->isValidationUnlocked($noOrder)) {
            throw new \DomainException(
                "Validasi hasil lab '{$noOrder}' terkunci: tagihan belum dilunasi atau disetujui oleh Kasir."
            );
        }

        if ($results === []) {
            throw new \InvalidArgumentException('Minimal satu parameter hasil lab wajib diisi.');
        }

        $now = $resultAt ?? new \DateTimeImmutable();
        $tgl = $now->format('Y-m-d');
        $jam = $now->format('H:i:s');

        return $this->tx->transaction(function () use ($order, $results, $tgl, $jam): array {
            $saved = [];
            foreach ($results as $row) {
                $nilai = (string) ($row['nilai'] ?? '');
                $nilaiRujukan = (string) ($row['nilai_rujukan'] ?? '');
                $keterangan = DetailPeriksaLab::evaluateReferenceRange($nilai, $nilaiRujukan);

                $detail = new DetailPeriksaLab(
                    noRawat: $order->noRawat,
                    kdJenisPrw: (string) $row['kd_jenis_prw'],
                    tglPeriksa: $tgl,
                    jam: $jam,
                    pemeriksaan: (string) $row['pemeriksaan'],
                    nilai: $nilai,
                    nilaiRujukan: $nilaiRujukan,
                    keterangan: $keterangan,
                );
                $this->labRepo->saveLabResult($detail);
                $saved[] = $detail;
            }

            $this->labRepo->markLabSampledAndResulted($order->noOrder, $tgl, $jam);

            return $saved;
        });
    }
}
