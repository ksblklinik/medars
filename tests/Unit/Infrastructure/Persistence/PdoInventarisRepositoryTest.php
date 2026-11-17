<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoInventarisRepository;
use PHPUnit\Framework\TestCase;

final class PdoInventarisRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoInventarisRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoInventarisRepository($this->pdo);
    }

    public function testCrudInventarisAset(): void
    {
        $this->repo->saveAset([
            'kode_aset' => 'AST-001',
            'nama_aset' => 'USG Mindray DC-40',
            'kategori' => 'Alat Medis',
            'lokasi' => 'Poli Kebidanan',
            'tgl_perolehan' => '2026-01-10',
            'nilai_perolehan' => 185000000,
            'kondisi' => 'Baik',
        ]);

        $rows = $this->repo->listAset('Mindray');
        $this->assertCount(1, $rows);
        $this->assertSame('Poli Kebidanan', $rows[0]['lokasi']);

        $this->repo->deleteAset('AST-001');
        $this->assertCount(0, $this->repo->listAset('Mindray'));
    }
}
