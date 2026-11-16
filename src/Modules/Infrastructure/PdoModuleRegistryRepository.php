<?php

declare(strict_types=1);

namespace App\Modules\Infrastructure;

use App\Modules\Contract\ModuleRegistryRepositoryInterface;
use App\Modules\Model\ModuleStatus;

final class PdoModuleRegistryRepository implements ModuleRegistryRepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * Memastikan tabel registri modul tersedia pada database next.
     */
    public function ensureSchema(): void
    {
        $driver = (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS sys_modules (
                slug VARCHAR(100) PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                version VARCHAR(32) NOT NULL,
                status VARCHAR(20) NOT NULL,
                manifest_json JSON NOT NULL,
                installed_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            return;
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sys_modules (
            slug TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            version TEXT NOT NULL,
            status TEXT NOT NULL,
            manifest_json TEXT NOT NULL,
            installed_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
    }

    public function find(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT slug, name, version, status, manifest_json, installed_at, updated_at FROM sys_modules WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT slug, name, version, status, manifest_json, installed_at, updated_at FROM sys_modules ORDER BY name ASC');
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    public function save(string $slug, string $name, string $version, ModuleStatus $status, array $manifest): void
    {
        $driver = (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($driver === 'mysql') {
            $sql = 'INSERT INTO sys_modules (slug, name, version, status, manifest_json, installed_at, updated_at)
                    VALUES (:slug, :name, :version, :status, :manifest_json, :installed_at, :updated_at)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        version = VALUES(version),
                        status = VALUES(status),
                        manifest_json = VALUES(manifest_json),
                        updated_at = VALUES(updated_at)';
        } else {
            $sql = 'INSERT INTO sys_modules (slug, name, version, status, manifest_json, installed_at, updated_at)
                    VALUES (:slug, :name, :version, :status, :manifest_json, :installed_at, :updated_at)
                    ON CONFLICT(slug) DO UPDATE SET
                        name = excluded.name,
                        version = excluded.version,
                        status = excluded.status,
                        manifest_json = excluded.manifest_json,
                        updated_at = excluded.updated_at';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'slug'          => $slug,
            'name'          => $name,
            'version'       => $version,
            'status'        => $status->value,
            'manifest_json' => $manifestJson,
            'installed_at'  => $now,
            'updated_at'    => $now,
        ]);
    }

    public function delete(string $slug): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sys_modules WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
    }
}
