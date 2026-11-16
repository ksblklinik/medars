<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Audit;

use App\Domain\Audit\Service\DataIntegrityAuditService;
use App\Domain\Audit\Service\LogRotationService;
use App\Infrastructure\Database\SchemaMigrator;
use PDO;
use PHPUnit\Framework\TestCase;

class DataIntegrityAndSecurityAuditTest extends TestCase
{
    private PDO $pdo;
    private DataIntegrityAuditService $integrityService;
    private LogRotationService $rotationService;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->integrityService = new DataIntegrityAuditService($this->pdo);
        $this->rotationService = new LogRotationService($this->pdo);

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
        ");
    }

    public function testDetectsOrphanRecordsAcrossMedicalAndBillingTables(): void
    {
        // Kondisi awal: 0 orphan
        $cleanAudit = $this->integrityService->auditOrphans();
        $this->assertTrue($cleanAudit['healthy']);
        $this->assertSame(0, $cleanAudit['total_orphans']);

        // Sisipkan orphan records tanpa induk di reg_periksa
        $this->pdo->exec("
            INSERT INTO mlite_billing_ledger (no_rawat, item_code, item_name, category, qty, unit_price, total_amount, claim_type, status, provider_id, created_at)
            VALUES ('2026/99/99/999999', 'RJ01', 'Konsul Ilegal', 'Tindakan', 1, 50000, 50000, 'Tunai', 'Pending', '', '2026-11-16 10:00:00');

            INSERT INTO detail_periksa_lab (no_rawat, kd_jenis_prw, tgl_periksa, jam, pemeriksaan, nilai, nilai_rujukan, keterangan)
            VALUES ('2026/99/99/999999', 'LAB01', '2026-11-16', '10:00:00', 'Hb', '12', '13-17', 'Rendah');
        ");

        $dirtyAudit = $this->integrityService->auditOrphans();
        $this->assertFalse($dirtyAudit['healthy']);
        $this->assertGreaterThanOrEqual(2, $dirtyAudit['total_orphans']);

        $orphanTables = array_column($dirtyAudit['findings'], 'table');
        $this->assertContains('mlite_billing_ledger', $orphanTables);
        $this->assertContains('detail_periksa_lab', $orphanTables);
    }

    public function testVerifiesSplitBillingBalanceIntegrity(): void
    {
        // Kasus 1: Billing balance seimbang
        $this->pdo->exec("
            INSERT INTO mlite_billing_ledger (no_rawat, item_code, item_name, category, qty, unit_price, total_amount, claim_type, status, provider_id, created_at)
            VALUES ('2026/11/16/000001', 'RJ01', 'Konsul', 'Tindakan', 1, 40000, 40000, 'Kapitasi', 'Paid', '', '2026-11-16 08:30:00');
            INSERT INTO mlite_billing_ledger (no_rawat, item_code, item_name, category, qty, unit_price, total_amount, claim_type, status, provider_id, created_at)
            VALUES ('2026/11/16/000001', 'OB01', 'Obat', 'Obat', 1, 15000, 15000, 'Tunai', 'Paid', '', '2026-11-16 08:35:00');
        ");

        $audit = $this->integrityService->auditBillingLedgerBalance('2026/11/16/000001');
        $this->assertTrue($audit['balanced']);
        $this->assertSame(55000.0, (float) $audit['total_amount']);
        $this->assertSame(15000.0, (float) $audit['tunai_amount']);
        $this->assertSame(40000.0, (float) $audit['bpjs_amount']);
    }

    public function testVerifiesPharmacyStockBalanceIntegrity(): void
    {
        $this->pdo->exec("
            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, jualbebas, status)
            VALUES ('OBT001', 'Paracetamol', 'TAB', 500, 600, 1000, 1500, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'FAR01', 'B01', 'F01', 100);
            INSERT INTO riwayat_barang_medis (kode_brng, kd_bangsal, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, status)
            VALUES ('OBT001', 'FAR01', 0, 100, 0, 100, 'Pengadaan', '2026-11-16', '08:00:00', 'petugas1', 'Simpan');
        ");

        $audit = $this->integrityService->auditStockBalance('OBT001', 'FAR01');
        $this->assertTrue($audit['balanced']);
        $this->assertSame(100.0, (float) $audit['current_stock']);
        $this->assertSame(100.0, (float) $audit['calculated_stock']);
    }

    public function testRotatesAndPrunesStaleLogsOlderThanRetentionDays(): void
    {
        $oldDate = date('Y-m-d H:i:s', strtotime('-40 days'));
        $recentDate = date('Y-m-d H:i:s', strtotime('-5 days'));

        $this->pdo->exec("
            INSERT INTO mlite_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (1, 'admin', 'login', 'User logged in', '127.0.0.1', '{$oldDate}');
            INSERT INTO mlite_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (1, 'admin', 'login', 'User logged in', '127.0.0.1', '{$oldDate}');
            INSERT INTO mlite_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (1, 'admin', 'pay', 'Billing paid', '127.0.0.1', '{$recentDate}');
        ");

        $countBefore = (int) $this->pdo->query('SELECT COUNT(*) FROM mlite_logs')->fetchColumn();
        $this->assertSame(3, $countBefore);

        // Pangkas log lebih tua dari 30 hari
        $prunedCount = $this->rotationService->pruneLogs(30);
        $this->assertSame(2, $prunedCount);

        $countAfter = (int) $this->pdo->query('SELECT COUNT(*) FROM mlite_logs')->fetchColumn();
        $this->assertSame(1, $countAfter);
    }
}
