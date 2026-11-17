<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface ModuleRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listModules(string $filter = 'all', string $search = ''): array;

    public function setActive(string $dir, bool $active): bool;

    public function updateSequence(string $dir, int $sequence): bool;

    /**
     * @return list<string>
     */
    public function getInactiveModuleDirs(): array;
}
