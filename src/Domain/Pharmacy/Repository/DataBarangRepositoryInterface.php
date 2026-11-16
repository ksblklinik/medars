<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\Repository;

use App\Domain\Pharmacy\Entity\DataBarang;

interface DataBarangRepositoryInterface
{
    public function findByKode(string $kodeBrng): ?DataBarang;

    /**
     * @return list<array<string, mixed>>
     */
    public function searchWithUniformPricing(string $keyword, bool $isBpjs = false, int $limit = 20): array;
}
