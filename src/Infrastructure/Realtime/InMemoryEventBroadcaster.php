<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

final class InMemoryEventBroadcaster implements EventBroadcasterInterface
{
    /**
     * @var array<string, array<int, callable(EventMessage): void>>
     */
    private array $subscribers = [];

    public function publish(string $channel, EventMessage $message): void
    {
        if (empty($this->subscribers[$channel])) {
            return;
        }

        foreach ($this->subscribers[$channel] as $listener) {
            $listener($message);
        }
    }

    public function subscribe(string $channel, callable $listener): void
    {
        $this->subscribers[$channel][] = $listener;
    }
}
