<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

final class SseStreamResponse
{
    /**
     * Memformat EventMessage menjadi baris data protokol SSE.
     */
    public static function formatEvent(EventMessage $message): string
    {
        $output = '';

        if (!empty($message->event)) {
            $output .= "event: {$message->event}\n";
        }

        if ($message->id !== null) {
            $output .= "id: {$message->id}\n";
        }

        if ($message->retry !== null) {
            $output .= "retry: {$message->retry}\n";
        }

        $jsonPayload = json_encode($message->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $output .= "data: {$jsonPayload}\n\n";

        return $output;
    }

    /**
     * Memformat komentar SSE ping untuk menjaga koneksi tetap hidup melalui proxy/router.
     */
    public static function formatHeartbeat(): string
    {
        return ": ping\n\n";
    }

    /**
     * Header HTTP wajib untuk transmisi streaming SSE.
     *
     * @return array<string, string>
     */
    public static function getHeaders(): array
    {
        return [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Menghindari Nginx buffering output stream
        ];
    }
}
