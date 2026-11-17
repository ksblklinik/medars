<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Ranap;

use App\Application\Ranap\UseCase\ManageKamarInapUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoRawatInapRepository;
use PHPUnit\Framework\TestCase;

final class RawatInapWorkflowTest extends TestCase
{
    private \PDO $pdo;
    private PdoRawatInapRepository $repo;
    private PdoBillingLedgerRepository $ledgerRepo;
    private ManageKamarInapUseCase $useCase;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->repo = new PdoRawatInapRepository($this->pdo);
        $this->ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $this->useCase = new ManageKamarInapUseCase(
            $this->repo,
            $this->ledgerRepo,
            new PdoTransactionManager($this->pdo)
        );
    }

    public function testCheckInAndCheckoutCalculatesStayAndPostsLedger(): void
    {
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000070', 'Slamet', '1975-01-01', 'L');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut, status_bayar)
            VALUES ('001', '2026/11/14/000070', '2026-11-14', '09:00:00', 'DR01', '000070', 'IGDK', 'A09', 'Belum', 'Ralan', 'Belum Bayar');
        ");
        $this->repo->saveKamar([
            'kd_kamar' => 'KLS1-01',
            'kd_bangsal' => 'B01',
            'nm_bangsal' => 'Melati',
            'trf_kamar' => 250000,
            'kelas' => 'Kelas 1',
        ]);

        $this->useCase->checkIn(
            new NoRawat('2026/11/14/000070'),
            'KLS1-01',
            new \DateTimeImmutable('2026-11-14 10:00:00')
        );

        $kamar = $this->repo->findKamar('KLS1-01');
        $this->assertSame('ISI', $kamar['status']);
        $sl = (string) $this->pdo->query("SELECT status_lanjut FROM reg_periksa WHERE no_rawat = '2026/11/14/000070'")->fetchColumn();
        $this->assertSame('Ranap', $sl);

        $out = $this->useCase->checkOut(
            new NoRawat('2026/11/14/000070'),
            'KLS1-01',
            'Membaik',
            'Tunai',
            new \DateTimeImmutable('2026-11-17 10:00:00')
        );

        $this->assertSame(3, $out['lama']);
        $this->assertSame(750000.0, $out['ttl_biaya']);
        $this->assertSame('KOSONG', $this->repo->findKamar('KLS1-01')['status']);

        $ledger = $this->ledgerRepo->findByNoRawat(new NoRawat('2026/11/14/000070'));
        $this->assertCount(1, $ledger);
        $this->assertSame(750000.0, $ledger[0]->totalAmount);
    }
}
