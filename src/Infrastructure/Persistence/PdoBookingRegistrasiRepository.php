<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\Entity\BookingRegistrasi;
use App\Domain\Registration\Repository\BookingRegistrasiRepositoryInterface;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;

final readonly class PdoBookingRegistrasiRepository implements BookingRegistrasiRepositoryInterface
{
    public function __construct(
        private \PDO $pdo
    ) {}

    public function findTodayPendingBooking(NoRkmMedis $noRkmMedis, \DateTimeInterface $today): ?BookingRegistrasi
    {
        $sql = 'SELECT * FROM booking_registrasi 
                WHERE no_rkm_medis = :no_rkm_medis 
                  AND tanggal_periksa = :tanggal_periksa 
                  AND status = "Belum" 
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'no_rkm_medis' => $noRkmMedis->value(),
            'tanggal_periksa' => $today->format('Y-m-d'),
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new BookingRegistrasi(
            noRkmMedis: new NoRkmMedis($row['no_rkm_medis']),
            tanggalPeriksa: new \DateTimeImmutable($row['tanggal_periksa']),
            kdPoli: (string) $row['kd_poli'],
            kdDokter: (string) $row['kd_dokter'],
            noReg: new NoReg((string) $row['no_reg']),
            kdPj: (string) ($row['kd_pj'] ?? 'BPJ'),
            status: (string) ($row['status'] ?? 'Belum'),
            waktuKunjungan: $row['waktu_kunjungan'] ?? null,
            kdDokterPengganti: $row['kd_dokter_pengganti'] ?? null,
            shift: $row['shift'] ?? null,
            prefik: $row['prefik'] ?? null,
            room: $row['room'] ?? null,
            tanggalBooking: isset($row['tanggal_booking']) ? new \DateTimeImmutable($row['tanggal_booking']) : null,
            jamBooking: $row['jam_booking'] ?? null
        );
    }

    public function markAsRegistered(NoRkmMedis $noRkmMedis, \DateTimeInterface $today): void
    {
        $sql = 'UPDATE booking_registrasi 
                SET status = "Terdaftar" 
                WHERE no_rkm_medis = :no_rkm_medis 
                  AND tanggal_periksa = :tanggal_periksa';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'no_rkm_medis' => $noRkmMedis->value(),
            'tanggal_periksa' => $today->format('Y-m-d'),
        ]);
    }

    public function save(BookingRegistrasi $booking): void
    {
        $sql = 'INSERT INTO booking_registrasi (
                    tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa,
                    kd_dokter, kd_poli, no_reg, kd_pj, status, shift, prefik, room
                ) VALUES (
                    :tanggal_booking, :jam_booking, :no_rkm_medis, :tanggal_periksa,
                    :kd_dokter, :kd_poli, :no_reg, :kd_pj, :status, :shift, :prefik, :room
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tanggal_booking' => $booking->tanggalBooking ? $booking->tanggalBooking->format('Y-m-d') : date('Y-m-d'),
            'jam_booking' => $booking->jamBooking ?? date('H:i:s'),
            'no_rkm_medis' => $booking->noRkmMedis->value(),
            'tanggal_periksa' => $booking->tanggalPeriksa->format('Y-m-d'),
            'kd_dokter' => $booking->kdDokter,
            'kd_poli' => $booking->kdPoli,
            'no_reg' => $booking->noReg->value(),
            'kd_pj' => $booking->kdPj,
            'status' => $booking->status,
            'shift' => $booking->shift,
            'prefik' => $booking->prefik,
            'room' => $booking->room,
        ]);
    }
}
