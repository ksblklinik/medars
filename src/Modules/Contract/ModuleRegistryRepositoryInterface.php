<?php

declare(strict_types=1);

namespace App\Modules\Contract;

use App\Modules\Model\ModuleStatus;

interface ModuleRegistryRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * @param array<string, mixed> $manifest
     */
    public function save(string $slug, string $name, string $version, ModuleStatus $status, array $manifest): void;

    public function delete(string $slug): void;
}
