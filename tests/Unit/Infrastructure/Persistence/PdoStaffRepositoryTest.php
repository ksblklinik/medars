<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Staff\Entity\Dokter;
use App\Domain\Staff\Entity\Pegawai;
use App\Domain\Staff\Entity\Petugas;
use App\Infrastructure\Persistence\PdoDokterRepository;
use App\Infrastructure\Persistence\PdoPegawaiRepository;
use App\Infrastructure\Persistence\PdoPetugasRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoStaffRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoDokterRepository $dokterRepo;
    private PdoPetugasRepository $petugasRepo;
    private PdoPegawaiRepository $pegawaiRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Skema tabel ksmlite: dokter, petugas, pegawai, reg_periksa
        $this->pdo->exec("
            CREATE TABLE dokter (
                kd_dokter TEXT PRIMARY KEY,
                nm_dokter TEXT NOT NULL,
                jk TEXT NOT NULL,
                no_ijn_praktek TEXT DEFAULT '',
                status TEXT DEFAULT '1',
                almt_tgl TEXT DEFAULT '',
                no_telp TEXT DEFAULT ''
            );

            CREATE TABLE petugas (
                nip TEXT PRIMARY KEY,
                nama TEXT NOT NULL,
                jk TEXT NOT NULL,
                jbtn TEXT NOT NULL,
                status TEXT DEFAULT '1',
                almt_tgl TEXT DEFAULT '',
                no_telp TEXT DEFAULT ''
            );

            CREATE TABLE pegawai (
                nik TEXT PRIMARY KEY,
                nama TEXT NOT NULL,
                jk TEXT NOT NULL,
                jbtn TEXT NOT NULL,
                departemen TEXT DEFAULT '',
                status TEXT DEFAULT 'AKTIF'
            );

            CREATE TABLE reg_periksa (
                no_rawat TEXT PRIMARY KEY,
                kd_dokter TEXT NOT NULL
            );
        ");

        $this->dokterRepo = new PdoDokterRepository($this->pdo);
        $this->petugasRepo = new PdoPetugasRepository($this->pdo);
        $this->pegawaiRepo = new PdoPegawaiRepository($this->pdo);
    }

    public function testSaveAndFindDokter(): void
    {
        $dokter = new Dokter(
            kdDokter: 'DR001',
            nmDokter: 'dr. Andi Pratama, Sp.PD',
            jk: 'L',
            noIjnPraktek: 'SIP/2026/001',
            status: '1',
            almtTgl: 'Klinik Utama Sehat',
            noTelp: '08123456789'
        );

        $this->dokterRepo->save($dokter);

        $found = $this->dokterRepo->findByKode('DR001');
        $this->assertNotNull($found);
        $this->assertSame('dr. Andi Pratama, Sp.PD', $found->getNmDokter());
        $this->assertTrue($found->isAktif());

        $list = $this->dokterRepo->findAllActive();
        $this->assertCount(1, $list);
        $this->assertSame('DR001', $list[0]->getKdDokter());
    }

    public function testDokterWithClinicalHistoryCannotBeHardDeleted(): void
    {
        $dokter = new Dokter(
            kdDokter: 'DR002',
            nmDokter: 'dr. Siti Spesialis Mata',
            jk: 'P',
            noIjnPraktek: 'SIP/2026/002',
            status: '1'
        );
        $this->dokterRepo->save($dokter);

        // Simulasi bahwa dokter DR002 sudah memiliki riwayat registrasi pasien
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, kd_dokter) VALUES ('2026/11/16/000001', 'DR002')");

        $this->assertFalse($this->dokterRepo->canHardDelete('DR002'));

        // Soft deactivation
        $this->dokterRepo->deactivate('DR002');
        $updated = $this->dokterRepo->findByKode('DR002');
        $this->assertFalse($updated->isAktif());
        $this->assertCount(0, $this->dokterRepo->findAllActive());
    }

    public function testSaveAndFindPetugas(): void
    {
        $petugas = new Petugas(
            nip: 'PET001',
            nama: 'Sri Wahyuni, Amd.Kep',
            jk: 'P',
            jbtn: 'Perawat',
            status: '1',
            almtTgl: 'Jl. Melati No. 5',
            noTelp: '08567890123'
        );

        $this->petugasRepo->save($petugas);

        $found = $this->petugasRepo->findByNip('PET001');
        $this->assertNotNull($found);
        $this->assertSame('Sri Wahyuni, Amd.Kep', $found->getNama());
        $this->assertTrue($found->isAktif());
    }

    public function testSaveAndFindPegawai(): void
    {
        $pegawai = new Pegawai(
            nik: 'PEG009',
            nama: 'Bambang Sudarsono',
            jk: 'Pria',
            jbtn: 'Kasir',
            departemen: 'Keuangan',
            status: 'AKTIF'
        );

        $this->pegawaiRepo->save($pegawai);

        $found = $this->pegawaiRepo->findByNik('PEG009');
        $this->assertNotNull($found);
        $this->assertSame('Bambang Sudarsono', $found->getNama());
        $this->assertSame('Keuangan', $found->getDepartemen());
        $this->assertTrue($found->isAktif());
    }
}
