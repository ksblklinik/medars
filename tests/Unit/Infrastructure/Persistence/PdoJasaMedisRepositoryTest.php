<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoJasaMedisRepository;
use PHPUnit\Framework\TestCase;

final class PdoJasaMedisRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoJasaMedisRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoJasaMedisRepository($this->pdo);
    }

    public function testSummarizeByDoctorAndListDetails(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000090', 'Sari', '1991-01-01', 'P');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000090', '{$today}', '08:00:00', 'DR01', '000090', 'U01', 'BPJ', 'Belum Bayar');
            INSERT INTO jns_perawatan (kd_jenis_prw, nm_perawatan, material, tarif_tindakandr, total_byrdr, status)
            VALUES ('TND01', 'Konsultasi Poli', 10000, 40000, 50000, '1');
            INSERT INTO rawat_jl_dr (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, material, tarif_tindakandr, biaya_rawat, jenis_klaim)
            VALUES ('2026/11/17/000090', 'TND01', 'DR01', '{$today}', '08:10:00', 10000, 40000, 50000, 'Tunai');
            INSERT INTO rawat_jl_dr (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, material, tarif_tindakandr, biaya_rawat, jenis_klaim)
            VALUES ('2026/11/17/000090', 'TND01', 'DR01', '{$today}', '08:20:00', 10000, 40000, 50000, 'Kapitasi');
        ");

        $summary = $this->repo->summarizeByDoctor($today, $today);
        $this->assertCount(1, $summary);
        $this->assertSame(2, (int) $summary[0]['jumlah_tindakan']);
        $this->assertSame(80000.0, (float) $summary[0]['total_jasa_dokter']);
        $this->assertSame(40000.0, (float) $summary[0]['jasa_tunai']);
        $this->assertSame(40000.0, (float) $summary[0]['jasa_bpjs']);

        $details = $this->repo->listDetails($today, $today, 'DR01');
        $this->assertCount(2, $details);
    }
}
