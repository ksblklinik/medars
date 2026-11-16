<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Pharmacy\Entity\DataBarang;
use App\Domain\Pharmacy\Entity\DetailPemberianObat;
use App\Domain\Pharmacy\Entity\ResepDokterNonRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikanDetail;
use App\Domain\Pharmacy\Entity\ResepObat;
use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use App\Domain\Pharmacy\ValueObject\JenisKlaim;
use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Domain\Pharmacy\ValueObject\UniformPricing;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoDataBarangRepository;
use App\Infrastructure\Persistence\PdoGudangBarangRepository;
use App\Infrastructure\Persistence\PdoResepRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoPharmacyRepositoriesTest extends TestCase
{
    private PDO $pdo;
    private PdoDataBarangRepository $barangRepo;
    private PdoGudangBarangRepository $gudangRepo;
    private PdoResepRepository $resepRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->barangRepo = new PdoDataBarangRepository($this->pdo);
        $this->gudangRepo = new PdoGudangBarangRepository($this->pdo);
        $this->resepRepo = new PdoResepRepository($this->pdo);

        $this->pdo->exec("
            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, harga_kapitasi, harga_nonkapitasi, jualbebas, status)
            VALUES ('OBT001', 'Vitamin B6 10mg', 'TAB', 100, 120, 500, 300, 400, 600, '1');

            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'IGD', 'B-01', 'F-01', 3),
                   ('OBT001', 'IGD', 'B-02', 'F-02', 10),
                   ('OBT001', 'APT', 'B-01', 'F-01', 50);
        ");
    }

    public function testSearchShortDrugNameWithUniformFlatPricingAndSmartClaimSorting(): void
    {
        // Mendukung keyword pendek 2 karakter ('B6')
        $results = $this->barangRepo->searchWithUniformPricing('B6', isBpjs: true);
        $this->assertCount(1, $results);
        $this->assertSame('OBT001', $results[0]['kode_brng']);
        $this->assertSame('Kapitasi', $results[0]['claim_options'][0]['claim_type']);
        $this->assertSame('Non-Kapitasi', $results[0]['claim_options'][1]['claim_type']);
        $this->assertSame('Tunai', $results[0]['claim_options'][2]['claim_type']);
    }

    public function testStockDeductionIsolatesDeposAndSplitsBatchesAndLogsRiwayat(): void
    {
        $depoIgd = new DepoBangsal('IGD');
        $depoApt = new DepoBangsal('APT');

        $this->assertSame(13.0, $this->gudangRepo->getStock('OBT001', $depoIgd));
        $this->assertSame(50.0, $this->gudangRepo->getStock('OBT001', $depoApt));

        // Kurangi 5 dari IGD (3 dari B-01 + 2 dari B-02)
        $deductedBatches = $this->gudangRepo->deductStock(
            kodeBrng: 'OBT001',
            depo: $depoIgd,
            qty: 5.0,
            tanggal: '2026-11-16',
            jam: '20:55:00',
            petugas: 'APOTEK01',
            keterangan: 'Resep 202611160001'
        );

        $this->assertCount(2, $deductedBatches);
        $this->assertSame('B-01', $deductedBatches[0]['no_batch']);
        $this->assertSame(3.0, $deductedBatches[0]['qty']);
        $this->assertSame('B-02', $deductedBatches[1]['no_batch']);
        $this->assertSame(2.0, $deductedBatches[1]['qty']);

        // Sisa di IGD = 8, sedangkan APT tetap 50 (terisolasi mutlak)
        $this->assertSame(8.0, $this->gudangRepo->getStock('OBT001', $depoIgd));
        $this->assertSame(50.0, $this->gudangRepo->getStock('OBT001', $depoApt));

        // Pastikan tercatat ke riwayat_barang_medis
        $riwayatCount = (int) $this->pdo->query("SELECT COUNT(*) FROM riwayat_barang_medis WHERE kd_bangsal = 'IGD'")->fetchColumn();
        $this->assertSame(2, $riwayatCount);
    }

    public function testDeductStockAboveBalanceThrowsInsufficientStockException(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->gudangRepo->deductStock(
            kodeBrng: 'OBT001',
            depo: new DepoBangsal('IGD'),
            qty: 99.0,
            tanggal: '2026-11-16',
            jam: '20:55:00',
            petugas: 'APOTEK01',
            keterangan: 'Overdraw'
        );
    }

    public function testResepSaveAndDetailPemberianObat6Keys(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');
        $noResep = $this->resepRepo->getNextNoResep($today);
        $this->assertSame('202611160001', $noResep->value());

        $noRawat = new NoRawat('2026/11/16/000001');
        $resep = new ResepObat($noResep, '2026-11-16', '09:00:00', $noRawat, 'DR001');
        $this->resepRepo->saveHeader($resep);

        $this->resepRepo->saveNonRacikan(new ResepDokterNonRacikan($noResep, 'OBT001', 10.0, '3x1'));
        $this->resepRepo->saveRacikan(new ResepDokterRacikan(
            $noResep,
            '1',
            'Puyer B6',
            'R01',
            10,
            '3x1',
            '-',
            [new ResepDokterRacikanDetail($noResep, '1', 'OBT001', 1.0, 1.0, '10mg', 5.0)]
        ));

        $this->assertCount(1, $this->resepRepo->findNonRacikan($noResep));
        $racikan = $this->resepRepo->findRacikan($noResep);
        $this->assertCount(1, $racikan);
        $this->assertCount(1, $racikan[0]->details);

        // Simpan 2 batch berbeda pada timestamp sama (PK 6 kolom)
        $this->resepRepo->saveDetailPemberianObat(new DetailPemberianObat(
            '2026-11-16', '20:55:00', $noRawat, 'OBT001', 'B-01', 'F-01',
            120.0, 500.0, 3.0, 0.0, 0.0, 1500.0, 'Ralan', new DepoBangsal('IGD'), JenisKlaim::fromString('Tunai')
        ));
        $this->resepRepo->saveDetailPemberianObat(new DetailPemberianObat(
            '2026-11-16', '20:55:00', $noRawat, 'OBT001', 'B-02', 'F-02',
            120.0, 500.0, 2.0, 0.0, 0.0, 1000.0, 'Ralan', new DepoBangsal('IGD'), JenisKlaim::fromString('Tunai')
        ));

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM detail_pemberian_obat")->fetchColumn();
        $this->assertSame(2, $count);
    }
}
