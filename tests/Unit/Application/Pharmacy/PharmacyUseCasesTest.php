<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Pharmacy;

use App\Application\Pharmacy\UseCase\CreateResepUseCase;
use App\Application\Pharmacy\UseCase\DeleteResepUseCase;
use App\Application\Pharmacy\UseCase\DispenseResepUseCase;
use App\Application\Pharmacy\UseCase\SaveCopyResepUseCase;
use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use App\Domain\Pharmacy\ValueObject\JenisKlaim;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoDataBarangRepository;
use App\Infrastructure\Persistence\PdoGudangBarangRepository;
use App\Infrastructure\Persistence\PdoResepRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PharmacyUseCasesTest extends TestCase
{
    private PDO $pdo;
    private PdoTransactionManager $tx;
    private PdoDataBarangRepository $barangRepo;
    private PdoGudangBarangRepository $gudangRepo;
    private PdoResepRepository $resepRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->tx = new PdoTransactionManager($this->pdo);
        $this->barangRepo = new PdoDataBarangRepository($this->pdo);
        $this->gudangRepo = new PdoGudangBarangRepository($this->pdo);
        $this->resepRepo = new PdoResepRepository($this->pdo);

        $this->pdo->exec("
            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, harga_kapitasi, harga_nonkapitasi, jualbebas, status)
            VALUES ('OBT001', 'Paracetamol 500mg', 'TAB', 150, 200, 1000, 500, 700, 1200, '1'),
                   ('OBT002', 'CTM 4mg', 'TAB', 50, 80, 300, 150, 200, 400, '1');

            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'FAR01', 'B01', 'F01', 20),
                   ('OBT002', 'FAR01', 'B02', 'F02', 20),
                   ('OBT001', 'APT', 'B99', 'F99', 100);
        ");
    }

    public function testCreateAndDispenseResepEnforcesPlusOneSecondOffsetEvenAtMidnight(): void
    {
        $createUseCase = new CreateResepUseCase($this->resepRepo, $this->tx);
        $noRawat = new NoRawat('2026/11/16/000001');

        $resep = $createUseCase->execute(
            noRawat: $noRawat,
            kdDokter: 'DR001',
            nonRacikanItems: [
                ['kode_brng' => 'OBT001', 'jml' => 6, 'aturan_pakai' => '3x1'],
            ],
            racikanItems: [
                [
                    'nama_racik' => 'Puyer Batuk',
                    'kd_racik' => 'R01',
                    'jml_dr' => 5,
                    'aturan_pakai' => '3x1 bungkus',
                    'details' => [
                        ['kode_brng' => 'OBT002', 'jml' => 4, 'kandungan' => '4mg'],
                    ],
                ],
            ],
            peresepanAt: new \DateTimeImmutable('2026-11-16 23:59:59')
        );

        // Dispense tepat pada pukul 23:59:59 -> Non-racikan 2026-11-16 23:59:59, Racikan +1 detik -> 2026-11-17 00:00:00
        $dispenseUseCase = new DispenseResepUseCase($this->resepRepo, $this->barangRepo, $this->gudangRepo, $this->tx);
        $dispenseUseCase->execute(
            noResep: $resep->noResep,
            depo: new DepoBangsal('FAR01'),
            jenisKlaim: JenisKlaim::fromString('Tunai'),
            petugasNip: 'APOTEK01',
            dispenseAt: new \DateTimeImmutable('2026-11-16 23:59:59')
        );

        // Cek Non-Racikan di detail_pemberian_obat
        $nonRacikRow = $this->pdo->query("SELECT * FROM detail_pemberian_obat WHERE kode_brng = 'OBT001'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('2026-11-16', $nonRacikRow['tgl_perawatan']);
        $this->assertSame('23:59:59', $nonRacikRow['jam']);
        $this->assertSame(6000.0, (float) $nonRacikRow['total']); // Uniform flat pricing: 6 * 1000

        // Cek Racikan (+1 detik rollover aman ke 2026-11-17 00:00:00)
        $racikRow = $this->pdo->query("SELECT * FROM detail_pemberian_obat WHERE kode_brng = 'OBT002'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('2026-11-17', $racikRow['tgl_perawatan']);
        $this->assertSame('00:00:00', $racikRow['jam']);

        $obatRacikanHeader = $this->pdo->query("SELECT * FROM obat_racikan")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('2026-11-17', $obatRacikanHeader['tgl_perawatan']);
        $this->assertSame('00:00:00', $obatRacikanHeader['jam']);

        // Stok FAR01 berkurang, sedangkan APT tetap 100
        $this->assertSame(14.0, $this->gudangRepo->getStock('OBT001', new DepoBangsal('FAR01')));
        $this->assertSame(16.0, $this->gudangRepo->getStock('OBT002', new DepoBangsal('FAR01')));
        $this->assertSame(100.0, $this->gudangRepo->getStock('OBT001', new DepoBangsal('APT')));
    }

    public function testDispenseRollsBackAllWhenOneItemInsufficientStock(): void
    {
        $createUseCase = new CreateResepUseCase($this->resepRepo, $this->tx);
        $resep = $createUseCase->execute(
            noRawat: new NoRawat('2026/11/16/000001'),
            kdDokter: 'DR001',
            nonRacikanItems: [
                ['kode_brng' => 'OBT001', 'jml' => 5, 'aturan_pakai' => '3x1'], // Stok cukup (20)
                ['kode_brng' => 'OBT002', 'jml' => 99, 'aturan_pakai' => '3x1'], // Stok kurang (20)
            ]
        );

        $dispenseUseCase = new DispenseResepUseCase($this->resepRepo, $this->barangRepo, $this->gudangRepo, $this->tx);

        try {
            $dispenseUseCase->execute(
                noResep: $resep->noResep,
                depo: new DepoBangsal('FAR01'),
                jenisKlaim: JenisKlaim::fromString('Tunai'),
                petugasNip: 'APOTEK01'
            );
            $this->fail('Seharusnya melempar InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            // OBT001 harus tetap 20 (seluruh transaksi rollback)
            $this->assertSame(20.0, $this->gudangRepo->getStock('OBT001', new DepoBangsal('FAR01')));
            $this->assertFalse($this->resepRepo->findByNoResep($resep->noResep)->isDispensed());
        }
    }

    public function testDeleteAndCopyResepWithClinicalGuard(): void
    {
        $createUseCase = new CreateResepUseCase($this->resepRepo, $this->tx);
        $deleteUseCase = new DeleteResepUseCase($this->resepRepo, $this->tx);
        $copyUseCase = new SaveCopyResepUseCase($this->resepRepo, $this->tx);

        $resep1 = $createUseCase->execute(
            noRawat: new NoRawat('2026/11/16/000001'),
            kdDokter: 'DR001',
            nonRacikanItems: [['kode_brng' => 'OBT001', 'jml' => 5, 'aturan_pakai' => '3x1']],
            peresepanAt: new \DateTimeImmutable('2026-11-16 09:00:00')
        );

        // Copy resep ke kunjungan baru
        $resepCopy = $copyUseCase->execute($resep1->noResep, new NoRawat('2026/11/16/000002'), 'DR001');
        $this->assertNotSame($resep1->noResep->value(), $resepCopy->noResep->value());
        $this->assertCount(1, $this->resepRepo->findNonRacikan($resepCopy->noResep));

        // Hapus resep copy yang belum diserahkan -> Berhasil
        $deleteUseCase->execute($resepCopy->noResep);
        $this->assertNull($this->resepRepo->findByNoResep($resepCopy->noResep));

        // Dispense resep1 -> Setelah diserahkan, DeleteResepUseCase wajib memblokir
        $dispenseUseCase = new DispenseResepUseCase($this->resepRepo, $this->barangRepo, $this->gudangRepo, $this->tx);
        $dispenseUseCase->execute($resep1->noResep, new DepoBangsal('FAR01'), JenisKlaim::fromString('Kapitasi'), 'APOTEK01');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sudah divalidasi/diserahkan');
        $deleteUseCase->execute($resep1->noResep);
    }
}
