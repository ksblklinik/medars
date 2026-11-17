<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoDokterRanapRepository;
use PHPUnit\Framework\TestCase;

final class PdoDokterRanapRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoDokterRanapRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoDokterRanapRepository($this->pdo);
    }

    public function testListRanapPatientsAndSaveCppt(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000080', 'Hendra Wijaya', '1978-05-05', 'L');
            INSERT INTO bangsal (kd_bangsal, nm_bangsal, status) VALUES ('B01', 'Mawar', '1');
            INSERT INTO kamar (kd_kamar, kd_bangsal, trf_kamar, status, kelas, statusdata) VALUES ('MWR-01', 'B01', 200000, 'ISI', 'Kelas 2', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/17/000080', '{$today}', '08:00:00', 'DR01', '000080', 'IGDK', 'A09', 'Dirawat', 'Ranap', 'Belum Bayar');
            INSERT INTO kamar_inap (no_rawat, kd_kamar, tgl_masuk, jam_masuk, trf_kamar, ttl_biaya, stts_pulang)
            VALUES ('2026/11/17/000080', 'MWR-01', '{$today}', '08:30:00', 200000, 200000, '-');
        ");

        $patients = $this->repo->listRanapPatients('Hendra');
        $this->assertCount(1, $patients);
        $this->assertSame('MWR-01', $patients[0]['kd_kamar']);

        $this->repo->saveCppt([
            'no_rawat' => '2026/11/17/000080',
            'tgl_perawatan' => $today,
            'jam_rawat' => '09:15:00',
            'keluhan' => 'Demam turun',
            'pemeriksaan' => 'CM, TD 120/80',
            'penilaian' => 'Obs. Febris membaik',
            'rtl' => 'Lanjut terapi oral',
            'nip' => 'DR01',
        ]);

        $cppt = $this->repo->listCppt('2026/11/17/000080');
        $this->assertCount(1, $cppt);
        $this->assertSame('Obs. Febris membaik', $cppt[0]['penilaian']);
    }
}
