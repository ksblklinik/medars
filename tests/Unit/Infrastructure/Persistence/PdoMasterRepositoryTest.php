<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Master\Entity\JadwalPraktik;
use App\Domain\Master\Entity\JenisPerawatan;
use App\Domain\Master\Entity\Penjab;
use App\Domain\Master\Exception\ScheduleOverlapException;
use App\Domain\Master\Service\ScheduleOverlapGuard;
use App\Infrastructure\Persistence\PdoJadwalRepository;
use App\Infrastructure\Persistence\PdoJenisPerawatanRepository;
use App\Infrastructure\Persistence\PdoPenjabRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoMasterRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoPenjabRepository $penjabRepo;
    private PdoJadwalRepository $jadwalRepo;
    private PdoJenisPerawatanRepository $tindakanRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE penjab (
                kd_pj TEXT PRIMARY KEY,
                png_jawab TEXT NOT NULL,
                status TEXT DEFAULT '1',
                nama_perusahaan TEXT DEFAULT '',
                alamat_asuransi TEXT DEFAULT '',
                no_telp TEXT DEFAULT ''
            );

            CREATE TABLE jadwal (
                kd_dokter TEXT NOT NULL,
                kd_poli TEXT NOT NULL,
                hari_kerja TEXT NOT NULL,
                jam_mulai TEXT NOT NULL,
                jam_selesai TEXT NOT NULL,
                kuota INTEGER DEFAULT 0,
                prefik TEXT DEFAULT '',
                room TEXT DEFAULT '',
                PRIMARY KEY (kd_dokter, kd_poli, hari_kerja, jam_mulai)
            );

            CREATE TABLE jns_perawatan (
                kd_jenis_prw TEXT PRIMARY KEY,
                nm_perawatan TEXT NOT NULL,
                kd_kategori TEXT NOT NULL,
                material REAL DEFAULT 0.0,
                tarif_tindakandr REAL DEFAULT 0.0,
                tarif_tindakanpr REAL DEFAULT 0.0,
                total_byrdr REAL DEFAULT 0.0,
                total_byrpr REAL DEFAULT 0.0,
                status TEXT DEFAULT '1'
            );
        ");

        $this->penjabRepo = new PdoPenjabRepository($this->pdo);
        $this->jadwalRepo = new PdoJadwalRepository($this->pdo, new ScheduleOverlapGuard());
        $this->tindakanRepo = new PdoJenisPerawatanRepository($this->pdo);
    }

    public function testSaveAndFindPenjab(): void
    {
        $penjab = new Penjab(
            kdPj: 'BPJ',
            pngJawab: 'BPJS Kesehatan',
            status: '1',
            namaPerusahaan: 'BPJS KCU',
            alamatAsuransi: 'Jl. Pemuda',
            noTelp: '021-123456'
        );

        $this->penjabRepo->save($penjab);

        $found = $this->penjabRepo->findByKode('BPJ');
        $this->assertNotNull($found);
        $this->assertSame('BPJS Kesehatan', $found->getPngJawab());
        $this->assertTrue($found->isAktif());

        $list = $this->penjabRepo->findAllActive();
        $this->assertCount(1, $list);
    }

    public function testSaveJadwalWithOverlapProtection(): void
    {
        $jadwal1 = new JadwalPraktik('DR001', 'POL01', 'SENIN', '08:00:00', '12:00:00', 30, 'A', '101');
        $this->jadwalRepo->save($jadwal1);

        $found = $this->jadwalRepo->findByDokterAndHari('DR001', 'SENIN');
        $this->assertCount(1, $found);
        $this->assertSame('08:00:00', $found[0]->getJamMulai());

        // Coba masukkan jadwal kedua untuk dokter yang sama di poli berbeda tapi jam bertabrakan (10:00 s.d 14:00)
        $jadwalTabrakan = new JadwalPraktik('DR001', 'POL02', 'SENIN', '10:00:00', '14:00:00', 20, 'B', '102');

        $this->expectException(ScheduleOverlapException::class);
        $this->jadwalRepo->save($jadwalTabrakan);
    }

    public function testSaveAndFindJenisPerawatan(): void
    {
        $tindakan = new JenisPerawatan(
            kdJenisPrw: 'PRW001',
            nmPerawatan: 'Konsultasi Dokter Umum',
            kdKategori: 'KAT01',
            material: 5000.0,
            tarifTindakanDr: 45000.0,
            tarifTindakanPr: 0.0,
            totalByrDr: 50000.0,
            totalByrPr: 0.0,
            status: '1'
        );

        $this->tindakanRepo->save($tindakan);

        $found = $this->tindakanRepo->findByKode('PRW001');
        $this->assertNotNull($found);
        $this->assertSame('Konsultasi Dokter Umum', $found->getNmPerawatan());
        $this->assertSame(50000.0, $found->getTotalByrDr());
    }
}
