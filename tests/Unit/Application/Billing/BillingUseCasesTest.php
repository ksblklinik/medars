<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing;

use App\Application\Billing\UseCase\ProcessPosApotekUmumUseCase;
use App\Application\Billing\UseCase\ProcessSplitPaymentUseCase;
use App\Application\Billing\UseCase\ReconcilePendingOrderUseCase;
use App\Application\Billing\UseCase\VoidBillingTransactionUseCase;
use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Billing\ValueObject\PosPaymentMethod;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoAuditLogRepository;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoDataBarangRepository;
use App\Infrastructure\Persistence\PdoGudangBarangRepository;
use App\Infrastructure\Persistence\PdoPosApotekUmumRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class BillingUseCasesTest extends TestCase
{
    private PDO $pdo;
    private PdoTransactionManager $tx;
    private PdoBillingLedgerRepository $ledgerRepo;
    private PdoRegPeriksaRepository $regRepo;
    private PdoAuditLogRepository $auditRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->tx = new PdoTransactionManager($this->pdo);
        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);
        $this->auditRepo = new PdoAuditLogRepository($this->pdo);

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
        ");
    }

    public function testPureBpjsClaimNeverDebitsPhysicalCashAccount(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            null, $noRawat, 'RJ002', 'Konsultasi Dokter', 'Tindakan',
            1.0, 35000.0, 35000.0, ClaimType::fromString('Kapitasi'), LedgerStatus::fromString('Pending')
        ));

        $useCase = new ProcessSplitPaymentUseCase($this->ledgerRepo, $this->regRepo, $this->tx);
        $result = $useCase->execute($noRawat, new \DateTimeImmutable('2026-11-16 10:00:00'));

        // Pasien BPJS murni membayar Rp 0 tunai
        $this->assertSame(0.0, $result['tunai_paid']);
        $this->assertSame(35000.0, $result['bpjs_covered']);

        // Kas Fisik 1101.01 TIDAK BOLEH didebet; hanya Piutang BPJS 1103.01
        $jurnals = $this->ledgerRepo->findJurnalsByNoBukti($noRawat->value());
        $this->assertCount(1, $jurnals);
        foreach ($jurnals[0]->details as $det) {
            $this->assertNotSame(JurnalEntry::COA_KAS_FISIK, $det->kdRek);
        }
        $this->assertSame(JurnalEntry::COA_PIUTANG_BPJS, $jurnals[0]->details[0]->kdRek);
        $this->assertSame(35000.0, $jurnals[0]->details[0]->debet);
    }

    public function testSplitBillingDebitsPhysicalCashOnlyForTunaiPortion(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');
        // Porsi Kapitasi BPJS: 50.000
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            null, $noRawat, 'RJ002', 'Konsultasi BPJS', 'Tindakan',
            1.0, 50000.0, 50000.0, ClaimType::fromString('Kapitasi'), LedgerStatus::fromString('Pending')
        ));
        // Porsi Tunai Mandiri (misal obat di luar paket): 25.000
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            null, $noRawat, 'OBT99', 'Suplemen Tunai', 'Obat',
            1.0, 25000.0, 25000.0, ClaimType::fromString('Tunai'), LedgerStatus::fromString('Pending')
        ));

        $useCase = new ProcessSplitPaymentUseCase($this->ledgerRepo, $this->regRepo, $this->tx);
        $result = $useCase->execute($noRawat, new \DateTimeImmutable('2026-11-16 10:00:00'));

        $this->assertSame(25000.0, $result['tunai_paid']);
        $this->assertSame(50000.0, $result['bpjs_covered']);

        $jurnals = $this->ledgerRepo->findJurnalsByNoBukti($noRawat->value());
        $this->assertCount(1, $jurnals);

        $kasDebet = 0.0;
        $bpjsDebet = 0.0;
        foreach ($jurnals[0]->details as $det) {
            if ($det->kdRek === JurnalEntry::COA_KAS_FISIK) $kasDebet += $det->debet;
            if ($det->kdRek === JurnalEntry::COA_PIUTANG_BPJS) $bpjsDebet += $det->debet;
        }
        $this->assertSame(25000.0, $kasDebet);
        $this->assertSame(50000.0, $bpjsDebet);
    }

    public function testVoidCreatesBalancedReversalJournalAndBlocksIfClinicalServiceExists(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            null, $noRawat, 'RJ002', 'Registrasi', 'Tindakan',
            1.0, 25000.0, 25000.0, ClaimType::fromString('Tunai'), LedgerStatus::fromString('Pending')
        ));

        $payUseCase = new ProcessSplitPaymentUseCase($this->ledgerRepo, $this->regRepo, $this->tx);
        $payUseCase->execute($noRawat, new \DateTimeImmutable('2026-11-16 10:00:00'));

        // Pasien belum diperiksa dokter (0 layanan klinis) -> Void diizinkan & membentuk Jurnal Pembalik
        $voidUseCase = new VoidBillingTransactionUseCase($this->ledgerRepo, $this->regRepo, $this->auditRepo, $this->tx);
        $voidRes = $voidUseCase->execute($noRawat, 1, 'admin', 'Salah input penjamin', '127.0.0.1');

        $this->assertSame('FAKTUR / KUITANSI TIDAK BERLAKU', $voidRes['stamp']);
        // Jurnal asli TIDAK dihapus, melainkan bertambah 1 Jurnal Pembalik (total 2 jurnal)
        $jurnals = $this->ledgerRepo->findJurnalsByNoBukti($noRawat->value());
        $this->assertCount(2, $jurnals);
        $this->assertStringContainsString('[REVERSAL', $jurnals[1]->keterangan);

        // Sekarang simulasikan pasien sudah punya SOAP di pemeriksaan_ralan -> Full Void wajib DIBLOKIR
        $this->pdo->exec("
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, keluhan, pemeriksaan, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/16/000001', '2026-11-16', '10:30:00', 'Demam', 'Baik', '-', '-', '-', '-', 'DR001')
        ");

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('memiliki layanan klinis');
        $voidUseCase->execute($noRawat, 1, 'admin', 'Coba void fraud', '127.0.0.1');
    }

    public function testPosApotekUmumIsolatesStockToAptAndClinicBilling(): void
    {
        $this->pdo->exec("
            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, jualbebas, status)
            VALUES ('OBT001', 'Vitamin C 500mg', 'TAB', 500, 600, 1000, 1500, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'APT', 'B01', 'F01', 20),
                   ('OBT001', 'FAR01', 'B02', 'F02', 50);
        ");

        $posUseCase = new ProcessPosApotekUmumUseCase(
            new PdoDataBarangRepository($this->pdo),
            new PdoGudangBarangRepository($this->pdo),
            new PdoPosApotekUmumRepository($this->pdo),
            $this->tx
        );

        $sale = $posUseCase->execute(
            items: [['kode_brng' => 'OBT001', 'jml' => 4]],
            paymentMethod: PosPaymentMethod::fromString('QRIS'),
            petugasNip: 'APT01',
            saleAt: new \DateTimeImmutable('2026-11-16 21:00:00')
        );

        $this->assertStringStartsWith('PO-APT-', $sale->noNota);
        $this->assertSame(6000.0, $sale->total); // 4 * jualbebas (1500)

        // Depo APT berkurang menjadi 16, Depo FAR01 klinik tetap 50, dan mlite_billing_ledger klinik tetap 0!
        $gudang = new PdoGudangBarangRepository($this->pdo);
        $this->assertSame(16.0, $gudang->getStock('OBT001', new DepoBangsal('APT')));
        $this->assertSame(50.0, $gudang->getStock('OBT001', new DepoBangsal('FAR01')));
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM mlite_billing_ledger")->fetchColumn());
    }

    public function testReconcilePendingOrderLogsUserAndReason(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            null, $noRawat, 'LAB01', 'Cek Darah', 'Lab',
            1.0, 50000.0, 50000.0, ClaimType::fromString('Tunai'), LedgerStatus::fromString('Pending')
        ));

        $reconcileUseCase = new ReconcilePendingOrderUseCase($this->ledgerRepo, $this->auditRepo, $this->tx);
        $reconcileUseCase->execute($noRawat, 1, 'kasir1', 'Pasien batal periksa lab akhir shift', '127.0.0.1');

        $items = $this->ledgerRepo->findByNoRawat($noRawat);
        $this->assertSame('Cancelled', $items[0]->status->value());

        $logs = $this->auditRepo->getRecentLogs(5, 'ORDER_RECONCILE_CANCEL');
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('Pasien batal periksa lab', $logs[0]['details']);
    }
}
