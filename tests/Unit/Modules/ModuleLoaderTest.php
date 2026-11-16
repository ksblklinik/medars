<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Infrastructure\Database\PdoTransactionManager;
use App\Modules\Contract\ModuleRegistryRepositoryInterface;
use App\Modules\Exception\ModuleDependencyException;
use App\Modules\Exception\ModuleManifestException;
use App\Modules\Model\ModuleManifest;
use App\Modules\Model\ModuleStatus;
use App\Modules\Service\DependencyResolver;
use App\Modules\Service\ModuleLoader;
use PHPUnit\Framework\TestCase;

final class ModuleLoaderTest extends TestCase
{
    private \PDO $pdo;
    private PdoTransactionManager $txManager;
    private ModuleRegistryRepositoryInterface $repository;
    private DependencyResolver $resolver;
    private ModuleLoader $loader;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Skema tabel registri modul
        $this->pdo->exec('CREATE TABLE sys_modules (
            slug TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            version TEXT NOT NULL,
            status TEXT NOT NULL,
            manifest_json TEXT NOT NULL,
            installed_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        $this->txManager = new PdoTransactionManager($this->pdo);

        // In-memory repository implementation untuk testing
        $this->repository = new class($this->pdo) implements ModuleRegistryRepositoryInterface {
            public function __construct(private \PDO $pdo) {}

            public function find(string $slug): ?array
            {
                $stmt = $this->pdo->prepare('SELECT * FROM sys_modules WHERE slug = ?');
                $stmt->execute([$slug]);
                $res = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $res ?: null;
            }

            public function all(): array
            {
                $stmt = $this->pdo->query('SELECT * FROM sys_modules');
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            public function save(string $slug, string $name, string $version, ModuleStatus $status, array $manifest): void
            {
                $stmt = $this->pdo->prepare('INSERT INTO sys_modules (slug, name, version, status, manifest_json, installed_at, updated_at) 
                    VALUES (:slug, :name, :version, :status, :manifest, datetime("now"), datetime("now"))
                    ON CONFLICT(slug) DO UPDATE SET status = :status, updated_at = datetime("now")');
                $stmt->execute([
                    'slug' => $slug,
                    'name' => $name,
                    'version' => $version,
                    'status' => $status->value,
                    'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                ]);
            }

            public function delete(string $slug): void
            {
                $stmt = $this->pdo->prepare('DELETE FROM sys_modules WHERE slug = ?');
                $stmt->execute([$slug]);
            }
        };

        $this->resolver = new DependencyResolver();
        $this->loader = new ModuleLoader($this->repository, $this->resolver, $this->txManager);
    }

    public function test_should_register_module_when_manifest_is_valid(): void
    {
        $manifest = new ModuleManifest(
            slug: 'pendaftaran',
            name: 'Pendaftaran & Antrean Pasien',
            version: '1.0.0',
            description: 'Modul registrasi pasien dan antrean klinik',
            author: 'KSM Dev Team',
            dependencies: [],
            permissions: ['pendaftaran.view', 'pendaftaran.create'],
            navigation: [
                'title' => 'Pendaftaran',
                'icon' => 'fa-user-plus',
                'route' => '/pendaftaran',
            ]
        );

        $module = $this->loader->install($manifest);

        $this->assertSame('pendaftaran', $module['slug']);
        $this->assertSame(ModuleStatus::INSTALLED->value, $module['status']);
    }

    public function test_should_reject_manifest_with_invalid_slug(): void
    {
        $this->expectException(ModuleManifestException::class);
        $this->expectExceptionMessage('Format slug modul harus kebab-case');

        new ModuleManifest(
            slug: 'Pendaftaran Pasien Salah', // Huruf kapital dan spasi
            name: 'Pendaftaran',
            version: '1.0.0',
            description: 'Test',
            author: 'Test',
            dependencies: []
        );
    }

    public function test_should_activate_module_and_retrieve_active_navigations(): void
    {
        $manifest = new ModuleManifest(
            slug: 'rawat-jalan',
            name: 'Rawat Jalan',
            version: '1.0.0',
            description: 'Pelayanan Poliklinik Rawat Jalan',
            author: 'KSM Dev Team',
            dependencies: [],
            navigation: ['title' => 'Poli Umum', 'icon' => 'fa-stethoscope', 'route' => '/rawat-jalan']
        );

        $this->loader->install($manifest);
        $this->loader->activate('rawat-jalan');

        $activeNavs = $this->loader->getActiveNavigations();
        $this->assertCount(1, $activeNavs);
        $this->assertSame('Poli Umum', $activeNavs[0]['title']);
    }

    public function test_should_block_module_activation_if_required_dependency_is_missing(): void
    {
        $manifest = new ModuleManifest(
            slug: 'e-resep',
            name: 'E-Resep Dokter',
            version: '1.0.0',
            description: 'Preskripsi obat digital',
            author: 'KSM Dev Team',
            dependencies: ['farmasi' => '^1.0.0'] // Butuh farmasi yang belum aktif
        );

        $this->loader->install($manifest);

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage('Prasyarat dependensi modul belum terpenuhi: farmasi');

        $this->loader->activate('e-resep');
    }

    public function test_should_deactivate_module_and_hide_navigation(): void
    {
        $manifest = new ModuleManifest(
            slug: 'laboratorium',
            name: 'Laboratorium',
            version: '1.0.0',
            description: 'Pemeriksaan Lab',
            author: 'KSM Dev Team',
            dependencies: [],
            navigation: ['title' => 'Lab Klinis', 'icon' => 'fa-flask', 'route' => '/lab']
        );

        $this->loader->install($manifest);
        $this->loader->activate('laboratorium');
        $this->assertCount(1, $this->loader->getActiveNavigations());

        $this->loader->deactivate('laboratorium');
        $this->assertCount(0, $this->loader->getActiveNavigations(), 'Menu navigasi harus disembunyikan saat modul dinonaktifkan.');
    }

    public function test_should_preserve_database_data_during_safe_uninstall(): void
    {
        // Simulasi modul kasir membuat tabel transaksi
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS kasir_billing (id INTEGER PRIMARY KEY, no_rawat TEXT, total REAL)');
        $this->pdo->exec("INSERT INTO kasir_billing (no_rawat, total) VALUES ('2026/11/16/000001', 150000.0)");

        $manifest = new ModuleManifest(
            slug: 'kasir',
            name: 'Kasir & Billing',
            version: '1.0.0',
            description: 'Kasir Pembayaran',
            author: 'KSM Dev Team',
            dependencies: []
        );

        $this->loader->install($manifest);
        $this->loader->activate('kasir');

        // Safe uninstall (default, purgeData = false)
        $this->loader->uninstall('kasir', purgeData: false);

        // Verifikasi registrasi modul terhapus
        $this->assertNull($this->repository->find('kasir'));

        // Verifikasi tabel data transaksi TETAP UTUH (tidak dihapus demi retensi data audit medis)
        $stmt = $this->pdo->query('SELECT COUNT(*) as cnt FROM kasir_billing');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['cnt'], 'Tabel data transaksi wajib dipertahankan pada safe uninstall.');
    }
}
