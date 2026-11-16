<?php

declare(strict_types=1);

namespace App\Domain\Staff\Entity;

final class Petugas
{
    public function __construct(
        private readonly string $nip,
        private readonly string $nama,
        private readonly string $jk,
        private readonly string $jbtn,
        private readonly string $status = '1',
        private readonly string $almtTgl = '',
        private readonly string $noTelp = '',
    ) {
    }

    public function getNip(): string
    {
        return $this->nip;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAlmtTgl(): string
    {
        return $this->almtTgl;
    }

    public function getNoTelp(): string
    {
        return $this->noTelp;
    }

    public function isAktif(): bool
    {
        return $this->status === '1';
    }
}
