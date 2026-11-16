<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Bridging\Service\HttpClientInterface;
use App\Domain\Bridging\ValueObject\TimeoutPolicy;

final class CurlHttpClient implements HttpClientInterface
{
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        TimeoutPolicy $timeout,
    ): array {
        if (!function_exists('curl_init')) {
            return ['status' => 503, 'body' => '{"error":"cURL extension not available"}'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 503, 'body' => '{"error":"Failed to initialize cURL"}'];
        }

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => $timeout->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $timeout->executionTimeoutSeconds,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'status' => 504,
                'body' => (string) json_encode(['error' => $error !== '' ? $error : 'Timeout']),
            ];
        }

        return [
            'status' => $status > 0 ? $status : 200,
            'body' => (string) $responseBody,
        ];
    }
}
