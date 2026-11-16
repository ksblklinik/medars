<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Audit\Repository\DataIntegrityAuditRepositoryInterface;
use PDO;

final readonly class PdoDataIntegrityAuditRepository implements DataIntegrityAuditRepositoryInterface
{
    private const ALLOWED_ORPHAN_TARGETS = [
        'mlite_billing_ledger' => 'no_rawat',
        'detail_periksa_lab' => 'no_rawat',
        'permintaan_lab' => 'no_rawat',
        'permintaan_radiologi' => 'no_rawat',
        'diagnosa_pasien' => 'no_rawat',
        'pemeriksaan_ralan' => 'no_rawat',
        'resep_obat' => 'no_rawat',
        'detail_pemberian_obat' => 'no_rawat',
    ];

    public function __construct(private PDO $pdo) {}

    public function countOrphans(string $table, string $column): int
    {
        if (!isset(self::ALLOWED_ORPHAN_TARGETS[$table]) || self::ALLOWED_ORPHAN_TARGETS[$table] !== $column) {
            throw new \InvalidArgumentException("Target audit orphan tidak dikenal: {$table}.{$column}");
        }

        $sql = "SELECT COUNT(*) FROM {$table} c
                LEFT JOIN reg_periksa p ON c.{$column} = p.no_rawat
                WHERE p.no_rawat IS NULL AND c.{$column} IS NOT NULL AND c.{$column} != ''";

        try {
            $stmt = $this->pdo->query($sql);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    public function getBillingSubtotalsByClaimType(string $noRawat): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT claim_type, COALESCE(SUM(total_amount), 0) AS subtotal
             FROM mlite_billing_ledger
             WHERE no_rawat = :nr AND status != 'Cancelled'
             GROUP BY claim_type"
        );
        $stmt->execute([':nr' => $noRawat]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[] = [
                'claim_type' => (string) $row['claim_type'],
                'subtotal' => (float) $row['subtotal'],
            ];
        }
        return $result;
    }

    public function getStockAndMutationTotals(string $kodeBrng, string $kdBangsal): array
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
            'current_stock' => $currentStock,
            'calculated_stock' => $calculatedStock,
        ];
    }
}
