<?php

declare(strict_types=1);

namespace App\Domain\Staff\Repository;

use App\Domain\Staff\Entity\Pegawai;

interface PegawaiRepositoryInterface
{
    /**
     * @return list<Pegawai>
     */
    public function findAllActive(): array;

    public function findByNik(string $nik): ?Pegawai;

    public function save(Pegawai $pegawai): void;

    public function deactivate(string $nik): void;
}
