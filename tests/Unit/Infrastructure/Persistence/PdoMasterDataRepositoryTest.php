<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoMasterDataRepository;
use PHPUnit\Framework\TestCase;

final class PdoMasterDataRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoMasterDataRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoMasterDataRepository($this->pdo);
    }

    public function testCrudPoliklinikDokterPenjabTarifAndJadwal(): void
    {
        $this->repo->save('poliklinik', [
            'kd_poli' => 'GIG01',
            'nm_poli' => 'Poli Gigi',
            'registrasi' => 25000,
            'registrasilama' => 15000,
            'status' => '1',
            'allow_bpjs' => 1,
        ]);
        $polis = $this->repo->list('poliklinik', 'Gigi');
        $this->assertCount(1, $polis);
        $this->assertSame('Poli Gigi', $polis[0]['nm_poli']);

        $this->repo->save('dokter', [
            'kd_dokter' => 'DRG01',
            'nm_dokter' => 'drg. Nina',
            'jk' => 'P',
            'no_ijn_praktek' => 'SIP-001',
            'status' => '1',
            'almt_tgl' => 'Jl. Merdeka',
            'no_telp' => '08123456',
        ]);
        $this->assertCount(1, $this->repo->list('dokter', 'Nina'));

        $this->repo->save('penjab', [
            'kd_pj' => 'ASR01',
            'png_jawab' => 'Asuransi Sehat',
            'status' => '1',
        ]);
        $this->assertCount(1, $this->repo->list('penjab', 'Sehat'));

        $this->repo->save('jns_perawatan', [
            'kd_jenis_prw' => 'TNDG1',
            'nm_perawatan' => 'Tambal Gigi',
            'material' => 50000,
            'tarif_tindakandr' => 100000,
            'total_byrdr' => 150000,
            'status' => '1',
        ]);
        $this->assertCount(1, $this->repo->list('jns_perawatan', 'Tambal'));

        $this->repo->save('jadwal', [
            'kd_dokter' => 'DRG01',
            'kd_poli' => 'GIG01',
            'hari_kerja' => 'SENIN',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '12:00:00',
            'kuota' => 20,
            'prefik' => 'G',
            'room' => 'Ruang 2',
        ]);
        $this->assertCount(1, $this->repo->list('jadwal', 'DRG01'));

        $this->repo->delete('jadwal', [
            'kd_dokter' => 'DRG01',
            'kd_poli' => 'GIG01',
            'hari_kerja' => 'SENIN',
            'jam_mulai' => '08:00:00',
        ]);
        $this->assertCount(0, $this->repo->list('jadwal', 'DRG01'));
    }

    public function testDeletePoliklinikBlockedWhenReferencedInRegPeriksa(): void
    {
        $this->repo->save('poliklinik', ['kd_poli' => 'UMU01', 'nm_poli' => 'Poli Umum']);
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000001', '2026-11-17', '08:00:00', 'DR01', '000001', 'UMU01', 'A09', 'Belum Bayar');
        ");

        $this->expectException(\DomainException::class);
        $this->repo->delete('poliklinik', ['kd_poli' => 'UMU01']);
    }
}
