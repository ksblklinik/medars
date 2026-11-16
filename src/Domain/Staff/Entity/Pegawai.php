<?php

declare(strict_types=1);

namespace App\Domain\Staff\Entity;

final class Pegawai
{
    public function __construct(
        private readonly string $nik,
        private readonly string $nama,
        private readonly string $jk,
        private readonly string $jbtn,
        private readonly string $departemen = '',
        private readonly string $status = 'AKTIF',
    ) {
    }

    public function getNik(): string
    {
        return $this->nik;
    }

    public function getNama(): string
    {
        return $this->nama;
    }

    public function getJk(): string
    {
        return $this->jk;
    }

    public function getJbtn(): string
    {
        return $this->jbtn;
    }

    public function getDepartemen(): string
    {
        return $this->departemen;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isAktif(): bool
    {
        return strtoupper($this->status) === 'AKTIF';
    }
}
