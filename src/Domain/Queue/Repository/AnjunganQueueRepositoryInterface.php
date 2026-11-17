<?php

declare(strict_types=1);

namespace App\Domain\Queue\Repository;

interface AnjunganQueueRepositoryInterface
{
    /** @return array<string, mixed> */
    public function issueTicket(
        string $type,
        string $noRawat = '',
        string $noRkmMedis = '',
        string $nmPasien = '',
        string $namaLoket = ''
    ): array;

    /** @return list<array<string, mixed>> */
    public function listByType(string $type, string $date = ''): array;

    /** @return array<string, mixed>|null */
    public function callNext(string $type, string $namaLoket = ''): ?array;

    /** @return array<string, mixed>|null */
    public function findTicket(int $id): ?array;
}
