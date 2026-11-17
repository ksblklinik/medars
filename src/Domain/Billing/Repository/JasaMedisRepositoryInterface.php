<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repository;

interface JasaMedisRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function summarizeByDoctor(string $from, string $to, string $kdDokter = ''): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listDetails(string $from, string $to, string $kdDokter = ''): array;
}
