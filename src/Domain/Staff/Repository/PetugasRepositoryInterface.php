<?php

declare(strict_types=1);

namespace App\Domain\Staff\Repository;

use App\Domain\Staff\Entity\Petugas;

interface PetugasRepositoryInterface
{
    /**
     * @return list<Petugas>
     */
    public function findAllActive(): array;

    public function findByNip(string $nip): ?Petugas;

    public function save(Petugas $petugas): void;

    public function deactivate(string $nip): void;
}
