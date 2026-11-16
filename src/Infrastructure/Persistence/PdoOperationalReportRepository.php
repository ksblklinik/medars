<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\OperationalReportRepositoryInterface;
use PDO;

final readonly class PdoOperationalReportRepository implements OperationalReportRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getVisitSummaryByPoli(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.kd_poli,
                    COALESCE(p.nm_poli, r.kd_poli) AS nm_poli,
                    COUNT(*) AS total_kunjungan
             FROM reg_periksa r
             LEFT JOIN poliklinik p ON r.kd_poli = p.kd_poli
             WHERE r.tgl_registrasi BETWEEN :from AND :to
             GROUP BY r.kd_poli, p.nm_poli
             ORDER BY total_kunjungan DESC'
        );
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'kd_poli' => (string) $row['kd_poli'],
                'nm_poli' => (string) $row['nm_poli'],
                'total_kunjungan' => (int) $row['total_kunjungan'],
            ];
        }
        return $result;
    }

    public function getTopDiseases(string $fromDate, string $toDate, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.kd_penyakit,
                    COALESCE(py.nm_indonesia, py.nm_penyakit, d.kd_penyakit) AS nm_indonesia,
                    COUNT(*) AS total
             FROM diagnosa_pasien d
             INNER JOIN reg_periksa r ON d.no_rawat = r.no_rawat
             LEFT JOIN penyakit py ON d.kd_penyakit = py.kd_penyakit
             WHERE r.tgl_registrasi BETWEEN :from AND :to
             GROUP BY d.kd_penyakit, py.nm_indonesia, py.nm_penyakit
             ORDER BY total DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':from', $fromDate);
        $stmt->bindValue(':to', $toDate);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'kd_penyakit' => (string) $row['kd_penyakit'],
                'nm_indonesia' => (string) $row['nm_indonesia'],
                'total' => (int) $row['total'],
            ];
        }
        return $result;
    }

    public function getRevenueByClaimType(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.claim_type,
                    COUNT(*) AS total_items,
                    COALESCE(SUM(l.total_amount), 0) AS total_amount
             FROM mlite_billing_ledger l
             INNER JOIN reg_periksa r ON l.no_rawat = r.no_rawat
             WHERE r.tgl_registrasi BETWEEN :from AND :to
               AND l.status != 'Cancelled'
             GROUP BY l.claim_type
             ORDER BY total_amount DESC"
        );
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'claim_type' => (string) $row['claim_type'],
                'total_items' => (int) $row['total_items'],
                'total_amount' => (float) $row['total_amount'],
            ];
        }
        return $result;
    }
}
