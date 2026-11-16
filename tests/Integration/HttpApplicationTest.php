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
}
