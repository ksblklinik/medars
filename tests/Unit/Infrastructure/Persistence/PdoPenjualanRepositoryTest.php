<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoPenjualanRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PdoPenjualanRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoPenjualanRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoPenjualanRepository($this->pdo);
    }

    public function testBarangOrderItemsBillingAndReport(): void
    {
        $barang = $this->repo->saveBarang([
            'nama_barang' => 'Tensimeter Digital',
            'kode_satuan' => 'Unit',
            'stok' => 5,
            'harga' => 250000,
            'keterangan' => 'Alkes',
        ]);
        $barangId = (int) $barang['id'];
        self::assertCount(1, $this->repo->listBarang('Tensimeter'));

        $order = $this->repo->createOrder([
            'nama_pembeli' => 'Tn. Agus',
            'nomor_telepon' => '08123456789',
            'id_user' => 'kasir01',
        ]);
        $orderId = (int) $order['id'];

        $item = $this->repo->addItem($orderId, $barangId, 2, 'kasir01');
        self::assertSame(500000.0, (float) $item['harga_total']);

        // Stock deducted from 5 to 3
        $bList = $this->repo->listBarang('Tensimeter');
        self::assertSame(3, (int) $bList[0]['stok']);

        $billing = $this->repo->settleOrder($orderId, 50000.0, 450000.0, 'QRIS', 'kasir01');
        self::assertSame(450000.0, (float) $billing['jumlah_harus_bayar']);

        $detail = $this->repo->getOrderDetail($orderId);
        self::assertNotNull($detail);
        self::assertSame('Lunas', $detail['header']['status']);
        self::assertCount(1, $detail['items']);

        $today = date('Y-m-d');
        $report = $this->repo->getTurnoverReport($today, $today);
        self::assertSame(1, $report['total_transaksi']);
        self::assertSame(450000.0, $report['total_omzet']);
        self::assertCount(1, $report['top_items']);
    }

    public function testAddItemThrowsWhenStockInsufficient(): void
    {
        $barang = $this->repo->saveBarang([
            'nama_barang' => 'Masker N95',
            'stok' => 1,
            'harga' => 15000,
        ]);
        $order = $this->repo->createOrder(['nama_pembeli' => 'Umum']);

        $this->expectException(RuntimeException::class);
        $this->repo->addItem((int) $order['id'], (int) $barang['id'], 3);
    }
}
