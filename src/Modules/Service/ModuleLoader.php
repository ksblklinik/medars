<?php

declare(strict_types=1);

namespace App\Modules\Service;

use App\Infrastructure\Database\TransactionManagerInterface;
use App\Modules\Contract\ModuleRegistryRepositoryInterface;
use App\Modules\Exception\ModuleDependencyException;
use App\Modules\Model\ModuleManifest;
use App\Modules\Model\ModuleStatus;

final class ModuleLoader
{
    public function __construct(
        private readonly ModuleRegistryRepositoryInterface $repository,
        private readonly DependencyResolver $dependencyResolver,
        private readonly TransactionManagerInterface $transactionManager
    ) {}

    /**
     * Mendaftarkan modul baru ke registri sistem.
     *
     * @return array<string, mixed>
     */
    public function install(ModuleManifest $manifest): array
    {
        return $this->transactionManager->transaction(function () use ($manifest) {
            $this->repository->save(
                $manifest->slug,
                $manifest->name,
                $manifest->version,
                ModuleStatus::INSTALLED,
                $manifest->toArray()
            );

            /** @var array<string, mixed> $saved */
            $saved = $this->repository->find($manifest->slug);
            return $saved;
        });
    }

    /**
     * Mengaktifkan modul setelah memvalidasi seluruh dependensinya sudah aktif.
     */
    public function activate(string $slug): void
    {
        $module = $this->repository->find($slug);
        if ($module === null) {
            throw new \InvalidArgumentException("Modul '{$slug}' belum terdaftar.");
        }

        $manifestData = json_decode((string) $module['manifest_json'], true, 512, JSON_THROW_ON_ERROR);
        $dependencies = $manifestData['dependencies'] ?? [];

        // Validasi seluruh modul yang dibutuhkan sudah terpasang dan berstatus aktif
        foreach (array_keys($dependencies) as $depSlug) {
            $dep = $this->repository->find($depSlug);
            if ($dep === null || $dep['status'] !== ModuleStatus::ACTIVE->value) {
                throw ModuleDependencyException::missingDependency($slug, $depSlug);
            }
        }

        $this->repository->save(
            $slug,
            (string) $module['name'],
            (string) $module['version'],
            ModuleStatus::ACTIVE,
            $manifestData
        );
    }

    /**
     * Menonaktifkan modul tanpa menghapus data.
     */
    public function deactivate(string $slug): void
    {
        $module = $this->repository->find($slug);
        if ($module === null) {
            return;
        }

        $manifestData = json_decode((string) $module['manifest_json'], true, 512, JSON_THROW_ON_ERROR);

        $this->repository->save(
            $slug,
            (string) $module['name'],
            (string) $module['version'],
            ModuleStatus::INACTIVE,
            $manifestData
        );
    }

    /**
     * Mencopot modul dari sistem.
     *
     * @param string $slug
     * @param bool $purgeData Jika true, jalankan rollback skema. Jika false (default), pertahankan data demi audit medis.
     */
    public function uninstall(string $slug, bool $purgeData = false): void
    {
        $this->transactionManager->transaction(function () use ($slug, $purgeData) {
            $this->deactivate($slug);
            $this->repository->delete($slug);
            // Pada mode safe uninstall (purgeData = false), tabel data tidak di-drop.
        });
    }

    /**
     * Mengambil daftar navigasi menu dari seluruh modul yang sedang aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveNavigations(): array
    {
        $modules = $this->repository->all();
        $navigations = [];

        foreach ($modules as $mod) {
            if ($mod['status'] === ModuleStatus::ACTIVE->value) {
                $manifest = json_decode((string) $mod['manifest_json'], true, 512, JSON_THROW_ON_ERROR);
                if (!empty($manifest['navigation'])) {
                    $navigations[] = $manifest['navigation'];
                }
            }
        }

        return $navigations;
    }
}
