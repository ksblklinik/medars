<?php

declare(strict_types=1);

namespace App\Domain\Master\Entity;

final class JadwalPraktik
{
    public function __construct(
        private readonly string $kdDokter,
        private readonly string $kdPoli,
        private readonly string $hariKerja,
        private readonly string $jamMulai,
        private readonly string $jamSelesai,
        private readonly int $kuota = 0,
        private readonly string $prefik = '',
        private readonly string $room = '',
    ) {
    }

    public function getKdDokter(): string
    {
        return $this->kdDokter;
    }

    public function getKdPoli(): string
    {
        return $this->kdPoli;
    }

    public function getHariKerja(): string
    {
        return strtoupper($this->hariKerja);
    }

    public function getJamMulai(): string
    {
        return $this->jamMulai;
    }

    public function getJamSelesai(): string
    {
        return $this->jamSelesai;
    }

    public function getKuota(): int
    {
        return $this->kuota;
    }

    public function getPrefik(): string
    {
        return $this->prefik;
    }

    public function getRoom(): string
    {
        return $this->room;
    }
}
