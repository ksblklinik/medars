<?php

declare(strict_types=1);

namespace App\Application\Rme\DTO;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class SavePrbCommand
{
    private const ALLOWED_FIELDS = [
        'no_rawat', 'tgl_perawatan', 'jam_rawat',
        'diagnosa_kronis', 'items', 'catatan',
    ];

    /**
     * @param list<array{nama_obat: string, jumlah: int|string, aturan_pakai: string}> $items
     */
    public function __construct(
        public NoRawat $noRawat,
        public string $diagnosaKronis,
        public array $items,
        public string $tglPerawatan = '',
        public string $jamRawat = '',
        public string $catatan = '',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromWhitelistedPayload(array $payload): self
    {
        foreach (array_keys($payload) as $key) {
            if (!in_array((string) $key, self::ALLOWED_FIELDS, true)) {
                throw new \InvalidArgumentException("Field tidak diizinkan pada payload PRB: {$key}");
            }
        }

        $items = (array) ($payload['items'] ?? []);
        if ($items === []) {
            throw new \InvalidArgumentException('Daftar obat resep PRB tidak boleh kosong.');
        }

        $normalizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['nama_obat'])) {
                throw new \InvalidArgumentException('Setiap item resep PRB wajib memiliki nama_obat.');
            }
            $normalizedItems[] = [
                'nama_obat' => trim((string) $item['nama_obat']),
                'jumlah' => (int) ($item['jumlah'] ?? 30),
                'aturan_pakai' => trim((string) ($item['aturan_pakai'] ?? '1x1')),
            ];
        }

        return new self(
            noRawat: new NoRawat((string) ($payload['no_rawat'] ?? '')),
            diagnosaKronis: trim((string) ($payload['diagnosa_kronis'] ?? '')),
            items: $normalizedItems,
            tglPerawatan: trim((string) ($payload['tgl_perawatan'] ?? '')),
            jamRawat: trim((string) ($payload['jam_rawat'] ?? '')),
            catatan: trim((string) ($payload['catatan'] ?? '')),
        );
    }
}
