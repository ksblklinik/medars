<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoRuangTindakanRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoRuangTindakanRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoRuangTindakanRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoRuangTindakanRepository($this->pdo);
    }

    public function testSaveListApproveAndDeleteFollowup(): void
    {
        $today = date('Y-m-d');
        // Paramedis cannot finalize to Selesai -> downgraded to Draft
        $draft = $this->repo->saveFollowup([
            'no_rawat' => '2026/11/17/000001',
            'kd_jenis_prw' => 'TIND-01',
            'tgl_order' => $today,
            'tgl_tindakan' => $today,
            'diagnosis' => 'Vulnus Laceratum',
            'laporan_operasi' => 'Debridement & hecting 3 jahitan.',
            'catatan' => 'Kontrol 3 hari',
            'kd_dokter' => 'DR01',
            'nm_petugas' => 'Ns. Sinta',
            'status' => 'Selesai',
        ], 'paramedis');
        self::assertSame('Draft', $draft['status']);

        // Medis approves to Selesai
        $approved = $this->repo->saveFollowup([
            'no_rawat' => '2026/11/17/000001',
            'kd_jenis_prw' => 'TIND-01',
            'tgl_order' => $today,
            'tgl_tindakan' => $today,
            'diagnosis' => 'Vulnus Laceratum',
            'laporan_operasi' => 'Debridement & hecting 3 jahitan.',
            'catatan' => 'Kontrol 3 hari',
            'kd_dokter' => 'DR01',
            'nm_petugas' => 'dr. Andi',
            'status' => 'Selesai',
        ], 'medis');
        self::assertSame('Selesai', $approved['status']);
        self::assertNotEmpty($approved['tgl_approve']);

        $list = $this->repo->listFollowup($today, $today, 'Vulnus');
        self::assertCount(1, $list);

        $id = (int) $list[0]['id'];
        self::assertNotNull($this->repo->findById($id));
        self::assertTrue($this->repo->deleteFollowup($id));
        self::assertNull($this->repo->findById($id));
    }
}
