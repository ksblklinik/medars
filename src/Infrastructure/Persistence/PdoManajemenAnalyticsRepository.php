<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Service\HfisLeanCalculator;
use App\Domain\Support\Repository\ManajemenAnalyticsRepositoryInterface;

final readonly class PdoManajemenAnalyticsRepository implements ManajemenAnalyticsRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private HfisLeanCalculator $leanCalculator,
    ) {
    }

    public function getExecutiveKpi(string $from, string $to): array
    {
        $stmtReg = $this->pdo->prepare("
            SELECT COUNT(*) FROM reg_periksa
            WHERE tgl_registrasi BETWEEN :from AND :to AND stts != 'Batal'
        ");
        $stmtReg->execute([':from' => $from, ':to' => $to]);
        $totalKunjungan = (int) $stmtReg->fetchColumn();

        $stmtRev = $this->pdo->prepare("
            SELECT COALESCE(SUM(l.total_amount), 0)
            FROM mlite_billing_ledger l
            INNER JOIN reg_periksa r ON l.no_rawat = r.no_rawat
            WHERE r.tgl_registrasi BETWEEN :from AND :to
              AND l.status IN ('Paid', 'Approved', 'Bundled', 'Pending')
        ");
        $stmtRev->execute([':from' => $from, ':to' => $to]);
        $totalPendapatan = (float) $stmtRev->fetchColumn();

        $poliMetrics = $this->getLeanPoliMetrics($from, $to);
        $leadSum = 0;
        $leadCount = 0;
        $overtimeCount = 0;
        foreach ($poliMetrics as $pm) {
            if ((int) $pm['avg_soap_lead_minutes'] > 0) {
                $leadSum += (int) $pm['avg_soap_lead_minutes'];
                $leadCount++;
            }
            $overtimeCount += (int) $pm['overtime_count'];
        }

        return [
            'total_kunjungan' => $totalKunjungan,
            'total_pendapatan' => $totalPendapatan,
            'avg_lead_time_minutes' => $leadCount > 0 ? (int) round($leadSum / $leadCount) : 0,
            'overtime_visits_count' => $overtimeCount,
        ];
    }

    public function getLeanPoliMetrics(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.kd_poli, COALESCE(pol.nm_poli, r.kd_poli) AS nm_poli,
                   r.tgl_registrasi, r.jam_reg, r.no_reg,
                   MIN(pr.tgl_perawatan) AS soap_tgl, MIN(pr.jam_rawat) AS soap_jam
            FROM reg_periksa r
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN pemeriksaan_ralan pr ON r.no_rawat = pr.no_rawat
            WHERE r.tgl_registrasi BETWEEN :from AND :to AND r.stts != 'Batal'
            GROUP BY r.no_rawat
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $kd = (string) $row['kd_poli'];
            $nm = (string) $row['nm_poli'];
            if (!isset($grouped[$kd])) {
                $grouped[$kd] = [
                    'kd_poli' => $kd,
                    'nm_poli' => $nm,
                    'total_pasien' => 0,
                    'signed_deltas' => [],
                    'soap_leads' => [],
                    'overtime_count' => 0,
                ];
            }
            $grouped[$kd]['total_pasien']++;

            $tgl = (string) $row['tgl_registrasi'];
            $jamReg = (string) $row['jam_reg'];
            $shift = $jamReg >= '14:00:00' ? 'Sore' : 'Pagi';
            $est = $this->leanCalculator->calculateEstimate($shift, $kd, $nm, (int) $row['no_reg'], 10, $tgl);
            $delta = $this->leanCalculator->calculateSignedArrivalDeltaMinutes($tgl . ' ' . $jamReg, $tgl . ' ' . $est['estimated_service_time']);
            $grouped[$kd]['signed_deltas'][] = $delta;

            if ($jamReg >= '21:00:00') {
                $grouped[$kd]['overtime_count']++;
            }

            if (!empty($row['soap_tgl']) && !empty($row['soap_jam'])) {
                $regTs = strtotime($tgl . ' ' . $jamReg);
                $soapTs = strtotime((string) $row['soap_tgl'] . ' ' . (string) $row['soap_jam']);
                if ($regTs !== false && $soapTs !== false && $soapTs >= $regTs) {
                    $grouped[$kd]['soap_leads'][] = (int) round(($soapTs - $regTs) / 60);
                }
            }
        }

        $result = [];
        foreach ($grouped as $g) {
            $dCount = count($g['signed_deltas']);
            $sCount = count($g['soap_leads']);
            $result[] = [
                'kd_poli' => $g['kd_poli'],
                'nm_poli' => $g['nm_poli'],
                'total_pasien' => $g['total_pasien'],
                'net_signed_avg_minutes' => $dCount > 0 ? (int) round(array_sum($g['signed_deltas']) / $dCount) : 0,
                'avg_soap_lead_minutes' => $sCount > 0 ? (int) round(array_sum($g['soap_leads']) / $sCount) : 0,
                'overtime_count' => $g['overtime_count'],
            ];
        }

        return $result;
    }

    public function getDailyTrend(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.tgl_registrasi AS tanggal, COUNT(*) AS kunjungan
            FROM reg_periksa r
            WHERE r.tgl_registrasi BETWEEN :from AND :to AND r.stts != 'Batal'
            GROUP BY r.tgl_registrasi
            ORDER BY r.tgl_registrasi ASC
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
