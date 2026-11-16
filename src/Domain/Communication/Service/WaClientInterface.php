<?php

declare(strict_types=1);

namespace App\Domain\Communication\Service;

interface WaClientInterface
{
    public function isOnWhatsApp(string $phone): bool;

    /**
     * @return array{status: int, message_id?: string, error?: string}
     */
    public function sendMessage(string $session, string $phone, string $message): array;

    public function getSessionStatus(string $session): string;
}
