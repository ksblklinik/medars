<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

use App\Domain\Registration\ValueObject\NoRkmMedis;

interface RmeHistoryRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getFullMedicalHistory(NoRkmMedis $noRkmMedis): array;
}
