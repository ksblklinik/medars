<?php

declare(strict_types=1);

namespace App\Domain\Communication\Repository;

interface WaCrmRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listCsChats(string $phone = ''): array;

    /** @return array<string, mixed> */
    public function saveCsChat(string $phone, string $direction, string $message, string $senderName = ''): array;

    /** @return list<array<string, mixed>> */
    public function listBroadcastTemplates(): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveBroadcastTemplate(array $data): array;

    public function generateAiDraftReply(string $incomingMessage): string;
}
