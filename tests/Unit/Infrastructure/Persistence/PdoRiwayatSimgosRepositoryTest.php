<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoRiwayatSimgosRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoRiwayatSimgosRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoRiwayatSimgosRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoRiwayatSimgosRepository($this->pdo);
    }

    public function testImportAndSearchRiwayatSimgos(): void
    {
        $saved = $this->repo->importRecord([
            'nopen' => '2301010001',
            'no_rkm_medis' => 'RM-OLD-01',
            'nik' => '3500012345678',
            'nm_pasien' => 'Ahmad SIMGOS',
            'tgl_kunjungan' => '2023-01-01',
            'unit_layanan' => 'Poli Penyakit Dalam',
            'dokter' => 'dr. Senior',
            'diagnosa_icd10' => 'E11.9 - Type 2 DM',
            'catatan_klinis' => 'Kontrol rutin gula darah',
        ]);

        $this->assertSame('2301010001', $saved['nopen']);
        $this->assertNotNull($this->repo->findById((int) $saved['id']));
        $this->assertCount(1, $this->repo->search('Ahmad', '2023-01-01', '2023-12-31'));
        $this->assertCount(0, $this->repo->search('TidakAda'));
    }
}
