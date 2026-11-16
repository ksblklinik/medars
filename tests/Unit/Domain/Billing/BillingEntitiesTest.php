<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Entity\JurnalDetail;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\Entity\KamarInap;
use App\Domain\Billing\Entity\PosApotekUmumSale;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Billing\ValueObject\PosPaymentMethod;
use App\Domain\Registration\ValueObject\NoRawat;
use PHPUnit\Framework\TestCase;

class BillingEntitiesTest extends TestCase
{
    public function test4PillarClaimTypeAndLedgerStatus(): void
    {
        $tunai = ClaimType::fromString('Tunai');
        $kapitasi = ClaimType::fromString('Kapitasi');
        $nonKap = ClaimType::fromString('Non-Kapitasi');
        $piutang = ClaimType::fromString('Piutang');

        $this->assertSame('Tunai', $tunai->value());
        $this->assertSame('Kapitasi', $kapitasi->value());
        $this->assertSame('Non-Kapitasi', $nonKap->value());
        $this->assertSame('Piutang', $piutang->value());

        $bundled = LedgerStatus::fromString('Bundled');
        $this->assertSame('Bundled', $bundled->value());

        $item = new BillingLedgerItem(
            id: 1,
            noRawat: new NoRawat('2026/11/16/000001'),
            itemCode: 'RJ002',
            itemName: 'Konsultasi Dokter Umum',
            category: 'Tindakan',
            qty: 1.0,
            unitPrice: 25000.0,
            totalAmount: 25000.0,
            claimType: $tunai,
            status: LedgerStatus::fromString('Pending'),
            providerId: 'DR001'
        );

        $this->assertSame(25000.0, $item->totalAmount);
        $this->assertTrue($item->isTunai());
    }

    public function testJurnalEntryEnforcesStrictDebitCreditBalanceAndCreatesReversal(): void
    {
        $entry = new JurnalEntry(
            noJurnal: 'JRN-20261116-0001',
            tglJurnal: '2026-11-16',
            jamJurnal: '21:10:00',
            noBukti: '2026/11/16/000001',
            keterangan: 'Pembayaran Kasir Tunai',
            details: [
                new JurnalDetail('1101.01', 50000.0, 0.0), // Debet Kas Fisik
                new JurnalDetail('4101.01', 0.0, 50000.0), // Kredit Pendapatan Jasa
            ]
        );

        $this->assertSame(50000.0, $entry->totalDebet());
        $this->assertSame(50000.0, $entry->totalKredit());

        // Buat Jurnal Pembalik (Reversal)
        $reversal = $entry->createReversal('JRN-20261116-0002', '2026-11-16', '21:15:00', 'Void Kasir');
        $this->assertSame(50000.0, $reversal->totalDebet());
        $this->assertSame(50000.0, $reversal->totalKredit());
        // Debet & Kredit bertukar posisi
        $this->assertSame(0.0, $reversal->details[0]->debet);
        $this->assertSame(50000.0, $reversal->details[0]->kredit);
        $this->assertSame(50000.0, $reversal->details[1]->debet);
        $this->assertSame(0.0, $reversal->details[1]->kredit);
    }

    public function testUnbalancedJurnalThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('tidak seimbang');

        new JurnalEntry(
            noJurnal: 'JRN-ERR',
            tglJurnal: '2026-11-16',
            jamJurnal: '21:10:00',
            noBukti: 'INV-1',
            keterangan: 'Jurnal Salah',
            details: [
                new JurnalDetail('1101.01', 50000.0, 0.0),
                new JurnalDetail('4101.01', 0.0, 40000.0),
            ]
        );
    }

    public function testKamarInapAdmissionStatusAndPosApotekUmum5PaymentMethods(): void
    {
        $kamarAktif = new KamarInap(
            noRawat: new NoRawat('2026/11/16/000002'),
            kdKamar: 'VIP-01',
            tglMasuk: '2026-11-16',
            jamMasuk: '10:00:00',
            tglKeluar: '0000-00-00',
            jamKeluar: '00:00:00',
            lama: 1,
            trfKamar: 350000.0,
            ttlBiaya: 350000.0,
            sttsPulang: '-'
        );
        $this->assertTrue($kamarAktif->isStillAdmitted());

        $kamarPulang = new KamarInap(
            noRawat: new NoRawat('2026/11/16/000002'),
            kdKamar: 'VIP-01',
            tglMasuk: '2026-11-15',
            jamMasuk: '10:00:00',
            tglKeluar: '2026-11-16',
            jamKeluar: '12:00:00',
            lama: 1,
            trfKamar: 350000.0,
            ttlBiaya: 350000.0,
            sttsPulang: 'Atas Persetujuan Dokter'
        );
        $this->assertFalse($kamarPulang->isStillAdmitted());

        $posSale = new PosApotekUmumSale(
            noNota: 'PO-APT-202611160001',
            tglJual: '2026-11-16',
            jamJual: '21:00:00',
            paymentMethod: PosPaymentMethod::fromString('QRIS'),
            total: 45000.0,
            petugasNip: 'APT01'
        );
        $this->assertSame('QRIS', $posSale->paymentMethod->value());
        $this->assertStringStartsWith('PO-APT-', $posSale->noNota);
    }
}
