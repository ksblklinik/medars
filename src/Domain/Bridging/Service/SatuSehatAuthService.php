<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Service;

use App\Domain\Bridging\ValueObject\TimeoutPolicy;

final class SatuSehatAuthService
{
    private ?string $cachedToken = null;
    private int $expiresAtEpoch = 0;

    public function __construct(
        private readonly string $authUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly HttpClientInterface $httpClient,
        private readonly TimeoutPolicy $timeoutPolicy = new TimeoutPolicy(2, 4),
    ) {}

    public function getAccessToken(): string
    {
        if ($this->cachedToken !== null && time() < ($this->expiresAtEpoch - 30)) {
            return $this->cachedToken;
        }

        if (
            trim($this->authUrl) === '' || trim($this->authUrl) === '-'
            || trim($this->clientId) === '' || trim($this->clientId) === '-'
            || trim($this->clientSecret) === '' || trim($this->clientSecret) === '-'
        ) {
            return '';
        }

        $url = rtrim($this->authUrl, '/') . '/accesstoken?grant_type=client_credentials';
        $body = http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $res = $this->httpClient->request(
            'POST',
            $url,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $body,
            $this->timeoutPolicy,
        );

        if ($res['status'] < 200 || $res['status'] >= 300) {
            return '';
        }

        $decoded = json_decode($res['body'], true);
        if (!is_array($decoded) || empty($decoded['access_token'])) {
            return '';
        }

        $this->cachedToken = (string) $decoded['access_token'];
        $ttl = (int) ($decoded['expires_in'] ?? 3600);
        $this->expiresAtEpoch = time() + max(60, $ttl);

        return $this->cachedToken;
    }
}
