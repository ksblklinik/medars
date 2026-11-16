<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Modules\Infrastructure\PdoModuleRegistryRepository;
use App\Modules\Model\ModuleStatus;
use PHPUnit\Framework\TestCase;

final class PdoModuleRegistryRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoModuleRegistryRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->repository = new PdoModuleRegistryRepository($this->pdo);
        $this->repository->ensureSchema();
    }

    public function test_persists_updates_and_deletes_module_record(): void
    {
        $manifest = ['slug' => 'pendaftaran', 'name' => 'Pendaftaran'];

        $this->repository->save('pendaftaran', 'Pendaftaran', '1.0.0', ModuleStatus::INSTALLED, $manifest);
        $found = $this->repository->find('pendaftaran');

        $this->assertNotNull($found);
        $this->assertSame('installed', $found['status']);

        $this->repository->save('pendaftaran', 'Pendaftaran', '1.0.0', ModuleStatus::ACTIVE, $manifest);
        $updated = $this->repository->find('pendaftaran');
        $this->assertSame('active', $updated['status']);

        $all = $this->repository->all();
        $this->assertCount(1, $all);

        $this->repository->delete('pendaftaran');
        $this->assertNull($this->repository->find('pendaftaran'));
    }
}
