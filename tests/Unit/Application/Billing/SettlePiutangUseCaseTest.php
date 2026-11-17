<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing;

use App\Application\Billing\UseCase\SettlePiutangUseCase;
use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use PHPUnit\Framework\TestCase;

final class SettlePiutangUseCaseTest extends TestCase
{
    private \PDO $pdo;
    private PdoBillingLedgerRepository $ledgerRepo;
    private SettlePiutangUseCase $useCase;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $this->useCase = new SettlePiutangUseCase(
            $this->ledgerRepo,
            new PdoTransactionManager($this->pdo)
        );
    }

    public function testSettlePiutangPostsBalancedCashToReceivableJournalAndBlocksDuplicate(): void
    {
        $noRawat = new NoRawat('2026/11/17/000001');
        $this->ledgerRepo->addItem(new BillingLedgerItem(
            id: null,
            noRawat: $noRawat,
            category: 'Tindakan',
            itemCode: 'TND01',
            itemName: 'Kontrol Poli',
            qty: 1.0,
            unitPrice: 125000.0,
            totalAmount: 125000.0,
            claimType: ClaimType::fromString('Piutang'),
            status: LedgerStatus::fromString('Paid'),
            providerId: 'TND-1'
        ));

        $result = $this->useCase->execute($noRawat, new \DateTimeImmutable('2026-11-17 09:00:00'));

        $this->assertSame(125000.0, $result['settled_amount']);
        $this->assertNotEmpty($result['no_jurnal']);

        $jurnals = $this->ledgerRepo->findJurnalsByNoBukti('2026/11/17/000001-PIUTANG');
        $this->assertCount(1, $jurnals);
        $this->assertSame(JurnalEntry::COA_KAS_FISIK, $jurnals[0]->details[0]->kdRek);
        $this->assertSame(125000.0, $jurnals[0]->details[0]->debet);
        $this->assertSame(JurnalEntry::COA_PIUTANG_REKANAN, $jurnals[0]->details[1]->kdRek);
        $this->assertSame(125000.0, $jurnals[0]->details[1]->kredit);

        $this->expectException(\DomainException::class);
        $this->useCase->execute($noRawat);
    }

    public function testSettlePiutangRejectsWhenNoPaidPiutangExists(): void
    {
        $this->expectException(\DomainException::class);
        $this->useCase->execute(new NoRawat('2026/11/17/000099'));
    }
}
