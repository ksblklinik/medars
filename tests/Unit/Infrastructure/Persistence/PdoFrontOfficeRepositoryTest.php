<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoFrontOfficeRepository;
use PHPUnit\Framework\TestCase;

final class PdoFrontOfficeRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoFrontOfficeRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoFrontOfficeRepository($this->pdo);
    }

    public function testGetTodayStatsBookingsAndVisits(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000050', 'Eko Prasetyo', '1987-01-10', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U01', 'Poli Umum', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO penjab (kd_pj, png_jawab, status) VALUES ('A09', 'UMUM', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('001', '2026/11/17/000050', '{$today}', '08:00:00', 'DR01', '000050', 'U01', 'A09', 'Belum', 'Belum Bayar');
            INSERT INTO booking_registrasi (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status)
            VALUES ('{$today}', '07:00:00', '000050', '{$today}', 'DR01', 'U01', '002', 'A09', 'Belum');
        ");

        $stats = $this->repo->getTodayStats($today);
        $this->assertSame(1, $stats['total_kunjungan']);
        $this->assertSame(1, $stats['belum_periksa']);
        $this->assertSame(0, $stats['sudah_periksa']);
        $this->assertSame(1, $stats['booking_menunggu']);
        $this->assertSame(1, $stats['belum_bayar']);

        $bookings = $this->repo->listTodayBookings($today);
        $this->assertCount(1, $bookings);
        $this->assertSame('Eko Prasetyo', $bookings[0]['nm_pasien']);

        $visits = $this->repo->listTodayVisits($today);
        $this->assertCount(1, $visits);
        $this->assertSame('2026/11/17/000050', $visits[0]['no_rawat']);
    }
}
