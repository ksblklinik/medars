<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoKeuanganRepository;
use PHPUnit\Framework\TestCase;

final class PdoKeuanganRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoKeuanganRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->repo = new PdoKeuanganRepository(
            $this->pdo,
            new PdoBillingLedgerRepository($this->pdo),
            new PdoTransactionManager($this->pdo)
        );
    }

    public function testPostManualJurnalBukuBesarAndLabaRugi(): void
    {
        $today = date('Y-m-d');
        $this->repo->saveRekening('1101.01', 'Kas Fisik', 'Aset', 'D');
        $this->repo->saveRekening('4101.01', 'Pendapatan Layanan', 'Pendapatan', 'K');
        $this->repo->saveRekening('5101.01', 'Beban Listrik & Air', 'Beban', 'D');

        $this->repo->postManualJurnal('BKM-01', 'Penerimaan Tunai', '1101.01', '4101.01', 500000.0, new \DateTimeImmutable($today . ' 09:00:00'));
        $this->repo->postManualJurnal('BKK-01', 'Bayar Listrik', '5101.01', '1101.01', 150000.0, new \DateTimeImmutable($today . ' 10:00:00'));

        $jurnals = $this->repo->listJurnal($today, $today);
        $this->assertCount(4, $jurnals);

        $bukuBesar = $this->repo->getBukuBesar($today, $today);
        $this->assertNotEmpty($bukuBesar);

        $lr = $this->repo->getLabaRugi($today, $today);
        $this->assertSame(500000.0, $lr['total_pendapatan']);
        $this->assertSame(150000.0, $lr['total_beban']);
        $this->assertSame(350000.0, $lr['laba_bersih']);
    }
}
