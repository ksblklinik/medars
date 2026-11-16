<?php

declare(strict_types=1);

namespace App\Domain\Master\Entity;

final class Penjab
{
    public function __construct(
        private readonly string $kdPj,
        private readonly string $pngJawab,
        private readonly string $status = '1',
        private readonly string $namaPerusahaan = '',
        private readonly string $alamatAsuransi = '',
        private readonly string $noTelp = '',
    ) {
    }

    public function getKdPj(): string
    {
        return $this->kdPj;
    }

    public function getPngJawab(): string
    {
        return $this->pngJawab;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNamaPerusahaan(): string
    {
        return $this->namaPerusahaan;
    }

    public function getAlamatAsuransi(): string
    {
        return $this->alamatAsuransi;
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
