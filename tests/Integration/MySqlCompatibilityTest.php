<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Database\InitialSeeder;
use App\Infrastructure\Database\SchemaMigrator;
use App\Presentation\Http\HttpApplication;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL 8.0 Strict Mode compatibility & E2E verification (STG-01).
 * Runs when MEDARS_MYSQL_DSN environment variable is set.
 */
class MySqlCompatibilityTest extends TestCase
{
    private ?PDO $pdo = null;
    private array $session = [];
    private ?HttpApplication $app = null;

    protected function setUp(): void
    {
        $dsn = getenv('MEDARS_MYSQL_DSN') ?: ($_ENV['MEDARS_MYSQL_DSN'] ?? '');
        if ($dsn === '') {
            $this->markTestSkipped('MEDARS_MYSQL_DSN tidak diset; lewati uji MySQL 8.0 nyata.');
        }

        $user = getenv('MEDARS_MYSQL_USER') ?: 'root';
        $pass = getenv('MEDARS_MYSQL_PASS') ?: 'rootpass';

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        // Enforce MySQL 8.0 Strict Mode
        $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        // Reset all tables in test database for clean deterministic run
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        (new SchemaMigrator($this->pdo))->migrate();
        // Second run must be idempotent on MySQL 8.0
        (new SchemaMigrator($this->pdo))->migrate();
        (new InitialSeeder($this->pdo))->seed('AdminPass2026!');

        $this->session = [];
        $this->app = new HttpApplication($this->pdo, $this->session);
    }

    public function testSchemaMigratorAndAllCoreE2EFlowsOnRealMysql8(): void
    {
        assert($this->pdo !== null && $this->app !== null);

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Siti Aminah', '1970-05-10', 'P');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia) VALUES ('J06.9', 'Acute URI', 'ISPA Akut');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/16/000001', 'J06.9', 'Ralan', 1);
            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, jualbebas, status)
            VALUES ('OBT001', 'Paracetamol 500mg', 'TAB', 500, 600, 1000, 1500, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'APT', 'B01', 'F01', 20);
            INSERT INTO mlite_homecare_preceptor_sk (nip, nama_nakes, institusi, no_sk, status)
            VALUES ('NIP001', 'Ns. Rina, S.Kep', 'STIKES Harapan', 'SK/2026/001', 'Aktif');
        ");

        // 1. Login admin
        $this->app->handle('GET', '/auth/login');
        $csrf = $this->session['_csrf_token'] ?? '';
        $login = $this->app->handle('POST', '/auth/login', postData: [
            'username' => 'admin',
            'password' => 'AdminPass2026!',
            '_csrf_token' => $csrf,
        ]);
        $this->assertSame(302, $login['status']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 2. SOAP Dokter Ralan
        $soap = $this->app->handle(
            'POST',
            '/api/v1/dokter-ralan/soap',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'tgl_perawatan' => $today,
                'jam_rawat' => '08:15:00',
                'suhu_tubuh' => '36.6',
                'tensi' => '120/80',
                'nadi' => '80',
                'respirasi' => '20',
                'tinggi' => '160',
                'berat' => '55',
                'spo2' => '98',
                'gcs' => '15',
                'kesadaran' => 'Compos Mentis',
                'keluhan' => 'Demam ringan',
                'pemeriksaan' => 'Faring hiperemis',
                'rtl' => 'Terapi simtomatis',
                'penilaian' => 'ISPA',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $soap['status'], 'SOAP MySQL 8.0 gagal: ' . $soap['body']);

        // 3. Order Lab + Lock Kasir + Bayar + Input Hasil
        $labOrder = $this->app->handle(
            'POST',
            '/api/v1/penunjang/lab/orders',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'dokter_perujuk' => 'DR001',
                'items' => [['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'Hemoglobin', 'harga' => 45000, 'claim_type' => 'Tunai']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $labOrder['status'], 'Order Lab MySQL 8.0 gagal: ' . $labOrder['body']);
        $noOrder = json_decode($labOrder['body'], true)['orders'][0]['no_order'];

        $pay = $this->app->handle(
            'POST',
            '/api/v1/kasir/pay',
            rawBody: json_encode(['no_rawat' => '2026/11/16/000001']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $pay['status'], 'Kasir Pay MySQL 8.0 gagal: ' . $pay['body']);

        $labRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/lab/results',
            rawBody: json_encode([
                'no_order' => $noOrder,
                'results' => [['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.8', 'nilai_rujukan' => '13.0 - 17.0']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $labRes['status'], 'Lab Result MySQL 8.0 gagal: ' . $labRes['body']);

        // 4. POS Apotek Umum (PO-APT-...)
        $pos = $this->app->handle(
            'POST',
            '/api/v1/apotek-umum/pos',
            rawBody: json_encode([
                'payment_method' => 'QRIS',
                'items' => [['kode_brng' => 'OBT001', 'jml' => 2]],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $pos['status'], 'POS Apotek Umum MySQL 8.0 gagal: ' . $pos['body']);

        // 5. Vedika + SatuSehat Queue + Laporan Operasional + Audit ACID
        $vedika = $this->app->handle(
            'POST',
            '/api/v1/bridging/vedika/verify',
            rawBody: json_encode(['no_rawat' => '2026/11/16/000001', 'no_sep' => '0123R0011126V000001']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $vedika['status'], 'Vedika MySQL 8.0 gagal: ' . $vedika['body']);

        $rep = $this->app->handle('GET', '/api/v1/laporan/operasional', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $rep['status'], 'Laporan MySQL 8.0 gagal: ' . $rep['body']);

        $auditOrphans = $this->app->handle('GET', '/api/v1/audit/integrity/orphans');
        $this->assertSame(200, $auditOrphans['status'], 'Audit Orphans MySQL 8.0 gagal: ' . $auditOrphans['body']);
        $this->assertTrue(json_decode($auditOrphans['body'], true)['audit']['healthy']);
    }
}
