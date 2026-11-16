<?php

declare(strict_types=1);

namespace App\Domain\Registration\Entity;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;

final readonly class RegPeriksa
{
    public function __construct(
        public NoRawat $noRawat,
        public NoReg $noReg,
        public NoRkmMedis $noRkmMedis,
        public string $kdPoli,
        public string $kdDokter,
        public string $kdPj,
        public \DateTimeInterface $tglRegistrasi,
        public string $jamReg,
        public float $biayaReg,
        public string $stts = 'Belum',
        public string $sttsDaftar = 'Baru',
        public string $statusBayar = 'Belum Bayar',
        public string $statusPoli = 'Baru',
        public string $statusLanjut = 'Ralan',
        public int $umurDaftar = 0,
        public string $sttsUmur = 'Th',
        public string $pJawab = '-',
        public string $almtPj = '-',
        public string $hubunganPj = '-',
        public string $prefik = '',
        public string $room = '',
        public string $shift = ''
    ) {}

    public function isServed(): bool
    {
        return in_array($this->stts, ['Sudah', 'Selesai'], true);
    }

    public function isPaid(): bool
    {
        return $this->statusBayar === 'Sudah Bayar';
    }

    public function isCancelled(): bool
    {
        return $this->stts === 'Batal';
    }

    public function withStatus(string $newStatus): self
    {
        return new self(
            noRawat: $this->noRawat,
            noReg: $this->noReg,
            noRkmMedis: $this->noRkmMedis,
            kdPoli: $this->kdPoli,
            kdDokter: $this->kdDokter,
            kdPj: $this->kdPj,
            tglRegistrasi: $this->tglRegistrasi,
            jamReg: $this->jamReg,
            biayaReg: $this->biayaReg,
            stts: $newStatus,
            sttsDaftar: $this->sttsDaftar,
            statusBayar: $this->statusBayar,
            statusPoli: $this->statusPoli,
            statusLanjut: $this->statusLanjut,
            umurDaftar: $this->umurDaftar,
            sttsUmur: $this->sttsUmur,
            pJawab: $this->pJawab,
            almtPj: $this->almtPj,
            hubunganPj: $this->hubunganPj,
            prefik: $this->prefik,
            room: $this->room,
            shift: $this->shift
        );
    }

    public function withNoRawat(NoRawat $newNoRawat): self
    {
        return new self(
            noRawat: $newNoRawat,
            noReg: $this->noReg,
            noRkmMedis: $this->noRkmMedis,
            kdPoli: $this->kdPoli,
            kdDokter: $this->kdDokter,
            kdPj: $this->kdPj,
            tglRegistrasi: $this->tglRegistrasi,
            jamReg: $this->jamReg,
            biayaReg: $this->biayaReg,
            stts: $this->stts,
            sttsDaftar: $this->sttsDaftar,
            statusBayar: $this->statusBayar,
            statusPoli: $this->statusPoli,
            statusLanjut: $this->statusLanjut,
            umurDaftar: $this->umurDaftar,
            sttsUmur: $this->sttsUmur,
            pJawab: $this->pJawab,
            almtPj: $this->almtPj,
            hubunganPj: $this->hubunganPj,
            prefik: $this->prefik,
            room: $this->room,
            shift: $this->shift
        );
    }
}
