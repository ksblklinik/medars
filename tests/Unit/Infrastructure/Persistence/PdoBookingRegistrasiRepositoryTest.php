<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Registration\Entity\BookingRegistrasi;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use PHPUnit\Framework\TestCase;

final class PdoBookingRegistrasiRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoBookingRegistrasiRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE booking_registrasi (
            tanggal_booking DATE,
            jam_booking TIME,
            no_rkm_medis VARCHAR(15),
            tanggal_periksa DATE,
            kd_dokter VARCHAR(20),
            kd_poli CHAR(5),
            no_reg VARCHAR(8),
            kd_pj CHAR(3),
            limit_reg INT DEFAULT 0,
            waktu_kunjungan DATETIME,
            status VARCHAR(20) DEFAULT "Belum",
            kd_dokter_pengganti VARCHAR(20),
            shift VARCHAR(20),
            prefik VARCHAR(10),
            room VARCHAR(50),
            PRIMARY KEY (no_rkm_medis, tanggal_periksa)
        )');

        $this->repository = new PdoBookingRegistrasiRepository($this->pdo);
    }

    public function test_find_today_pending_booking(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        $this->pdo->exec("INSERT INTO booking_registrasi 
            (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status, shift)
            VALUES ('2026-11-10', '09:00:00', '000123', '2026-11-16', 'DR001', 'U01', '005', 'BPJ', 'Belum', '07:00:00')");

        $booking = $this->repository->findTodayPendingBooking(new NoRkmMedis('000123'), $today);

        $this->assertNotNull($booking);
        $this->assertSame('005', $booking->noReg->value());
        $this->assertSame('DR001', $booking->kdDokter);
        $this->assertSame('U01', $booking->kdPoli);
        $this->assertTrue($booking->isPending());
    }

    public function test_mark_as_registered(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        $this->pdo->exec("INSERT INTO booking_registrasi 
            (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status)
            VALUES ('2026-11-10', '09:00:00', '000123', '2026-11-16', 'DR001', 'U01', '005', 'BPJ', 'Belum')");

        $this->repository->markAsRegistered(new NoRkmMedis('000123'), $today);

        $booking = $this->repository->findTodayPendingBooking(new NoRkmMedis('000123'), $today);
        $this->assertNull($booking, 'Booking yang sudah terdaftar tidak boleh muncul sebagai pending');

        $status = $this->pdo->query("SELECT status FROM booking_registrasi WHERE no_rkm_medis = '000123'")->fetchColumn();
        $this->assertSame('Terdaftar', $status);
    }
}
