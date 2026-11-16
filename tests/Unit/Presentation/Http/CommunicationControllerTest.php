<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Communication\UseCase\AssignHomeCarePreceptorUseCase;
use App\Application\Communication\UseCase\ProcessInboundWaWebhookUseCase;
use App\Application\Communication\UseCase\SendWhatsAppMessageUseCase;
use App\Domain\Communication\Service\WaClientInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoHomeCareRepository;
use App\Infrastructure\Persistence\PdoWaGatewayRepository;
use App\Presentation\Http\CommunicationController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class CommunicationControllerTest extends TestCase
{
    private PDO $pdo;
    private CommunicationController $controller;
    private PdoWaGatewayRepository $waRepo;
    private PdoHomeCareRepository $homeCareRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Siti Aminah', '1970-05-10', 'P');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO mlite_homecare_preceptor_sk (nip, nama_nakes, institusi, no_sk, status)
            VALUES ('NIP001', 'Ns. Rina, S.Kep', 'STIKES Harapan', 'SK/2026/001', 'Aktif');
        ");

        $this->waRepo = new PdoWaGatewayRepository($this->pdo);
        $this->homeCareRepo = new PdoHomeCareRepository($this->pdo);

        $fakeClient = new class implements WaClientInterface {
            public int $sent = 0;
            public function sendMessage(string $session, string $phone, string $message): array {
                $this->sent++;
                return ['status' => 200, 'message_id' => 'wa-mock-1'];
            }
            public function getSessionStatus(string $session): string { return 'ONLINE'; }
        };

        $renderer = new ViewRenderer(dirname(__DIR__, 4) . '/src/Presentation/View/templates');

        $this->controller = new CommunicationController(
            $this->waRepo,
            $this->homeCareRepo,
            new SendWhatsAppMessageUseCase($this->waRepo, $fakeClient),
            new ProcessInboundWaWebhookUseCase($this->waRepo, $fakeClient),
            new AssignHomeCarePreceptorUseCase($this->homeCareRepo),
            $renderer,
        );
    }

    public function testIndexRendersWaGatewayAndHomeCarePages(): void
    {
        $waRes = $this->controller->indexWa(null, 'csrf-token');
        $this->assertSame(200, $waRes['status']);
        $this->assertStringContainsString('WhatsApp Gateway', $waRes['body']);
        $this->assertStringContainsString('Anti-Banned Meta', $waRes['body']);

        $hcRes = $this->controller->indexHomeCare(null, 'csrf-token');
        $this->assertSame(200, $hcRes['status']);
        $this->assertStringContainsString('Home Care', $hcRes['body']);
        $this->assertStringContainsString('Trias Preceptorship', $hcRes['body']);
    }

    public function testSendWaAndInboundWebhookEndpoints(): void
    {
        $admin = new UserAccount(1, 'admin', 'p', 'a@m.l', 'Admin', 'admin', ['all'], ['all']);

        $sendRes = $this->controller->apiSendWa($admin, [
            'phone' => '081234567890',
            'message' => 'Tes Notifikasi Hasil Lab',
            'session' => 'system',
        ]);
        $this->assertSame(200, $sendRes['status']);
        $sendData = json_decode($sendRes['body'], true);
        $this->assertSame('Sent', $sendData['result']['status']);

        // Inbound Webhook QR checkin (zero outbound reply)
        $hookRes = $this->controller->apiInboundWebhook([
            'session' => 'system',
            'phone' => '6281234567890',
            'message' => 'CHECKIN 2026/11/16/000001',
        ]);
        $this->assertSame(200, $hookRes['status']);
        $hookData = json_decode($hookRes['body'], true);
        $this->assertTrue($hookData['result']['verified']);
        $this->assertSame(0, $hookData['result']['outbound_sent_count']);
    }

    public function testHomeCareLifecycleAndAcademicSupervisorPrivacyShield(): void
    {
        // 1. Buat kunjungan home care baru
        $createRes = $this->controller->apiCreateHomeCare([
            'no_rawat' => '2026/11/16/000001',
            'tgl_kunjungan' => date('Y-m-d'),
            'jam_kunjungan' => '09:00:00',
            'kd_dokter_dpjp' => 'DR001',
            'diagnosis' => 'Perawatan Luka DM Post Operasi',
            'payment_gateway' => 'Kasera',
        ]);
        $this->assertSame(201, $createRes['status']);

        // 2. Tugaskan preceptor ber-SK
        $assignRes = $this->controller->apiAssignPreceptor([
            'no_rawat' => '2026/11/16/000001',
            'preceptor_nip' => 'NIP001',
            'institusi' => 'STIKES Harapan',
            'mahasiswa_nim' => 'MHS202601',
            'supervisor_id' => 'DOSEN01',
        ]);
        $this->assertSame(200, $assignRes['status']);

        // 3. Akses tampilan Supervisor Akademik Kampus (Patient Privacy Shield aktif)
        $supervisorViewRes = $this->controller->apiSupervisorVisits([
            'from' => date('Y-m-d'),
            'to' => date('Y-m-d'),
        ]);
        $this->assertSame(200, $supervisorViewRes['status']);
        $viewData = json_decode($supervisorViewRes['body'], true);
        $this->assertCount(1, $viewData['visits']);

        // Nama asli "Siti Aminah" wajib disamarkan menjadi "Ny. S*** (56 Th, P)"
        $firstVisit = $viewData['visits'][0];
        $this->assertSame('Ny. S*** (56 Th, P)', $firstVisit['pseudonym_pasien']);
        $this->assertArrayNotHasKey('nm_pasien', $firstVisit);
    }
}
