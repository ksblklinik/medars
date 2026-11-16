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

    public function isOnWhatsApp(string $phone): bool
    {
        if (trim($this->gatewayUrl) === '' || trim($this->gatewayUrl) === '-' || !function_exists('curl_init')) {
            return false;
        }

        $url = rtrim($this->gatewayUrl, '/') . '/check-number?phone=' . rawurlencode($phone);
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiToken],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            return false;
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) && (bool) ($decoded['registered'] ?? $decoded['exists'] ?? false);
    }

    public function sendMessage(string $session, string $phone, string $message): array
    {
        if (trim($this->gatewayUrl) === '' || trim($this->gatewayUrl) === '-') {
            return [
                'status' => 503,
                'error' => 'WA Gateway belum dikonfigurasi.',
            ];
        }

        $url = rtrim($this->gatewayUrl, '/') . '/send-message';
        $payload = (string) json_encode([
            'session' => $session,
            'to' => $phone,
            'text' => $message,
        ], JSON_UNESCAPED_UNICODE);

        if (!function_exists('curl_init')) {
            return ['status' => 503, 'error' => 'cURL extension not loaded'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 503, 'error' => 'cURL init failed'];
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
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status === 0) {
            return [
                'status' => 503,
                'error' => $error !== '' ? $error : 'Koneksi WA Gateway gagal.',
            ];
        }

        $decoded = json_decode((string) $body, true);

        return [
            'status' => $status,
            'message_id' => is_array($decoded) ? (string) ($decoded['message_id'] ?? '') : '',
            'error' => $status >= 400 ? (is_array($decoded) ? (string) ($decoded['error'] ?? "HTTP {$status}") : "HTTP {$status}") : null,
        ];
    }

    public function getSessionStatus(string $session): string
    {
        if (trim($this->gatewayUrl) === '' || trim($this->gatewayUrl) === '-') {
            return 'OFFLINE';
        }

        if (!function_exists('curl_init')) {
            return 'OFFLINE';
        }

        $url = rtrim($this->gatewayUrl, '/') . '/session/status?session=' . rawurlencode($session);
        $ch = curl_init($url);
        if ($ch === false) {
            return 'OFFLINE';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiToken],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            return 'OFFLINE';
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? strtoupper((string) ($decoded['status'] ?? 'OFFLINE')) : 'OFFLINE';
    }
}
