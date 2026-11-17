<?php

declare(strict_types=1);

namespace App\Domain\Ranap\Repository;

interface DokterRanapRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listRanapPatients(string $q = ''): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listCppt(string $noRawat): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function saveCppt(array $payload): void;
}
