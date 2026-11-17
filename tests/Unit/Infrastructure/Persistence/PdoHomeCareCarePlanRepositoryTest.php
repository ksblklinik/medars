<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoHomeCareCarePlanRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoHomeCareCarePlanRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoHomeCareCarePlanRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoHomeCareCarePlanRepository($this->pdo);
    }

    public function testCarePlanSignOffDpjpAndMidtransKaseraPayment(): void
    {
        $cp = $this->repo->saveCarePlan([
            'no_rawat' => '2026/11/17/000401',
            'care_plan' => 'Observasi luka pasca operasi & ganti verban tiap 2 hari',
            'nominal_tagihan' => 250000,
        ]);
        $this->assertSame(0, (int) $cp['dpjp_signed']);
        $this->assertSame('Belum', $cp['status_bayar']);

        $signed = $this->repo->signOffDpjp((int) $cp['id'], 'dr.andi');
        $this->assertNotNull($signed);
        $this->assertSame(1, (int) $signed['dpjp_signed']);
        $this->assertSame('dr.andi', $signed['dpjp_signed_by']);

        $paid = $this->repo->payCarePlan((int) $cp['id'], 'Midtrans', 'MID-20261117-01');
        $this->assertNotNull($paid);
        $this->assertSame('Lunas', $paid['status_bayar']);
        $this->assertSame('Midtrans', $paid['payment_gateway']);
        $this->assertCount(1, $this->repo->listCarePlans('2026/11/17/000401'));
    }
}
