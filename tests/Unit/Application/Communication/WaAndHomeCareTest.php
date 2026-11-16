<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Communication;

use App\Application\Communication\UseCase\AssignHomeCarePreceptorUseCase;
use App\Application\Communication\UseCase\ProcessInboundWaWebhookUseCase;
use App\Application\Communication\UseCase\SendWhatsAppMessageUseCase;
use App\Domain\Communication\Entity\HomeCarePrivacyShield;
use App\Domain\Communication\Entity\WaSessionManager;
use App\Domain\Communication\Entity\WaSpintax;
use App\Domain\Communication\Repository\HomeCareRepositoryInterface;
use App\Domain\Communication\Repository\WaGatewayRepositoryInterface;
use App\Domain\Communication\Service\WaClientInterface;
use App\Domain\Communication\ValueObject\IndonesianPhoneNumber;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoHomeCareRepository;
use App\Infrastructure\Persistence\PdoWaGatewayRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class WaAndHomeCareTest extends TestCase
{
    private PDO $pdo;
    private WaGatewayRepositoryInterface $waRepo;
    private HomeCareRepositoryInterface $homeCareRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->waRepo = new PdoWaGatewayRepository($this->pdo);
        $this->homeCareRepo = new PdoHomeCareRepository($this->pdo);

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Siti Aminah', '1970-05-10', 'P');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
        ");
    }

    public function testShouldValidateIndonesianMobileRegex(): void
    {
        $this->assertSame('6281234567890', (new IndonesianPhoneNumber('081234567890'))->value());
        $this->assertSame('6281234567890', (new IndonesianPhoneNumber('+6281234567890'))->value());

        $this->expectException(\InvalidArgumentException::class);
        new IndonesianPhoneNumber('0211234567'); // Bukan nomor seluler 628[1-9]
    }

    public function testShouldPreventNonAdminFromSendingWaToNonWhitelistedNumber(): void
    {
        $fakeClient = new class implements WaClientInterface {
            public int $sent = 0;
            public function isOnWhatsApp(string $phone): bool { return true; }
            public function sendMessage(string $session, string $phone, string $message): array {
                $this->sent++;
                return ['status' => 200, 'message_id' => 'wa-123'];
            }
            public function getSessionStatus(string $session): string { return 'ONLINE'; }
        };

        $useCase = new SendWhatsAppMessageUseCase($this->waRepo, $fakeClient);

        $staffUser = new UserAccount(2, 'staff1', 'p', 's@m.l', 'Staff Medis', 'staff', ['pendaftaran'], ['all']);
        $adminUser = new UserAccount(1, 'admin', 'p', 'a@m.l', 'Super Admin', 'admin', ['all'], ['all']);

        // Nomor belum ada di whitelist
        $targetPhone = new IndonesianPhoneNumber('081987654321');

        try {
            $useCase->execute($staffUser, $targetPhone, 'Halo Pasien');
            $this->fail('Non-admin seharusnya dilarang mengirim WA ke nomor non-whitelist.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Whitelist', $e->getMessage());
        }

        // Admin bebas kirim ke nomor manapun
        $resAdmin = $useCase->execute($adminUser, $targetPhone, 'Halo Pasien dari Admin');
        $this->assertSame('Sent', $resAdmin['status']);
        $this->assertSame(1, $fakeClient->sent);

        // Setelah dimasukkan whitelist, staff kini boleh kirim
        $this->waRepo->addToWhitelist('6281987654321', 'Pasien Whitelist', 'Pasien VIP');
        $resStaff = $useCase->execute($staffUser, $targetPhone, 'Halo dari Staff');
        $this->assertSame('Sent', $resStaff['status']);
        $this->assertSame(2, $fakeClient->sent);
    }

    public function testShouldNeverSendOutboundReplyOnQrCheckinVerification(): void
    {
        $fakeClient = new class implements WaClientInterface {
            public int $sent = 0;
            public function isOnWhatsApp(string $phone): bool { return true; }
            public function sendMessage(string $session, string $phone, string $message): array {
                $this->sent++;
                return ['status' => 200, 'message_id' => 'wa-123'];
            }
            public function getSessionStatus(string $session): string { return 'ONLINE'; }
        };

        $inboundUseCase = new ProcessInboundWaWebhookUseCase($this->waRepo, $fakeClient);

        // Pasien scan QR pendaftaran: sistem catat verifikasi, tapi ZERO outbound reply (0 kirim WA keluar)
        $result = $inboundUseCase->execute(
            sessionName: 'system',
            senderPhone: '6281234567890',
            messageText: 'CHECKIN 2026/11/16/000001',
        );

        $this->assertTrue($result['verified']);
        $this->assertSame(0, $result['outbound_sent_count']);
        $this->assertSame(0, $fakeClient->sent); // Memastikan 0 outbound response
    }

    public function testShouldGenerateConversationalDynamicSpintaxForQrLink(): void
    {
        $template = '{Halo|Selamat pagi|Salam} {Admin|Klinik}, saya konfirmasi antrean no {001|002}';
        $expanded = WaSpintax::parse($template);

        $this->assertNotEmpty($expanded);
        $this->assertStringNotContainsString('{', $expanded);
        $this->assertStringNotContainsString('}', $expanded);
    }

    public function testShouldAbortBulkSendingImmediatelyOnCircuitBreakerTrigger(): void
    {
        $failingClient = new class implements WaClientInterface {
            public int $attempts = 0;
            public function isOnWhatsApp(string $phone): bool { return true; }
            public function sendMessage(string $session, string $phone, string $message): array {
                $this->attempts++;
                return ['status' => 500, 'error' => 'Temporary gateway error'];
            }
            public function getSessionStatus(string $session): string { return 'ONLINE'; }
        };

        $useCase = new SendWhatsAppMessageUseCase($this->waRepo, $failingClient);
        $admin = new UserAccount(1, 'admin', 'p', 'a@m.l', 'Admin', 'admin', ['all'], ['all']);

        $recipients = [
            new IndonesianPhoneNumber('081111111111'),
            new IndonesianPhoneNumber('082222222222'),
            new IndonesianPhoneNumber('083333333333'),
            new IndonesianPhoneNumber('084444444444'),
            new IndonesianPhoneNumber('085555555555'),
        ];
        foreach ($recipients as $idx => $r) {
            $this->waRepo->addToWhitelist($r->value(), 'Pasien ' . $idx, 'Pasien');
        }

        $summary = $useCase->sendBulk($admin, $recipients, 'Pesan Broadcast');

        // Gagal 3x berturut-turut langsung trip circuit breaker, sisa 2 penerima tidak dieksekusi
        $this->assertSame(3, $failingClient->attempts);
        $this->assertTrue($summary['circuit_breaker_tripped']);
        $this->assertSame(2, $summary['aborted_count']);
    }

    public function testShouldSkipQrGateGracefullyWhenAllSessionsAreOffline(): void
    {
        $offlineClient = new class implements WaClientInterface {
            public function isOnWhatsApp(string $phone): bool { return false; }
            public function sendMessage(string $session, string $phone, string $message): array { return ['status' => 500]; }
            public function getSessionStatus(string $session): string { return 'OFFLINE'; }
        };

        $sessionMgr = new WaSessionManager($offlineClient);
        $policy = $sessionMgr->resolveQrGatePolicy();

        $this->assertTrue($policy['all_offline']);
        $this->assertTrue($policy['skip_qr']); // Loket pendaftaran tetap jalan lancar tanpa macet
    }

    public function testShouldIsolateBotAiResponsesExclusivelyToCsSession(): void
    {
        $sessionMgr = new WaSessionManager();

        $this->assertFalse($sessionMgr->isBotAllowedForSession('system'));
        $this->assertTrue($sessionMgr->isBotAllowedForSession('cs'));
    }

    public function testShouldPseudonymizePatientIdentityForAcademicSupervisorView(): void
    {
        $pseudonym = HomeCarePrivacyShield::pseudonymize('Siti Aminah', 56, 'P');
        $this->assertSame('Ny. S*** (56 Th, P)', $pseudonym);

        $malePseudonym = HomeCarePrivacyShield::pseudonymize('Budi Santoso', 12, 'L');
        $this->assertSame('An. B*** (12 Th, L)', $malePseudonym);
    }

    public function testShouldRestrictPreceptorSelectionToInstitutionSkHolders(): void
    {
        $this->pdo->exec("
            INSERT INTO mlite_homecare_preceptor_sk (nip, nama_nakes, institusi, no_sk, status)
            VALUES ('NIP001', 'Ns. Rina, S.Kep', 'STIKES Harapan', 'SK/2026/001', 'Aktif');
        ");
        $this->homeCareRepo->createKunjungan([
            'no_rawat' => '2026/11/16/000001',
            'tgl_kunjungan' => date('Y-m-d'),
            'jam_kunjungan' => '09:00:00',
            'kd_dokter_dpjp' => 'DR001',
        ]);

        $useCase = new AssignHomeCarePreceptorUseCase($this->homeCareRepo);

        // Preceptor valid dengan SK aktif
        $assignment = $useCase->execute(
            noRawat: new NoRawat('2026/11/16/000001'),
            preceptorNip: 'NIP001',
            institusi: 'STIKES Harapan',
            mahasiswaNim: 'MHS202601',
            supervisorId: 'DOSEN01',
        );
        $this->assertSame('NIP001', $assignment['preceptor_nip']);

        // Preceptor tanpa SK di institusi bersangkutan -> ditolak
        $this->expectException(\DomainException::class);
        $useCase->execute(
            noRawat: new NoRawat('2026/11/16/000001'),
            preceptorNip: 'NIP999',
            institusi: 'STIKES Harapan',
            mahasiswaNim: 'MHS202602',
            supervisorId: 'DOSEN01',
        );
    }

    public function testShouldPreventDpjpFromBeingAssignedEducationalGradingTasks(): void
    {
        $useCase = new AssignHomeCarePreceptorUseCase($this->homeCareRepo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DPJP');
        // Dokter DPJP dilarang merangkap tugas penilaian/preceptor mahasiswa di alur yang sama
        $useCase->assertDpjpNotAssignedPreceptorTask(
            dpjpDoctorId: 'DR001',
            assignedPreceptorNip: 'DR001',
        );
    }
}
