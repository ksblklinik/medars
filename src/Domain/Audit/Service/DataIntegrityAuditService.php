<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

use App\Domain\Audit\Repository\DataIntegrityAuditRepositoryInterface;

final readonly class DataIntegrityAuditService
{
    private const CHILD_TABLES = [
        ['table' => 'mlite_billing_ledger', 'column' => 'no_rawat'],
        ['table' => 'detail_periksa_lab', 'column' => 'no_rawat'],
        ['table' => 'permintaan_lab', 'column' => 'no_rawat'],
        ['table' => 'permintaan_radiologi', 'column' => 'no_rawat'],
        ['table' => 'diagnosa_pasien', 'column' => 'no_rawat'],
        ['table' => 'pemeriksaan_ralan', 'column' => 'no_rawat'],
        ['table' => 'resep_obat', 'column' => 'no_rawat'],
        ['table' => 'detail_pemberian_obat', 'column' => 'no_rawat'],
    ];

    public function __construct(private DataIntegrityAuditRepositoryInterface $repo) {}

    /**
     * @return array{healthy: bool, total_orphans: int, findings: list<array{table: string, column: string, orphan_count: int}>}
     */
    public function auditOrphans(): array
    {
        $findings = [];
        $totalOrphans = 0;

        foreach (self::CHILD_TABLES as $target) {
            $orphanCount = $this->repo->countOrphans($target['table'], $target['column']);
            if ($orphanCount > 0) {
                $findings[] = [
                    'table' => $target['table'],
                    'column' => $target['column'],
                    'orphan_count' => $orphanCount,
                ];
                $totalOrphans += $orphanCount;
            }
        }

        return [
            'healthy' => $totalOrphans === 0,
            'total_orphans' => $totalOrphans,
            'findings' => $findings,
        ];
    }

    /**
     * @return array{balanced: bool, total_amount: float, tunai_amount: float, bpjs_amount: float, piutang_amount: float}
     */
    public function auditBillingLedgerBalance(string $noRawat): array
    {
        $rows = $this->repo->getBillingSubtotalsByClaimType($noRawat);

        $tunai = 0.0;
        $bpjs = 0.0;
        $piutang = 0.0;
        $total = 0.0;

        foreach ($rows as $row) {
            $type = $row['claim_type'];
            $amount = $row['subtotal'];
            $total += $amount;

            if ($type === 'Tunai') {
                $tunai += $amount;
            } elseif (in_array($type, ['Kapitasi', 'Non-Kapitasi'], true)) {
                $bpjs += $amount;
            } elseif ($type === 'Piutang') {
                $piutang += $amount;
            }
        }

        return [
            'balanced' => abs(($tunai + $bpjs + $piutang) - $total) < 0.01,
            'total_amount' => $total,
            'tunai_amount' => $tunai,
            'bpjs_amount' => $bpjs,
            'piutang_amount' => $piutang,
        ];
    }

    /**
     * @return array{balanced: bool, current_stock: float, calculated_stock: float}
     */
    public function auditStockBalance(string $kodeBrng, string $kdBangsal): array
    {
        $totals = $this->repo->getStockAndMutationTotals($kodeBrng, $kdBangsal);

        return [
            'balanced' => abs($totals['current_stock'] - $totals['calculated_stock']) < 0.001,
            'current_stock' => $totals['current_stock'],
            'calculated_stock' => $totals['calculated_stock'],
        ];
    }
}
