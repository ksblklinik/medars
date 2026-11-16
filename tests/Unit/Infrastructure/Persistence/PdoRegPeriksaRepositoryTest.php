<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use PHPUnit\Framework\TestCase;

final class PdoRegPeriksaRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoRegPeriksaRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Schema reg_periksa
        $this->pdo->exec('CREATE TABLE reg_periksa (
            no_reg VARCHAR(8),
            prefik VARCHAR(10) DEFAULT "",
            room VARCHAR(50) DEFAULT "",
            no_rawat VARCHAR(17) PRIMARY KEY,
            tgl_registrasi DATE,
            jam_reg TIME,
            shift VARCHAR(20),
            kd_dokter VARCHAR(20),
            no_rkm_medis VARCHAR(15),
            kd_poli CHAR(5),
            p_jawab VARCHAR(100),
            almt_pj VARCHAR(200),
            hubunganpj VARCHAR(20),
            biaya_reg DOUBLE,
            stts VARCHAR(20) DEFAULT "Belum",
            stts_daftar VARCHAR(20) DEFAULT "Baru",
            status_lanjut VARCHAR(20) DEFAULT "Ralan",
            kd_pj CHAR(3),
            umurdaftar INT DEFAULT 0,
            sttsumur VARCHAR(5) DEFAULT "Th",
            status_bayar VARCHAR(20) DEFAULT "Belum Bayar",
            status_poli VARCHAR(20) DEFAULT "Baru"
        )');

        // Unique index untuk mencegah nomor antrean ganda pada dokter/poli/shift yang sama
        $this->pdo->exec('CREATE UNIQUE INDEX uq_reg_antrean ON reg_periksa (tgl_registrasi, kd_dokter, no_reg)');

        // Schema booking_registrasi
        $this->pdo->exec('CREATE TABLE booking_registrasi (
            no_rkm_medis VARCHAR(15),
            tanggal_periksa DATE,
            kd_dokter VARCHAR(20),
            kd_poli CHAR(5),
            no_reg VARCHAR(8),
            status VARCHAR(20) DEFAULT "Belum",
            shift VARCHAR(20) DEFAULT ""
        )');

        // Schema clinical tables for clinical records check
        $this->pdo->exec('CREATE TABLE pemeriksaan_ralan (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE rawat_jl_dr (no_rawat VARCHAR(17), kd_jenis_prw VARCHAR(15))');
        $this->pdo->exec('CREATE TABLE resep_obat (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE periksa_lab (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE periksa_radiologi (no_rawat VARCHAR(17) PRIMARY KEY)');

        $this->repository = new PdoRegPeriksaRepository($this->pdo);
    }

    public function test_should_not_assign_insite_no_reg_that_collides_with_mobile_jkn_booking(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        // Di reg_periksa baru ada pasien antrean 001
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj)
            VALUES ('2026/11/16/000001', '001', '2026-11-16', 'DR001', 'U01', '000001', 'A09')");

        // Di booking_registrasi (Mobile JKN) sudah ada pasien yang booking antrean 005
        $this->pdo->exec("INSERT INTO booking_registrasi (no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, status)
            VALUES ('000002', '2026-11-16', 'DR001', 'U01', '005', 'Belum')");

        // Pasien Walk-in baru datang: nomor antrean harus MAX(1, 5) + 1 = 6 -> '006' (tidak mencuri slot 005!)
        $nextNoReg = $this->repository->getNextNoReg('U01', 'DR001', $today);
        $this->assertSame('006', $nextNoReg->value());
    }

    public function test_should_auto_regenerate_no_rawat_and_no_reg_on_concurrent_insert(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        // Sudah ada no_rawat 000001 dan no_reg 001
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj)
            VALUES ('2026/11/16/000001', '001', '2026-11-16', 'DR001', 'U01', '000001', 'A09')");

        // Objek pasien kedua yang disiapkan saat awal bentrok dengan no_rawat 000001 atau no_reg 001
        $staleReg = new RegPeriksa(
            noRawat: new NoRawat('2026/11/16/000001'), // Sengaja tabrakan dengan 000001
            noReg: new NoReg('001'),                  // Sengaja tabrakan dengan 001
            noRkmMedis: new NoRkmMedis('000002'),
            kdPoli: 'U01',
            kdDokter: 'DR001',
            kdPj: 'A09',
            tglRegistrasi: $today,
            jamReg: '08:30:00',
            biayaReg: 25000.0
        );

        // saveWithAtomicRetry harus mendeteksi tabrakan, meregenerasi no_rawat jadi 000002 dan no_reg jadi 002
        $saved = $this->repository->saveWithAtomicRetry($staleReg);

        $this->assertSame('2026/11/16/000002', $saved->noRawat->value());
        $this->assertSame('002', $saved->noReg->value());

        // Verifikasi benar tersimpan di database
        $found = $this->repository->findByNoRawat($saved->noRawat);
        $this->assertNotNull($found);
        $this->assertSame('000002', $found->noRkmMedis->value());
    }

    public function test_has_active_registration_today_blocks_duplicate_submission(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj, stts)
            VALUES ('2026/11/16/000001', '001', '2026-11-16', 'DR001', 'U01', '000123', 'A09', 'Belum')");

        $hasActive = $this->repository->hasActiveRegistrationToday(
            new NoRkmMedis('000123'),
            'U01',
            'DR001',
            $today
        );

        $this->assertTrue($hasActive);

        // Jika statusnya Batal, tidak dianggap aktif
        $this->pdo->exec("UPDATE reg_periksa SET stts = 'Batal' WHERE no_rawat = '2026/11/16/000001'");

        $hasActiveCancelled = $this->repository->hasActiveRegistrationToday(
            new NoRkmMedis('000123'),
            'U01',
            'DR001',
            $today
        );
        $this->assertFalse($hasActiveCancelled);
    }

    public function test_has_unpaid_previous_visit_detects_arrears(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        // Kunjungan kemarin belum bayar
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj, stts, status_bayar)
            VALUES ('2026/11/15/000001', '001', '2026-11-15', 'DR001', 'U01', '000123', 'A09', 'Sudah', 'Belum Bayar')");

        $hasArrears = $this->repository->hasUnpaidPreviousVisit(new NoRkmMedis('000123'), $today);
        $this->assertTrue($hasArrears);

        // Jika sudah bayar
        $this->pdo->exec("UPDATE reg_periksa SET status_bayar = 'Sudah Bayar' WHERE no_rawat = '2026/11/15/000001'");
        $this->assertFalse($this->repository->hasUnpaidPreviousVisit(new NoRkmMedis('000123'), $today));
    }

    public function test_has_clinical_records_detects_soap_and_prescriptions(): void
    {
        $noRawat = new NoRawat('2026/11/16/000001');

        $this->assertFalse($this->repository->hasClinicalRecords($noRawat));

        // Tambah catatan SOAP dokter
        $this->pdo->exec("INSERT INTO pemeriksaan_ralan (no_rawat) VALUES ('2026/11/16/000001')");
        $this->assertTrue($this->repository->hasClinicalRecords($noRawat));
    }
}
