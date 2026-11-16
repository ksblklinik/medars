<?php

declare(strict_types=1);

namespace App\Application\Bridging\UseCase;

use App\Domain\Bridging\Repository\BridgingQueueRepositoryInterface;
use App\Domain\Bridging\Service\HttpClientInterface;
use App\Domain\Bridging\ValueObject\BridgingCredentials;
use App\Domain\Bridging\ValueObject\TimeoutPolicy;

final readonly class ProcessBridgingQueueUseCase
{
    public function __construct(
        private BridgingQueueRepositoryInterface $queueRepo,
        private HttpClientInterface $httpClient,
        private BridgingCredentials $credentials,
        private TimeoutPolicy $timeoutPolicy = new TimeoutPolicy(2, 4),
    ) {}

    /**
     * @return array{processed: int, synced: int, failed: int, circuit_open: bool}
     */
    public function processPending(int $limit = 10): array
    {
        if (!$this->credentials->isActive()) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'circuit_open' => true];
        }

        $items = $this->queueRepo->fetchPending($limit);
        $synced = 0;
        $failed = 0;

        foreach ($items as $item) {
            $id = (int) $item['id'];
            $url = rtrim($this->credentials->baseUrl, '/') . '/' . ltrim((string) $item['endpoint'], '/');
            $res = $this->httpClient->request(
                'POST',
                $url,
                [
                    'Authorization' => 'Bearer ' . $this->credentials->userKey,
                    'Content-Type' => 'application/json',
                ],
                (string) $item['payload_json'],
                $this->timeoutPolicy,
            );

            if ($res['status'] >= 200 && $res['status'] < 300) {
                $decoded = json_decode($res['body'], true);
                $extId = is_array($decoded) ? (string) ($decoded['id'] ?? '') : '';
                $this->queueRepo->markSynced($id, $extId);
                $synced++;
            } else {
                $this->queueRepo->markFailed($id, 'HTTP ' . $res['status'] . ': ' . $res['body']);
                $failed++;
            }
        }

        return [
            'processed' => count($items),
            'synced' => $synced,
            'failed' => $failed,
            'circuit_open' => false,
        ];
    }
}
