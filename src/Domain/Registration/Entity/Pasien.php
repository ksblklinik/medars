<?php

declare(strict_types=1);

namespace App\Domain\Registration\Entity;

use App\Domain\Registration\ValueObject\NoRkmMedis;

final readonly class Pasien
{
    public function __construct(
        public NoRkmMedis $noRkmMedis,
        public string $nmPasien,
        public string $nik,
        public string $jk,
        public \DateTimeInterface $tglLahir,
        public string $alamat = '-',
        public string $noTelp = '-',
        public string $noPeserta = '',
        public string $keluarga = '-',
        public string $namaKeluarga = '-',
        public string $alamatPj = '-'
    ) {
        if (trim($nmPasien) === '') {
            throw new \InvalidArgumentException('Nama pasien tidak boleh kosong.');
        }

        if (!in_array($jk, ['L', 'P', '-'], true)) {
            throw new \InvalidArgumentException("Jenis kelamin harus 'L', 'P', atau '-'.");
        }
    }

    /**
     * Menghitung umur pada tanggal pendaftaran sesuai standar SIMRS (Tahun/Bulan/Hari).
     *
     * @return array{umur: int, satuan: 'Th'|'Bl'|'Hr'}
     */
    public function hitungUmurPada(\DateTimeInterface $refDate): array
    {
        if ($this->tglLahir > $refDate) {
            throw new \InvalidArgumentException('Tanggal lahir tidak boleh lebih besar dari tanggal referensi.');
        }

        $diff = $refDate->diff($this->tglLahir);

        if ($diff->y > 0) {
            return ['umur' => $diff->y, 'satuan' => 'Th'];
        }

        if ($diff->m > 0) {
            return ['umur' => $diff->m, 'satuan' => 'Bl'];
        }

        return ['umur' => $diff->d, 'satuan' => 'Hr'];
    }
}
