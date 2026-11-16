<?php

declare(strict_types=1);

namespace App\Application\Registration\Exception;

final class BookingCheckInException extends \DomainException
{
    public static function notFound(string $noRkmMedis, string $tgl): self
    {
        return new self("Data booking antrean pasien '{$noRkmMedis}' untuk tanggal {$tgl} tidak ditemukan.");
    }

    public static function alreadyRegistered(string $noRkmMedis): self
    {
        return new self("Pasien '{$noRkmMedis}' sudah melakukan check-in dan berstatus terdaftar hari ini.");
    }

    public static function cancelled(string $noRkmMedis): self
    {
        return new self("Pendaftaran booking pasien '{$noRkmMedis}' telah dibatalkan.");
    }

    public static function invalidDate(string $bookingDate, string $today): self
    {
        return new self("Anda tidak bisa check-in hari ini ({$today}). Booking Anda terdaftar untuk tanggal {$bookingDate}.");
    }
}
