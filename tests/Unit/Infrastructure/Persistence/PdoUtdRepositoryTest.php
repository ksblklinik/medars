<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoUtdRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoUtdRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoUtdRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoUtdRepository($this->pdo);
    }

    public function testKomponenPendonorStokAndCrossmatch(): void
    {
        $this->repo->saveKomponen(['kode' => 'PRC', 'nama' => 'Packed Red Cells', 'lama' => 35, 'total' => 360000]);
        self::assertCount(1, $this->repo->listKomponen());

        $this->repo->savePendonor(['no_pendonor' => 'DNR-01', 'nama' => 'Ahmad', 'golongan_darah' => 'O', 'resus' => '+']);
        self::assertCount(1, $this->repo->listPendonor('Ahmad'));

        $this->repo->saveStokDarah([
            'no_kantong' => 'KTG-001',
            'kode_komponen' => 'PRC',
            'golongan_darah' => 'O',
            'resus' => '+',
            'tanggal_aftap' => '2026-11-17',
            'tanggal_kadaluarsa' => '2026-12-22',
        ]);
        self::assertCount(1, $this->repo->listStokDarah('O'));

        $req = $this->repo->savePermintaan([
            'no_rawat' => '2026/11/17/000001',
            'golongan_darah' => 'O',
            'petugas' => 'Analis UTD',
        ]);
        $reqId = (int) $req['id'];

        self::assertTrue($this->repo->updateCrossmatch($reqId, 'KTG-001', 'Cocok', 'Diserahkan'));

        $stok = $this->repo->listStokDarah('O');
        self::assertSame('Terpakai', $stok[0]['status']);
    }
}
