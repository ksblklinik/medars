<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Service;

use App\Domain\Bridging\ValueObject\BridgingCredentials;
use App\Domain\Bridging\ValueObject\TimeoutPolicy;

final readonly class BpjsGatewayService
{
    public function __construct(
        private BridgingCredentials $credentials,
        private HttpClientInterface $httpClient,
        private TimeoutPolicy $timeoutPolicy = new TimeoutPolicy(2, 4),
    ) {}

    /**
     * @return array{ok: bool, circuit_open: bool, metaData: array{code: int, message: string}, response: mixed}
     */
    public function get(string $endpoint, ?int $timestampOverride = null): array
    {
        return $this->dispatch('GET', $endpoint, null, $timestampOverride);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, circuit_open: bool, metaData: array{code: int, message: string}, response: mixed}
     */
    public function post(string $endpoint, array $payload, ?int $timestampOverride = null): array
    {
        return $this->dispatch('POST', $endpoint, (string) json_encode($payload, JSON_UNESCAPED_UNICODE), $timestampOverride);
    }

    /**
     * @return array{ok: bool, circuit_open: bool, metaData: array{code: int, message: string}, response: mixed}
     */
    private function dispatch(string $method, string $endpoint, ?string $body, ?int $timestampOverride = null): array
    {
        // Zero-Latency Circuit Breaker: return immediately in 0ms when credentials missing/dummy
        if (!$this->credentials->isActive()) {
            return [
                'ok' => false,
                'circuit_open' => true,
                'metaData' => [
                    'code' => 503,
                    'message' => 'Bridging BPJS nonaktif atau kredensial belum lengkap (Circuit Breaker Open).',
                ],
                'response' => null,
            ];
        }

        $timestamp = (string) ($timestampOverride ?? time());
        $signature = base64_encode(hash_hmac('sha256', $this->credentials->consId . '&' . $timestamp, $this->credentials->secretKey, true));

        $url = rtrim($this->credentials->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = [
            'X-cons-id' => $this->credentials->consId,
            'X-timestamp' => $timestamp,
            'X-signature' => $signature,
            'user_key' => $this->credentials->userKey,
            'Content-Type' => 'application/json',
        ];

        $raw = $this->httpClient->request($method, $url, $headers, $body, $this->timeoutPolicy);
        $decoded = json_decode($raw['body'], true);

        $responseData = $decoded['response'] ?? $decoded;
        if (is_string($responseData) && $responseData !== '') {
            $plain = BpjsDecryptor::decrypt(
                $responseData,
                $this->credentials->consId,
                $this->credentials->secretKey,
                $timestamp
            );
            if ($plain !== '') {
                $jsonDecoded = json_decode($plain, true);
                $responseData = is_array($jsonDecoded) ? $jsonDecoded : $plain;
            }
        }

        return [
            'ok' => $raw['status'] >= 200 && $raw['status'] < 300,
            'circuit_open' => false,
            'metaData' => is_array($decoded['metaData'] ?? null)
                ? $decoded['metaData']
                : ['code' => $raw['status'], 'message' => 'OK'],
            'response' => $responseData,
        ];
    }
}
