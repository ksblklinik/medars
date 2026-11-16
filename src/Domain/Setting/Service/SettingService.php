<?php

declare(strict_types=1);

namespace App\Domain\Setting\Service;

use App\Domain\Setting\Repository\SettingRepositoryInterface;

final class SettingService
{
    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(
        private readonly SettingRepositoryInterface $repository,
    ) {
    }

    public function get(string $module, string $field, ?string $default = null): ?string
    {
        $key = "{$module}.{$field}";
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $val = $this->repository->get($module, $field);
        if ($val === null) {
            return $default;
        }

        $this->cache[$key] = $val;
        return $val;
    }

    public function getInt(string $module, string $field, int $default = 0): int
    {
        $val = $this->get($module, $field);
        return $val !== null ? (int) $val : $default;
    }

    public function getBool(string $module, string $field, bool $default = false): bool
    {
        $val = $this->get($module, $field);
        if ($val === null) {
            return $default;
        }

        return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }

    public function set(string $module, string $field, string $value): void
    {
        $key = "{$module}.{$field}";
        $this->repository->set($module, $field, $value);
        $this->cache[$key] = $value;
    }

    /**
     * @return array<string, string>
     */
    public function getAll(string $module): array
    {
        $all = $this->repository->getAll($module);
        foreach ($all as $field => $val) {
            $this->cache["{$module}.{$field}"] = $val;
        }

        return $all;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
