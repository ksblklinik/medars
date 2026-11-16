<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Billing\UseCase\ProcessSplitPaymentUseCase;
use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\CreateRadiologyOrderUseCase;
use App\Application\Support\UseCase\DeleteLabOrderUseCase;
use App\Application\Support\UseCase\SaveLabResultUseCase;
use App\Application\Support\UseCase\SaveRadiologyResultUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoLabRadiologyRepository;
use App\Infrastructure\Persistence\PdoOperationalReportRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Presentation\Http\SupportController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class SupportControllerTest extends TestCase
{
    private PDO $pdo;
    private SupportController $controller;
    private PdoBillingLedgerRepository $ledgerRepo;
    private PdoRegPeriksaRepository $regRepo;
    private PdoTransactionManager $tx;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->tx = new PdoTransactionManager($this->pdo);
        $labRepo = new PdoLabRadiologyRepository($this->pdo);
        $reportRepo = new PdoOperationalReportRepository($this->pdo);
        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);

        $renderer = new ViewRenderer(dirname(__DIR__, 4) . '/src/Presentation/View/templates');

        $this->controller = new SupportController(
            $labRepo,
            $reportRepo,
            new CreateLabOrderUseCase($labRepo, $this->ledgerRepo, $this->tx),
            new SaveLabResultUseCase($labRepo, $this->ledgerRepo, $this->tx),
            new DeleteLabOrderUseCase($labRepo, $this->tx),
            new CreateRadiologyOrderUseCase($labRepo, $this->ledgerRepo, $this->tx),
            new SaveRadiologyResultUseCase($labRepo, $this->tx),
            $renderer,
        );

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia) VALUES ('J06.9', 'Acute URI', 'ISPA Akut');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/16/000001', 'J06.9', 'Ralan', 1);
        ");
    }

    public function testIndexRendersSupportPage(): void
    {
        $res = $this->controller->index(null, 'csrf-token-123');
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('Laboratorium', $res['body']);
        $this->assertStringContainsString('Radiologi', $res['body']);
        $this->assertStringContainsString('Laporan Operasional', $res['body']);
    }

    public function testLabOrderLifecycleWithCashierLockAndDelete(): void
    {
        $createRes = $this->controller->createLabOrder([
            'no_rawat' => '2026/11/16/000001',
            'dokter_perujuk' => 'DR001',
            'items' => [
                ['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'Hemoglobin', 'harga' => 40000, 'claim_type' => 'Tunai'],
                ['kd_jenis_prw' => 'LAB02', 'nm_perawatan' => 'GDS', 'harga' => 25000, 'claim_type' => 'Kapitasi'],
            ],
        ]);
        $this->assertSame(201, $createRes['status']);
        $created = json_decode($createRes['body'], true);
        $this->assertTrue($created['ok']);
        $this->assertCount(2, $created['orders']);

        $tunaiOrder = $created['orders'][0]['jenis_klaim'] === 'Tunai' ? $created['orders'][0]['no_order'] : $created['orders'][1]['no_order'];
        $kapitasiOrder = $created['orders'][0]['jenis_klaim'] === 'Kapitasi' ? $created['orders'][0]['no_order'] : $created['orders'][1]['no_order'];

        // Tunai terkunci sebelum Kasir lunas
        $lockedRes = $this->controller->saveLabResult([
            'no_order' => $tunaiOrder,
            'results' => [['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.0', 'nilai_rujukan' => '13.0 - 17.0']],
        ]);
        $this->assertSame(422, $lockedRes['status']);

        // Lunasi di Kasir
        (new ProcessSplitPaymentUseCase($this->ledgerRepo, $this->regRepo, $this->tx))
            ->execute(new NoRawat('2026/11/16/000001'));

        $unlockedRes = $this->controller->saveLabResult([
            'no_order' => $tunaiOrder,
            'results' => [['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.0', 'nilai_rujukan' => '13.0 - 17.0']],
        ]);
        $this->assertSame(200, $unlockedRes['status']);
        $saved = json_decode($unlockedRes['body'], true);
        $this->assertSame('Rendah', $saved['results'][0]['keterangan']);

        // Hapus order Kapitasi yang belum ada hasilnya
        $delRes = $this->controller->deleteLabOrder(['no_order' => $kapitasiOrder]);
        $this->assertSame(200, $delRes['status']);
    }

    public function testRadiologyAndOperationalReportsEndpoints(): void
    {
        $radRes = $this->controller->createRadiologyOrder([
            'no_rawat' => '2026/11/16/000001',
            'dokter_perujuk' => 'DR001',
            'items' => [
                ['kd_jenis_prw' => 'RAD01', 'nm_perawatan' => 'Thorax PA', 'harga' => 150000, 'claim_type' => 'Tunai'],
            ],
        ]);
        $this->assertSame(201, $radRes['status']);
        $radData = json_decode($radRes['body'], true);
        $noOrder = $radData['orders'][0]['no_order'];

        $expRes = $this->controller->saveRadiologyResult([
            'no_order' => $noOrder,
            'hasil' => 'Cor dan pulmo dalam batas normal.',
        ]);
        $this->assertSame(200, $expRes['status']);

        $today = date('Y-m-d');
        $repRes = $this->controller->operationalReports(['from' => $today, 'to' => $today]);
        $this->assertSame(200, $repRes['status']);
        $rep = json_decode($repRes['body'], true);
        $this->assertTrue($rep['ok']);
        $this->assertCount(1, $rep['visits_by_poli']);
        $this->assertCount(1, $rep['top_diseases']);
        $this->assertNotEmpty($rep['revenue_by_claim']);
    }
}
