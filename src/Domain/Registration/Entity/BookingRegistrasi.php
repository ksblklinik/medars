<?php

declare(strict_types=1);

namespace App\Domain\Registration\Entity;

use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;

final readonly class BookingRegistrasi
{
    public function __construct(
        public NoRkmMedis $noRkmMedis,
        public \DateTimeInterface $tanggalPeriksa,
        public string $kdPoli,
        public string $kdDokter,
        public NoReg $noReg,
        public string $kdPj,
        public string $status = 'Belum',
        public ?string $waktuKunjungan = null,
        public ?string $kdDokterPengganti = null,
        public ?string $shift = null,
        public ?string $prefik = null,
        public ?string $room = null,
        public ?\DateTimeInterface $tanggalBooking = null,
        public ?string $jamBooking = null
    ) {}

    public function isToday(\DateTimeInterface $today): bool
    {
        return $this->tanggalPeriksa->format('Y-m-d') === $today->format('Y-m-d');
    }

    public function isPending(): bool
    {
        return $this->status === 'Belum';
    }

    public function isRegistered(): bool
    {
        return $this->status === 'Terdaftar';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Batal';
    }

    public function withStatus(string $newStatus): self
    {
        return new self(
            noRkmMedis: $this->noRkmMedis,
            tanggalPeriksa: $this->tanggalPeriksa,
            kdPoli: $this->kdPoli,
            kdDokter: $this->kdDokter,
            noReg: $this->noReg,
            kdPj: $this->kdPj,
            status: $newStatus,
            waktuKunjungan: $this->waktuKunjungan,
            kdDokterPengganti: $this->kdDokterPengganti,
            shift: $this->shift,
            prefik: $this->prefik,
            room: $this->room,
            tanggalBooking: $this->tanggalBooking,
            jamBooking: $this->jamBooking
        );
    }
}
