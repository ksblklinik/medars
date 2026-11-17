<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoVeronisaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoVeronisaRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoVeronisaRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoVeronisaRepository($this->pdo);
    }

    public function testClaimsAndObatMappingLifecycle(): void
    {
        $today = date('Y-m-d');
        $saved = $this->repo->saveClaim([
            'tanggal' => $today,
            'no_rkm_medis' => '000001',
            'no_rawat' => '2026/11/17/000001',
            'nosep' => '0001R0011126V000001',
            'status' => 'Pengajuan',
            'catatan' => 'Klaim non-kapitasi PRB',
            'username' => 'verifikator01',
        ]);
        $id = (int) $saved['id'];
        self::assertCount(1, $this->repo->listClaims($today, $today, '0001R001'));

        self::assertTrue($this->repo->updateStatus($id, 'Layak', 'Berkas lengkap'));
        $found = $this->repo->findClaimById($id);
        self::assertNotNull($found);
        self::assertSame('Layak', $found['status']);

        $map = $this->repo->saveObatMapping([
            'kode_brng' => 'OBT-01',
            'kode_dpho' => '123456',
            'nama_dpho' => 'Amlodipine 5mg',
            'harga_dpho' => 500,
        ]);
        self::assertSame('123456', $map['kode_dpho']);
        self::assertCount(1, $this->repo->listObatMapping('Amlodipine'));

        self::assertTrue($this->repo->deleteClaim($id));
        self::assertNull($this->repo->findClaimById($id));
    }
}
