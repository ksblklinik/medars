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
}
