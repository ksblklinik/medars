<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoModuleRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoModuleRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoModuleRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoModuleRepository($this->pdo);
    }

    public function testListToggleAndSequenceModules(): void
    {
        $all = $this->repo->listModules();
        self::assertNotEmpty($all);

        // Non-core module can be deactivated & reactivated
        self::assertTrue($this->repo->setActive('inventaris', false));
        self::assertContains('inventaris', $this->repo->getInactiveModuleDirs());

        $inactive = $this->repo->listModules('inactive');
        self::assertCount(1, $inactive);
        self::assertSame('inventaris', $inactive[0]['dir']);

        self::assertTrue($this->repo->setActive('inventaris', true));
        self::assertNotContains('inventaris', $this->repo->getInactiveModuleDirs());

        self::assertTrue($this->repo->updateSequence('inventaris', 99));
        $filtered = $this->repo->listModules('all', 'inventaris');
        self::assertCount(1, $filtered);
        self::assertSame(99, $filtered[0]['sequence']);
    }

    public function testCannotDeactivateCoreModule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->setActive('modules', false);
    }
}
