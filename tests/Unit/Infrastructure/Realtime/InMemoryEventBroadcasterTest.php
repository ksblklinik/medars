<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Realtime;

use App\Infrastructure\Realtime\EventMessage;
use App\Infrastructure\Realtime\InMemoryEventBroadcaster;
use PHPUnit\Framework\TestCase;

final class InMemoryEventBroadcasterTest extends TestCase
{
    public function test_publishes_and_dispatches_event_to_channel_subscribers(): void
    {
        $broadcaster = new InMemoryEventBroadcaster();
        $received = [];

        $broadcaster->subscribe('antrean.poli', function (EventMessage $msg) use (&$received) {
            $received[] = $msg;
        });

        $message = new EventMessage(
            event: 'panggil',
            data: ['nomor' => 'B-02', 'poli' => 'Poli Gigi']
        );

        $broadcaster->publish('antrean.poli', $message);

        $this->assertCount(1, $received);
        $this->assertSame('panggil', $received[0]->event);
        $this->assertSame('B-02', $received[0]->data['nomor']);
    }

    public function test_does_not_leak_events_across_different_channels(): void
    {
        $broadcaster = new InMemoryEventBroadcaster();
        $farmasiEvents = [];

        $broadcaster->subscribe('antrean.farmasi', function (EventMessage $msg) use (&$farmasiEvents) {
            $farmasiEvents[] = $msg;
        });

        $poliMessage = new EventMessage(
            event: 'panggil',
            data: ['nomor' => 'A-01', 'poli' => 'Poli Umum']
        );

        // Publish ke channel poli, bukan farmasi
        $broadcaster->publish('antrean.poli', $poliMessage);

        $this->assertCount(0, $farmasiEvents, 'Subscriber farmasi tidak boleh menerima event poli.');
    }
}
