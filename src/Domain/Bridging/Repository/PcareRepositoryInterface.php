<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface PcareRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listPendaftaran(string $from = '', string $to = '', string $search = ''): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function savePendaftaran(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listKunjungan(string $from = '', string $to = '', string $search = ''): array;

    /** @return array<string, mixed>|null */
    public function findKunjungan(string $noKunjungan): ?array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveKunjungan(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listRujukan(string $search = ''): array;

    /** @return array<string, mixed>|null */
    public function findRujukan(string $noKunjungan): ?array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveRujukan(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listObat(string $noKunjungan = ''): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveObat(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listTindakan(string $noKunjungan = ''): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveTindakan(array $data): array;
}
