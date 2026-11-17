<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoAnjunganQueueRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoAnjunganQueueRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoAnjunganQueueRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoAnjunganQueueRepository($this->pdo);
    }

    public function testSeparatedFarmasiAndKasirQueuesAndThermalTicket(): void
    {
        $f1 = $this->repo->issueTicket('Farmasi', '2026/11/17/000301', 'RM01', 'Budi', 'Loket Obat 1');
        $k1 = $this->repo->issueTicket('Kasir', '2026/11/17/000301', 'RM01', 'Budi', 'Kasir 1');

        $this->assertSame('F001', $f1['noantrian']);
        $this->assertSame('K001', $k1['noantrian']);
        $this->assertCount(1, $this->repo->listByType('Farmasi'));
        $this->assertCount(1, $this->repo->listByType('Kasir'));

        $called = $this->repo->callNext('Farmasi', 'Loket Obat 2');
        $this->assertNotNull($called);
        $this->assertSame('F001', $called['noantrian']);
        $this->assertSame('1', (string) $called['status']);
        $this->assertNotNull($this->repo->findTicket((int) $f1['id']));
    }
}
