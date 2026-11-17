<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoIgdRepository;
use PHPUnit\Framework\TestCase;

final class PdoIgdRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoIgdRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoIgdRepository($this->pdo);
    }

    public function testSaveTriaseOrdersCriticalFirstAndUpdatesDisposisi(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000060', 'Pasien Hijau', '1990-01-01', 'L');
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000061', 'Pasien Merah', '1970-05-05', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('IGDK', 'IGD', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Jaga', '1');
            INSERT INTO penjab (kd_pj, png_jawab, status) VALUES ('A09', 'UMUM', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/17/000060', '{$today}', '08:00:00', 'DR01', '000060', 'IGDK', 'A09', 'Belum', 'Ralan', 'Belum Bayar');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('002', '2026/11/17/000061', '{$today}', '08:05:00', 'DR01', '000061', 'IGDK', 'A09', 'Belum', 'Ralan', 'Belum Bayar');
        ");

        $this->repo->saveTriase([
            'no_rawat' => '2026/11/17/000060',
            'zona_triase' => 'Hijau',
            'keluhan_utama' => 'Demam ringan',
            'spo2' => '98',
        ]);
        $this->repo->saveTriase([
            'no_rawat' => '2026/11/17/000061',
            'zona_triase' => 'Merah',
            'cara_masuk' => 'Ambulans',
            'keluhan_utama' => 'Nyeri dada hebat',
            'spo2' => '88',
        ]);

        $visits = $this->repo->listIgdVisits($today, $today);
        $this->assertCount(2, $visits);
        $this->assertSame('2026/11/17/000061', $visits[0]['no_rawat']);
        $this->assertSame('Merah', $visits[0]['zona_triase']);

        $this->repo->updateDisposisi('2026/11/17/000061', 'Rawat Inap', 'Ranap');
        $statusLanjut = (string) $this->pdo->query("SELECT status_lanjut FROM reg_periksa WHERE no_rawat = '2026/11/17/000061'")->fetchColumn();
        $this->assertSame('Ranap', $statusLanjut);
    }
}
