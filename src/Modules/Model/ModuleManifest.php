<?php

declare(strict_types=1);

namespace App\Modules\Model;

use App\Modules\Exception\ModuleManifestException;

final readonly class ModuleManifest
{
    /**
     * @param array<string, string> $dependencies Daftar modul prasyarat beserta versi (e.g. ['farmasi' => '^1.0.0'])
     * @param array<int, string> $permissions Daftar hak akses granular RBAC
     * @param array<string, mixed> $navigation Konfigurasi menu sidebar antarmuka
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $version,
        public string $description,
        public string $author,
        public array $dependencies = [],
        public array $permissions = [],
        public array $navigation = []
    ) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ModuleManifestException::invalidSlug($slug);
        }

        if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
            throw ModuleManifestException::invalidVersion($version);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'dependencies' => $this->dependencies,
            'permissions' => $this->permissions,
            'navigation' => $this->navigation,
        ];
    }
}
