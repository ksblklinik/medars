<?php

declare(strict_types=1);

namespace App\Domain\Master\Repository;

use App\Domain\Master\Entity\Penjab;

interface PenjabRepositoryInterface
{
    /**
     * @return list<Penjab>
     */
    public function findAllActive(): array;

    public function findByKode(string $kdPj): ?Penjab;

    public function save(Penjab $penjab): void;
}
