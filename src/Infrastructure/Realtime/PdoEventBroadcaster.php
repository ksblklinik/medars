<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

final class PdoEventBroadcaster implements EventBroadcasterInterface
{
    /**
     * @var array<string, array<int, callable(EventMessage): void>>
     */
    private array $localSubscribers = [];

    public function __construct(
        private readonly \PDO $pdo
    ) {}

    public function ensureTableExists(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS sse_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel VARCHAR(64) NOT NULL,
                    event VARCHAR(128) NOT NULL,
                    payload TEXT NOT NULL,
                    created_at DATETIME NOT NULL
                )'
            );
        } else {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS sse_events (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    channel VARCHAR(64) NOT NULL,
                    event VARCHAR(128) NOT NULL,
                    payload TEXT NOT NULL,
                    created_at DATETIME NOT NULL,
                    KEY idx_sse_channel_id (channel, id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
    }

    public function publish(string $channel, EventMessage $message): void
    {
        $sql = 'INSERT INTO sse_events (channel, event, payload, created_at) VALUES (:channel, :event, :payload, :created_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'channel' => $channel,
            'event' => $message->event,
            'payload' => json_encode($message->data, JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!empty($this->localSubscribers[$channel])) {
            foreach ($this->localSubscribers[$channel] as $listener) {
                $listener($message);
            }
        }
    }

    public function subscribe(string $channel, callable $listener): void
    {
        $this->localSubscribers[$channel][] = $listener;
    }

    /**
     * @return array<int, EventMessage>
     */
    public function fetchSince(string $channel, int $lastId = 0, int $limit = 50): array
    {
        $sql = 'SELECT id, event, payload FROM sse_events WHERE channel = :channel AND id > :last_id ORDER BY id ASC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'channel' => $channel,
            'last_id' => $lastId,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $messages = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
            $messages[] = new EventMessage(
                event: (string) $row['event'],
                data: $decoded,
                id: (string) $row['id']
            );
        }

        return $messages;
    }
}
