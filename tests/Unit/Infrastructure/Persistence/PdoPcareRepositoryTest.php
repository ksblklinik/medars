<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoPcareRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoPcareRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoPcareRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoPcareRepository($this->pdo);
    }

    public function testPendaftaranKunjunganRujukanObatTindakanLifecycle(): void
    {
        $pendaftaran = $this->repo->savePendaftaran([
            'no_rawat' => '2026/11/17/000101',
            'tglDaftar' => '2026-11-17',
            'noKartu' => '0001234567890',
            'nmPasien' => 'Siti FKTP',
            'kdPoli' => '001',
            'nmPoli' => 'Poli Umum',
            'keluhan' => 'Demam 2 hari',
            'sistole' => 120,
            'diastole' => 80,
        ]);
        $this->assertNotEmpty($pendaftaran['noUrut']);
        $this->assertCount(1, $this->repo->listPendaftaran('2026-11-17', '2026-11-17'));

        $kunjungan = $this->repo->saveKunjungan([
            'no_rawat' => '2026/11/17/000101',
            'tglDaftar' => '2026-11-17',
            'noKartu' => '0001234567890',
            'nmPasien' => 'Siti FKTP',
            'kdPoli' => '001',
            'nmPoli' => 'Poli Umum',
            'keluhan' => 'Demam 2 hari',
            'kdDiagnosa1' => 'J00',
            'nmDiagnosa1' => 'Acute nasopharyngitis',
            'kdStatusPulang' => '4',
            'nmStatusPulang' => 'Rujuk Vertikal',
            'kdDokter' => 'D001',
            'nmDokter' => 'dr. Budi FKTP',
        ]);
        $noKunjungan = $kunjungan['noKunjungan'];
        $this->assertNotEmpty($noKunjungan);
        $this->assertNotNull($this->repo->findKunjungan($noKunjungan));
        $this->assertCount(1, $this->repo->listKunjungan('2026-11-17', '2026-11-17'));

        $rujukan = $this->repo->saveRujukan([
            'noKunjungan' => $noKunjungan,
            'no_rawat' => '2026/11/17/000101',
            'tglEstRujuk' => '2026-11-18',
            'kdppk' => '0112R002',
            'nmppk' => 'RSUD Rujukan',
            'kdSubSpesialis' => 'INT',
            'nmSubSpesialis' => 'Penyakit Dalam',
            'catatan' => 'Evaluasi lanjut',
        ]);
        $this->assertSame($noKunjungan, $rujukan['noKunjungan']);
        $this->assertNotNull($this->repo->findRujukan($noKunjungan));
        $this->assertCount(1, $this->repo->listRujukan());

        $obat = $this->repo->saveObat([
            'noKunjungan' => $noKunjungan,
            'kdObat' => 'OBT-PCT',
            'nmObat' => 'Paracetamol 500mg',
            'signa1' => 3,
            'signa2' => 1,
            'jmlObat' => 10,
        ]);
        $this->assertSame('Paracetamol 500mg', $obat['nmObat']);
        $this->assertCount(1, $this->repo->listObat($noKunjungan));

        $tindakan = $this->repo->saveTindakan([
            'noKunjungan' => $noKunjungan,
            'kdTindakan' => 'LAB-DARAH',
            'nmTindakan' => 'Cek Darah Lengkap FKTP',
            'biaya' => 45000,
            'keterangan' => 'Lab penunjang FKTP',
        ]);
        $this->assertSame('Cek Darah Lengkap FKTP', $tindakan['nmTindakan']);
        $this->assertCount(1, $this->repo->listTindakan($noKunjungan));
    }
}
