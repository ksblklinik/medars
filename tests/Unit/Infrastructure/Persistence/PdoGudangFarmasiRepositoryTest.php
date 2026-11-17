<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoGudangFarmasiRepository;
use PHPUnit\Framework\TestCase;

final class PdoGudangFarmasiRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoGudangFarmasiRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoGudangFarmasiRepository($this->pdo, new PdoTransactionManager($this->pdo));
    }

    public function testReceiveMutateAndKartuStok(): void
    {
        $this->repo->saveBarang([
            'kode_brng' => 'OBT001',
            'nama_brng' => 'Amoxicillin 500mg',
            'kode_sat' => 'TAB',
            'h_beli' => 400,
            'harga_tunai' => 1000,
            'harga_kapitasi' => 800,
            'harga_nonkapitasi' => 900,
            'jualbebas' => 1200,
        ]);

        $this->repo->receiveStock('OBT001', 'FAR01', 100, 'B01', 'PO-001', 'APT1', 'Penerimaan PO');
        $barang = $this->repo->listBarang('Amoxicillin');
        $this->assertCount(1, $barang);
        $this->assertSame(100.0, (float) $barang[0]['total_stok']);

        $this->repo->mutateStock('OBT001', 'FAR01', 'APT', 30, 'APT1', 'Drop ke Apotek Umum');
        $kartu = $this->repo->listKartuStok('OBT001');
        $this->assertCount(3, $kartu);

        $this->expectException(InsufficientStockException::class);
        $this->repo->mutateStock('OBT001', 'APT', 'IGD', 99, 'APT1', 'Stok tidak cukup');
    }
}
