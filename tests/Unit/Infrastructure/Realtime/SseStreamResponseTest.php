<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Realtime;

use App\Infrastructure\Realtime\EventMessage;
use App\Infrastructure\Realtime\SseStreamResponse;
use PHPUnit\Framework\TestCase;

final class SseStreamResponseTest extends TestCase
{
    public function test_formats_single_event_according_to_sse_specification(): void
    {
        $message = new EventMessage(
            event: 'antrean.dipanggil',
            data: [
                'nomor' => 'A-05',
                'ruang' => 'Poli Umum',
                'dokter' => 'dr. Budi'
            ],
            id: 'evt-1001',
            retry: 3000
        );

        $formatted = SseStreamResponse::formatEvent($message);

        $expected = "event: antrean.dipanggil\n" .
                    "id: evt-1001\n" .
                    "retry: 3000\n" .
                    'data: {"nomor":"A-05","ruang":"Poli Umum","dokter":"dr. Budi"}' . "\n\n";

        $this->assertSame($expected, $formatted);
    }

    public function test_formats_heartbeat_ping_comment(): void
    {
        $ping = SseStreamResponse::formatHeartbeat();
        $this->assertSame(": ping\n\n", $ping);
    }

    public function test_provides_required_sse_headers(): void
    {
        $headers = SseStreamResponse::getHeaders();

        $this->assertSame('text/event-stream; charset=utf-8', $headers['Content-Type']);
        $this->assertSame('no-cache, no-transform', $headers['Cache-Control']);
        $this->assertSame('keep-alive', $headers['Connection']);
        $this->assertSame('no', $headers['X-Accel-Buffering']);
    }
}
