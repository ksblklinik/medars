<?php

declare(strict_types=1);

namespace App\Domain\Registration\Repository;

use App\Domain\Registration\Entity\BookingRegistrasi;
use App\Domain\Registration\ValueObject\NoRkmMedis;

interface BookingRegistrasiRepositoryInterface
{
    public function findTodayPendingBooking(NoRkmMedis $noRkmMedis, \DateTimeInterface $today): ?BookingRegistrasi;

    public function markAsRegistered(NoRkmMedis $noRkmMedis, \DateTimeInterface $today): void;

    public function save(BookingRegistrasi $booking): void;
}
