<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Registration;

use App\Application\Registration\DTO\CheckInMobileJknCommand;
use App\Application\Registration\Exception\BookingCheckInException;
use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\Entity\PoliklinikSetting;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Infrastructure\Persistence\PdoPoliklinikRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use PHPUnit\Framework\TestCase;

final class CheckInMobileJknUseCaseTest extends TestCase
{
    private \PDO $pdo;
    private PdoPasienRepository $pasienRepo;
    private PdoPoliklinikRepository $poliRepo;
    private PdoRegPeriksaRepository $regRepo;
    private PdoBookingRegistrasiRepository $bookingRepo;
    private PdoTransactionManager $tx;
    private CheckInMobileJknUseCase $useCase;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

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
            kd_dokter_berhalangan VARCHAR(20),
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
        $this->tx = new PdoTransactionManager($this->pdo);

        $this->useCase = new CheckInMobileJknUseCase(
            $this->pasienRepo,
            $this->poliRepo,
            $this->regRepo,
            $this->bookingRepo,
            $this->tx
        );

        $this->poliRepo->save(new PoliklinikSetting('U01', 'Poli Umum', 25000.0, 15000.0, true));
        $this->pasienRepo->save(new Pasien(
            noRkmMedis: new NoRkmMedis('000123'),
            nmPasien: 'Budi Santoso',
            nik: '3201010101900001',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('1990-05-10'),
            noPeserta: '0001234567890'
        ));
    }

    public function test_should_checkin_mobile_jkn_booking_preserving_original_no_reg(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        // Pasien memiliki booking antrean '005'
        $this->pdo->exec("INSERT INTO booking_registrasi 
            (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status, shift)
            VALUES ('2026-11-10', '09:00:00', '000123', '2026-11-16', 'DR001', 'U01', '005', 'BPJ', 'Belum', '07:00:00')");

        $cmd = new CheckInMobileJknCommand(
            noRkmMedis: '000123',
            today: $today
        );

        $reg = $this->useCase->execute($cmd);

        // Nomor antrean wajib mempertahankan nomor asli booking '005'
        $this->assertSame('005', $reg->noReg->value());
        $this->assertSame('2026/11/16/000001', $reg->noRawat->value());
        $this->assertSame('BPJ', $reg->kdPj);

        // Status di booking_registrasi berubah jadi 'Terdaftar'
        $bookingStatus = $this->pdo->query("SELECT status FROM booking_registrasi WHERE no_rkm_medis = '000123'")->fetchColumn();
        $this->assertSame('Terdaftar', $bookingStatus);
    }

    public function test_should_reject_checkin_if_booking_date_is_not_today_or_already_registered(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        // Booking untuk besok
        $this->pdo->exec("INSERT INTO booking_registrasi 
            (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status)
            VALUES ('2026-11-10', '09:00:00', '000123', '2026-11-17', 'DR001', 'U01', '001', 'BPJ', 'Belum')");

        $cmd = new CheckInMobileJknCommand('000123', $today);

        $this->expectException(BookingCheckInException::class);
        $this->useCase->execute($cmd);
    }

    public function test_should_handle_replacement_doctor_during_checkin(): void
    {
        $today = new \DateTimeImmutable('2026-11-16');

        // Dokter asli DR001 berhalangan dan digantikan DR009
        $this->pdo->exec("INSERT INTO booking_registrasi 
            (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_dokter_pengganti, kd_poli, no_reg, kd_pj, status)
            VALUES ('2026-11-10', '09:00:00', '000123', '2026-11-16', 'DR001', 'DR009', 'U01', '003', 'BPJ', 'Belum')");

        $cmd = new CheckInMobileJknCommand('000123', $today);
        $reg = $this->useCase->execute($cmd);

        $this->assertSame('DR009', $reg->kdDokter);
    }
}
