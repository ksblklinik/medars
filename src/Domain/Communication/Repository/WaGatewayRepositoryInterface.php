<?php

declare(strict_types=1);

namespace App\Domain\Communication\Repository;

interface WaGatewayRepositoryInterface
{
    public function isWhitelisted(string $phone): bool;

    public function addToWhitelist(string $phone, string $nama, string $kategori = 'Pegawai'): void;

    public function recordOutbox(
        string $session,
        string $phone,
        string $message,
        string $status,
        ?string $error = null,
    ): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentOutbox(int $limit = 50): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listWhitelist(): array;
}
