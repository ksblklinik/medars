<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Application\Registration\UseCase\RegisterInSiteUseCase;
use App\Domain\Registration\Entity\BookingRegistrasi;
use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\Entity\PoliklinikSetting;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Infrastructure\Persistence\PdoPoliklinikRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Presentation\Http\RegistrationController;
use App\Presentation\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class RegistrationControllerTest extends TestCase
{
    private \PDO $pdo;
    private PdoPasienRepository $pasienRepo;
    private PdoPoliklinikRepository $poliRepo;
    private PdoRegPeriksaRepository $regRepo;
    private PdoBookingRegistrasiRepository $bookingRepo;
    private RegistrationController $controller;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Schema setup
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

        $this->pdo->exec('CREATE TABLE dokter (
            kd_dokter VARCHAR(20) PRIMARY KEY,
            nm_dokter VARCHAR(50),
            status VARCHAR(2) DEFAULT "1"
        )');

        $this->pdo->exec('CREATE TABLE penjab (
            kd_pj CHAR(3) PRIMARY KEY,
            png_jawab VARCHAR(30),
            status VARCHAR(2) DEFAULT "1"
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

        $this->pdo->exec('CREATE TABLE pemeriksaan_ralan (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE rawat_jl_dr (no_rawat VARCHAR(17), kd_jenis_prw VARCHAR(15), kd_dokter VARCHAR(20))');
        $this->pdo->exec('CREATE TABLE resep_obat (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE periksa_lab (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE periksa_radiologi (no_rawat VARCHAR(17) PRIMARY KEY)');

        $this->pasienRepo = new PdoPasienRepository($this->pdo);
        $this->poliRepo = new PdoPoliklinikRepository($this->pdo);
        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);
        $this->bookingRepo = new PdoBookingRegistrasiRepository($this->pdo);
        $tx = new PdoTransactionManager($this->pdo);

        $registerInSiteUseCase = new RegisterInSiteUseCase(
            $this->pasienRepo,
            $this->poliRepo,
            $this->regRepo,
            $this->bookingRepo
        );

        $checkInMobileJknUseCase = new CheckInMobileJknUseCase(
            $this->pasienRepo,
            $this->poliRepo,
            $this->regRepo,
            $this->bookingRepo,
            $tx
        );

        $clinicalGuardUseCase = new ClinicalGuardShieldUseCase($this->regRepo);

        $renderer = new ViewRenderer();

        $this->controller = new RegistrationController(
            $this->pdo,
            $this->pasienRepo,
            $this->poliRepo,
            $this->regRepo,
            $registerInSiteUseCase,
            $checkInMobileJknUseCase,
            $clinicalGuardUseCase,
            $renderer
        );

        // Seed master data
        $this->poliRepo->save(new PoliklinikSetting('U01', 'Poli Umum', 25000.0, 15000.0, true));
        $this->poliRepo->save(new PoliklinikSetting('PEX', 'Poli Eksekutif', 150000.0, 100000.0, false));

        $this->pdo->exec("INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Anton Wijaya, Sp.PD')");
        $this->pdo->exec("INSERT INTO penjab (kd_pj, png_jawab) VALUES ('A09', 'Umum / Mandiri'), ('BPJ', 'BPJS Kesehatan')");

        $this->pasienRepo->save(new Pasien(
            noRkmMedis: new NoRkmMedis('000123'),
            nmPasien: 'Budi Santoso',
            nik: '3201010101900001',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('1990-05-10'),
            alamat: 'Jl. Melati No. 5',
            noTelp: '08123456789'
        ));
    }

    public function test_index_renders_registration_view(): void
    {
        $response = $this->controller->index();
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Pendaftaran Rawat Jalan', $response['body']);
        $this->assertStringContainsString('id="formRegistration"', $response['body']);
        $this->assertStringContainsString('id="tableTodayRegistrations"', $response['body']);
        $this->assertStringContainsString('F4', $response['body']);
    }

    public function test_api_search_patients_returns_json_results(): void
    {
        $response = $this->controller->apiSearchPatients(['q' => 'Budi']);
        $this->assertSame(200, $response['status']);

        $data = json_decode($response['body'], true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('000123', $data[0]['no_rkm_medis']);
        $this->assertSame('Budi Santoso', $data[0]['nm_pasien']);
    }

    public function test_api_register_insite_success_returns_created_record(): void
    {
        $payload = [
            'no_rkm_medis' => '000123',
            'kd_poli' => 'U01',
            'kd_dokter' => 'DR001',
            'kd_pj' => 'A09',
            'tgl_registrasi' => date('Y-m-d'),
            'jam_reg' => '08:45:00',
        ];

        $response = $this->controller->apiRegisterInSite($payload);
        $this->assertSame(200, $response['status']);

        $result = json_decode($response['body'], true);
        $this->assertSame('success', $result['status']);
        $this->assertSame('001', $result['data']['no_reg']);
        $this->assertStringStartsWith(date('Y/m/d/'), $result['data']['no_rawat']);
    }

    public function test_api_register_insite_disallowed_penjamin_returns_422(): void
    {
        $payload = [
            'no_rkm_medis' => '000123',
            'kd_poli' => 'PEX', // Poli Eksekutif (allowBpjs = false)
            'kd_dokter' => 'DR001',
            'kd_pj' => 'BPJ',   // Ditolak
        ];

        $response = $this->controller->apiRegisterInSite($payload);
        $this->assertSame(422, $response['status']);

        $result = json_decode($response['body'], true);
        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('tidak diperbolehkan', $result['message']);
    }

    public function test_api_checkin_mobile_jkn_success(): void
    {
        $today = new \DateTimeImmutable('today');

        // Pasien sudah booking antrean 007
        $this->bookingRepo->save(new BookingRegistrasi(
            noRkmMedis: new NoRkmMedis('000123'),
            tanggalPeriksa: $today,
            kdPoli: 'U01',
            kdDokter: 'DR001',
            noReg: new NoReg('007'),
            kdPj: 'BPJ',
            status: 'Belum'
        ));

        $payload = ['no_rkm_medis' => '000123'];
        $response = $this->controller->apiCheckInMobileJkn($payload);

        $this->assertSame(200, $response['status']);
        $result = json_decode($response['body'], true);
        $this->assertSame('success', $result['status']);
        $this->assertSame('007', $result['data']['no_reg']);
    }

    public function test_api_list_today_returns_active_registrations(): void
    {
        // Daftarkan 1 pasien
        $this->controller->apiRegisterInSite([
            'no_rkm_medis' => '000123',
            'kd_poli' => 'U01',
            'kd_dokter' => 'DR001',
            'kd_pj' => 'A09',
        ]);

        $response = $this->controller->apiListToday(['date' => date('Y-m-d')]);
        $this->assertSame(200, $response['status']);

        $result = json_decode($response['body'], true);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('000123', $result[0]['no_rkm_medis']);
        $this->assertSame('Budi Santoso', $result[0]['nm_pasien']);
        $this->assertSame('Poli Umum', $result[0]['nm_poli']);
    }

    public function test_api_shift_queue_blocked_by_clinical_records(): void
    {
        $res = $this->controller->apiRegisterInSite([
            'no_rkm_medis' => '000123',
            'kd_poli' => 'U01',
            'kd_dokter' => 'DR001',
            'kd_pj' => 'A09',
        ]);
        $resData = json_decode($res['body'], true);
        $noRawat = $resData['data']['no_rawat'];

        // Tambah rekam SOAP dokter
        $this->pdo->exec("INSERT INTO pemeriksaan_ralan (no_rawat) VALUES ('{$noRawat}')");

        // Coba geser antrean
        $shiftRes = $this->controller->apiShiftQueue([
            'no_rawat' => $noRawat,
            'new_no_reg' => '010',
        ]);

        $this->assertSame(422, $shiftRes['status']);
        $shiftData = json_decode($shiftRes['body'], true);
        $this->assertSame('error', $shiftData['status']);
        $this->assertStringContainsString('Clinical Guard', $shiftData['message']);
    }
}
