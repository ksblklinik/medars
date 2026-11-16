<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repository;

use App\Domain\Billing\Entity\PosApotekUmumSale;

interface PosApotekUmumRepositoryInterface
{
    public function getNextNoNota(\DateTimeInterface $date): string;

    /**
     * @param list<array{kode_brng: string, jml: float, harga_jual: float, subtotal: float}> $items
     */
    public function saveSale(PosApotekUmumSale $sale, array $items): void;
}
