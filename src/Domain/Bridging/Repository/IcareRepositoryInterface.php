<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface IcareRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findVisitParticipant(string $noRawat): ?array;
}
