<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Support;

use App\Application\Billing\UseCase\ProcessSplitPaymentUseCase;
use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\DeleteLabOrderUseCase;
use App\Application\Support\UseCase\SaveLabResultUseCase;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Support\Entity\DetailPeriksaLab;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoLabRadiologyRepository;
use App\Infrastructure\Persistence\PdoOperationalReportRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class LabRadiologyAndReportTest extends TestCase
{
    private PDO $pdo;
    private PdoTransactionManager $tx;
    private PdoLabRadiologyRepository $labRepo;
    private PdoBillingLedgerRepository $ledgerRepo;
    private PdoRegPeriksaRepository $regRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->tx = new PdoTransactionManager($this->pdo);
        $this->labRepo = new PdoLabRadiologyRepository($this->pdo);
        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');

            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia) VALUES ('J06.9', 'Acute URI', 'ISPA Akut');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/16/000001', 'J06.9', 'Ralan', 1);
        ");
    }

    public function testSegregatesKapitasiAndTunaiItemsIntoDistinctOrders(): void
    {
        $useCase = new CreateLabOrderUseCase($this->labRepo, $this->ledgerRepo, $this->tx);
        $noRawat = new NoRawat('2026/11/16/000001');

        $orders = $useCase->execute(
            noRawat: $noRawat,
            dokterPerujuk: 'DR001',
            items: [
                ['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'Cek Darah Rutin', 'harga' => 50000, 'claim_type' => 'Kapitasi'],
                ['kd_jenis_prw' => 'LAB02', 'nm_perawatan' => 'Profil Lipid Lengkap', 'harga' => 120000, 'claim_type' => 'Tunai'],
            ],
            orderAt: new \DateTimeImmutable('2026-11-16 09:00:00')
        );

        // Wajib terpisah menjadi 2 nomor PL berbeda (1 Kapitasi, 1 Tunai)
        $this->assertCount(2, $orders);
        $this->assertNotSame($orders[0]->noOrder, $orders[1]->noOrder);
        $this->assertStringStartsWith('PL20261116', $orders[0]->noOrder);
    }

    public function testGeneratesNewOrderNumberIfPreviousOrderAlreadyPaid(): void
    {
        $createLab = new CreateLabOrderUseCase($this->labRepo, $this->ledgerRepo, $this->tx);
        $payUseCase = new ProcessSplitPaymentUseCase($this->ledgerRepo, $this->regRepo, $this->tx);
        $noRawat = new NoRawat('2026/11/16/000001');

        // Order 1
        $orders1 = $createLab->execute(
            noRawat: $noRawat,
            dokterPerujuk: 'DR001',
            items: [['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'GDS', 'harga' => 25000, 'claim_type' => 'Tunai']],
            orderAt: new \DateTimeImmutable('2026-11-16 09:00:00')
        );
        $this->assertSame('PL202611160001', $orders1[0]->noOrder);

        // Lunasi di Kasir
        $payUseCase->execute($noRawat, new \DateTimeImmutable('2026-11-16 09:10:00'));

        // Order susulan setelah lunas -> wajib dapat nomor PL baru
        $orders2 = $createLab->execute(
            noRawat: $noRawat,
            dokterPerujuk: 'DR001',
            items: [['kd_jenis_prw' => 'LAB03', 'nm_perawatan' => 'Asam Urat', 'harga' => 30000, 'claim_type' => 'Tunai']],
            orderAt: new \DateTimeImmutable('2026-11-16 09:20:00')
        );
        $this->assertSame('PL202611160002', $orders2[0]->noOrder);
    }

    public function testLocksLabValidationUntilCashierApprovesAndFlagsAbnormalValues(): void
    {
        $createLab = new CreateLabOrderUseCase($this->labRepo, $this->ledgerRepo, $this->tx);
        $saveResult = new SaveLabResultUseCase($this->labRepo, $this->ledgerRepo, $this->tx);
        $noRawat = new NoRawat('2026/11/16/000001');

        $orders = $createLab->execute(
            noRawat: $noRawat,
            dokterPerujuk: 'DR001',
            items: [['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'Hemoglobin', 'harga' => 40000, 'claim_type' => 'Tunai']],
            orderAt: new \DateTimeImmutable('2026-11-16 10:00:00')
        );
        $noOrder = $orders[0]->noOrder;

        // Belum lunas kasir -> tombol validasi/simpan hasil HARUS terkunci
        $this->assertFalse($saveResult->isValidationUnlocked($noOrder));

        try {
            $saveResult->execute($noOrder, [
                ['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.5', 'nilai_rujukan' => '13.0 - 17.0'],
            ]);
            $this->fail('Validasi hasil lab seharusnya terkunci sebelum kasir menyetujui/melunasi.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Kasir', $e->getMessage());
        }

        // Lunasi di Kasir -> sekarang validasi terbuka
        $payUseCase = new ProcessSplitPaymentUseCase($this->ledgerRepo, $this->regRepo, $this->tx);
        $payUseCase->execute($noRawat, new \DateTimeImmutable('2026-11-16 10:05:00'));

        $this->assertTrue($saveResult->isValidationUnlocked($noOrder));

        $savedDetails = $saveResult->execute($noOrder, [
            ['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.5', 'nilai_rujukan' => '13.0 - 17.0'],
            ['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Leukosit', 'nilai' => '15.2', 'nilai_rujukan' => '4.0 - 10.0'],
            ['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Trombosit', 'nilai' => '250', 'nilai_rujukan' => '150 - 400'],
        ]);

        $this->assertSame('Rendah', $savedDetails[0]->keterangan);
        $this->assertSame('Tinggi', $savedDetails[1]->keterangan);
        $this->assertSame('Normal', $savedDetails[2]->keterangan);
    }

    public function testDeleteLabOrderAtomicAndOperationalReports(): void
    {
        $createLab = new CreateLabOrderUseCase($this->labRepo, $this->ledgerRepo, $this->tx);
        $deleteLab = new DeleteLabOrderUseCase($this->labRepo, $this->tx, $this->ledgerRepo);
        $noRawat = new NoRawat('2026/11/16/000001');

        $orders = $createLab->execute(
            noRawat: $noRawat,
            dokterPerujuk: 'DR001',
            items: [['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'GDS', 'harga' => 25000, 'claim_type' => 'Kapitasi']],
            orderAt: new \DateTimeImmutable('2026-11-16 11:00:00')
        );

        $deleteLab->execute($orders[0]->noOrder);
        $this->assertNull($this->labRepo->findLabOrder($orders[0]->noOrder));

        // Pastikan item ledger pending ikut dibatalkan (tidak jadi tagihan yatim)
        $ledgerItems = $this->ledgerRepo->findByNoRawat($noRawat);
        $this->assertCount(1, $ledgerItems);
        $this->assertSame('Cancelled', $ledgerItems[0]->status->value());

        // Laporan Operasional
        $reportRepo = new PdoOperationalReportRepository($this->pdo);
        $today = date('Y-m-d');
        $visits = $reportRepo->getVisitSummaryByPoli($today, $today);
        $this->assertCount(1, $visits);
        $this->assertSame(1, (int) $visits[0]['total_kunjungan']);

        $topIcd = $reportRepo->getTopDiseases($today, $today, 10);
        $this->assertCount(1, $topIcd);
        $this->assertSame('J06.9', $topIcd[0]['kd_penyakit']);
    }
}
