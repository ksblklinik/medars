<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Realtime;

use App\Infrastructure\Realtime\EventMessage;
use App\Infrastructure\Realtime\PdoEventBroadcaster;
use PHPUnit\Framework\TestCase;

final class PdoEventBroadcasterTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function test_publishes_and_fetches_events_across_separate_broadcaster_instances(): void
    {
        $publisher = new PdoEventBroadcaster($this->pdo);
        $publisher->ensureTableExists();

        $publisher->publish('antrean', new EventMessage(
            event: 'queue.called',
            data: ['no_reg' => '001', 'nm_poli' => 'Poli Umum']
        ));

        $publisher->publish('antrean', new EventMessage(
            event: 'queue.called',
            data: ['no_reg' => '002', 'nm_poli' => 'Poli Gigi']
        ));

        // Simulasikan proses HTTP SSE berbeda yang membaca dari database yang sama
        $consumer = new PdoEventBroadcaster($this->pdo);
        $events = $consumer->fetchSince('antrean', 0);

        $this->assertCount(2, $events);
        $this->assertSame('queue.called', $events[0]->event);
        $this->assertSame('001', $events[0]->data['no_reg']);
        $this->assertSame('002', $events[1]->data['no_reg']);

        // Ambil hanya event setelah ID pertama
        $laterEvents = $consumer->fetchSince('antrean', (int) $events[0]->id);
        $this->assertCount(1, $laterEvents);
        $this->assertSame('002', $laterEvents[0]->data['no_reg']);
    }
}
