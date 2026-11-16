<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Application\Rme\UseCase\DeleteOdontogramUseCase;
use App\Application\Rme\UseCase\SaveOdontogramUseCase;
use App\Application\Rme\UseCase\SavePrbPrescriptionUseCase;
use App\Application\Rme\UseCase\SaveSoapUseCase;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoIcdGroundingRepository;
use App\Infrastructure\Persistence\PdoOdontogramRepository;
use App\Infrastructure\Persistence\PdoPemeriksaanRalanRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Infrastructure\Persistence\PdoRmeHistoryRepository;
use App\Infrastructure\Persistence\PdoSuratMedisRepository;
use App\Presentation\Http\DokterRalanController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class DokterRalanControllerTest extends TestCase
{
    private PDO $pdo;
    private DokterRalanController $controller;
    private UserAccount $dokter;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Siti Rahma', '1992-05-12', 'P');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poliklinik Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Jaga Umum');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('UMU', 'Umum');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'UMU', 'Belum');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia, status, skdi_level)
            VALUES ('J06.9', 'Acute URI', 'ISPA Akut', 'Tidak Menular', '4A');
        ");

        $tx = new PdoTransactionManager($this->pdo);
        $ralanRepo = new PdoPemeriksaanRalanRepository($this->pdo);
        $regRepo = new PdoRegPeriksaRepository($this->pdo);
        $historyRepo = new PdoRmeHistoryRepository($this->pdo);
        $icdRepo = new PdoIcdGroundingRepository($this->pdo);
        $odoRepo = new PdoOdontogramRepository($this->pdo);
        $suratRepo = new PdoSuratMedisRepository($this->pdo);

        $this->controller = new DokterRalanController(
            pdo: $this->pdo,
            historyRepo: $historyRepo,
            icdRepo: $icdRepo,
            odoRepo: $odoRepo,
            saveSoapUseCase: new SaveSoapUseCase($ralanRepo, $regRepo, $tx),
            savePrbUseCase: new SavePrbPrescriptionUseCase($ralanRepo, $regRepo, $tx),
            saveOdoUseCase: new SaveOdontogramUseCase($odoRepo),
            deleteOdoUseCase: new DeleteOdontogramUseCase($odoRepo),
            suratUseCase: new CreateSuratMedisUseCase($suratRepo, $tx),
            renderer: new ViewRenderer()
        );

        $this->dokter = new UserAccount(
            id: 10,
            username: 'DR001',
            passwordHash: 'hash',
            email: 'dr001@medars.local',
            fullname: 'dr. Jaga Umum',
            role: 'dokter',
            access: ['dokter_ralan'],
            cap: ['UMU01'],
            status: '1'
        );
    }

    public function testIndexRendersDoctorRmeWorkspace(): void
    {
        $res = $this->controller->index($this->dokter, 'test-csrf');
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('Rekam Medis Dokter Rawat Jalan', $res['body']);
        $this->assertStringContainsString('Smart Peek', $res['body']);
    }

    public function testApiSaveSoapAndSearchIcd10AndHistory(): void
    {
        // 1. Search ICD-10
        $icdRes = $this->controller->apiSearchIcd10(['q' => 'ISPA']);
        $this->assertSame(200, $icdRes['status']);
        $icds = json_decode($icdRes['body'], true);
        $this->assertCount(1, $icds);
        $this->assertSame('J06.9', $icds[0]['kd_penyakit']);

        // 2. Save SOAP
        $saveRes = $this->controller->apiSaveSoap($this->dokter, [
            'no_rawat' => '2026/11/16/000001',
            'keluhan' => 'Batuk pilek 2 hari',
            'pemeriksaan' => 'Faring hiperemis',
            'penilaian' => 'ISPA Akut',
            'rtl' => 'Paracetamol 3x500mg',
        ]);
        $this->assertSame(200, $saveRes['status']);
        $decodedSave = json_decode($saveRes['body'], true);
        $this->assertSame('success', $decodedSave['status']);

        // 3. History Smart Peek
        $histRes = $this->controller->apiHistory(['no_rkm_medis' => '000001']);
        $this->assertSame(200, $histRes['status']);
        $hist = json_decode($histRes['body'], true);
        $this->assertCount(1, $hist);
        $this->assertSame('2026/11/16/000001', $hist[0]['no_rawat']);
    }

    public function testApiSaveSoapRejectsUnwhitelistedFieldWith422(): void
    {
        $res = $this->controller->apiSaveSoap($this->dokter, [
            'no_rawat' => '2026/11/16/000001',
            'evil_column' => 'drop table',
        ]);

        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('Field tidak diizinkan', $res['body']);
    }
}
