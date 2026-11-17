<?php

declare(strict_types=1);

namespace App\Domain\Communication\Repository;

interface ChatRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listConversations(): array;

    /** @param array<string, mixed> $data */
    public function createConversation(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listMessages(int $conversationId, int $limit = 100): array;

    /** @param array<string, mixed> $data */
    public function sendMessage(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listQuickResponses(): array;

    /** @param array<string, mixed> $data */
    public function saveQuickResponse(array $data): array;

    public function deleteQuickResponse(int $id): bool;
}
