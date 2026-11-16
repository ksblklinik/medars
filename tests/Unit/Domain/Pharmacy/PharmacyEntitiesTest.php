<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pharmacy;

use App\Domain\Pharmacy\Entity\DataBarang;
use App\Domain\Pharmacy\Entity\DetailPemberianObat;
use App\Domain\Pharmacy\Entity\GudangBarang;
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
use PHPUnit\Framework\TestCase;

class PharmacyEntitiesTest extends TestCase
{
    public function testUniformFlatPricingAndSmartClaimSorting(): void
    {
        $pricing = new UniformPricing(
            hargaTunai: 15000.0,
            hargaKapitasi: 10000.0,
            hargaNonKapitasi: 12000.0,
            jualBebas: 16500.0,
            dasar: 8000.0,
            hBeli: 8500.0
        );

        // Pasien Non-BPJS: Tunai tetap tersedia, tanpa gate kd_pj memblokir opsi lain yang > 0
        $nonBpjsOptions = $pricing->getAvailableClaimOptions(isBpjs: false);
        $this->assertSame(['Tunai', 'Kapitasi', 'Non-Kapitasi'], array_column($nonBpjsOptions, 'claim_type'));
        $this->assertSame(15000.0, $pricing->getPriceForClaim(JenisKlaim::fromString('Tunai')));

        // Pasien BPJS: Smart Claim Sorting (Kapitasi -> Non-Kapitasi -> Tunai)
        $bpjsOptions = $pricing->getAvailableClaimOptions(isBpjs: true);
        $this->assertSame(['Kapitasi', 'Non-Kapitasi', 'Tunai'], array_column($bpjsOptions, 'claim_type'));
        $this->assertSame(10000.0, $pricing->getPriceForClaim(JenisKlaim::fromString('Kapitasi')));

        // Obat tanpa tarif kapitasi (harga_kapitasi = 0) -> Kapitasi tidak muncul
        $tunaiOnly = new UniformPricing(hargaTunai: 25000.0);
        $this->assertSame(['Tunai'], array_column($tunaiOnly->getAvailableClaimOptions(isBpjs: true), 'claim_type'));
    }

    public function testDataBarangAndDepoBangsalIsolation(): void
    {
        $obat = new DataBarang(
            kodeBrng: 'OBT001',
            namaBrng: 'Paracetamol 500mg Tablet',
            kodeSat: 'TAB',
            pricing: new UniformPricing(hargaTunai: 500.0, hargaKapitasi: 300.0),
            status: '1'
        );

        $this->assertSame('OBT001', $obat->kodeBrng);
        $this->assertTrue($obat->isActive());

        $depoIgd = new DepoBangsal('IGD');
        $depoApt = new DepoBangsal('APT');
        $depoKlinik = new DepoBangsal('FAR01');

        $this->assertTrue($depoIgd->isIgd());
        $this->assertTrue($depoApt->isApotekUmum());
        $this->assertFalse($depoKlinik->isApotekUmum());
    }

    public function testGudangBarangPreventsNegativeStock(): void
    {
        $stok = new GudangBarang(
            kodeBrng: 'OBT001',
            kdBangsal: new DepoBangsal('FAR01'),
            noBatch: 'BATCH-2026A',
            noFaktur: 'FKT-001',
            stok: 10.0
        );

        $sisa = $stok->deduct(4.0);
        $this->assertSame(6.0, $sisa->stok);

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Stok tidak mencukupi');
        $sisa->deduct(7.0);
    }

    public function testResepObatAndCompoundedVsNonCompoundedItems(): void
    {
        $noResep = new NoResep('202611160001');
        $resep = new ResepObat(
            noResep: $noResep,
            tglPeresepan: '2026-11-16',
            jamPeresepan: '09:00:00',
            noRawat: new NoRawat('2026/11/16/000001'),
            kdDokter: 'DR001',
            tglPerawatan: '0000-00-00',
            jam: '00:00:00',
            status: 'ralan'
        );

        $this->assertFalse($resep->isDispensed());

        $nonRacik = new ResepDokterNonRacikan($noResep, 'OBT001', 10.0, '3x1 sesudah makan');
        $this->assertSame(10.0, $nonRacik->jml);

        $detail1 = new ResepDokterRacikanDetail($noResep, '1', 'OBT001', 1.0, 1.0, '250mg', 5.0);
        $racikan = new ResepDokterRacikan(
            noResep: $noResep,
            noRacik: '1',
            namaRacik: 'Puyer Demam Anak',
            kdRacik: 'R01',
            jmlDr: 10,
            aturanPakai: '3x1 bungkus',
            keterangan: 'Habiskan',
            details: [$detail1]
        );

        $this->assertCount(1, $racikan->details);
        $this->assertSame('Puyer Demam Anak', $racikan->namaRacik);
    }

    public function testDetailPemberianObatComposite6Keys(): void
    {
        $pemberian = new DetailPemberianObat(
            tglPerawatan: '2026-11-16',
            jam: '23:59:59',
            noRawat: new NoRawat('2026/11/16/000001'),
            kodeBrng: 'OBT001',
            noBatch: 'B01',
            noFaktur: 'F01',
            hBeli: 300.0,
            biayaObat: 500.0,
            jml: 10.0,
            embalase: 0.0,
            tuslah: 0.0,
            total: 5000.0,
            status: 'Ralan',
            kdBangsal: new DepoBangsal('FAR01'),
            jenisKlaim: JenisKlaim::fromString('Tunai')
        );

        $this->assertSame('B01', $pemberian->noBatch);
        $this->assertSame('F01', $pemberian->noFaktur);
        $this->assertSame('Tunai', $pemberian->jenisKlaim->value());
    }
}
