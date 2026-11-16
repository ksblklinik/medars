<?php

declare(strict_types=1);

namespace App\Domain\Setting\Repository;

interface SettingRepositoryInterface
{
    public function get(string $module, string $field): ?string;

    /**
     * @return array<string, string>
     */
    public function getAll(string $module): array;

    public function set(string $module, string $field, string $value): void;
}
