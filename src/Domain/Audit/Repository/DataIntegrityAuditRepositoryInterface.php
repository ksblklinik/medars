<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repository;

interface DataIntegrityAuditRepositoryInterface
{
    public function countOrphans(string $table, string $column): int;

    /**
     * @return list<array{claim_type: string, subtotal: float}>
     */
    public function getBillingSubtotalsByClaimType(string $noRawat): array;

    /**
     * @return array{current_stock: float, calculated_stock: float}
     */
    public function getStockAndMutationTotals(string $kodeBrng, string $kdBangsal): array;
}
