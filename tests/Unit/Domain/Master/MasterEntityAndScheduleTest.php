<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Master;

use App\Domain\Master\Entity\JadwalPraktik;
use App\Domain\Master\Entity\JenisPerawatan;
use App\Domain\Master\Entity\Penjab;
use App\Domain\Master\Exception\ScheduleOverlapException;
use App\Domain\Master\Service\ScheduleOverlapGuard;
use PHPUnit\Framework\TestCase;

class MasterEntityAndScheduleTest extends TestCase
{
    public function testPenjabEntity(): void
    {
        $penjab = new Penjab(
            kdPj: 'BPJ',
            pngJawab: 'BPJS Kesehatan',
            status: '1',
            namaPerusahaan: 'BPJS Kesehatan Cabang',
            alamatAsuransi: 'Jl. Teuku Umar No. 10',
            noTelp: '1500400'
        );

        $this->assertSame('BPJ', $penjab->getKdPj());
        $this->assertSame('BPJS Kesehatan', $penjab->getPngJawab());
        $this->assertTrue($penjab->isAktif());
    }

    public function testJenisPerawatanEntityAndCalculations(): void
    {
        $tindakan = new JenisPerawatan(
            kdJenisPrw: 'TND001',
            nmPerawatan: 'Pemeriksaan Dokter Spesialis',
            kdKategori: 'KAT01',
            material: 20000.0,
            tarifTindakanDr: 80000.0,
            tarifTindakanPr: 0.0,
            totalByrDr: 100000.0,
            totalByrPr: 0.0,
            status: '1'
        );

        $this->assertSame('TND001', $tindakan->getKdJenisPrw());
        $this->assertSame('Pemeriksaan Dokter Spesialis', $tindakan->getNmPerawatan());
        $this->assertSame(100000.0, $tindakan->getTotalByrDr());
        $this->assertTrue($tindakan->isAktif());
    }

    public function testJadwalPraktikProperties(): void
    {
        $jadwal = new JadwalPraktik(
            kdDokter: 'DR001',
            kdPoli: 'POL01',
            hariKerja: 'SENIN',
            jamMulai: '08:00:00',
            jamSelesai: '12:00:00',
            kuota: 30,
            prefik: 'A',
            room: 'R-101'
        );

        $this->assertSame('DR001', $jadwal->getKdDokter());
        $this->assertSame('SENIN', $jadwal->getHariKerja());
        $this->assertSame('08:00:00', $jadwal->getJamMulai());
        $this->assertSame('12:00:00', $jadwal->getJamSelesai());
        $this->assertSame(30, $jadwal->getKuota());
    }

    public function testScheduleOverlapDetection(): void
    {
        $guard = new ScheduleOverlapGuard();

        $existingSchedules = [
            new JadwalPraktik('DR001', 'POL01', 'SENIN', '08:00:00', '12:00:00', 30),
            new JadwalPraktik('DR001', 'POL02', 'SELASA', '08:00:00', '12:00:00', 30),
        ];

        // Jadwal hari Senin tapi jam sore -> Tidak overlap (PASS)
        $guard->assertNoOverlap(
            $existingSchedules,
            new JadwalPraktik('DR001', 'POL03', 'SENIN', '13:00:00', '16:00:00', 20)
        );

        // Jadwal hari Rabu -> Beda hari, tidak overlap (PASS)
        $guard->assertNoOverlap(
            $existingSchedules,
            new JadwalPraktik('DR001', 'POL01', 'RABU', '08:00:00', '12:00:00', 20)
        );

        // Jadwal hari Senin jam 10:00:00 s.d 14:00:00 -> Menabrak jadwal 08:00 s.d 12:00!
        $this->expectException(ScheduleOverlapException::class);
        $this->expectExceptionMessage('Jadwal dokter DR001 pada hari SENIN bertabrakan dengan jadwal yang sudah ada');

        $guard->assertNoOverlap(
            $existingSchedules,
            new JadwalPraktik('DR001', 'POL02', 'SENIN', '10:00:00', '14:00:00', 25)
        );
    }
}
