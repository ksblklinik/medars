<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Staff;

use App\Domain\Staff\Entity\Dokter;
use App\Domain\Staff\Entity\Pegawai;
use App\Domain\Staff\Entity\Petugas;
use PHPUnit\Framework\TestCase;

class StaffEntityTest extends TestCase
{
    public function testPegawaiEntity(): void
    {
        $pegawai = new Pegawai(
            nik: 'PEG001',
            nama: 'Ahmad Subarjo',
            jk: 'Pria',
            jbtn: 'Administrasi',
            departemen: 'IT & SIMRS',
            status: 'AKTIF'
        );

        $this->assertSame('PEG001', $pegawai->getNik());
        $this->assertSame('Ahmad Subarjo', $pegawai->getNama());
        $this->assertSame('Pria', $pegawai->getJk());
        $this->assertSame('Administrasi', $pegawai->getJbtn());
        $this->assertTrue($pegawai->isAktif());
    }

    public function testDokterEntity(): void
    {
        $dokter = new Dokter(
            kdDokter: 'DR001',
            nmDokter: 'dr. Andi Pratama, Sp.PD',
            jk: 'L',
            noIjnPraktek: 'SIP/2026/001/DPMPTSP',
            status: '1',
            almtTgl: 'Jl. Merdeka No. 45',
            noTelp: '08123456789'
        );

        $this->assertSame('DR001', $dokter->getKdDokter());
        $this->assertSame('dr. Andi Pratama, Sp.PD', $dokter->getNmDokter());
        $this->assertSame('L', $dokter->getJk());
        $this->assertSame('SIP/2026/001/DPMPTSP', $dokter->getNoIjnPraktek());
        $this->assertTrue($dokter->isAktif());
    }

    public function testPetugasEntity(): void
    {
        $petugas = new Petugas(
            nip: 'PET002',
            nama: 'Siti Aminah, S.Tr.Kes',
            jk: 'P',
            jbtn: 'Perawat Pelaksana',
            status: '1',
            almtTgl: 'Jl. Cempaka No. 12',
            noTelp: '08987654321'
        );

        $this->assertSame('PET002', $petugas->getNip());
        $this->assertSame('Siti Aminah, S.Tr.Kes', $petugas->getNama());
        $this->assertSame('P', $petugas->getJk());
        $this->assertSame('Perawat Pelaksana', $petugas->getJbtn());
        $this->assertTrue($petugas->isAktif());
    }
}
