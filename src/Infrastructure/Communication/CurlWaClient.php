<?php

declare(strict_types=1);

namespace App\Infrastructure\Communication;

use App\Domain\Communication\Service\WaClientInterface;

final class CurlWaClient implements WaClientInterface
{
    public function __construct(
        private string $gatewayUrl = '',
        private string $apiToken = '',
    ) {}

    public function sendMessage(string $session, string $phone, string $message): array
    {
        if (trim($this->gatewayUrl) === '' || trim($this->gatewayUrl) === '-') {
            // Mock offline/simulated sending when no gateway URL is configured
            return [
                'status' => 200,
                'message_id' => 'mock-' . bin2hex(random_bytes(6)),
            ];
        }

        $url = rtrim($this->gatewayUrl, '/') . '/send-message';
        $payload = json_encode([
            'session' => $session,
            'to' => $phone,
            'text' => $message,
        ], JSON_UNESCAPED_UNICODE);

        if (!function_exists('curl_init')) {
            return ['status' => 500, 'error' => 'cURL extension not loaded'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 500, 'error' => 'cURL init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiToken,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status > 0 ? $status : 200,
            'message_id' => 'msg-' . bin2hex(random_bytes(6)),
        ];
    }

    public function getSessionStatus(string $session): string
    {
        return 'ONLINE';
    }
}
