<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface BridgingQueueRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function enqueue(
        string $provider,
        string $resourceType,
        string $noRawat,
        string $endpoint,
        string $payloadJson,
    ): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchPending(int $limit = 10): array;

    public function markSynced(int $id, string $externalId): void;

    public function markFailed(int $id, string $error): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 50): array;
}
