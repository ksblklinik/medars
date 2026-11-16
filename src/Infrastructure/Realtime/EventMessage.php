<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

final readonly class EventMessage
{
    /**
     * @param string $event Nama tipe event (e.g. 'antrean.dipanggil', 'resep.selesai')
     * @param array<string, mixed> $data Payload data terstruktur
     * @param string|null $id ID unik event untuk penanda Last-Event-ID browser resume
     * @param int|null $retry Interval milidetik browser mencoba reconnect otomatis
     */
    public function __construct(
        public string $event,
        public array $data,
        public ?string $id = null,
        public ?int $retry = null
    ) {}
}
