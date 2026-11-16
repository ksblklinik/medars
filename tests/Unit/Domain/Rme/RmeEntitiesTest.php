<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rme;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Entity\DiagnosaPasien;
use App\Domain\Rme\Entity\Odontogram;
use App\Domain\Rme\Entity\PemeriksaanRalan;
use App\Domain\Rme\Entity\PemeriksaanRanap;
use App\Domain\Rme\Entity\RmeMetadata;
use App\Domain\Rme\Entity\SuratSakit;
use App\Domain\Rme\Entity\TriaseIgd;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;
use PHPUnit\Framework\TestCase;

class RmeEntitiesTest extends TestCase
{
    public function testTandaVitalAndKesadaranStrictModeSanitization(): void
    {
        $ttv = new TandaVital(
            suhuTubuh: '36.7',
            tensi: '',
            sistole: '120',
            diastole: '80',
            nadi: '82',
            respirasi: '',
            tinggi: '170',
            berat: '65',
            spo2: '',
            gcs: '',
            lingkarPerut: ''
        );

        $this->assertSame('36.7', $ttv->suhuTubuh);
        $this->assertSame('-', $ttv->tensi);
        $this->assertSame('-', $ttv->respirasi);
        $this->assertSame('-', $ttv->spo2);
        $this->assertSame('-', $ttv->gcs);
        $this->assertSame('-', $ttv->lingkarPerut);

        $kesadaranDefault = Kesadaran::fromString('');
        $this->assertSame('Compos Mentis', $kesadaranDefault->value());

        $kesadaranValid = Kesadaran::fromString('Somnolence');
        $this->assertSame('Somnolence', $kesadaranValid->value());
    }

    public function testInvalidKesadaranThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Kesadaran::fromString('PingsanTidakStandar');
    }

    public function testPemeriksaanRalanCompositeKeyAndSanitization(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');
        $ttv = new TandaVital(suhuTubuh: '37.2', tensi: '110/70');
        $kesadaran = Kesadaran::fromString('Compos Mentis');

        $soap = new PemeriksaanRalan(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '09:15:00',
            tandaVital: $ttv,
            kesadaran: $kesadaran,
            keluhan: 'Demam sejak 2 hari lalu',
            pemeriksaan: 'Faring hiperemis',
            alergi: '',
            rtl: '',
            penilaian: '',
            instruksi: '',
            evaluasi: '',
            nip: 'DR001'
        );

        $this->assertSame('2026/11/16/000001', $soap->noRawat->value());
        $this->assertSame('2026-11-16', $soap->tglPerawatan);
        $this->assertSame('09:15:00', $soap->jamRawat);
        $this->assertSame('-', $soap->alergi);
        $this->assertSame('-', $soap->rtl);
        $this->assertSame('-', $soap->penilaian);
        $this->assertSame('-', $soap->instruksi);
        $this->assertSame('-', $soap->evaluasi);
        $this->assertSame('DR001', $soap->nip);
    }

    public function testPemeriksaanRanapEntity(): void
    {
        $noRawat = new NoRawat('2026/11/16/000002');
        $cppt = new PemeriksaanRanap(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '14:00:00',
            tandaVital: new TandaVital(suhuTubuh: '36.5', tensi: '120/80'),
            kesadaran: Kesadaran::fromString('Compos Mentis'),
            keluhan: 'Nyeri luka post-op berkurang',
            pemeriksaan: 'Luka kering, tidak ada pus',
            alergi: '-',
            rtl: 'Lanjutkan terapi antibiotik IV',
            penilaian: 'Post-op hari ke-2 baik',
            instruksi: 'Mobilisasi bertahap',
            evaluasi: 'Observasi TTV tiap 6 jam',
            nip: 'DR001'
        );

        $this->assertSame('2026/11/16/000002', $cppt->noRawat->value());
        $this->assertSame('Lanjutkan terapi antibiotik IV', $cppt->rtl);
    }

    public function testRmeMetadataSmartPeekShield(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');

        // 1. Jika isMetaDirty === false -> dilarang disimpan (Smart Peek)
        $peekOnly = new RmeMetadata(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '09:15:00',
            metaKey: 'meta_kebidanan',
            metaValueJson: '{"gravida":2}',
            isMetaDirty: false
        );
        $this->assertFalse($peekOnly->shouldPersist());

        // 2. Jika payload kosong '{}' -> dilarang disimpan
        $emptyJson = new RmeMetadata(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '09:15:00',
            metaKey: 'meta_kebidanan',
            metaValueJson: '{}',
            isMetaDirty: true
        );
        $this->assertFalse($emptyJson->shouldPersist());

        // 3. Jika isMetaDirty === true dan JSON valid non-kosong -> wajib disimpan
        $validMeta = new RmeMetadata(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '09:15:00',
            metaKey: 'meta_prb',
            metaValueJson: '{"diagnosa_kronis":"Hipertensi","obat":["Amlodipine 10mg 30 tab"]}',
            isMetaDirty: true
        );
        $this->assertTrue($validMeta->shouldPersist());
    }

    public function testDiagnosaPasienAndTriaseIgd(): void
    {
        $dx = new DiagnosaPasien(
            noRawat: new NoRawat('2026/11/16/000001'),
            kdPenyakit: 'J06.9',
            status: 'Ralan',
            prioritas: 1,
            statusPenyakit: 'Baru'
        );

        $this->assertSame('J06.9', $dx->kdPenyakit);
        $this->assertTrue($dx->isPrimer());

        $triase = new TriaseIgd(
            noRawat: new NoRawat('2026/11/16/000003'),
            tglTriase: '2026-11-16',
            jamTriase: '20:10:00',
            skalaTriase: 'Emergensi',
            keluhanUtama: 'Nyeri dada menjalar ke lengan kiri',
            tandaVital: new TandaVital(tensi: '160/100', spo2: '94%'),
            nipPetugas: 'PET001'
        );

        $this->assertSame('Emergensi', $triase->skalaTriase);
    }

    public function testOdontogramSupportsUneStatusAndFdiValidation(): void
    {
        $odo = new Odontogram(
            id: 15,
            noRkmMedis: new NoRkmMedis('000001'),
            noRawat: new NoRawat('2026/11/16/000001'),
            tglPemeriksaan: '2026-11-16',
            nomorGigi: '18',
            kondisi: 'UNE',
            catatan: 'Gigi bungsu belum erupsi',
            idUser: 4
        );

        $this->assertSame(15, $odo->id);
        $this->assertSame('18', $odo->nomorGigi);
        $this->assertSame('UNE', $odo->kondisi);

        $this->expectException(\InvalidArgumentException::class);
        new Odontogram(
            id: null,
            noRkmMedis: new NoRkmMedis('000001'),
            noRawat: new NoRawat('2026/11/16/000001'),
            tglPemeriksaan: '2026-11-16',
            nomorGigi: '99',
            kondisi: 'CAR',
            catatan: '',
            idUser: 4
        );
    }

    public function testSuratSakitCalculatesDuration(): void
    {
        $surat = new SuratSakit(
            noSurat: 'SKT/2026/11/0001',
            noRawat: new NoRawat('2026/11/16/000001'),
            tanggalAwal: new \DateTimeImmutable('2026-11-16'),
            tanggalAkhir: new \DateTimeImmutable('2026-11-18')
        );

        $this->assertSame('3 Hari', $surat->lamaSakit);
    }
}
