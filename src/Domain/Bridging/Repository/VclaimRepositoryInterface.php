<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface VclaimRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function findPesertaLocal(string $noKartuOrNik): ?array;

    /** @return list<array<string, mixed>> */
    public function listSep(string $from = '', string $to = '', string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function saveSep(array $data): array;

    /** @return array<string, mixed>|null */
    public function findSep(string $noSep): ?array;

    /** @return list<array<string, mixed>> */
    public function listRujukan(string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function saveRujukan(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listSuratKontrol(string $search = ''): array;

    /** @param array<string, mixed> $data */
    public function saveSuratKontrol(array $data): array;
}
