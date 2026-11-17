<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoJknMobileRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoJknMobileRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoJknMobileRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoJknMobileRepository($this->pdo);
    }

    public function testMobileJknFkrtlFullAntreanLifecycle(): void
    {
        $this->pdo->exec("INSERT INTO poliklinik (kd_poli, nm_poli, registrasi, registrasilama, status) VALUES ('INT', 'Penyakit Dalam', 50000, 30000, '1')");
        $this->pdo->exec("INSERT INTO dokter (kd_dokter, nm_dokter, jk, status) VALUES ('D001', 'dr. Andi Sp.PD', 'L', '1')");

        $pasienBaru = $this->repo->pasienBaru([
            'nomorkartu' => '0001234567899',
            'nik' => '3500099887766',
            'nomorkk' => '3500011223344',
            'nama' => 'Dewi JKN',
            'jeniskelamin' => 'P',
            'tanggallahir' => '1992-05-10',
            'nohp' => '081234567890',
            'alamat' => 'Jl. Merdeka 1',
        ]);
        $this->assertNotEmpty($pasienBaru['norm']);

        $ambil = $this->repo->ambilAntrean([
            'nomorkartu' => '0001234567899',
            'nik' => '3500099887766',
            'nohp' => '081234567890',
            'kodepoli' => 'INT',
            'norm' => $pasienBaru['norm'],
            'tanggalperiksa' => '2026-11-17',
            'kodedokter' => 'D001',
            'jampraktek' => '08:00-12:00',
            'jeniskunjungan' => 1,
            'nomorreferensi' => '0112R0011126P000001',
        ]);
        $kodebooking = $ambil['kodebooking'];
        $this->assertSame(1, $ambil['angkaantrean']);
        $this->assertSame('I1', $ambil['nomorantrean']);

        $status = $this->repo->statusAntrean('INT', 'D001', '2026-11-17');
        $this->assertSame(1, $status['totalantrean']);
        $this->assertSame(1, $status['sisaantrean']);

        $sisa = $this->repo->sisaAntrean($kodebooking);
        $this->assertNotNull($sisa);
        $this->assertSame('I1', $sisa['nomorantrean']);

        $checkin = $this->repo->checkIn($kodebooking, 1763352000000);
        $this->assertNotNull($checkin);
        $this->assertSame('Checkin', $checkin['status']);

        $farmasi = $this->repo->ambilAntreanFarmasi($kodebooking, 'Non Racikan');
        $this->assertNotNull($farmasi);
        $this->assertSame(1, $farmasi['nomorantrean']);

        $statusFarmasi = $this->repo->statusAntreanFarmasi($kodebooking);
        $this->assertNotNull($statusFarmasi);
        $this->assertSame('Non Racikan', $statusFarmasi['jenisresep']);

        $this->assertTrue($this->repo->batalAntrean($kodebooking, 'Pasien berhalangan'));
        $this->assertIsArray($this->repo->jadwalOperasiRs('2026-11-01', '2026-11-30'));
        $this->assertIsArray($this->repo->jadwalOperasiPasien('0001234567899'));
    }
}
