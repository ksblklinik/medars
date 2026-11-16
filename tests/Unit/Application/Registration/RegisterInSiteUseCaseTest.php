<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Registration;

use App\Application\Registration\DTO\RegisterInSiteCommand;
use App\Application\Registration\Exception\DuplicateRegistrationException;
use App\Application\Registration\Exception\PenjaminDisallowedException;
use App\Application\Registration\Exception\UnpaidArrearsException;
use App\Application\Registration\UseCase\RegisterInSiteUseCase;
use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\Entity\PoliklinikSetting;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Infrastructure\Persistence\PdoPoliklinikRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use PHPUnit\Framework\TestCase;

final class RegisterInSiteUseCaseTest extends TestCase
{
    private \PDO $pdo;
    private PdoPasienRepository $pasienRepo;
    private PdoPoliklinikRepository $poliRepo;
    private PdoRegPeriksaRepository $regRepo;
    private PdoBookingRegistrasiRepository $bookingRepo;
    private RegisterInSiteUseCase $useCase;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Setup tables
        $this->pdo->exec('CREATE TABLE pasien (
            no_rkm_medis VARCHAR(15) PRIMARY KEY,
            nm_pasien VARCHAR(50),
            no_ktp VARCHAR(20),
            jk VARCHAR(2),
            tgl_lahir DATE,
            alamat VARCHAR(300),
            no_tlp VARCHAR(40),
            no_peserta VARCHAR(25),
            keluarga VARCHAR(20),
            namakeluarga VARCHAR(50),
            alamatpj VARCHAR(100)
        )');

        $this->pdo->exec('CREATE TABLE poliklinik (
            kd_poli CHAR(5) PRIMARY KEY,
            nm_poli VARCHAR(50),
            registrasi DOUBLE,
            registrasilama DOUBLE,
            status VARCHAR(2),
            allow_bpjs TINYINT DEFAULT 1
        )');

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

        $this->pdo->exec('CREATE TABLE booking_registrasi (
            tanggal_booking DATE,
            jam_booking TIME,
            no_rkm_medis VARCHAR(15),
            tanggal_periksa DATE,
            kd_dokter VARCHAR(20),
            kd_poli CHAR(5),
            no_reg VARCHAR(8),
            kd_pj CHAR(3),
            limit_reg INT DEFAULT 0,
            waktu_kunjungan DATETIME,
            status VARCHAR(20) DEFAULT "Belum",
            kd_dokter_pengganti VARCHAR(20),
            shift VARCHAR(20),
            prefik VARCHAR(10),
            room VARCHAR(50)
        )');

        $this->pasienRepo = new PdoPasienRepository($this->pdo);
        $this->poliRepo = new PdoPoliklinikRepository($this->pdo);
        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);
        $this->bookingRepo = new PdoBookingRegistrasiRepository($this->pdo);

        $this->useCase = new RegisterInSiteUseCase(
            $this->pasienRepo,
            $this->poliRepo,
            $this->regRepo,
            $this->bookingRepo
        );

        // Seed master poliklinik
        $this->poliRepo->save(new PoliklinikSetting('U01', 'Poli Umum', 25000.0, 15000.0, true));
        $this->poliRepo->save(new PoliklinikSetting('PEX', 'Poli Eksekutif', 150000.0, 100000.0, false));

        // Seed pasien
        $this->pasienRepo->save(new Pasien(
            noRkmMedis: new NoRkmMedis('000123'),
            nmPasien: 'Budi Santoso',
            nik: '3201010101900001',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('1990-05-10')
        ));
    }

    public function test_should_register_walkin_patient_and_calculate_correct_fee_and_age(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');
        $cmd = new RegisterInSiteCommand(
            noRkmMedis: '000123',
            kdPoli: 'U01',
            kdDokter: 'DR001',
            kdPj: 'A09',
            tglRegistrasi: $today,
            jamReg: '08:30:00',
            shift: '07:00:00'
        );

        $result = $this->useCase->execute($cmd);

        $this->assertSame('2026/11/16/000001', $result->noRawat->value());
        $this->assertSame('001', $result->noReg->value());
        $this->assertSame('Baru', $result->sttsDaftar);
        $this->assertSame('Baru', $result->statusPoli);
        $this->assertSame(25000.0, $result->biayaReg);
        $this->assertSame(36, $result->umurDaftar);
        $this->assertSame('Th', $result->sttsUmur);
    }

    public function test_should_reject_duplicate_active_registration_for_same_patient_poli_doctor_shift(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');
        $cmd = new RegisterInSiteCommand(
            noRkmMedis: '000123',
            kdPoli: 'U01',
            kdDokter: 'DR001',
            kdPj: 'A09',
            tglRegistrasi: $today
        );

        $this->useCase->execute($cmd);

        $this->expectException(DuplicateRegistrationException::class);
        $this->useCase->execute($cmd);
    }

    public function test_should_reject_penjamin_when_poliklinik_setting_disallows_it(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');
        $cmd = new RegisterInSiteCommand(
            noRkmMedis: '000123',
            kdPoli: 'PEX', // Poli Eksekutif (allowBpjs = false)
            kdDokter: 'DR002',
            kdPj: 'BPJ',   // Penjamin BPJS ditolak
            tglRegistrasi: $today
        );

        $this->expectException(PenjaminDisallowedException::class);
        $this->useCase->execute($cmd);
    }

    public function test_should_reject_registration_when_unpaid_arrears_exist(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        // Pasien memiliki kunjungan kemarin yang belum dibayar
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj, status_bayar, stts)
            VALUES ('2026/11/15/000001', '001', '2026-11-15', 'DR001', 'U01', '000123', 'A09', 'Belum Bayar', 'Sudah')");

        $cmd = new RegisterInSiteCommand(
            noRkmMedis: '000123',
            kdPoli: 'U01',
            kdDokter: 'DR001',
            kdPj: 'A09',
            tglRegistrasi: $today
        );

        $this->expectException(UnpaidArrearsException::class);
        $this->useCase->execute($cmd);
    }

    public function test_should_route_future_date_to_booking_registrasi(): void
    {
        $tomorrow = new \DateTimeImmutable('2026-11-17');
        $cmd = new RegisterInSiteCommand(
            noRkmMedis: '000123',
            kdPoli: 'U01',
            kdDokter: 'DR001',
            kdPj: 'A09',
            tglRegistrasi: $tomorrow
        );

        $result = $this->useCase->execute($cmd);

        // Karena untuk besok, reg_periksa tidak diisi, tapi booking_registrasi diisi
        $countReg = (int) $this->pdo->query("SELECT COUNT(*) FROM reg_periksa")->fetchColumn();
        $this->assertSame(0, $countReg);

        $countBooking = (int) $this->pdo->query("SELECT COUNT(*) FROM booking_registrasi WHERE tanggal_periksa = '2026-11-17'")->fetchColumn();
        $this->assertSame(1, $countBooking);
    }
}
