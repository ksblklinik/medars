<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Entity\JurnalDetail;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\Entity\KamarInap;
use App\Domain\Billing\Entity\PosApotekUmumSale;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Billing\ValueObject\PosPaymentMethod;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoKamarInapRepository;
use App\Infrastructure\Persistence\PdoPosApotekUmumRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoBillingRepositoriesTest extends TestCase
{
    private PDO $pdo;
    private PdoBillingLedgerRepository $ledgerRepo;
    private PdoKamarInapRepository $kamarRepo;
    private PdoPosApotekUmumRepository $posRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $this->kamarRepo = new PdoKamarInapRepository($this->pdo);
        $this->posRepo = new PdoPosApotekUmumRepository($this->pdo);
    }

    public function testLedgerAndJurnalPersistence(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            id: null,
            noRawat: $noRawat,
            itemCode: 'RJ002',
            itemName: 'Konsultasi Dokter',
            category: 'Tindakan',
            qty: 1.0,
            unitPrice: 50000.0,
            totalAmount: 50000.0,
            claimType: ClaimType::fromString('Tunai'),
            status: LedgerStatus::fromString('Pending'),
            providerId: 'DR001'
        ));

        $items = $this->ledgerRepo->findByNoRawat($noRawat);
        $this->assertCount(1, $items);
        $this->assertSame('Pending', $items[0]->status->value());

        $this->ledgerRepo->updateStatusByNoRawat($noRawat, LedgerStatus::fromString('Paid'));
        $updated = $this->ledgerRepo->findByNoRawat($noRawat);
        $this->assertSame('Paid', $updated[0]->status->value());

        // Simpan Jurnal Akuntansi
        $noJurnal = $this->ledgerRepo->getNextJurnalNumber(new \DateTimeImmutable('2026-11-16'));
        $this->assertSame('JRN-20261116-0001', $noJurnal);

        $entry = new JurnalEntry(
            noJurnal: $noJurnal,
            tglJurnal: '2026-11-16',
            jamJurnal: '21:15:00',
            noBukti: $noRawat->value(),
            keterangan: 'Lunas Tunai Kasir',
            details: [
                new JurnalDetail('1101.01', 50000.0, 0.0),
                new JurnalDetail('4101.01', 0.0, 50000.0),
            ]
        );
        $this->ledgerRepo->saveJurnal($entry);

        $jurnals = $this->ledgerRepo->findJurnalsByNoBukti($noRawat->value());
        $this->assertCount(1, $jurnals);
        $this->assertSame(50000.0, $jurnals[0]->totalDebet());
    }

    public function testKamarInapStatusChecker(): void
    {
        $noRawat = new NoRawat('2026/11/16/000002');
        $this->kamarRepo->save(new KamarInap($noRawat, 'VIP-01', '2026-11-16', '10:00:00'));

        $this->assertTrue($this->kamarRepo->isPatientStillAdmitted($noRawat));
        $this->assertCount(1, $this->kamarRepo->findByNoRawat($noRawat));

        $this->kamarRepo->save(new KamarInap(
            $noRawat, 'VIP-01', '2026-11-16', '10:00:00',
            '2026-11-17', '11:00:00', 1, 300000.0, 300000.0, 'Atas Persetujuan Dokter'
        ));
        $this->assertFalse($this->kamarRepo->isPatientStillAdmitted($noRawat));
    }

    public function testPosApotekUmumPrefixAndIsolation(): void
    {
        $nota = $this->posRepo->getNextNoNota(new \DateTimeImmutable('2026-11-16'));
        $this->assertSame('PO-APT-202611160001', $nota);

        $sale = new PosApotekUmumSale(
            noNota: $nota,
            tglJual: '2026-11-16',
            jamJual: '21:20:00',
            paymentMethod: PosPaymentMethod::fromString('QRIS'),
            total: 30000.0,
            petugasNip: 'APT01'
        );

        $this->posRepo->saveSale($sale, [
            ['kode_brng' => 'OBT001', 'jml' => 3.0, 'harga_jual' => 10000.0, 'subtotal' => 30000.0],
        ]);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM mlite_penjualan_apotek_umum WHERE no_nota LIKE 'PO-APT-%'")->fetchColumn();
        $this->assertSame(1, $count);
    }
}
