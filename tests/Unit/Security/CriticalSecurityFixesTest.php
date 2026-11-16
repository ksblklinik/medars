<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Application\Communication\UseCase\ProcessInboundWaWebhookUseCase;
use App\Application\Communication\UseCase\SendWhatsAppMessageUseCase;
use App\Domain\Audit\Service\LogRotationService;
use App\Domain\Communication\Service\WaClientInterface;
use App\Domain\Communication\ValueObject\IndonesianPhoneNumber;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Communication\CurlWaClient;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoWaGatewayRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for critical review findings K2, K4, K5.
 */
class CriticalSecurityFixesTest extends TestCase
{
    private PDO $pdo;
    private PdoWaGatewayRepository $waRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->waRepo = new PdoWaGatewayRepository($this->pdo);

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

    // ---- K5: WA client tidak boleh palsu-sukses ----

    public function testUnconfiguredWaClientReportsFailureAndOfflineInsteadOfFakeSuccess(): void
    {
        $client = new CurlWaClient('', '');

        $res = $client->sendMessage('system', '6281234567890', 'Tes');
        $this->assertGreaterThanOrEqual(300, $res['status'], 'Gateway belum dikonfigurasi tidak boleh dilaporkan sukses.');
        $this->assertNotEmpty($res['error'] ?? '');

        $this->assertSame('OFFLINE', $client->getSessionStatus('system'));
        $this->assertSame('OFFLINE', $client->getSessionStatus('cs'));
    }

    public function testSendIsAbortedAndRecordedFailedWhenAllSessionsOffline(): void
    {
        $offline = new class implements WaClientInterface {
            public int $sent = 0;
            public function sendMessage(string $session, string $phone, string $message): array
            {
                $this->sent++;
                return ['status' => 200];
            }
            public function getSessionStatus(string $session): string { return 'OFFLINE'; }
        };

        $admin = new UserAccount(1, 'admin', 'p', 'a@m.l', 'Admin', 'admin', ['all'], ['all']);
        $res = (new SendWhatsAppMessageUseCase($this->waRepo, $offline))
            ->execute($admin, new IndonesianPhoneNumber('081234567890'), 'Halo');

        $this->assertSame('Failed', $res['status']);
        $this->assertSame(0, $offline->sent, 'Tidak boleh menembak HTTP saat kedua sesi offline.');
        $this->assertSame('Failed', $this->waRepo->listRecentOutbox(1)[0]['status']);
    }

    // ---- K2: Webhook check-in tidak boleh memutasi data klinis & wajib validasi no_rawat ----

    public function testQrCheckinRecordsVerificationWithoutMutatingRegistrationAndRejectsUnknownNoRawat(): void
    {
        $useCase = new ProcessInboundWaWebhookUseCase($this->waRepo);

        $statusBefore = $this->pdo->query("SELECT status_lanjut FROM reg_periksa WHERE no_rawat = '2026/11/16/000001'")->fetchColumn();

        $ok = $useCase->execute('system', '6281234567890', 'CHECKIN 2026/11/16/000001');
        $this->assertTrue($ok['verified']);
        $this->assertSame(0, $ok['outbound_sent_count']);

        $statusAfter = $this->pdo->query("SELECT status_lanjut FROM reg_periksa WHERE no_rawat = '2026/11/16/000001'")->fetchColumn();
        $this->assertSame($statusBefore, $statusAfter, 'Webhook tidak boleh mengubah status_lanjut reg_periksa.');

        $checkins = (int) $this->pdo->query("SELECT COUNT(*) FROM wagateway_checkin WHERE no_rawat = '2026/11/16/000001'")->fetchColumn();
        $this->assertSame(1, $checkins);

        $unknown = $useCase->execute('system', '6281234567890', 'CHECKIN 2099/01/01/999999');
        $this->assertFalse($unknown['verified']);
        $this->assertSame(0, $unknown['outbound_sent_count']);
    }

    // ---- K4: Rotasi log tidak boleh menyentuh audit trail ----

    public function testLogRotationNeverDeletesAuditTrailAndEnforcesMinimumRetention(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-200 days'));
        $this->pdo->exec("
            INSERT INTO mlite_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (1, 'admin', 'pay', 'Billing paid', '127.0.0.1', '{$old}');
            INSERT INTO wagateway_outbox (session_name, no_hp, pesan, status, error_message, created_at)
            VALUES ('system', '6281234567890', 'lama', 'Sent', NULL, '{$old}');
        ");

        $service = new LogRotationService($this->pdo);

        try {
            $service->pruneLogs(30);
            $this->fail('Retensi di bawah minimum wajib ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString((string) LogRotationService::MIN_RETENTION_DAYS, $e->getMessage());
        }

        $pruned = $service->pruneLogs(LogRotationService::MIN_RETENTION_DAYS);
        $this->assertSame(1, $pruned);

        $auditCount = (int) $this->pdo->query('SELECT COUNT(*) FROM mlite_logs')->fetchColumn();
        $this->assertSame(1, $auditCount, 'Audit trail mlite_logs wajib permanen (PRD 11).');
    }
}
