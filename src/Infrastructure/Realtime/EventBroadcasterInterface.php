<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

interface EventBroadcasterInterface
{
    /**
     * Mempublikasikan event ke channel tertentu.
     */
    public function publish(string $channel, EventMessage $message): void;

    /**
     * Mendaftarkan listener callback ke channel.
     *
     * @param callable(EventMessage): void $listener
     */
    public function subscribe(string $channel, callable $listener): void;
}
