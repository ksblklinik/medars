<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface HfisAntrolRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listJadwalHfis(string $search = ''): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveJadwalHfis(array $data): array;

    /** @return list<array<string, mixed>> */
    public function listAntrolTasks(string $search = ''): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function pushAntrolTask(array $data): array;
}
