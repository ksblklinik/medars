<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoDashboardSummaryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoDashboardSummaryRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoDashboardSummaryRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoDashboardSummaryRepository($this->pdo);
    }

    public function testSevenDayTrendsAndAnnouncements(): void
    {
        $today = '2026-11-17';
        $this->pdo->exec("INSERT INTO reg_periksa (no_reg, no_rawat, no_rkm_medis, tgl_registrasi, jam_reg, kd_dokter, kd_poli, kd_pj, status_lanjut, stts)
            VALUES ('001', '2026/11/17/000001', '000001', '2026-11-17', '08:00:00', 'DR01', 'POL01', 'UMUM', 'Ralan', 'Belum')");
        $this->pdo->exec("INSERT INTO mlite_billing_ledger (no_rawat, item_code, item_name, category, qty, unit_price, total_amount, claim_type, status, created_at)
            VALUES ('2026/11/17/000001', 'T01', 'Konsultasi', 'Tindakan', 1, 75000, 75000, 'Tunai', 'Paid', '2026-11-17 08:30:00')");

        $trends = $this->repo->getSevenDayTrends($today);
        self::assertCount(7, $trends);
        $lastDay = $trends[6];
        self::assertSame($today, $lastDay['tanggal']);
        self::assertSame(1, $lastDay['kunjungan']);
        self::assertSame(75000.0, $lastDay['pendapatan']);

        $created = $this->repo->createAnnouncement([
            'judul' => 'Jadwal Briefing Pagi',
            'isi' => 'Briefing pukul 07.30 WIB di aula.',
            'prioritas' => 'Penting',
            'penulis' => 'Admin',
        ]);
        self::assertSame('Jadwal Briefing Pagi', $created['judul']);

        $list = $this->repo->listAnnouncements();
        self::assertCount(1, $list);

        self::assertTrue($this->repo->deleteAnnouncement((int) $created['id']));
        self::assertSame([], $this->repo->listAnnouncements());
    }
}
