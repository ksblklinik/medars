<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Bridging\UseCase\ProcessBridgingQueueUseCase;
use App\Application\Bridging\UseCase\QueueSatuSehatSyncUseCase;
use App\Application\Bridging\UseCase\VerifyVedikaClaimUseCase;
use App\Domain\Bridging\Service\BpjsGatewayService;
use App\Domain\Bridging\Service\HfisLeanCalculator;
use App\Domain\Bridging\Service\HttpClientInterface;
use App\Domain\Bridging\ValueObject\BridgingCredentials;
use App\Domain\Bridging\ValueObject\TimeoutPolicy;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBridgingQueueRepository;
use App\Presentation\Http\BridgingController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class BridgingControllerTest extends TestCase
{
    private PDO $pdo;
    private BridgingController $controller;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi, tinggi, berat, spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi, lingkar_perut, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/16/000001', '{$today}', '08:15:00', '36.5', '120/80', '80', '20', '170', '65', '98', '15', 'Compos Mentis', 'Batuk', 'Ronkhi (-)', '-', '-', 'Obat', 'ISPA', '-', '-', 'DR001');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia) VALUES ('J06.9', 'Acute URI', 'ISPA Akut');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/16/000001', 'J06.9', 'Ralan', 1);
        ");

        $queueRepo = new PdoBridgingQueueRepository($this->pdo);
        $dummyCreds = new BridgingCredentials('-', '-', '-', '-');
        $client = new class implements HttpClientInterface {
            public function request(string $method, string $url, array $headers, ?string $body, TimeoutPolicy $timeout): array
            {
                return ['status' => 503, 'body' => '{}'];
            }
        };

        $renderer = new ViewRenderer(dirname(__DIR__, 4) . '/src/Presentation/View/templates');

        $this->controller = new BridgingController(
            $queueRepo,
            new BpjsGatewayService($dummyCreds, $client, new TimeoutPolicy(2, 4)),
            new HfisLeanCalculator(),
            new QueueSatuSehatSyncUseCase($queueRepo),
            new ProcessBridgingQueueUseCase($queueRepo, $client, $dummyCreds, new TimeoutPolicy(2, 4)),
            new VerifyVedikaClaimUseCase($this->pdo),
            $renderer,
        );
    }

    public function testIndexRendersBridgingAndLeanDashboard(): void
    {
        $res = $this->controller->index(null, 'csrf-123');
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('Bridging BPJS', $res['body']);
        $this->assertStringContainsString('SatuSehat FHIR', $res['body']);
        $this->assertStringContainsString('HFIS LEAN', $res['body']);
    }

    public function testStatusAndLeanAndVedikaEndpoints(): void
    {
        $statusRes = $this->controller->status();
        $this->assertSame(200, $statusRes['status']);
        $statusData = json_decode($statusRes['body'], true);
        $this->assertTrue($statusData['bpjs_circuit']['circuit_open']);

        $leanRes = $this->controller->leanEstimate([
            'shift' => 'Sore',
            'kd_poli' => 'GIG01',
            'nm_poli' => 'Poli Gigi',
            'no_reg' => 2,
            'duration_minutes' => 15,
            'date' => '2026-11-16',
        ]);
        $this->assertSame(200, $leanRes['status']);
        $leanData = json_decode($leanRes['body'], true);
        $this->assertSame('15:00:00', $leanData['estimate']['doctor_start_time']);
        $this->assertSame('15:30:00', $leanData['estimate']['estimated_service_time']);

        $queueRes = $this->controller->queueSatuSehat([
            'no_rawat' => '2026/11/16/000001',
            'patient_ihs_id' => 'P001',
            'practitioner_ihs_id' => 'N001',
            'icd10_code' => 'J06.9',
            'icd10_display' => 'ISPA Akut',
        ]);
        $this->assertSame(201, $queueRes['status']);

        $vedikaRes = $this->controller->verifyVedika([
            'no_rawat' => '2026/11/16/000001',
            'no_sep' => '0123R0011126V000001',
        ], 'admin');
        $this->assertSame(200, $vedikaRes['status']);
        $vedikaData = json_decode($vedikaRes['body'], true);
        $this->assertSame('Layak', $vedikaData['verification']['status']);
    }
}
