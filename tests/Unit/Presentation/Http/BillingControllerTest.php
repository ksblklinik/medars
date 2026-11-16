<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Billing\UseCase\ProcessPosApotekUmumUseCase;
use App\Application\Billing\UseCase\ProcessSplitPaymentUseCase;
use App\Application\Billing\UseCase\ReconcilePendingOrderUseCase;
use App\Application\Billing\UseCase\VoidBillingTransactionUseCase;
use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoAuditLogRepository;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoDataBarangRepository;
use App\Infrastructure\Persistence\PdoGudangBarangRepository;
use App\Infrastructure\Persistence\PdoPosApotekUmumRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Presentation\Http\BillingController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class BillingControllerTest extends TestCase
{
    private PDO $pdo;
    private BillingController $controller;
    private PdoBillingLedgerRepository $ledgerRepo;
    private UserAccount $kasir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');

            INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, jualbebas, status)
            VALUES ('OBT001', 'Vitamin C', 'TAB', 500, 600, 1000, 2000, '1');
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok)
            VALUES ('OBT001', 'APT', 'B01', 'F01', 30);
        ");

        $tx = new PdoTransactionManager($this->pdo);
        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $regRepo = new PdoRegPeriksaRepository($this->pdo);
        $auditRepo = new PdoAuditLogRepository($this->pdo);

        $this->controller = new BillingController(
            pdo: $this->pdo,
            ledgerRepo: $this->ledgerRepo,
            payUseCase: new ProcessSplitPaymentUseCase($this->ledgerRepo, $regRepo, $tx),
            voidUseCase: new VoidBillingTransactionUseCase($this->ledgerRepo, $regRepo, $auditRepo, $tx),
            reconcileUseCase: new ReconcilePendingOrderUseCase($this->ledgerRepo, $auditRepo, $tx),
            posUseCase: new ProcessPosApotekUmumUseCase(
                new PdoDataBarangRepository($this->pdo),
                new PdoGudangBarangRepository($this->pdo),
                new PdoPosApotekUmumRepository($this->pdo),
                $tx
            ),
            renderer: new ViewRenderer()
        );

        $this->kasir = new UserAccount(
            id: 30,
            username: 'KASIR01',
            passwordHash: 'hash',
            email: 'kasir@medars.local',
            fullname: 'Siti Kasir',
            role: 'kasir',
            access: ['kasir', 'kasir_rawat_jalan'],
            cap: ['all'],
            status: '1'
        );
    }

    public function testIndexPayVoidAndPosApotekUmumEndpoints(): void
    {
        $indexRes = $this->controller->index($this->kasir, 'csrf-tok');
        $this->assertSame(200, $indexRes['status']);
        $this->assertStringContainsString('Kasir &amp; Billing Ledger 4-Pilar', $indexRes['body']);

        // Seed ledger split billing
        $noRawat = new NoRawat('2026/11/16/000001');
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            null, $noRawat, 'RJ002', 'Konsultasi BPJS', 'Tindakan',
            1.0, 50000.0, 50000.0, ClaimType::fromString('Kapitasi'), LedgerStatus::fromString('Pending')
        ));
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            null, $noRawat, 'OBT99', 'Obat Tunai', 'Obat',
            1.0, 15000.0, 15000.0, ClaimType::fromString('Tunai'), LedgerStatus::fromString('Pending')
        ));

        // 1. Detail
        $detRes = $this->controller->apiDetail(['no_rawat' => '2026/11/16/000001']);
        $this->assertSame(200, $detRes['status']);
        $det = json_decode($detRes['body'], true);
        $this->assertCount(2, $det['items']);
        $this->assertSame(15000.0, (float) $det['summary']['Tunai']);
        $this->assertSame(50000.0, (float) $det['summary']['Kapitasi']);

        // 2. Pay Split Billing
        $payRes = $this->controller->apiPay(['no_rawat' => '2026/11/16/000001']);
        $this->assertSame(200, $payRes['status']);
        $paid = json_decode($payRes['body'], true);
        $this->assertSame(15000.0, (float) $paid['data']['tunai_paid']);

        // 3. Void Anti-Fraud (0 layanan klinis)
        $voidRes = $this->controller->apiVoid($this->kasir, [
            'no_rawat' => '2026/11/16/000001',
            'reason' => 'Salah input pasien',
        ]);
        $this->assertSame(200, $voidRes['status']);
        $voidData = json_decode($voidRes['body'], true);
        $this->assertSame('FAKTUR / KUITANSI TIDAK BERLAKU', $voidData['data']['stamp']);

        // 4. POS Apotek Umum
        $posRes = $this->controller->apiPosApotekUmum($this->kasir, [
            'payment_method' => 'Debit EDC',
            'items' => [['kode_brng' => 'OBT001', 'jml' => 3]],
        ]);
        $this->assertSame(200, $posRes['status']);
        $posData = json_decode($posRes['body'], true);
        $this->assertStringStartsWith('PO-APT-', $posData['data']['no_nota']);
        $this->assertSame(6000.0, (float) $posData['data']['total']);
    }
}
