<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Domain\Audit\Service\DataIntegrityAuditService;
use App\Domain\Audit\Service\LogRotationService;
use App\Infrastructure\Database\SchemaMigrator;
use App\Presentation\Http\AuditController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class AuditControllerTest extends TestCase
{
    private PDO $pdo;
    private AuditController $controller;

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
        ");

        $renderer = new ViewRenderer(dirname(__DIR__, 4) . '/src/Presentation/View/templates');

        $this->controller = new AuditController(
            new DataIntegrityAuditService($this->pdo),
            new LogRotationService($this->pdo),
            $renderer,
        );
    }

    public function testIndexRendersAuditDashboard(): void
    {
        $res = $this->controller->index(null, 'csrf-123');
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('Audit Keamanan &amp; Integritas Data', $res['body']);
        $this->assertStringContainsString('Orphan Records', $res['body']);
    }

    public function testApiOrphanAndRotateLogsEndpoints(): void
    {
        $orphanRes = $this->controller->apiOrphanCheck();
        $this->assertSame(200, $orphanRes['status']);
        $orphanData = json_decode($orphanRes['body'], true);
        $this->assertTrue($orphanData['ok']);
        $this->assertTrue($orphanData['audit']['healthy']);

        $rotateRes = $this->controller->apiRotateLogs(['retention_days' => 30]);
        $this->assertSame(200, $rotateRes['status']);
        $rotateData = json_decode($rotateRes['body'], true);
        $this->assertTrue($rotateData['ok']);
        $this->assertArrayHasKey('pruned_count', $rotateData);
    }
}
