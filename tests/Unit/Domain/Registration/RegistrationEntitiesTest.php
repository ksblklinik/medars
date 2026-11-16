<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\Entity\PoliklinikSetting;
use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use PHPUnit\Framework\TestCase;

final class RegistrationEntitiesTest extends TestCase
{
    public function test_poliklinik_setting_enforces_data_driven_bpjs_allowance(): void
    {
        $poliUmum = new PoliklinikSetting(
            kdPoli: 'U01',
            nmPoli: 'Poli Umum',
            registrasiBaru: 25000.0,
            registrasiLama: 15000.0,
            allowBpjs: true
        );

        $poliEksekutif = new PoliklinikSetting(
            kdPoli: 'PEX',
            nmPoli: 'Poli Eksekutif',
            registrasiBaru: 150000.0,
            registrasiLama: 100000.0,
            allowBpjs: false
        );

        $this->assertTrue($poliUmum->isPenjaminAllowed('BPJ'));
        $this->assertTrue($poliUmum->isPenjaminAllowed('A09'));

        // Poli PEX ditolak untuk BPJ berdasarkan pengaturan poli (bukan hardcode)
        $this->assertFalse($poliEksekutif->isPenjaminAllowed('BPJ'));
        $this->assertTrue($poliEksekutif->isPenjaminAllowed('A09'));
    }

    public function test_pasien_calculates_age_in_years_months_or_days_accurately(): void
    {
        $refDate = new \DateTimeImmutable('2026-11-16');

        // Dewasa (Tahun)
        $adult = new Pasien(
            noRkmMedis: new NoRkmMedis('000123'),
            nmPasien: 'Budi Santoso',
            nik: '3201010101900001',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('1990-05-10')
        );
        $this->assertSame(['umur' => 36, 'satuan' => 'Th'], $adult->hitungUmurPada($refDate));

        // Bayi (Bulan)
        $infant = new Pasien(
            noRkmMedis: new NoRkmMedis('000124'),
            nmPasien: 'Bayi Melati',
            nik: '-',
            jk: 'P',
            tglLahir: new \DateTimeImmutable('2026-08-10')
        );
        $this->assertSame(['umur' => 3, 'satuan' => 'Bl'], $infant->hitungUmurPada($refDate));

        // Neonatus (Hari)
        $newborn = new Pasien(
            noRkmMedis: new NoRkmMedis('000125'),
            nmPasien: 'By. Ny. Sari',
            nik: '-',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('2026-11-10')
        );
        $this->assertSame(['umur' => 6, 'satuan' => 'Hr'], $newborn->hitungUmurPada($refDate));
    }

    public function test_pasien_rejects_birth_date_in_future_relative_to_ref_date(): void
    {
        $refDate = new \DateTimeImmutable('2026-11-16');
        $futureBorn = new Pasien(
            noRkmMedis: new NoRkmMedis('000126'),
            nmPasien: 'Anak Masa Depan',
            nik: '-',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('2026-11-20')
        );

        $this->expectException(\InvalidArgumentException::class);
        $futureBorn->hitungUmurPada($refDate);
    }

    public function test_reg_periksa_tracks_service_status_and_clinical_lock(): void
    {
        $reg = new RegPeriksa(
            noRawat: new NoRawat('2026/11/16/000001'),
            noReg: new NoReg('001'),
            noRkmMedis: new NoRkmMedis('000123'),
            kdPoli: 'U01',
            kdDokter: 'DR001',
            kdPj: 'A09',
            tglRegistrasi: new \DateTimeImmutable('2026-11-16'),
            jamReg: '08:15:00',
            biayaReg: 25000.0,
            stts: 'Belum',
            sttsDaftar: 'Baru',
            statusBayar: 'Belum Bayar',
            statusPoli: 'Baru'
        );

        $this->assertFalse($reg->isServed());
        $this->assertFalse($reg->isPaid());

        $servedReg = $reg->withStatus('Sudah');
        $this->assertTrue($servedReg->isServed());
        $this->assertFalse($reg->isServed(), 'Entity bersifat immutable');
    }

    public function test_reg_periksa_supports_prefik_room_and_shift(): void
    {
        $reg = new RegPeriksa(
            noRawat: new NoRawat('2026/11/16/000001'),
            noReg: new NoReg('001'),
            noRkmMedis: new NoRkmMedis('000123'),
            kdPoli: 'U01',
            kdDokter: 'DR001',
            kdPj: 'A09',
            tglRegistrasi: new \DateTimeImmutable('2026-11-16'),
            jamReg: '08:15:00',
            biayaReg: 25000.0,
            prefik: 'A',
            room: '1',
            shift: '07:00:00'
        );

        $this->assertSame('A', $reg->prefik);
        $this->assertSame('1', $reg->room);
        $this->assertSame('07:00:00', $reg->shift);

        $updatedStatus = $reg->withStatus('Sudah');
        $this->assertSame('A', $updatedStatus->prefik);
        $this->assertSame('1', $updatedStatus->room);
        $this->assertSame('07:00:00', $updatedStatus->shift);

        $updatedRawat = $reg->withNoRawat(new NoRawat('2026/11/16/000099'));
        $this->assertSame('A', $updatedRawat->prefik);
        $this->assertSame('1', $updatedRawat->room);
        $this->assertSame('07:00:00', $updatedRawat->shift);
    }
}
