<?php

declare(strict_types=1);

namespace App\Domain\Staff\Repository;

use App\Domain\Staff\Entity\Dokter;

interface DokterRepositoryInterface
{
    /**
     * @return list<Dokter>
     */
    public function findAllActive(): array;

    public function findByKode(string $kdDokter): ?Dokter;

    public function save(Dokter $dokter): void;

    public function canHardDelete(string $kdDokter): bool;

    public function deactivate(string $kdDokter): void;
}
