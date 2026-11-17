<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Bridging\Service\HfisLeanCalculator;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoManajemenAnalyticsRepository;
use PHPUnit\Framework\TestCase;

final class PdoManajemenAnalyticsRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoManajemenAnalyticsRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoManajemenAnalyticsRepository($this->pdo, new HfisLeanCalculator());
    }

    public function testExecutiveKpiLeanMetricsAndTrend(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U01', 'Poli Umum', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000100', 'Tono', '1989-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('001', '2026/11/17/000100', '{$today}', '08:15:00', 'DR01', '000100', 'U01', 'A09', 'Sudah', 'Sudah Bayar');
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, keluhan, pemeriksaan, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/17/000100', '{$today}', '08:30:00', 'Batuk', 'Normal', 'Obat', 'ISPA', '-', '-', 'DR01');
            INSERT INTO mlite_billing_ledger (no_rawat, category, item_code, item_name, qty, unit_price, total_amount, claim_type, status, provider_id)
            VALUES ('2026/11/17/000100', 'Tindakan', 'T01', 'Konsultasi', 1, 75000, 75000, 'Tunai', 'Paid', 'T-1');
        ");

        $kpi = $this->repo->getExecutiveKpi($today, $today);
        $this->assertSame(1, $kpi['total_kunjungan']);
        $this->assertSame(75000.0, $kpi['total_pendapatan']);
        $this->assertSame(15, $kpi['avg_lead_time_minutes']);

        $lean = $this->repo->getLeanPoliMetrics($today, $today);
        $this->assertCount(1, $lean);
        $this->assertSame('Poli Umum', $lean[0]['nm_poli']);
        $this->assertSame(15, $lean[0]['avg_soap_lead_minutes']);

        $trend = $this->repo->getDailyTrend($today, $today);
        $this->assertCount(1, $trend);
        $this->assertSame(1, (int) $trend[0]['kunjungan']);
    }
}
