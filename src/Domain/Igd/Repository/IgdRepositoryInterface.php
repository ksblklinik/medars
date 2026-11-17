<?php

declare(strict_types=1);

namespace App\Domain\Igd\Repository;

interface IgdRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listIgdVisits(string $from, string $to): array;

    public function findTriase(string $noRawat): ?array;

    /**
     * @param array<string, mixed> $payload
     */
    public function saveTriase(array $payload): void;

    public function updateDisposisi(string $noRawat, string $disposisi, string $statusLanjut = 'Ralan'): void;
}
