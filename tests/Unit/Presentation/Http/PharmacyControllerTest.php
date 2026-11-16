<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Pharmacy\UseCase\CreateResepUseCase;
use App\Application\Pharmacy\UseCase\DeleteResepUseCase;
use App\Application\Pharmacy\UseCase\DispenseResepUseCase;
use App\Application\Pharmacy\UseCase\SaveCopyResepUseCase;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoDataBarangRepository;
use App\Infrastructure\Persistence\PdoGudangBarangRepository;
use App\Infrastructure\Persistence\PdoResepRepository;
use App\Presentation\Http\PharmacyController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class PharmacyControllerTest extends TestCase
{
    private PDO $pdo;
    private PharmacyController $controller;
    private UserAccount $apoteker;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Ahmad', '1988-01-01', 'L');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Jaga');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Sudah');

            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, harga_kapitasi, harga_nonkapitasi, jualbebas, status)
            VALUES ('OBT001', 'Vitamin B6 10mg', 'TAB', 100, 120, 500, 300, 400, 600, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'FAR01', 'B01', 'F01', 30);
        ");

        $tx = new PdoTransactionManager($this->pdo);
        $barangRepo = new PdoDataBarangRepository($this->pdo);
        $gudangRepo = new PdoGudangBarangRepository($this->pdo);
        $resepRepo = new PdoResepRepository($this->pdo);

        $this->controller = new PharmacyController(
            pdo: $this->pdo,
            barangRepo: $barangRepo,
            gudangRepo: $gudangRepo,
            createResepUseCase: new CreateResepUseCase($resepRepo, $tx),
            dispenseResepUseCase: new DispenseResepUseCase($resepRepo, $barangRepo, $gudangRepo, $tx),
            deleteResepUseCase: new DeleteResepUseCase($resepRepo, $tx),
            copyResepUseCase: new SaveCopyResepUseCase($resepRepo, $tx),
            renderer: new ViewRenderer()
        );

        $this->apoteker = new UserAccount(
            id: 20,
            username: 'APOTEK01',
            passwordHash: 'hash',
            email: 'apotek@medars.local',
            fullname: 'Apt. Rina, S.Farm',
            role: 'farmasi',
            access: ['farmasi'],
            cap: ['all'],
            status: '1'
        );
    }

    public function testIndexAndSearchShortNameAndFullResepLifecycle(): void
    {
        $indexRes = $this->controller->index($this->apoteker, 'csrf-tok');
        $this->assertSame(200, $indexRes['status']);
        $this->assertStringContainsString('Farmasi &amp; Validasi E-Resep', $indexRes['body']);

        // Search obat pendek 'B6' dengan flag BPJS
        $searchRes = $this->controller->apiSearchObat(['q' => 'B6', 'is_bpjs' => '1']);
        $this->assertSame(200, $searchRes['status']);
        $items = json_decode($searchRes['body'], true);
        $this->assertCount(1, $items);
        $this->assertSame('Kapitasi', $items[0]['claim_options'][0]['claim_type']);

        // Create Resep
        $createRes = $this->controller->apiCreateResep($this->apoteker, [
            'no_rawat' => '2026/11/16/000001',
            'kd_dokter' => 'DR001',
            'non_racikan' => [
                ['kode_brng' => 'OBT001', 'jml' => 10, 'aturan_pakai' => '3x1'],
            ],
        ]);
        $this->assertSame(200, $createRes['status']);
        $created = json_decode($createRes['body'], true);
        $noResep = $created['data']['no_resep'];

        // Dispense Resep
        $dispenseRes = $this->controller->apiDispenseResep($this->apoteker, [
            'no_resep' => $noResep,
            'kd_bangsal' => 'FAR01',
            'jenis_klaim' => 'Kapitasi',
        ]);
        $this->assertSame(200, $dispenseRes['status']);

        // Delete setelah dispense harus ditolak 422
        $delRes = $this->controller->apiDeleteResep(['no_resep' => $noResep]);
        $this->assertSame(422, $delRes['status']);
    }
}
