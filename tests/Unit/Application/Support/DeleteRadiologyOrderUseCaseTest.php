<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Support;

use App\Application\Support\UseCase\CreateRadiologyOrderUseCase;
use App\Application\Support\UseCase\DeleteRadiologyOrderUseCase;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoLabRadiologyRepository;
use PHPUnit\Framework\TestCase;

final class DeleteRadiologyOrderUseCaseTest extends TestCase
{
    private \PDO $pdo;
    private PdoLabRadiologyRepository $repo;
    private PdoBillingLedgerRepository $ledgerRepo;
    private CreateRadiologyOrderUseCase $createUseCase;
    private DeleteRadiologyOrderUseCase $deleteUseCase;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $tx = new PdoTransactionManager($this->pdo);
        $this->repo = new PdoLabRadiologyRepository($this->pdo);
        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $this->createUseCase = new CreateRadiologyOrderUseCase($this->repo, $this->ledgerRepo, $tx);
        $this->deleteUseCase = new DeleteRadiologyOrderUseCase($this->repo, $tx, $this->ledgerRepo);
    }

    public function testDeletePendingRadiologyOrderCancelsPendingLedger(): void
    {
        $noRawat = new NoRawat('2026/11/17/000030');
        $orders = $this->createUseCase->execute(
            noRawat: $noRawat,
            dokterPerujuk: 'DR01',
            items: [['kd_jenis_prw' => 'RAD01', 'nm_perawatan' => 'Thorax PA', 'harga' => 150000, 'claim_type' => 'Tunai']]
        );
        $noOrder = $orders[0]->noOrder;

        $this->deleteUseCase->execute($noOrder);

        $this->assertNull($this->repo->findRadiologiOrder($noOrder));
        $ledgerItems = $this->ledgerRepo->findByNoRawat($noRawat);
        $this->assertCount(1, $ledgerItems);
        $this->assertSame('Cancelled', $ledgerItems[0]->status->value());
    }

    public function testDeleteRadiologyOrderBlocksWhenResultAlreadyValidated(): void
    {
        $noRawat = new NoRawat('2026/11/17/000031');
        $orders = $this->createUseCase->execute(
            noRawat: $noRawat,
            dokterPerujuk: 'DR01',
            items: [['kd_jenis_prw' => 'RAD01', 'nm_perawatan' => 'Thorax PA', 'harga' => 150000, 'claim_type' => 'Tunai']]
        );
        $noOrder = $orders[0]->noOrder;
        $this->repo->markRadiologiResulted($noOrder, '2026-11-17', '09:00:00');

        $this->expectException(\DomainException::class);
        $this->deleteUseCase->execute($noOrder);
    }
}
