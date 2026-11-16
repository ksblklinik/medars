<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Entity\Odontogram;

interface OdontogramRepositoryInterface
{
    /**
     * @return list<Odontogram>
     */
    public function findByNoRkmMedis(NoRkmMedis $noRkmMedis): array;

    public function save(Odontogram $odontogram): void;

    public function deleteById(int $id): void;
}
