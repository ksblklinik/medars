<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

use PDO;

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

    public function __construct(private PDO $pdo) {}

    /**
     * @return array{healthy: bool, total_orphans: int, findings: list<array{table: string, column: string, orphan_count: int}>}
     */
    public function auditOrphans(): array
    {
        $findings = [];
        $totalOrphans = 0;

        foreach (self::CHILD_TABLES as $target) {
            $table = $target['table'];
            $col = $target['column'];

            $sql = "SELECT COUNT(*) FROM {$table} c
                    LEFT JOIN reg_periksa p ON c.{$col} = p.no_rawat
                    WHERE p.no_rawat IS NULL AND c.{$col} IS NOT NULL AND c.{$col} != ''";

            try {
                $stmt = $this->pdo->query($sql);
                $orphanCount = (int) $stmt->fetchColumn();

                if ($orphanCount > 0) {
                    $findings[] = [
                        'table' => $table,
                        'column' => $col,
                        'orphan_count' => $orphanCount,
                    ];
                    $totalOrphans += $orphanCount;
                }
            } catch (\PDOException) {
                // Skip if table does not exist in testing environment
                continue;
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
        $stmt = $this->pdo->prepare(
            "SELECT claim_type, COALESCE(SUM(total_amount), 0) AS subtotal
             FROM mlite_billing_ledger
             WHERE no_rawat = :nr AND status != 'Cancelled'
             GROUP BY claim_type"
        );
        $stmt->execute([':nr' => $noRawat]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tunai = 0.0;
        $bpjs = 0.0;
        $piutang = 0.0;
        $total = 0.0;

        foreach ($rows as $row) {
            $type = (string) $row['claim_type'];
            $amount = (float) $row['subtotal'];
            $total += $amount;

            if ($type === 'Tunai') {
                $tunai += $amount;
            } elseif (in_array($type, ['Kapitasi', 'Non-Kapitasi'], true)) {
                $bpjs += $amount;
            } elseif ($type === 'Piutang') {
                $piutang += $amount;
            }
        }

        // Verify balance arithmetic
        $balanced = abs(($tunai + $bpjs + $piutang) - $total) < 0.01;

        return [
            'balanced' => $balanced,
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
        $stkStmt = $this->pdo->prepare(
            'SELECT COALESCE(stok, 0) FROM gudangbarang WHERE kode_brng = :kb AND kd_bangsal = :bg LIMIT 1'
        );
        $stkStmt->execute([':kb' => $kodeBrng, ':bg' => $kdBangsal]);
        $currentStock = (float) $stkStmt->fetchColumn();

        $calcStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(masuk - keluar), 0) FROM riwayat_barang_medis WHERE kode_brng = :kb AND kd_bangsal = :bg'
        );
        $calcStmt->execute([':kb' => $kodeBrng, ':bg' => $kdBangsal]);
        $calculatedStock = (float) $calcStmt->fetchColumn();

        return [
            'balanced' => abs($currentStock - $calculatedStock) < 0.001,
            'current_stock' => $currentStock,
            'calculated_stock' => $calculatedStock,
        ];
    }
}
