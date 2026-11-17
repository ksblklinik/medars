<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Application\Bridging\UseCase\VerifyVedikaClaimUseCase;
use App\Application\Communication\UseCase\AssignHomeCarePreceptorUseCase;
use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\SaveLabResultUseCase;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Support\Entity\DetailPeriksaLab;
use App\Domain\Support\Entity\HasilRadiologi;
use App\Domain\Support\Entity\PermintaanLab;
use App\Domain\Support\Entity\PermintaanPemeriksaanLab;
use App\Domain\Support\Entity\PermintaanRadiologi;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoHomeCareRepository;
use App\Infrastructure\Persistence\PdoLabRadiologyRepository;
use App\Infrastructure\Persistence\PdoVedikaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for high review findings T1, T2, T6, T7.
 */
class HighFindingsFixesTest extends TestCase
{
    private PDO $pdo;
    private PdoTransactionManager $tx;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->tx = new PdoTransactionManager($this->pdo);

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Budi', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('BPJ', 'BPJS Kesehatan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'BPJ', 'Belum Bayar');
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi, tinggi, berat, spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi, lingkar_perut, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/16/000001', '{$today}', '08:15:00', '36.5', '120/80', '80', '20', '170', '65', '98', '15', 'Compos Mentis', 'Batuk', 'Ronkhi (-)', '-', '-', 'Obat', 'ISPA', '-', '-', 'DR001');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia) VALUES ('J06.9', 'Acute URI', 'ISPA Akut');
            INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas) VALUES ('2026/11/16/000001', 'J06.9', 'Ralan', 1);
        ");
    }

    // ---- T1: Retry atomik nomor order PL saat tabrakan PK ----

    public function testCreateLabOrderRetriesOnPrimaryKeyCollision(): void
    {
        $realRepo = new PdoLabRadiologyRepository($this->pdo);
        $ledgerRepo = new PdoBillingLedgerRepository($this->pdo);

        // Decorator yang menyimulasikan race condition 23000 pada panggilan saveLabOrder pertama
        $collidingRepo = new class($realRepo) implements LabRadiologyRepositoryInterface {
            public int $attempts = 0;

            public function __construct(private PdoLabRadiologyRepository $inner) {}

            public function getNextOrderNumber(string $prefix, string $dateYmd): string
            {
                return $this->attempts === 0
                    ? $this->inner->getNextOrderNumber($prefix, $dateYmd)
                    : $prefix . $dateYmd . '0002';
            }

            public function saveLabOrder(PermintaanLab $order): void
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    $e = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: permintaan_lab.noorder');
                    $e->errorInfo = ['23000', 19, 'UNIQUE constraint failed'];
                    throw $e;
                }
                $this->inner->saveLabOrder($order);
            }

            public function saveLabItem(PermintaanPemeriksaanLab $item): void { $this->inner->saveLabItem($item); }
            public function findLabOrder(string $noOrder): ?PermintaanLab { return $this->inner->findLabOrder($noOrder); }
            public function findLabItems(string $noOrder): array { return $this->inner->findLabItems($noOrder); }
            public function listLabOrdersByDate(string $fromDate, string $toDate): array { return $this->inner->listLabOrdersByDate($fromDate, $toDate); }
            public function findLabResultsByNoRawat(NoRawat $noRawat): array { return $this->inner->findLabResultsByNoRawat($noRawat); }
            public function saveLabResult(DetailPeriksaLab $detail): void { $this->inner->saveLabResult($detail); }
            public function markLabSampledAndResulted(string $noOrder, string $tgl, string $jam): void { $this->inner->markLabSampledAndResulted($noOrder, $tgl, $jam); }
            public function deleteLabOrder(string $noOrder): void { $this->inner->deleteLabOrder($noOrder); }
            public function saveRadiologiOrder(PermintaanRadiologi $order): void { $this->inner->saveRadiologiOrder($order); }
            public function saveRadiologiItem(string $noOrder, string $kdJenisPrw, string $jenisKlaim): void { $this->inner->saveRadiologiItem($noOrder, $kdJenisPrw, $jenisKlaim); }
            public function findRadiologiOrder(string $noOrder): ?PermintaanRadiologi { return $this->inner->findRadiologiOrder($noOrder); }
            public function listRadiologiOrdersByDate(string $fromDate, string $toDate): array { return $this->inner->listRadiologiOrdersByDate($fromDate, $toDate); }
            public function saveHasilRadiologi(HasilRadiologi $hasil): void { $this->inner->saveHasilRadiologi($hasil); }
            public function findRadiologiResultsByNoRawat(NoRawat $noRawat): array { return $this->inner->findRadiologiResultsByNoRawat($noRawat); }
            public function markRadiologiResulted(string $noOrder, string $tgl, string $jam): void { $this->inner->markRadiologiResulted($noOrder, $tgl, $jam); }
            public function deleteRadiologiOrder(string $noOrder): void { $this->inner->deleteRadiologiOrder($noOrder); }
            public function listLabTemplates(string $search = ''): array { return $this->inner->listLabTemplates($search); }
            public function saveLabTemplate(array $data): array { return $this->inner->saveLabTemplate($data); }
            public function listRadiologiImages(string $noRawat = ''): array { return $this->inner->listRadiologiImages($noRawat); }
            public function saveRadiologiImage(array $data): array { return $this->inner->saveRadiologiImage($data); }
        };

        $useCase = new CreateLabOrderUseCase($collidingRepo, $ledgerRepo, $this->tx);
        $orders = $useCase->execute(
            new NoRawat('2026/11/16/000001'),
            'DR001',
            [['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'GDS', 'harga' => 25000, 'claim_type' => 'Kapitasi']],
            new \DateTimeImmutable('2026-11-16 09:00:00')
        );

        $this->assertSame(2, $collidingRepo->attempts);
        $this->assertSame('PL202611160002', $orders[0]->noOrder);
        // Pastikan item ledger tidak terduplikasi akibat retry
        $this->assertCount(1, $ledgerRepo->findByNoRawat(new NoRawat('2026/11/16/000001')));
    }

    // ---- T2: Edit / simpan ulang hasil lab di detik yang sama ----

    public function testSaveLabResultAllowsEditingResultInSameSecondWithoutPkCollision(): void
    {
        $labRepo = new PdoLabRadiologyRepository($this->pdo);
        $ledgerRepo = new PdoBillingLedgerRepository($this->pdo);
        $noRawat = new NoRawat('2026/11/16/000001');

        $orders = (new CreateLabOrderUseCase($labRepo, $ledgerRepo, $this->tx))->execute(
            $noRawat,
            'DR001',
            [['kd_jenis_prw' => 'LAB01', 'nm_perawatan' => 'Hemoglobin', 'harga' => 40000, 'claim_type' => 'Kapitasi']],
            new \DateTimeImmutable('2026-11-16 10:00:00')
        );

        $saveResult = new SaveLabResultUseCase($labRepo, $ledgerRepo, $this->tx);
        $sameSecond = new \DateTimeImmutable('2026-11-16 10:05:00');

        $saveResult->execute($orders[0]->noOrder, [
            ['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '9.5', 'nilai_rujukan' => '13.0 - 17.0'],
        ], $sameSecond);

        // Koreksi hasil di detik yang sama tidak boleh melempar 500 duplicate PK
        $updated = $saveResult->execute($orders[0]->noOrder, [
            ['kd_jenis_prw' => 'LAB01', 'pemeriksaan' => 'Hb', 'nilai' => '14.2', 'nilai_rujukan' => '13.0 - 17.0'],
        ], $sameSecond);

        $this->assertSame('Normal', $updated[0]->keterangan);
        $rows = $labRepo->findLabResultsByNoRawat($noRawat);
        $this->assertCount(1, $rows);
        $this->assertSame('14.2', $rows[0]->nilai);
    }

    // ---- T6: AssignHomeCarePreceptorUseCase wajib cek kunjungan ada & blokir DPJP otomatis ----

    public function testAssignHomeCarePreceptorRejectsMissingVisitAndBlocksDpjpAutomatically(): void
    {
        $this->pdo->exec("
            INSERT INTO mlite_homecare_preceptor_sk (nip, nama_nakes, institusi, no_sk, status)
            VALUES ('DR001', 'dr. Budi', 'STIKES Harapan', 'SK/2026/002', 'Aktif');
        ");

        $hcRepo = new PdoHomeCareRepository($this->pdo);
        $useCase = new AssignHomeCarePreceptorUseCase($hcRepo);
        $noRawat = new NoRawat('2026/11/16/000001');

        // 1. Kunjungan belum dibuat -> harus ditolak, bukan diam-diam sukses UPDATE 0 baris
        try {
            $useCase->execute($noRawat, 'DR001', 'STIKES Harapan', 'MHS01', 'DOSEN01');
            $this->fail('Assign preceptor pada kunjungan yang tidak ada seharusnya ditolak.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('tidak ditemukan', $e->getMessage());
        }

        // 2. Buat kunjungan dengan DPJP = DR001, lalu coba assign DR001 sebagai preceptor -> harus diblokir otomatis
        $hcRepo->createKunjungan([
            'no_rawat' => '2026/11/16/000001',
            'tgl_kunjungan' => date('Y-m-d'),
            'jam_kunjungan' => '09:00:00',
            'kd_dokter_dpjp' => 'DR001',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('DPJP');
        $useCase->execute($noRawat, 'DR001', 'STIKES Harapan', 'MHS01', 'DOSEN01');
    }

    // ---- T7: VerifyVedikaClaimUseCase wajib transaksi ACID ----

    public function testVerifyVedikaClaimUsesTransaction(): void
    {
        $verify = new VerifyVedikaClaimUseCase(new PdoVedikaRepository($this->pdo), $this->tx);
        $res = $verify->execute(new NoRawat('2026/11/16/000001'), '0123R0011126V000001', 'verif1');
        $this->assertSame('Layak', $res['status']);
        $this->assertFalse($this->pdo->inTransaction());
    }
}
