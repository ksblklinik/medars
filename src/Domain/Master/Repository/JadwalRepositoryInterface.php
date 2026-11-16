<?php

declare(strict_types=1);

namespace App\Domain\Master\Repository;

use App\Domain\Master\Entity\JadwalPraktik;

interface JadwalRepositoryInterface
{
    /**
     * @return list<JadwalPraktik>
     */
    public function findByDokterAndHari(string $kdDokter, string $hariKerja): array;

    /**
     * @return list<JadwalPraktik>
     */
    public function findAllByHari(string $hariKerja): array;

    public function save(JadwalPraktik $jadwal): void;

    public function delete(string $kdDokter, string $kdPoli, string $hariKerja, string $jamMulai): void;
}
