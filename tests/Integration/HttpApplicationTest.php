<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Database\InitialSeeder;
use App\Infrastructure\Database\SchemaMigrator;
use App\Presentation\Http\HttpApplication;
use PDO;
use PHPUnit\Framework\TestCase;

class HttpApplicationTest extends TestCase
{
    private PDO $pdo;
    private array $session = [];
    private HttpApplication $app;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        (new SchemaMigrator($this->pdo))->migrate();
        (new InitialSeeder($this->pdo))->seed('AdminPass2026!');

        $this->session = [];
        $this->app = new HttpApplication($this->pdo, $this->session);
    }

    public function testUnauthenticatedHtmlRouteRedirectsToLogin(): void
    {
        $res = $this->app->handle('GET', '/pendaftaran');
        $this->assertSame(302, $res['status']);
        $this->assertSame('/auth/login', $res['headers']['Location']);
        $this->assertSame('SAMEORIGIN', $res['headers']['X-Frame-Options']);
        $this->assertSame('nosniff', $res['headers']['X-Content-Type-Options']);
    }

    public function testUnauthenticatedApiRouteReturns401JsonNotHtmlRedirect(): void
    {
        $res = $this->app->handle('GET', '/api/v1/registration/patients/search?q=Budi');
        $this->assertSame(401, $res['status']);
        $this->assertStringContainsString('application/json', $res['headers']['Content-Type']);
        $decoded = json_decode($res['body'], true);
        $this->assertSame('error', $decoded['status']);
    }

    public function testPublicRoutesAreAccessibleWithoutLogin(): void
    {
        $loginRes = $this->app->handle('GET', '/auth/login');
        $this->assertSame(200, $loginRes['status']);

        $favRes = $this->app->handle('GET', '/favicon.ico');
        $this->assertSame(204, $favRes['status']);

        $displayRes = $this->app->handle('GET', '/display-antrean');
        $this->assertSame(200, $displayRes['status']);

        $kioskRes = $this->app->handle('GET', '/kiosk');
        $this->assertSame(200, $kioskRes['status']);
        $this->assertStringContainsString('csrf-token', $kioskRes['body']);

        $summaryRes = $this->app->handle('GET', '/api/v1/queue/summary');
        $this->assertSame(200, $summaryRes['status']);
    }

    public function testFullLoginAndProtectedRegistrationFlowWithCsrfAndStrictDate(): void
    {
        // 1. Ambil halaman login untuk mendapatkan CSRF token
        $this->app->handle('GET', '/auth/login');
        $csrfToken = $this->session['_csrf_token'] ?? '';
        $this->assertNotEmpty($csrfToken);

        // 2. Login sebagai admin
        $loginPost = $this->app->handle('POST', '/auth/login', postData: [
            'username' => 'admin',
            'password' => 'AdminPass2026!',
            '_csrf_token' => $csrfToken,
        ]);
        $this->assertSame(302, $loginPost['status']);
        $this->assertSame('/pendaftaran', $loginPost['headers']['Location']);
        $this->assertSame(1, $this->session['user_id']);

        // 3. Akses halaman /pendaftaran setelah login
        $pendaftaranRes = $this->app->handle('GET', '/pendaftaran');
        $this->assertSame(200, $pendaftaranRes['status']);
        $this->assertStringContainsString('Super Administrator', $pendaftaranRes['body']);

        // 4. POST API tanpa CSRF header harus ditolak 403
        $noCsrfRes = $this->app->handle(
            'POST',
            '/api/v1/registration/insite',
            rawBody: json_encode(['no_rkm_medis' => '000001']),
            headers: ['Content-Type' => 'application/json']
        );
        $this->assertSame(403, $noCsrfRes['status']);

        // 5. POST API dengan JSON malformed harus mengembalikan 400 Bad Request
        $badJsonRes = $this->app->handle(
            'POST',
            '/api/v1/registration/insite',
            rawBody: '{invalid-json',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $csrfToken,
            ]
        );
        $this->assertSame(400, $badJsonRes['status']);

        // 6. POST API dengan tanggal mustahil ('2026-02-30') harus mengembalikan 422
        $badDateRes = $this->app->handle(
            'POST',
            '/api/v1/registration/insite',
            rawBody: json_encode([
                'no_rkm_medis' => '000001',
                'kd_poli' => 'UMU01',
                'kd_dokter' => 'DR001',
                'kd_pj' => 'UMU',
                'tgl_registrasi' => '2026-02-30',
            ]),
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $csrfToken,
            ]
        );
        $this->assertSame(422, $badDateRes['status']);
        $this->assertStringContainsString('Format tanggal', $badDateRes['body']);
    }

    public function testKioskCheckInWorksWithoutLoginAndPublishesDoctorSse(): void
    {
        // Seed pasien dan booking hari ini
        $today = date('Y-m-d');
        $this->pdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi Santoso', '1990-01-01', 'L')");
        $this->pdo->exec("
            INSERT INTO booking_registrasi (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status)
            VALUES ('{$today}', '07:00:00', '000001', '{$today}', 'DR001', 'UMU01', '001', 'BPJ', 'Belum')
        ");

        // Buka /kiosk (tanpa login user) untuk inisialisasi sesi CSRF kiosk
        $this->app->handle('GET', '/kiosk');
        $kioskCsrf = $this->session['_csrf_token'] ?? '';
        $this->assertNotEmpty($kioskCsrf);

        // Kiosk check-in
        $checkInRes = $this->app->handle(
            'POST',
            '/api/v1/kiosk/checkin',
            rawBody: json_encode(['no_rkm_medis' => '000001', 'today' => $today]),
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $kioskCsrf,
            ]
        );

        $this->assertSame(200, $checkInRes['status']);
        $decoded = json_decode($checkInRes['body'], true);
        $this->assertSame('success', $decoded['status']);
        $this->assertSame('001', $decoded['data']['no_reg']);

        // Pastikan event SSE 'pasien_ralan_updated' masuk ke channel dokter_DR001
        $stmt = $this->pdo->query("SELECT * FROM sse_events WHERE channel = 'dokter_DR001' AND event = 'pasien_ralan_updated'");
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($ev);
    }

    public function testIdorProtectionOnDoctorSseChannel(): void
    {
        // Login sebagai staff loket (bukan dokter DR001 dan bukan admin)
        $hash = password_hash('Loket123!', PASSWORD_BCRYPT);
        $this->pdo->exec("
            INSERT INTO mlite_users (id, username, password, email, no_telp, fullname, role, cap, access, status)
            VALUES (2, 'loket1', '{$hash}', 'loket@medars.local', '', 'Loket 1', 'staff', 'UMU01', 'pendaftaran', '1')
        ");
        $this->session['user_id'] = 2;

        // Coba akses channel dokter_DR001 -> Harus 403 Forbidden
        $res = $this->app->handle('GET', '/api/stream?channel=dokter_DR001');
        $this->assertSame(403, $res['status']);
    }

    public function testDokterRalanEndToEndSoapDiagnosaAndCrossPoliIdorProtection(): void
    {
        $today = date('Y-m-d');
        $hash = password_hash('Dokter123!', PASSWORD_BCRYPT);
        $this->pdo->exec("
            INSERT INTO mlite_users (id, username, password, email, no_telp, fullname, role, cap, access, status)
            VALUES (3, 'DR001', '{$hash}', 'dr001@medars.local', '', 'dr. Jaga Umum', 'dokter', 'UMU01', 'dokter_ralan', '1');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR002', 'drg. Gigi');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'UMU', 'Belum');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts)
            VALUES ('002', '2026/11/16/000002', '{$today}', '08:10:00', 'DR002', '000001', 'GIG01', 'UMU', 'Belum');
        ");

        $this->session['user_id'] = 3;
        $pageRes = $this->app->handle('GET', '/dokter-ralan');
        $this->assertSame(200, $pageRes['status']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 1. Simpan diagnosa via HTTP POST /api/v1/dokter-ralan/diagnosa
        $dxRes = $this->app->handle(
            'POST',
            '/api/v1/dokter-ralan/diagnosa',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'kd_penyakit' => 'J06.9',
                'status' => 'Ralan',
                'prioritas' => 1,
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $dxRes['status']);

        // 2. Anti-IDOR lintas-poli pada surat-sakit (DR001 mencoba terbitkan surat untuk pasien GIG01 milik DR002)
        $idorRes = $this->app->handle(
            'POST',
            '/api/v1/dokter-ralan/surat-sakit',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000002',
                'tanggal_awal' => $today,
                'tanggal_akhir' => $today,
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(403, $idorRes['status']);
    }

    public function testPharmacyEndToEndResepCreateAndDispense(): void
    {
        $today = date('Y-m-d');
        $hash = password_hash('Apotek123!', PASSWORD_BCRYPT);
        $this->pdo->exec("
            INSERT INTO mlite_users (id, username, password, email, no_telp, fullname, role, cap, access, status)
            VALUES (4, 'APOTEK01', '{$hash}', 'apt@medars.local', '', 'Apt. Rina', 'farmasi', 'all', 'farmasi', '1');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Sudah');
            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, harga_kapitasi, harga_nonkapitasi, jualbebas, status)
            VALUES ('OBT001', 'Vitamin B6', 'TAB', 100, 120, 500, 300, 400, 600, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'FAR01', 'B01', 'F01', 20);
        ");

        $this->session['user_id'] = 4;
        $page = $this->app->handle('GET', '/farmasi');
        $this->assertSame(200, $page['status']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $create = $this->app->handle(
            'POST',
            '/api/v1/farmasi/resep/create',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'kd_dokter' => 'DR001',
                'non_racikan' => [['kode_brng' => 'OBT001', 'jml' => 5, 'aturan_pakai' => '3x1']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $create['status']);
        $noResep = json_decode($create['body'], true)['data']['no_resep'];

        $dispense = $this->app->handle(
            'POST',
            '/api/v1/farmasi/resep/dispense',
            rawBody: json_encode([
                'no_resep' => $noResep,
                'kd_bangsal' => 'FAR01',
                'jenis_klaim' => 'Kapitasi',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $dispense['status']);

        $stok = (float) $this->pdo->query("SELECT stok FROM gudangbarang WHERE kode_brng = 'OBT001' AND kd_bangsal = 'FAR01'")->fetchColumn();
        $this->assertSame(15.0, $stok);
    }

    public function testKasirSplitBillingAndPosApotekUmumEndToEnd(): void
    {
        $today = date('Y-m-d');
        $hash = password_hash('Kasir123!', PASSWORD_BCRYPT);
        $this->pdo->exec("
            INSERT INTO mlite_users (id, username, password, email, no_telp, fullname, role, cap, access, status)
            VALUES (5, 'KASIR01', '{$hash}', 'kasir@medars.local', '', 'Siti Kasir', 'kasir', 'all', 'kasir', '1');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, jualbebas, status)
            VALUES ('OBT001', 'Vitamin C', 'TAB', 500, 600, 1000, 2000, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'APT', 'B01', 'F01', 10);
        ");

        $this->session['user_id'] = 5;
        $page = $this->app->handle('GET', '/kasir');
        $this->assertSame(200, $page['status']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // Tambah item Tunai & Kapitasi
        $this->app->handle(
            'POST',
            '/api/v1/kasir/item',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'item_code' => 'RJ002',
                'item_name' => 'Konsultasi BPJS',
                'category' => 'Tindakan',
                'qty' => 1,
                'unit_price' => 40000,
                'claim_type' => 'Kapitasi',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->app->handle(
            'POST',
            '/api/v1/kasir/item',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'item_code' => 'OBT99',
                'item_name' => 'Vitamin Tunai',
                'category' => 'Obat',
                'qty' => 1,
                'unit_price' => 15000,
                'claim_type' => 'Tunai',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );

        // Pay
        $pay = $this->app->handle(
            'POST',
            '/api/v1/kasir/pay',
            rawBody: json_encode(['no_rawat' => '2026/11/16/000001']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $pay['status']);
        $paidData = json_decode($pay['body'], true);
        $this->assertSame(15000.0, (float) $paidData['data']['tunai_paid']);
        $this->assertSame(40000.0, (float) $paidData['data']['bpjs_covered']);

        // POS Apotek Umum
        $pos = $this->app->handle(
            'POST',
            '/api/v1/apotek-umum/pos',
            rawBody: json_encode([
                'payment_method' => 'QRIS',
                'items' => [['kode_brng' => 'OBT001', 'jml' => 2]],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $pos['status']);
        $this->assertStringContainsString('PO-APT-', $pos['body']);
    }

    public function testLabAndReportEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia) VALUES ('J06.9', 'Acute URI', 'ISPA Akut');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/16/000001', 'J06.9', 'Ralan', 1);
        ");

        $this->session['user_id'] = 1;
        $labPage = $this->app->handle('GET', '/laboratorium');
        $this->assertSame(200, $labPage['status']);
        $this->assertStringContainsString('Antrean Order Lab', $labPage['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 1. Buat order lab Tunai
        $createRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/lab/orders',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'dokter_perujuk' => 'DR001',
                'items' => [
                    ['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'Hemoglobin', 'harga' => 45000, 'claim_type' => 'Tunai'],
                ],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $createRes['status']);
        $created = json_decode($createRes['body'], true);
        $noOrder = $created['orders'][0]['no_order'];

        // 2. Validasi terkunci sebelum Kasir lunas
        $lockedRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/lab/results',
            rawBody: json_encode([
                'no_order' => $noOrder,
                'results' => [['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.2', 'nilai_rujukan' => '13.0 - 17.0']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(422, $lockedRes['status']);

        // 3. Lunasi di Kasir
        $payRes = $this->app->handle(
            'POST',
            '/api/v1/kasir/pay',
            rawBody: json_encode(['no_rawat' => '2026/11/16/000001']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $payRes['status']);

        // 4. Validasi hasil sekarang terbuka dan mendeteksi Rendah
        $saveRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/lab/results',
            rawBody: json_encode([
                'no_order' => $noOrder,
                'results' => [['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.2', 'nilai_rujukan' => '13.0 - 17.0']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $saveRes['status']);
        $saved = json_decode($saveRes['body'], true);
        $this->assertSame('Rendah', $saved['results'][0]['keterangan']);

        // 5. Laporan Operasional
        $repRes = $this->app->handle('GET', '/api/v1/laporan/operasional', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $repRes['status']);
        $rep = json_decode($repRes['body'], true);
        $this->assertTrue($rep['ok']);
        $this->assertCount(1, $rep['visits_by_poli']);
        $this->assertSame('J06.9', $rep['top_diseases'][0]['kd_penyakit']);
    }

    public function testBridgingCircuitBreakerAndSatuSehatQueueEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi, tinggi, berat, spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi, lingkar_perut, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/16/000001', '{$today}', '08:15:00', '36.5', '120/80', '80', '20', '170', '65', '98', '15', 'Compos Mentis', 'Batuk', 'Ronkhi (-)', '-', '-', 'Obat', 'ISPA', '-', '-', 'DR001');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia) VALUES ('J06.9', 'Acute URI', 'ISPA Akut');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/16/000001', 'J06.9', 'Ralan', 1);
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/bridging');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Circuit Breaker', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 1. Status Circuit Breaker (0ms mock karena kredensial belum diisi)
        $statusRes = $this->app->handle('GET', '/api/v1/bridging/status');
        $this->assertSame(200, $statusRes['status']);
        $statusData = json_decode($statusRes['body'], true);
        $this->assertTrue($statusData['bpjs_circuit']['circuit_open']);

        // 2. Antrean SatuSehat FHIR Asinkron
        $qRes = $this->app->handle(
            'POST',
            '/api/v1/bridging/satusehat/queue',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'patient_ihs_id' => 'P001',
                'practitioner_ihs_id' => 'N001',
                'icd10_code' => 'J06.9',
                'icd10_display' => 'ISPA Akut',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $qRes['status']);

        // 3. Verifikasi Klaim Vedika
        $vRes = $this->app->handle(
            'POST',
            '/api/v1/bridging/vedika/verify',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'no_sep' => '0123R0011126V000001',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $vRes['status']);
        $vData = json_decode($vRes['body'], true);
        $this->assertSame('Layak', $vData['verification']['status']);
    }

    public function testWaGatewayAndHomeCareEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Siti Aminah', '1970-05-10', 'P');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO mlite_homecare_preceptor_sk (nip, nama_nakes, institusi, no_sk, status)
            VALUES ('NIP001', 'Ns. Rina, S.Kep', 'STIKES Harapan', 'SK/2026/001', 'Aktif');
        ");

        $this->session['user_id'] = 1;
        $waPage = $this->app->handle('GET', '/wagateway');
        $this->assertSame(200, $waPage['status']);
        $this->assertStringContainsString('WhatsApp Gateway', $waPage['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 1. Kirim pesan WA (admin)
        $sendRes = $this->app->handle(
            'POST',
            '/api/v1/wagateway/send',
            rawBody: json_encode([
                'phone' => '081234567890',
                'message' => 'Tes Notifikasi WA SIMRS Medars',
                'session' => 'system',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $sendRes['status']);
        $sendData = json_decode($sendRes['body'], true);
        // K5: gateway WA belum dikonfigurasi -> wajib jujur 'Failed', bukan palsu 'Sent'
        $this->assertSame('Failed', $sendData['result']['status']);

        // 2. Inbound Webhook QR check-in (Zero-Reply Policy)
        $hookPayload = json_encode([
            'session' => 'system',
            'phone' => '6281234567890',
            'message' => 'CHECKIN 2026/11/16/000001',
        ]);

        // K2: tanpa secret terkonfigurasi -> fail-closed 403
        $noSecretRes = $this->app->handle('POST', '/api/v1/wagateway/webhook', rawBody: $hookPayload, headers: ['Content-Type' => 'application/json', 'X-Webhook-Token' => 'apa-saja']);
        $this->assertSame(403, $noSecretRes['status']);

        $this->pdo->exec("INSERT INTO mlite_settings (module, field, value) VALUES ('wagateway', 'webhook_secret', 'rahasia-webhook-123')");

        // K2: token salah / tidak ada -> 403
        $badTokenRes = $this->app->handle('POST', '/api/v1/wagateway/webhook', rawBody: $hookPayload, headers: ['Content-Type' => 'application/json', 'X-Webhook-Token' => 'salah']);
        $this->assertSame(403, $badTokenRes['status']);
        $missingTokenRes = $this->app->handle('POST', '/api/v1/wagateway/webhook', rawBody: $hookPayload, headers: ['Content-Type' => 'application/json']);
        $this->assertSame(403, $missingTokenRes['status']);

        $hookRes = $this->app->handle(
            'POST',
            '/api/v1/wagateway/webhook',
            rawBody: $hookPayload,
            headers: ['Content-Type' => 'application/json', 'X-Webhook-Token' => 'rahasia-webhook-123']
        );
        $this->assertSame(200, $hookRes['status']);
        $hookData = json_decode($hookRes['body'], true);
        $this->assertTrue($hookData['result']['verified']);
        $this->assertSame(0, $hookData['result']['outbound_sent_count']);

        // 3. Home Care: buat kunjungan dan tugaskan preceptor
        $createRes = $this->app->handle(
            'POST',
            '/api/v1/homecare/kunjungan',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'tgl_kunjungan' => $today,
                'jam_kunjungan' => '09:00:00',
                'kd_dokter_dpjp' => 'DR001',
                'diagnosis' => 'Perawatan Luka DM',
                'payment_gateway' => 'Kasera',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $createRes['status']);

        $assignRes = $this->app->handle(
            'POST',
            '/api/v1/homecare/preceptor/assign',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000001',
                'preceptor_nip' => 'NIP001',
                'institusi' => 'STIKES Harapan',
                'mahasiswa_nim' => 'MHS202601',
                'supervisor_id' => 'DOSEN01',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $assignRes['status']);

        // 4. Akses tampilan Supervisor Dosen (Patient Privacy Shield aktif)
        $supRes = $this->app->handle('GET', '/api/v1/homecare/supervisor/visits', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $supRes['status']);
        $supData = json_decode($supRes['body'], true);
        $this->assertCount(1, $supData['visits']);
        $this->assertSame('Ny. S*** (56 Th, P)', $supData['visits'][0]['pseudonym_pasien']);
        // K3: tidak boleh ada identifier pasien yang lolos ke pihak kampus
        foreach (['nm_pasien', 'no_rkm_medis', 'tgl_lahir', 'jk', 'no_rawat'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $supData['visits'][0]);
        }
    }

    public function testContentSecurityPolicyAllowsOnlyTheCdnAssetsUsedByLayout(): void
    {
        $res = $this->app->handle('GET', '/auth/login');
        $csp = $res['headers']['Content-Security-Policy'] ?? '';

        // K1: CDN yang dipakai layout.php/login.php/display wajib diizinkan, kalau tidak UI rusak total
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString('https://cdnjs.cloudflare.com', $csp);
        $this->assertStringContainsString("connect-src 'self' https://cdn.jsdelivr.net", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);

        // Setiap host eksternal di template harus tercakup oleh CSP
        $templateDir = dirname(__DIR__, 2) . '/src/Presentation/View/templates';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templateDir));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('#(?:src|href)="(https://[^/"]+)#', (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $host) {
                $this->assertStringContainsString($host, $csp, "Host {$host} di {$file->getFilename()} diblokir CSP.");
            }
        }
    }

    public function testAuditIntegrityAndLogRotationEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $auditPage = $this->app->handle('GET', '/audit');
        $this->assertSame(200, $auditPage['status']);
        $this->assertStringContainsString('Audit Keamanan &amp; Integritas Data', $auditPage['body']);
        $this->assertArrayHasKey('Content-Security-Policy', $auditPage['headers']);
        $this->assertArrayHasKey('Permissions-Policy', $auditPage['headers']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 1. Audit Orphan Records via API
        $orphanRes = $this->app->handle('GET', '/api/v1/audit/integrity/orphans');
        $this->assertSame(200, $orphanRes['status']);
        $orphanData = json_decode($orphanRes['body'], true);
        $this->assertTrue($orphanData['ok']);
        $this->assertTrue($orphanData['audit']['healthy']);

        // 2. Rotasi Log via API
        $rotateRes = $this->app->handle(
            'POST',
            '/api/v1/audit/logs/rotate',
            rawBody: json_encode(['retention_days' => 90]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $rotateRes['status']);
        $rotateData = json_decode($rotateRes['body'], true);
        $this->assertTrue($rotateData['ok']);
    }

    public function testMasterPasienEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/pasien');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Form Master Pasien', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle(
            'POST',
            '/api/v1/pasien/save',
            rawBody: json_encode([
                'nm_pasien' => 'Dewi Lestari',
                'no_ktp' => '3201010101950001',
                'jk' => 'P',
                'tgl_lahir' => '1995-03-15',
                'alamat' => 'Jl. Melati 1',
                'no_tlp' => '081234567890',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $save['status']);
        $saved = json_decode($save['body'], true);
        $rm = $saved['pasien']['no_rkm_medis'];

        $kartu = $this->app->handle('GET', '/pasien/kartu', queryParams: ['no_rkm_medis' => $rm]);
        $this->assertSame(200, $kartu['status']);
        $this->assertStringContainsString('Dewi Lestari', $kartu['body']);
    }

    public function testPoliDeepeningEndToEnd(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000010', 'Siti Aminah', '1988-04-10', 'P');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U01', 'Poli Umum', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('001', '2026/11/16/000010', '{$yesterday}', '09:00:00', 'DR01', '000010', 'U01', 'BPJ', 'Belum', 'Belum Bayar');
            INSERT INTO jns_perawatan (kd_jenis_prw, nm_perawatan, kd_kategori, material, tarif_tindakandr, total_byrdr, status)
            VALUES ('TND01', 'Nebulizer Dewasa', 'KAT01', 15000, 35000, 50000, '1');
        ");

        $this->session['user_id'] = 1;
        $regPage = $this->app->handle('GET', '/pendaftaran');
        $this->assertSame(200, $regPage['status']);
        $this->assertStringContainsString('regFilterFrom', $regPage['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 1. Multi-day filter on /api/v1/registration/today
        $listRes = $this->app->handle('GET', '/api/v1/registration/today', queryParams: ['from' => $yesterday, 'to' => $today]);
        $this->assertSame(200, $listRes['status']);
        $rows = json_decode($listRes['body'], true);
        $this->assertCount(1, $rows);
        $this->assertSame('2026/11/16/000010', $rows[0]['no_rawat']);

        // 2. Update status via /api/v1/registration/update-status
        $sttsRes = $this->app->handle(
            'POST',
            '/api/v1/registration/update-status',
            rawBody: json_encode(['no_rawat' => '2026/11/16/000010', 'stts' => 'Sudah']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $sttsRes['status']);
        $sttsJson = json_decode($sttsRes['body'], true);
        $this->assertSame('success', $sttsJson['status']);

        // 3. Save Tindakan Dokter via /api/v1/dokter-ralan/tindakan/save
        $tindakanRes = $this->app->handle(
            'POST',
            '/api/v1/dokter-ralan/tindakan/save',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000010',
                'kd_jenis_prw' => 'TND01',
                'claim_type' => 'Tunai',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $tindakanRes['status']);
        $tindakanJson = json_decode($tindakanRes['body'], true);
        $this->assertSame('success', $tindakanJson['status']);

        // 4. Save & Get Resume Medis
        $resumeRes = $this->app->handle(
            'POST',
            '/api/v1/dokter-ralan/resume',
            rawBody: json_encode([
                'no_rawat' => '2026/11/16/000010',
                'keluhan_utama' => 'Sesak napas ringan',
                'jalannya_penyakit' => 'Membaik pasca nebulizer',
                'diagnosa_utama' => 'Asma Bronkial',
                'kd_diagnosa_utama' => 'J45',
                'kondisi_pulang' => 'Hidup',
                'obat_pulang' => 'Salbutamol inhaler',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $resumeRes['status']);
        $resumeJson = json_decode($resumeRes['body'], true);
        $this->assertSame('success', $resumeJson['status']);

        $getResume = $this->app->handle('GET', '/api/v1/dokter-ralan/resume', queryParams: ['no_rawat' => '2026/11/16/000010']);
        $this->assertSame(200, $getResume['status']);
        $getResumeJson = json_decode($getResume['body'], true);
        $this->assertSame('J45', $getResumeJson['data']['kd_diagnosa_utama']);
    }

    public function testApotekAndKasirDeepeningEndToEnd(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000020', 'Ahmad Fauzi', '1985-06-15', 'L');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR02', 'dr. Sari', '1');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U02', 'Poli Penyakit Dalam', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('002', '2026/11/16/000020', '{$yesterday}', '10:00:00', 'DR02', '000020', 'U02', 'A09', 'Sudah', 'Belum Bayar');
            INSERT INTO databarang (kode_brng, nama_brng, h_beli, harga_tunai, status) VALUES ('OBT099', 'Paracetamol 500mg', 500, 1500, '1');
            INSERT INTO resep_obat (no_resep, tgl_peresepan, jam_peresepan, no_rawat, kd_dokter, tgl_perawatan, jam, status)
            VALUES ('202611160001', '{$yesterday}', '10:15:00', '2026/11/16/000020', 'DR02', '0000-00-00', '00:00:00', 'ralan');
            INSERT INTO resep_dokter_nonracikan (no_resep, kode_brng, jml, aturan_pakai) VALUES ('202611160001', 'OBT099', 10, '3 x 1 Sesudah Makan');
            INSERT INTO mlite_billing_ledger (no_rawat, category, item_code, item_name, qty, unit_price, total_amount, claim_type, status, provider_id)
            VALUES ('2026/11/16/000020', 'Tindakan', 'TND99', 'Konsultasi Spesialis', 1, 150000, 150000, 'Piutang', 'Pending', 'TND-99');
        ");

        $this->session['user_id'] = 1;

        // 1. Apotek UI & multi-day queue
        $apotekPage = $this->app->handle('GET', '/apotek-ralan');
        $this->assertSame(200, $apotekPage['status']);
        $this->assertStringContainsString('farmasiFilterFrom', $apotekPage['body']);
        $this->assertStringContainsString('btnPrintEtiket', $apotekPage['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $resepQueue = $this->app->handle('GET', '/api/v1/farmasi/queue', queryParams: ['from' => $yesterday, 'to' => $today]);
        $this->assertSame(200, $resepQueue['status']);
        $resepRows = json_decode($resepQueue['body'], true);
        $this->assertCount(1, $resepRows);
        $this->assertSame('202611160001', $resepRows[0]['no_resep']);

        // 2. Cetak Etiket & Cetak E-Resep
        $etiket = $this->app->handle('GET', '/farmasi/etiket', queryParams: ['no_resep' => '202611160001']);
        $this->assertSame(200, $etiket['status']);
        $this->assertStringContainsString('Paracetamol 500mg', $etiket['body']);
        $this->assertStringContainsString('3 x 1 Sesudah Makan', $etiket['body']);

        $cetakResep = $this->app->handle('GET', '/farmasi/cetak-resep', queryParams: ['no_resep' => '202611160001']);
        $this->assertSame(200, $cetakResep['status']);
        $this->assertStringContainsString('SALINAN E-RESEP FARMASI', $cetakResep['body']);

        // 3. Kasir UI, multi-day queue, pay + settle piutang + cetak faktur
        $kasirPage = $this->app->handle('GET', '/kasir');
        $this->assertSame(200, $kasirPage['status']);
        $this->assertStringContainsString('billingFilterFrom', $kasirPage['body']);
        $this->assertStringContainsString('btnSettlePiutang', $kasirPage['body']);

        $kasirQueue = $this->app->handle('GET', '/api/v1/kasir/queue', queryParams: ['from' => $yesterday, 'to' => $today]);
        $this->assertSame(200, $kasirQueue['status']);
        $kasirRows = json_decode($kasirQueue['body'], true);
        $this->assertCount(1, $kasirRows);

        $payRes = $this->app->handle(
            'POST',
            '/api/v1/kasir/pay',
            rawBody: json_encode(['no_rawat' => '2026/11/16/000020']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $payRes['status']);

        $settleRes = $this->app->handle(
            'POST',
            '/api/v1/kasir/settle-piutang',
            rawBody: json_encode(['no_rawat' => '2026/11/16/000020']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $settleRes['status']);
        $settleJson = json_decode($settleRes['body'], true);
        $this->assertSame('success', $settleJson['status']);

        $faktur = $this->app->handle('GET', '/kasir/faktur', queryParams: ['no_rawat' => '2026/11/16/000020']);
        $this->assertSame(200, $faktur['status']);
        $this->assertStringContainsString('KUITANSI &amp; RINCIAN BILLING 4-PILAR', $faktur['body']);
        $this->assertStringContainsString('Konsultasi Spesialis', $faktur['body']);
    }

    public function testLabAndRadiologiDeepeningEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000030', 'Rina Kartika', '1992-07-20', 'P');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR03', 'dr. Hendra', '1');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U03', 'Poli Anak', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('003', '2026/11/17/000030', '{$today}', '08:30:00', 'DR03', '000030', 'U03', 'A09', 'Belum', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $labPage = $this->app->handle('GET', '/laboratorium');
        $this->assertSame(200, $labPage['status']);
        $this->assertStringContainsString('supportFilterFrom', $labPage['body']);
        $this->assertStringContainsString('btnPrintLabResult', $labPage['body']);
        $this->assertStringContainsString('btnDeleteRadOrder', $labPage['body']);
        $this->assertStringContainsString('btnPrintRadResult', $labPage['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        // 1. Create Lab order & print Lab result
        $labOrderRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/lab/orders',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000030',
                'dokter_perujuk' => 'DR03',
                'items' => [['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'Darah Lengkap', 'harga' => 75000, 'claim_type' => 'Tunai']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $labOrderRes['status']);
        $labNoOrder = json_decode($labOrderRes['body'], true)['orders'][0]['no_order'];

        $cetakLab = $this->app->handle('GET', '/penunjang/lab/cetak', queryParams: ['noorder' => $labNoOrder]);
        $this->assertSame(200, $cetakLab['status']);
        $this->assertStringContainsString('HASIL PEMERIKSAAN LABORATORIUM', $cetakLab['body']);

        // 2. Create Radiology order, save expertise, & print Expertise
        $radOrderRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/radiologi/orders',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000030',
                'dokter_perujuk' => 'DR03',
                'items' => [['kd_jenis_prw' => 'RAD01', 'nm_perawatan' => 'Thorax PA', 'harga' => 150000, 'claim_type' => 'Tunai']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $radOrderRes['status']);
        $radNoOrder = json_decode($radOrderRes['body'], true)['orders'][0]['no_order'];

        $radResultRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/radiologi/results',
            rawBody: json_encode(['no_order' => $radNoOrder, 'hasil' => 'Cor dan pulmo normal']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $radResultRes['status']);

        $cetakRad = $this->app->handle('GET', '/penunjang/radiologi/cetak', queryParams: ['noorder' => $radNoOrder]);
        $this->assertSame(200, $cetakRad['status']);
        $this->assertStringContainsString('HASIL EXPERTISE RADIOLOGI', $cetakRad['body']);
        $this->assertStringContainsString('Cor dan pulmo normal', $cetakRad['body']);

        // 3. Create another Radiology order & delete it cleanly
        $radOrder2 = $this->app->handle(
            'POST',
            '/api/v1/penunjang/radiologi/orders',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000030',
                'dokter_perujuk' => 'DR03',
                'items' => [['kd_jenis_prw' => 'RAD02', 'nm_perawatan' => 'USG Abdomen', 'harga' => 250000, 'claim_type' => 'Tunai']],
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $radNoOrder2 = json_decode($radOrder2['body'], true)['orders'][0]['no_order'];

        $delRadRes = $this->app->handle(
            'POST',
            '/api/v1/penunjang/radiologi/orders/delete',
            rawBody: json_encode(['noorder' => $radNoOrder2]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $delRadRes['status']);
        $this->assertTrue(json_decode($delRadRes['body'], true)['ok']);
    }

    public function testMasterDataEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/master');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Master Data Klinik', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle(
            'POST',
            '/api/v1/master/poliklinik/save',
            rawBody: json_encode(['kd_poli' => 'MAT01', 'nm_poli' => 'Poli Mata', 'registrasi' => 30000]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $save['status']);

        $list = $this->app->handle('GET', '/api/v1/master/poliklinik', queryParams: ['q' => 'Mata']);
        $this->assertSame(200, $list['status']);
        $rows = json_decode($list['body'], true)['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('Poli Mata', $rows[0]['nm_poli']);

        $del = $this->app->handle(
            'POST',
            '/api/v1/master/poliklinik/delete',
            rawBody: json_encode(['kd_poli' => 'MAT01']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $del['status']);
    }

    public function testSuratMedisEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk, alamat)
            VALUES ('000040', 'Dian Permata', '1993-08-12', 'P', 'Jl. Anggrek 5');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000040', '{$today}', '09:00:00', 'DR01', '000040', 'U01', 'A09', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/surat');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Surat Keterangan Medis', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $create = $this->app->handle(
            'POST',
            '/api/v1/surat/create',
            rawBody: json_encode([
                'type' => 'sakit',
                'no_rawat' => '2026/11/17/000040',
                'tanggal_awal' => $today,
                'tanggal_akhir' => $today,
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $create['status']);
        $noSurat = json_decode($create['body'], true)['data']['no_surat'];

        $list = $this->app->handle('GET', '/api/v1/surat/list', queryParams: ['type' => 'sakit', 'q' => 'Dian']);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, json_decode($list['body'], true)['data']);

        $cetak = $this->app->handle('GET', '/surat/cetak', queryParams: ['type' => 'sakit', 'no_surat' => $noSurat]);
        $this->assertSame(200, $cetak['status']);
        $this->assertStringContainsString('SURAT KETERANGAN SAKIT', $cetak['body']);
        $this->assertStringContainsString('Dian Permata', $cetak['body']);
    }

    public function testKepegawaianEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/kepegawaian');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Manajemen Kepegawaian', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle(
            'POST',
            '/api/v1/kepegawaian/pegawai/save',
            rawBody: json_encode(['nik' => 'PEG99', 'nama' => 'Dr. Maya', 'jbtn' => 'Dokter Umum', 'departemen' => 'Medis']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $save['status']);

        $cetak = $this->app->handle('GET', '/kepegawaian/cetak', queryParams: ['entity' => 'pegawai']);
        $this->assertSame(200, $cetak['status']);
        $this->assertStringContainsString('Dr. Maya', $cetak['body']);

        $del = $this->app->handle(
            'POST',
            '/api/v1/kepegawaian/pegawai/delete',
            rawBody: json_encode(['nik' => 'PEG99']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $del['status']);
    }

    public function testFrontOfficeEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000050', 'Eko Prasetyo', '1987-01-10', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U01', 'Poli Umum', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO penjab (kd_pj, png_jawab, status) VALUES ('A09', 'UMUM', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('001', '2026/11/17/000050', '{$today}', '08:00:00', 'DR01', '000050', 'U01', 'A09', 'Belum', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/frontoffice');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Front Office &amp; Gatekeeper', $page['body']);

        $overview = $this->app->handle('GET', '/api/v1/frontoffice/overview', queryParams: ['date' => $today]);
        $this->assertSame(200, $overview['status']);
        $json = json_decode($overview['body'], true);
        $this->assertSame(1, $json['stats']['total_kunjungan']);
        $this->assertCount(1, $json['visits']);
    }

    public function testIgdEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000060', 'Pasien IGD', '1980-02-02', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('IGDK', 'IGD', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Jaga', '1');
            INSERT INTO penjab (kd_pj, png_jawab, status) VALUES ('A09', 'UMUM', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/17/000060', '{$today}', '08:00:00', 'DR01', '000060', 'IGDK', 'A09', 'Belum', 'Ralan', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/igd');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Instalasi Gawat Darurat', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $triase = $this->app->handle(
            'POST',
            '/api/v1/igd/triase',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000060',
                'zona_triase' => 'Merah',
                'keluhan_utama' => 'Sesak berat',
                'spo2' => '85',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $triase['status']);

        $disp = $this->app->handle(
            'POST',
            '/api/v1/igd/disposisi',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000060',
                'disposisi' => 'Rawat Inap',
                'status_lanjut' => 'Ranap',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $disp['status']);

        $list = $this->app->handle('GET', '/api/v1/igd/list', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $list['status']);
        $rows = json_decode($list['body'], true)['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('Merah', $rows[0]['zona_triase']);
        $this->assertSame('Ranap', $rows[0]['status_lanjut']);
    }

    public function testRawatInapEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000070', 'Slamet Ranap', '1975-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/17/000070', '{$today}', '09:00:00', 'DR01', '000070', 'IGDK', 'A09', 'Belum', 'Ralan', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/rawat-inap');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Rawat Inap &amp; Manajemen Kamar', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $saveKamar = $this->app->handle(
            'POST',
            '/api/v1/ranap/kamar/save',
            rawBody: json_encode(['kd_kamar' => 'VIP-01', 'kd_bangsal' => 'B01', 'nm_bangsal' => 'Anggrek', 'kelas' => 'VIP', 'trf_kamar' => 500000]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $saveKamar['status']);

        $checkIn = $this->app->handle(
            'POST',
            '/api/v1/ranap/checkin',
            rawBody: json_encode(['no_rawat' => '2026/11/17/000070', 'kd_kamar' => 'VIP-01']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $checkIn['status']);

        $checkOut = $this->app->handle(
            'POST',
            '/api/v1/ranap/checkout',
            rawBody: json_encode(['no_rawat' => '2026/11/17/000070', 'kd_kamar' => 'VIP-01', 'stts_pulang' => 'Membaik']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $checkOut['status']);
        $outJson = json_decode($checkOut['body'], true);
        $this->assertSame(500000, $outJson['data']['ttl_biaya']);
    }

    public function testDokterRanapEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000080', 'Hendra Wijaya', '1978-05-05', 'L');
            INSERT INTO bangsal (kd_bangsal, nm_bangsal, status) VALUES ('B01', 'Mawar', '1');
            INSERT INTO kamar (kd_kamar, kd_bangsal, trf_kamar, status, kelas, statusdata) VALUES ('MWR-01', 'B01', 200000, 'ISI', 'Kelas 2', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/17/000080', '{$today}', '08:00:00', 'DR01', '000080', 'IGDK', 'A09', 'Dirawat', 'Ranap', 'Belum Bayar');
            INSERT INTO kamar_inap (no_rawat, kd_kamar, tgl_masuk, jam_masuk, trf_kamar, ttl_biaya, stts_pulang)
            VALUES ('2026/11/17/000080', 'MWR-01', '{$today}', '08:30:00', 200000, 200000, '-');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/dokter-ranap');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Dokter Rawat Inap', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $patients = $this->app->handle('GET', '/api/v1/dokter-ranap/patients', queryParams: ['q' => 'Hendra']);
        $this->assertSame(200, $patients['status']);
        $this->assertCount(1, json_decode($patients['body'], true)['data']);

        $save = $this->app->handle(
            'POST',
            '/api/v1/dokter-ranap/cppt/save',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000080',
                'keluhan' => 'Kondisi stabil',
                'pemeriksaan' => 'TD 120/80',
                'penilaian' => 'Membaik',
                'rtl' => 'Observasi lanjut',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $save['status']);

        $cppt = $this->app->handle('GET', '/api/v1/dokter-ranap/cppt', queryParams: ['no_rawat' => '2026/11/17/000080']);
        $this->assertSame(200, $cppt['status']);
        $this->assertCount(1, json_decode($cppt['body'], true)['data']);
    }

    public function testGudangFarmasiEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/farmasi-gudang');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Gudang Farmasi', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle(
            'POST',
            '/api/v1/gudang/barang/save',
            rawBody: json_encode(['kode_brng' => 'OBT777', 'nama_brng' => 'Cefixime 100mg', 'harga_tunai' => 3500]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $save['status']);

        $recv = $this->app->handle(
            'POST',
            '/api/v1/gudang/receive',
            rawBody: json_encode(['kode_brng' => 'OBT777', 'kd_bangsal' => 'FAR01', 'qty' => 50, 'no_batch' => 'B1']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $recv['status']);

        $mut = $this->app->handle(
            'POST',
            '/api/v1/gudang/mutate',
            rawBody: json_encode(['kode_brng' => 'OBT777', 'from_bangsal' => 'FAR01', 'to_bangsal' => 'APT', 'qty' => 15]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $mut['status']);

        $kartu = $this->app->handle('GET', '/api/v1/gudang/kartu-stok', queryParams: ['kode_brng' => 'OBT777']);
        $this->assertSame(200, $kartu['status']);
        $this->assertCount(3, json_decode($kartu['body'], true)['data']);
    }

    public function testKeuanganEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/keuangan');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Keuangan, Jurnal Umum', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $coa = $this->app->handle(
            'POST',
            '/api/v1/keuangan/rekening',
            rawBody: json_encode(['kd_rek' => '5101.01', 'nm_rek' => 'Beban Listrik', 'tipe' => 'Beban', 'balance' => 'D']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $coa['status']);

        $jurnal = $this->app->handle(
            'POST',
            '/api/v1/keuangan/jurnal',
            rawBody: json_encode([
                'no_bukti' => 'BKK-99',
                'kd_debet' => '5101.01',
                'kd_kredit' => '1101.01',
                'nominal' => 250000,
                'keterangan' => 'Bayar Tagihan Listrik',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $jurnal['status']);

        $today = date('Y-m-d');
        $ov = $this->app->handle('GET', '/api/v1/keuangan/overview', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $ov['status']);
        $json = json_decode($ov['body'], true);
        $this->assertSame(250000, $json['laba_rugi']['total_beban']);
        $this->assertCount(2, $json['jurnal']);
    }

    public function testJasaMedisEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000090', 'Sari', '1991-01-01', 'P');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000090', '{$today}', '08:00:00', 'DR01', '000090', 'U01', 'A09', 'Belum Bayar');
            INSERT INTO jns_perawatan (kd_jenis_prw, nm_perawatan, material, tarif_tindakandr, total_byrdr, status)
            VALUES ('TND01', 'Konsultasi Poli', 10000, 40000, 50000, '1');
            INSERT INTO rawat_jl_dr (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, material, tarif_tindakandr, biaya_rawat, jenis_klaim)
            VALUES ('2026/11/17/000090', 'TND01', 'DR01', '{$today}', '08:10:00', 10000, 40000, 50000, 'Tunai');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/jasa-medis');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Rekap Jasa Medis Dokter', $page['body']);

        $api = $this->app->handle('GET', '/api/v1/jasa-medis/summary', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $api['status']);
        $json = json_decode($api['body'], true);
        $this->assertCount(1, $json['summary']);
        $this->assertSame(40000, (int) $json['summary'][0]['total_jasa_dokter']);

        $cetak = $this->app->handle('GET', '/jasa-medis/cetak', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $cetak['status']);
        $this->assertStringContainsString('REKAPITULASI JASA MEDIS DOKTER', $cetak['body']);
    }

    public function testManajemenEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U01', 'Poli Umum', '1');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000100', 'Tono', '1989-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('001', '2026/11/17/000100', '{$today}', '08:15:00', 'DR01', '000100', 'U01', 'A09', 'Sudah', 'Sudah Bayar');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/manajemen');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Dashboard Eksekutif &amp; LEAN Analytics Board', $page['body']);

        $api = $this->app->handle('GET', '/api/v1/manajemen/dashboard', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $api['status']);
        $json = json_decode($api['body'], true);
        $this->assertSame(1, $json['kpi']['total_kunjungan']);
        $this->assertCount(1, $json['lean_poli']);
    }

    public function testLaporanCsvAndRekonsiliasiEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000110', 'Dewi', '1990-01-01', 'P');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000110', '{$today}', '08:00:00', 'DR01', '000110', 'U01', 'A09', 'Belum Bayar');
            INSERT INTO mlite_billing_ledger (no_rawat, category, item_code, item_name, qty, unit_price, total_amount, claim_type, status, provider_id)
            VALUES ('2026/11/17/000110', 'Lab', 'LAB01', 'Darah Lengkap', 1, 75000, 75000, 'Tunai', 'Cancelled', 'PL-1');
        ");

        $this->session['user_id'] = 1;
        $rep = $this->app->handle('GET', '/api/v1/laporan/operasional', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $rep['status']);
        $json = json_decode($rep['body'], true);
        $this->assertCount(1, $json['reconciliation']);

        $csv = $this->app->handle('GET', '/api/v1/laporan/export-csv', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $csv['status']);
        $this->assertStringContainsString('text/csv', $csv['headers']['Content-Type']);
        $this->assertStringContainsString('Darah Lengkap', $csv['body']);
    }

    public function testProfilUpdateAndPasswordChangeEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/profil');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Data Profil Pengguna', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $upd = $this->app->handle(
            'POST',
            '/api/v1/profil/update',
            rawBody: json_encode(['fullname' => 'Admin Medars Baru', 'email' => 'admin2@medars.local']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $upd['status']);
        $this->assertSame('Admin Medars Baru', json_decode($upd['body'], true)['data']['fullname']);

        $pwd = $this->app->handle(
            'POST',
            '/api/v1/profil/password',
            rawBody: json_encode([
                'current_password' => 'AdminPass2026!',
                'new_password' => 'NewSecret2026!',
                'confirm_password' => 'NewSecret2026!',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $pwd['status']);
        $this->assertSame('success', json_decode($pwd['body'], true)['status']);
    }

    public function testAuditLogsViewerAndUsersSearchEndToEnd(): void
    {
        $this->pdo->exec("
            INSERT INTO mlite_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (1, 'admin', 'LOGIN_SUCCESS', 'Login berhasil dari test', '127.0.0.1', '2026-11-17 09:00:00');
        ");

        $this->session['user_id'] = 1;
        $logsRes = $this->app->handle('GET', '/api/v1/audit/logs', queryParams: ['q' => 'LOGIN']);
        $this->assertSame(200, $logsRes['status']);
        $logsJson = json_decode($logsRes['body'], true);
        $this->assertTrue($logsJson['ok']);
        $this->assertCount(1, $logsJson['logs']);

        $usersPage = $this->app->handle('GET', '/users');
        $this->assertSame(200, $usersPage['status']);
        $this->assertStringContainsString('usersTableBody', $usersPage['body']);
    }

    public function testVedikaClaimsAndBundleEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000120', 'Budi BPJS', '1985-05-05', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000120', '{$today}', '08:00:00', 'DR01', '000120', 'U01', 'BPJ', 'Belum Bayar');
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, keluhan, pemeriksaan, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/17/000120', '{$today}', '08:15:00', 'Pusing', 'Normal', 'Obat', 'Vertigo', '-', '-', 'DR01');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/17/000120', 'H81', 'Ralan', 1);
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/bridging');
        $this->assertSame(200, $page['status']);
        $csrf = $this->session['_csrf_token'] ?? '';
        $verify = $this->app->handle(
            'POST',
            '/api/v1/bridging/vedika/verify',
            rawBody: json_encode(['no_rawat' => '2026/11/17/000120', 'no_sep' => '0123R001001']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $verify['status']);

        $claims = $this->app->handle('GET', '/api/v1/bridging/vedika/claims', queryParams: ['from' => $today, 'to' => $today]);
        $this->assertSame(200, $claims['status']);
        $this->assertCount(1, json_decode($claims['body'], true)['claims']);

        $bundle = $this->app->handle('GET', '/vedika/bundel', queryParams: ['no_rawat' => '2026/11/17/000120']);
        $this->assertSame(200, $bundle['status']);
        $this->assertStringContainsString('BUNDEL BERKAS KLAIM VEDIKA BPJS', $bundle['body']);
        $this->assertStringContainsString('Budi BPJS', $bundle['body']);
    }

    public function testApotekUmumPosSalesAndReceiptEndToEnd(): void
    {
        $this->pdo->exec("
            INSERT INTO databarang (kode_brng, nama_brng, h_beli, harga_tunai, jualbebas, status) VALUES ('OBT888', 'Vitamin C', 500, 1500, 2000, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok) VALUES ('OBT888', 'APT', '', '', 50);
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/kasir');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('tbodyPosSales', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $pos = $this->app->handle(
            'POST',
            '/api/v1/apotek-umum/pos',
            rawBody: json_encode(['payment_method' => 'QRIS', 'items' => [['kode_brng' => 'OBT888', 'jml' => 2]]]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $pos['status']);
        $noNota = json_decode($pos['body'], true)['data']['no_nota'];

        $sales = $this->app->handle('GET', '/api/v1/apotek-umum/sales');
        $this->assertSame(200, $sales['status']);
        $this->assertCount(1, json_decode($sales['body'], true)['data']);

        $struk = $this->app->handle('GET', '/apotek-umum/struk', queryParams: ['no_nota' => $noNota]);
        $this->assertSame(200, $struk['status']);
        $this->assertStringContainsString('APOTEK UMUM MEDARS', $struk['body']);
        $this->assertStringContainsString('QRIS', $struk['body']);
    }

    public function testRujukanInternalEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000130', 'Nadia', '1994-01-01', 'P');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U02', 'Poli Gigi', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR02', 'drg. Nina', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000130', '{$today}', '08:00:00', 'DR01', '000130', 'U01', 'BPJ', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/surat');
        $this->assertSame(200, $page['status']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $create = $this->app->handle(
            'POST',
            '/api/v1/rujukan-internal',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000130',
                'kd_poli' => 'U02',
                'kd_dokter' => 'DR02',
                'catatan' => 'Konsul keluhan gigi',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $create['status']);

        $list = $this->app->handle('GET', '/api/v1/rujukan-internal', queryParams: ['q' => 'Nadia']);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, json_decode($list['body'], true)['data']);
    }

    public function testPortalPasienEndToEnd(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000140', 'Lia Amalia', '1992-05-20', 'P');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U01', 'Poli Umum', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
        ");

        $page = $this->app->handle('GET', '/portal-pasien');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Portal Pasien Mandiri', $page['body']);

        $lookup = $this->app->handle(
            'POST',
            '/api/v1/portal-pasien/lookup',
            rawBody: json_encode(['no_rkm_medis' => '000140', 'tgl_lahir' => '1992-05-20']),
            headers: ['Content-Type' => 'application/json']
        );
        $this->assertSame(200, $lookup['status']);
        $this->assertSame('Lia Amalia', json_decode($lookup['body'], true)['data']['patient']['nm_pasien']);

        $book = $this->app->handle(
            'POST',
            '/api/v1/portal-pasien/booking',
            rawBody: json_encode([
                'no_rkm_medis' => '000140',
                'tgl_lahir' => '1992-05-20',
                'tanggal_periksa' => $tomorrow,
                'kd_poli' => 'U01',
                'kd_dokter' => 'DR01',
            ]),
            headers: ['Content-Type' => 'application/json']
        );
        $this->assertSame(200, $book['status']);
        $this->assertSame('001', json_decode($book['body'], true)['data']['no_reg']);
    }

    public function testApotekAndKasirRanapFilterEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000150', 'Rudi Ranap', '1980-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/17/000150', '{$today}', '08:00:00', 'DR01', '000150', 'IGDK', 'A09', 'Dirawat', 'Ranap', 'Belum Bayar');
            INSERT INTO resep_obat (no_resep, tgl_peresepan, jam_peresepan, no_rawat, kd_dokter, tgl_perawatan, jam, status)
            VALUES ('202611170001', '{$today}', '09:00:00', '2026/11/17/000150', 'DR01', '0000-00-00', '00:00:00', 'ranap');
        ");

        $this->session['user_id'] = 1;
        $resepRanap = $this->app->handle('GET', '/api/v1/farmasi/queue', queryParams: ['from' => $today, 'to' => $today, 'status_layanan' => 'ranap']);
        $this->assertSame(200, $resepRanap['status']);
        $this->assertCount(1, json_decode($resepRanap['body'], true));

        $kasirRanap = $this->app->handle('GET', '/api/v1/kasir/queue', queryParams: ['from' => $today, 'to' => $today, 'status_lanjut' => 'Ranap']);
        $this->assertSame(200, $kasirRanap['status']);
        $this->assertCount(1, json_decode($kasirRanap['body'], true));
    }

    public function testKasirIgdFilterEndToEnd(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000160', 'Agus IGD', '1988-08-08', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/17/000160', '{$today}', '08:00:00', 'DR01', '000160', 'IGDK', 'A09', 'Belum', 'Ralan', 'Belum Bayar');
        ");

        $this->session['user_id'] = 1;
        $kasirIgd = $this->app->handle('GET', '/api/v1/kasir/queue', queryParams: ['from' => $today, 'to' => $today, 'kd_poli' => 'IGDK']);
        $this->assertSame(200, $kasirIgd['status']);
        $this->assertCount(1, json_decode($kasirIgd['body'], true));
    }

    public function testPresensiEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/presensi');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Presensi &amp; Absensi Shift Pegawai', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $in = $this->app->handle(
            'POST',
            '/api/v1/presensi/clock-in',
            rawBody: json_encode(['shift' => 'Pagi', 'lokasi' => 'Poli Umum']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $in['status']);

        $out = $this->app->handle(
            'POST',
            '/api/v1/presensi/clock-out',
            rawBody: json_encode([]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $out['status']);

        $list = $this->app->handle('GET', '/api/v1/presensi/list');
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, json_decode($list['body'], true)['data']);
    }

    public function testInventarisEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/inventaris');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Manajemen Inventaris', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle(
            'POST',
            '/api/v1/inventaris/save',
            rawBody: json_encode(['kode_aset' => 'AST-99', 'nama_aset' => 'EKG 12 Lead', 'kategori' => 'Alat Medis', 'nilai_perolehan' => 25000000]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $save['status']);

        $list = $this->app->handle('GET', '/api/v1/inventaris/list', queryParams: ['q' => 'EKG']);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, json_decode($list['body'], true)['data']);

        $del = $this->app->handle(
            'POST',
            '/api/v1/inventaris/delete',
            rawBody: json_encode(['kode_aset' => 'AST-99']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $del['status']);
    }

    public function testApiKeyManagementEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/api-keys');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Manajemen API Key Eksternal', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $create = $this->app->handle(
            'POST',
            '/api/v1/api-keys/create',
            rawBody: json_encode([
                'label' => 'LIS Lab Analyzer',
                'username' => 'lis_svc',
                'methods' => 'GET,POST',
                'ip_range' => '192.168.1.0/24',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $create['status']);
        $createdData = json_decode($create['body'], true)['data'];
        $this->assertStringStartsWith('ksm_', $createdData['raw_key']);
        $keyId = (int) $createdData['id'];

        $list = $this->app->handle('GET', '/api/v1/api-keys', queryParams: ['q' => 'LIS']);
        $this->assertSame(200, $list['status']);
        $listData = json_decode($list['body'], true)['data'];
        $this->assertCount(1, $listData);
        $this->assertArrayNotHasKey('api_key_hash', $listData[0]);

        $toggle = $this->app->handle(
            'POST',
            '/api/v1/api-keys/toggle',
            rawBody: json_encode(['id' => $keyId, 'is_active' => false]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $toggle['status']);

        $delete = $this->app->handle(
            'POST',
            '/api/v1/api-keys/delete',
            rawBody: json_encode(['id' => $keyId]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $delete['status']);
    }

    public function testModuleManagerEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/modules');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Manajer Modul SIMRS', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $list = $this->app->handle('GET', '/api/v1/modules', queryParams: ['q' => 'inventaris']);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, json_decode($list['body'], true)['data']);

        $toggleOff = $this->app->handle(
            'POST',
            '/api/v1/modules/toggle',
            rawBody: json_encode(['dir' => 'inventaris', 'is_active' => false]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $toggleOff['status']);

        $coreBlock = $this->app->handle(
            'POST',
            '/api/v1/modules/toggle',
            rawBody: json_encode(['dir' => 'modules', 'is_active' => false]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(422, $coreBlock['status']);

        $seq = $this->app->handle(
            'POST',
            '/api/v1/modules/sequence',
            rawBody: json_encode(['dir' => 'inventaris', 'sequence' => 50]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $seq['status']);
    }

    public function testVerifikasiSuratPublikEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $suratPage = $this->app->handle('GET', '/surat');
        $this->assertSame(200, $suratPage['status']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $today = date('Y-m-d');
        $create = $this->app->handle(
            'POST',
            '/api/v1/surat/create',
            rawBody: json_encode([
                'type' => 'sakit',
                'no_rawat' => '2026/11/17/000040',
                'tanggal_awal' => $today,
                'tanggal_akhir' => $today,
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $create['status']);
        $noSurat = (string) json_decode($create['body'], true)['data']['no_surat'];

        // Public verification without login session
        unset($this->session['user_id']);
        $hash = \App\Application\Rme\UseCase\VerifySuratUseCase::encodeHash('sakit', '2026/11/17/000040', $noSurat);

        $publicPage = $this->app->handle('GET', '/verifikasi', queryParams: ['hash' => $hash]);
        $this->assertSame(200, $publicPage['status']);
        $this->assertStringContainsString('Dokumen Terverifikasi Asli', $publicPage['body']);
        $this->assertStringContainsString($noSurat, $publicPage['body']);

        $apiRes = $this->app->handle('GET', '/api/v1/verifikasi', queryParams: ['type' => 'sakit', 'no_surat' => $noSurat]);
        $this->assertSame(200, $apiRes['status']);
        $this->assertTrue(json_decode($apiRes['body'], true)['valid']);

        $notFound = $this->app->handle('GET', '/api/v1/verifikasi', queryParams: ['type' => 'sakit', 'no_surat' => 'PALSU-999']);
        $this->assertSame(404, $notFound['status']);
    }

    public function testSettingsMultiTabSaveEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/settings');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Bridging BPJS &amp; SatuSehat', $page['body']);
        $this->assertStringContainsString('WhatsApp Gateway', $page['body']);
        $this->assertStringContainsString('Tarif &amp; Billing', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle('POST', '/settings/save', postData: [
            '_csrf_token' => $csrf,
            'settings' => [
                'general' => ['nama_instansi' => 'Klinik Utama Medars', 'kode_rs' => '3201999'],
                'bridging' => ['bpjs_consid' => '998877', 'satusehat_org_id' => 'ORG-123'],
                'wagateway' => ['api_url' => 'http://127.0.0.1:3000', 'anti_ban_delay_ms' => '4000'],
                'tarif' => ['ppn_persen' => '11', 'biaya_admin_ralan' => '15000'],
            ],
        ]);
        $this->assertSame(302, $save['status']);

        $reloaded = $this->app->handle('GET', '/settings');
        $this->assertSame(200, $reloaded['status']);
        $this->assertStringContainsString('Klinik Utama Medars', $reloaded['body']);
        $this->assertStringContainsString('ORG-123', $reloaded['body']);
        $this->assertStringContainsString('15000', $reloaded['body']);
    }

    public function testDashboardTrendsAndAnnouncementsEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/dashboard');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Tren Kunjungan', $page['body']);
        $this->assertStringContainsString('Pengumuman Internal', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $create = $this->app->handle(
            'POST',
            '/api/v1/dashboard/announcements/create',
            rawBody: json_encode([
                'judul' => 'Maintenance Server Malam',
                'isi' => 'Dilakukan pukul 23:00 WIB.',
                'prioritas' => 'Penting',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $create['status']);
        $annId = (int) json_decode($create['body'], true)['data']['id'];

        $afterCreate = $this->app->handle('GET', '/dashboard');
        $this->assertStringContainsString('Maintenance Server Malam', $afterCreate['body']);

        $del = $this->app->handle(
            'POST',
            '/api/v1/dashboard/announcements/delete',
            rawBody: json_encode(['id' => $annId]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $del['status']);
    }

    public function testPenjualanNonResepEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/penjualan');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Penjualan Non-Resep', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $saveBarang = $this->app->handle(
            'POST',
            '/api/v1/penjualan/barang-save',
            rawBody: json_encode(['nama_barang' => 'Termometer Infra', 'kode_satuan' => 'Unit', 'stok' => 10, 'harga' => 120000]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $saveBarang['status']);
        $barangId = (int) json_decode($saveBarang['body'], true)['data']['id'];

        $order = $this->app->handle(
            'POST',
            '/api/v1/penjualan/order-create',
            rawBody: json_encode(['nama_pembeli' => 'Ny. Rina']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $order['status']);
        $orderId = (int) json_decode($order['body'], true)['data']['id'];

        $addItem = $this->app->handle(
            'POST',
            '/api/v1/penjualan/item-add',
            rawBody: json_encode(['id_penjualan' => $orderId, 'id_barang' => $barangId, 'jumlah' => 2]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $addItem['status']);

        $settle = $this->app->handle(
            'POST',
            '/api/v1/penjualan/settle',
            rawBody: json_encode(['id_penjualan' => $orderId, 'potongan' => 0, 'jumlah_bayar' => 240000, 'cara_bayar' => 'Tunai']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $settle['status']);

        $faktur = $this->app->handle('GET', '/penjualan/faktur', queryParams: ['id' => $orderId]);
        $this->assertSame(200, $faktur['status']);
        $this->assertStringContainsString('Ny. Rina', $faktur['body']);

        $report = $this->app->handle('GET', '/api/v1/penjualan/report');
        $this->assertSame(200, $report['status']);
        $this->assertSame(240000, (int) json_decode($report['body'], true)['data']['total_omzet']);
    }

    public function testOperasiEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/operasi');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Jadwal &amp; Booking Operasi', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $paket = $this->app->handle(
            'POST',
            '/api/v1/operasi/paket-save',
            rawBody: json_encode(['kode_paket' => 'OP-99', 'nm_perawatan' => 'Sirkumsisi', 'tarif_total' => 850000]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $paket['status']);

        $booking = $this->app->handle(
            'POST',
            '/api/v1/operasi/booking-save',
            rawBody: json_encode(['no_rawat' => '2026/11/17/000040', 'kode_paket' => 'OP-99', 'kd_ruang_ok' => 'OK-2']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $booking['status']);
        $bookingId = (int) json_decode($booking['body'], true)['data']['id'];

        $statusRes = $this->app->handle(
            'POST',
            '/api/v1/operasi/booking-status',
            rawBody: json_encode(['id' => $bookingId, 'status' => 'Selesai']),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $statusRes['status']);

        $laporan = $this->app->handle(
            'POST',
            '/api/v1/operasi/laporan-save',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000040',
                'kode_paket' => 'OP-99',
                'diagnosa_preop' => 'Fimosis',
                'diagnosa_postop' => 'Post Sirkumsisi',
                'operator1' => 'dr. Andi',
                'laporan_operasi' => 'Tindakan selesai baik.',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(201, $laporan['status']);
    }

    public function testRuangTindakanEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/ruang-tindakan');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Ruang Tindakan', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle(
            'POST',
            '/api/v1/ruang-tindakan/save',
            rawBody: json_encode([
                'no_rawat' => '2026/11/17/000040',
                'kd_jenis_prw' => 'TIND-02',
                'diagnosis' => 'Abses Manus',
                'laporan_operasi' => 'Insisi & drainase abses.',
                'status' => 'Selesai',
            ]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $save['status']);
        $id = (int) json_decode($save['body'], true)['data']['id'];

        $list = $this->app->handle('GET', '/api/v1/ruang-tindakan/list', queryParams: ['q' => 'Abses']);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, json_decode($list['body'], true)['data']);

        $cetak = $this->app->handle('GET', '/ruang-tindakan/cetak', queryParams: ['id' => $id]);
        $this->assertSame(200, $cetak['status']);
        $this->assertStringContainsString('Abses Manus', $cetak['body']);

        $del = $this->app->handle(
            'POST',
            '/api/v1/ruang-tindakan/delete',
            rawBody: json_encode(['id' => $id]),
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]
        );
        $this->assertSame(200, $del['status']);
    }

    public function testUtdBankDarahEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/utd');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Unit Transfusi Darah', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $komp = $this->app->handle('POST', '/api/v1/utd/komponen-save', rawBody: json_encode(['kode' => 'WB', 'nama' => 'Whole Blood', 'total' => 350000]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $komp['status']);

        $stok = $this->app->handle('POST', '/api/v1/utd/stok-save', rawBody: json_encode(['no_kantong' => 'KTG-99', 'kode_komponen' => 'WB', 'golongan_darah' => 'AB']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $stok['status']);

        $req = $this->app->handle('POST', '/api/v1/utd/permintaan-save', rawBody: json_encode(['no_rawat' => '2026/11/17/000040', 'golongan_darah' => 'AB', 'no_kantong' => 'KTG-99']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(201, $req['status']);
        $reqId = (int) json_decode($req['body'], true)['data']['id'];

        $cm = $this->app->handle('POST', '/api/v1/utd/crossmatch', rawBody: json_encode(['id' => $reqId, 'no_kantong' => 'KTG-99', 'hasil_crossmatch' => 'Cocok', 'status' => 'Diserahkan']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $cm['status']);

        $stokAfter = $this->app->handle('GET', '/api/v1/utd/stok', queryParams: ['golongan' => 'AB']);
        $this->assertSame('Terpakai', json_decode($stokAfter['body'], true)['data'][0]['status']);
    }

    public function testVeronisaEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/veronisa');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Veronisa', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle('POST', '/api/v1/veronisa/claims-save', rawBody: json_encode(['no_rawat' => '2026/11/17/000040', 'nosep' => '0001R0011126V000040', 'catatan' => 'PRB']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(201, $save['status']);
        $id = (int) json_decode($save['body'], true)['data']['id'];

        $statusRes = $this->app->handle('POST', '/api/v1/veronisa/claims-status', rawBody: json_encode(['id' => $id, 'status' => 'Layak', 'catatan' => 'OK']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $statusRes['status']);

        $pdf = $this->app->handle('GET', '/veronisa/pdf', queryParams: ['id' => $id]);
        $this->assertSame(200, $pdf['status']);
        $this->assertStringContainsString('0001R0011126V000040', $pdf['body']);

        $map = $this->app->handle('POST', '/api/v1/veronisa/mapping-save', rawBody: json_encode(['kode_brng' => 'OB01', 'kode_dpho' => 'DPHO-01', 'nama_dpho' => 'Paracetamol', 'harga_dpho' => 250]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $map['status']);
    }

    public function testChatInternalEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/chat');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Live Chat Internal', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $conv = $this->app->handle('POST', '/api/v1/chat/conversations-create', rawBody: json_encode(['title' => 'Kanal IGD']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(201, $conv['status']);
        $cid = (int) json_decode($conv['body'], true)['data']['id'];

        $send = $this->app->handle('POST', '/api/v1/chat/send', rawBody: json_encode(['conversation_id' => $cid, 'message' => 'Pasien zona merah masuk.']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(201, $send['status']);

        $msgs = $this->app->handle('GET', '/api/v1/chat/messages', queryParams: ['conversation_id' => $cid]);
        $this->assertSame(200, $msgs['status']);
        $this->assertCount(1, json_decode($msgs['body'], true)['data']);
    }

    public function testOrthancPacsEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/orthanc');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Orthanc PACS', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle('POST', '/api/v1/orthanc/studies-save', rawBody: json_encode(['no_rawat' => '2026/11/17/000040', 'study_uid' => '1.2.840.999.1', 'modality' => 'CR', 'study_description' => 'Thorax AP']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(201, $save['status']);
        $id = (int) json_decode($save['body'], true)['data']['id'];

        $baca = $this->app->handle('POST', '/api/v1/orthanc/studies-interpretasi', rawBody: json_encode(['id' => $id, 'hasil_baca' => 'Tidak tampak infiltrat.']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $baca['status']);

        $list = $this->app->handle('GET', '/api/v1/orthanc/studies', queryParams: ['q' => 'Thorax']);
        $this->assertSame('Tidak tampak infiltrat.', json_decode($list['body'], true)['data'][0]['hasil_baca']);
    }

    public function testSmartAgentEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/smartagent');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('SmartAgent AI', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle('POST', '/api/v1/smartagent/knowledge-save', rawBody: json_encode(['title' => 'PDT ISPA Akut', 'category' => 'PDT', 'icd10_code' => 'J06.9', 'keywords' => 'batuk pilek ispa', 'content' => 'Terapi simtomatik & edukasi hidrasi.']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(201, $save['status']);

        $rec = $this->app->handle('POST', '/api/v1/smartagent/recommend', rawBody: json_encode(['query' => 'pasien batuk pilek']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $rec['status']);
        $recData = json_decode($rec['body'], true)['data'];
        $this->assertCount(1, $recData['knowledge_matches']);
        $this->assertStringContainsString('J06.9', $recData['summary']);
    }

    public function testIcareViewerEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $this->pdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, alamat, no_peserta)
            VALUES ('000099', 'Pasien iCare', '3201010101900099', 'L', 'Bandung', '1990-01-01', 'Jl. Test', '0001122334455')");
        $noRawat = '2026/11/17/000099';
        $this->pdo->exec("INSERT INTO reg_periksa (no_reg, no_rawat, no_rkm_medis, tgl_registrasi, jam_reg, kd_dokter, kd_poli, kd_pj, status_lanjut, stts)
            VALUES ('099', '{$noRawat}', '000099', '2026-11-17', '08:00:00', 'DR01', 'UMU', 'BPJ', 'Ralan', 'Belum')");

        $dokterPage = $this->app->handle('GET', '/dokter-ralan');
        $this->assertSame(200, $dokterPage['status']);
        $this->assertStringContainsString('btnIcareHistory', $dokterPage['body']);

        $viewer = $this->app->handle('GET', '/icare/riwayat', queryParams: ['no_rawat' => $noRawat]);
        $this->assertSame(200, $viewer['status']);
        $this->assertStringContainsString('0001122334455', $viewer['body']);

        $api = $this->app->handle('GET', '/api/v1/icare/riwayat', queryParams: ['no_rawat' => $noRawat]);
        $this->assertSame(200, $api['status']);
        $this->assertTrue(json_decode($api['body'], true)['ok']);
    }

    public function testMasterBangsalKamarPenyakitEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/master');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Penyakit (ICD-10)', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $bSave = $this->app->handle('POST', '/api/v1/master/bangsal/save', rawBody: json_encode(['kd_bangsal' => 'B99', 'nm_bangsal' => 'Melati']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $bSave['status']);

        $kSave = $this->app->handle('POST', '/api/v1/master/kamar/save', rawBody: json_encode(['kd_kamar' => 'K99', 'kd_bangsal' => 'B99', 'kelas' => 'VIP', 'trf_kamar' => 500000]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $kSave['status']);

        $pSave = $this->app->handle('POST', '/api/v1/master/penyakit/save', rawBody: json_encode(['kd_penyakit' => 'A09', 'nm_penyakit' => 'Diarrhoea', 'nm_indonesia' => 'Diare Akut', 'skdi_level' => '4A']), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $pSave['status']);

        $pList = $this->app->handle('GET', '/api/v1/master/penyakit', queryParams: ['q' => 'A09']);
        $this->assertCount(1, json_decode($pList['body'], true)['data']);
    }

    public function testKepegawaianBerkasDigitalEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/kepegawaian');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Berkas Digital SDM', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $save = $this->app->handle('POST', '/api/v1/kepegawaian/sdm_berkas/save', rawBody: json_encode([
            'nik' => '32010101',
            'jenis_berkas' => 'STR',
            'nama_file' => 'str_dokter.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'tgl_kadaluarsa' => '2029-12-31',
        ]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $save['status']);

        $list = $this->app->handle('GET', '/api/v1/kepegawaian/sdm_berkas', queryParams: ['q' => '32010101']);
        $rows = json_decode($list['body'], true)['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('STR', $rows[0]['jenis_berkas']);

        $del = $this->app->handle('POST', '/api/v1/kepegawaian/sdm_berkas/delete', rawBody: json_encode(['id' => $rows[0]['id']]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $del['status']);
    }

    public function testSuratSehatDanCutiHamilEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/surat');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Surat Sehat', $page['body']);
        $this->assertStringContainsString('Surat Cuti Hamil', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $sehat = $this->app->handle('POST', '/api/v1/surat/create', rawBody: json_encode([
            'type' => 'sehat',
            'no_rawat' => '2026/11/17/000040',
            'berat' => '64',
            'tinggi' => '170',
            'tensi' => '120/80',
            'keperluan' => 'CPNS',
            'kesimpulan' => 'Sehat',
        ]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $sehat['status']);
        $noSehat = json_decode($sehat['body'], true)['data']['no_surat'];

        $cetakSehat = $this->app->handle('GET', '/surat/cetak', queryParams: ['type' => 'sehat', 'no_surat' => $noSehat]);
        $this->assertSame(200, $cetakSehat['status']);
        $this->assertStringContainsString('SURAT KETERANGAN SEHAT', $cetakSehat['body']);

        $cuti = $this->app->handle('POST', '/api/v1/surat/create', rawBody: json_encode([
            'type' => 'cuti_hamil',
            'no_rawat' => '2026/11/17/000040',
            'keterangan_hamil' => 'G1P0A0 36 Minggu',
            'terhitung_mulai' => '2026-11-17',
            'perkiraan_lahir' => '2026-12-17',
        ]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $cuti['status']);
        $noCuti = json_decode($cuti['body'], true)['data']['no_surat'];

        $cetakCuti = $this->app->handle('GET', '/surat/cetak', queryParams: ['type' => 'cuti_hamil', 'no_surat' => $noCuti]);
        $this->assertSame(200, $cetakCuti['status']);
        $this->assertStringContainsString('SURAT KETERANGAN CUTI HAMIL', $cetakCuti['body']);
    }

    public function testRawatJalanParityEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $this->pdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, alamat)
            VALUES ('000088', 'Pasien Ralan', '3201010101900088', 'L', 'Bandung', '1990-01-01', 'Jl. Ralan')");
        $noRawat = '2026/11/17/000088';
        $this->pdo->exec("INSERT INTO reg_periksa (no_reg, no_rawat, no_rkm_medis, tgl_registrasi, jam_reg, kd_dokter, kd_poli, kd_pj, status_lanjut, stts)
            VALUES ('088', '{$noRawat}', '000088', '2026-11-17', '08:00:00', 'DR01', 'UMU', 'UMUM', 'Ralan', 'Belum')");

        $page = $this->app->handle('GET', '/pendaftaran');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('btnRegPrintAntrean', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $chg = $this->app->handle('POST', '/api/v1/registration/change-poli-dokter', rawBody: json_encode([
            'no_rawat' => $noRawat,
            'kd_poli' => 'GIG',
            'kd_dokter' => 'DR02',
        ]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $chg['status']);

        $up = $this->app->handle('POST', '/api/v1/registration/berkas-upload', rawBody: json_encode([
            'no_rawat' => $noRawat,
            'kode' => 'KTP',
            'nama_file' => 'ktp_pasien.pdf',
            'mime_type' => 'application/pdf',
        ]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(201, $up['status']);

        $listBerkas = $this->app->handle('GET', '/api/v1/registration/berkas', queryParams: ['no_rawat' => $noRawat]);
        $this->assertCount(1, json_decode($listBerkas['body'], true)['data']);

        $cetak = $this->app->handle('GET', '/pendaftaran/cetak', queryParams: ['no_rawat' => $noRawat]);
        $this->assertSame(200, $cetak['status']);
        $this->assertStringContainsString('BUKTI ANTREAN RAWAT JALAN', $cetak['body']);
    }

    public function testDokterRalanPrintResumeEresepAndFullHistoryEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $this->pdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, alamat)
            VALUES ('000077', 'Pasien Dokter', '3201010101900077', 'L', 'Bandung', '1992-02-02', 'Jl. Dokter')");
        $noRawat = '2026/11/17/000077';
        $this->pdo->exec("INSERT INTO reg_periksa (no_reg, no_rawat, no_rkm_medis, tgl_registrasi, jam_reg, kd_dokter, kd_poli, kd_pj, status_lanjut, stts)
            VALUES ('077', '{$noRawat}', '000077', '2026-11-17', '08:00:00', 'DR01', 'UMU', 'UMUM', 'Ralan', 'Belum')");
        $this->pdo->exec("INSERT INTO resume_pasien (no_rawat, kd_dokter, keluhan_utama, jalannya_penyakit, pemeriksaan_penunjang, hasil_laborat, kd_diagnosa_utama, diagnosa_utama, kondisi_pulang, obat_pulang)
            VALUES ('{$noRawat}', 'DR01', 'Demam', '3 hari', '-', '-', 'R50', 'Fever', 'Hidup', 'Paracetamol')");

        $resume = $this->app->handle('GET', '/dokter-ralan/resume/cetak', queryParams: ['no_rawat' => $noRawat]);
        $this->assertSame(200, $resume['status']);
        $this->assertStringContainsString('RESUME MEDIS PASIEN', $resume['body']);

        $hist = $this->app->handle('GET', '/api/v1/dokter-ralan/full-history', queryParams: ['no_rkm_medis' => '000077']);
        $this->assertSame(200, $hist['status']);
        $this->assertArrayHasKey('soap', json_decode($hist['body'], true)['data']);
    }

    public function testApotekBatchStockAdjustmentEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $page = $this->app->handle('GET', '/farmasi');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Penyesuaian Stok per Batch', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $adj = $this->app->handle('POST', '/api/v1/farmasi/stok-adjust', rawBody: json_encode([
            'kode_brng' => 'OB001',
            'kd_bangsal' => 'APTK',
            'no_batch' => 'BATCH-2026',
            'stok_baru' => 45,
            'keterangan' => 'Opname Apotek',
        ]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $adj['status']);

        $list = $this->app->handle('GET', '/api/v1/farmasi/stok-batch', queryParams: ['q' => 'BATCH-2026']);
        $this->assertSame(200, $list['status']);
        $rows = json_decode($list['body'], true)['data'];
        $this->assertCount(1, $rows);
        $this->assertSame(45.0, (float) $rows[0]['stok']);
    }

    public function testKasirThermalAndEmailFakturEndToEnd(): void
    {
        $this->session['user_id'] = 1;
        $this->pdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, alamat)
            VALUES ('000066', 'Pasien Kasir', '3201010101900066', 'P', 'Bandung', '1993-03-03', 'Jl. Kasir')");
        $noRawat = '2026/11/17/000066';
        $this->pdo->exec("INSERT INTO reg_periksa (no_reg, no_rawat, no_rkm_medis, tgl_registrasi, jam_reg, kd_dokter, kd_poli, kd_pj, status_lanjut, stts)
            VALUES ('066', '{$noRawat}', '000066', '2026-11-17', '08:00:00', 'DR01', 'UMU', 'UMUM', 'Ralan', 'Belum')");

        $page = $this->app->handle('GET', '/kasir');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('btnPrintThermal', $page['body']);
        $csrf = $this->session['_csrf_token'] ?? '';

        $thermal = $this->app->handle('GET', '/kasir/faktur', queryParams: ['no_rawat' => $noRawat, 'format' => 'thermal']);
        $this->assertSame(200, $thermal['status']);
        $this->assertStringContainsString('KUITANSI THERMAL KASIR', $thermal['body']);

        $email = $this->app->handle('POST', '/api/v1/kasir/email-faktur', rawBody: json_encode([
            'no_rawat' => $noRawat,
            'email' => 'pasien@example.com',
        ]), headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf]);
        $this->assertSame(200, $email['status']);
    }
}
