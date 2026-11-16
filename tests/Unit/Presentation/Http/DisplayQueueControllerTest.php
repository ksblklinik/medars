<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\Entity\PoliklinikSetting;
use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Infrastructure\Persistence\PdoPoliklinikRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Infrastructure\Realtime\EventMessage;
use App\Infrastructure\Realtime\InMemoryEventBroadcaster;
use App\Presentation\Http\DisplayQueueController;
use App\Presentation\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class DisplayQueueControllerTest extends TestCase
{
    private \PDO $pdo;
    private PdoPasienRepository $pasienRepo;
    private PdoPoliklinikRepository $poliRepo;
    private PdoRegPeriksaRepository $regRepo;
    private InMemoryEventBroadcaster $broadcaster;
    private DisplayQueueController $controller;

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

        $this->pdo->exec('CREATE TABLE dokter (
            kd_dokter VARCHAR(20) PRIMARY KEY,
            nm_dokter VARCHAR(50),
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

        $this->pasienRepo = new PdoPasienRepository($this->pdo);
        $this->poliRepo = new PdoPoliklinikRepository($this->pdo);
        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);
        $this->broadcaster = new InMemoryEventBroadcaster();
        $renderer = new ViewRenderer();

        $this->controller = new DisplayQueueController(
            $this->pdo,
            $this->poliRepo,
            $this->regRepo,
            $this->broadcaster,
            $renderer
        );

        // Seed master data
        $this->poliRepo->save(new PoliklinikSetting('U01', 'Poli Umum', 25000.0, 15000.0, true));
        $this->poliRepo->save(new PoliklinikSetting('G01', 'Poli Gigi & Mulut', 35000.0, 20000.0, true));
        $this->pdo->exec("INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi Wicaksono')");

        $this->pasienRepo->save(new Pasien(
            noRkmMedis: new NoRkmMedis('000123'),
            nmPasien: 'Ahmad Dahlan',
            nik: '3201010101900001',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('1990-05-10')
        ));
    }

    public function test_display_antrean_renders_tv_view(): void
    {
        $response = $this->controller->index();
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Display Antrean Poliklinik', $response['body']);
        $this->assertStringContainsString('id="displayTvAntrean"', $response['body']);
        $this->assertStringNotContainsString('id="navbar-wrapper"', $response['body'], 'TV Display tidak boleh dibungkus layout admin');
    }

    public function test_kiosk_renders_kiosk_view(): void
    {
        $response = $this->controller->kiosk();
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Layar Kiosk Mandiri', $response['body']);
        $this->assertStringContainsString('kiosk-wrapper', $response['body']);
        $this->assertStringContainsString('General Consent', $response['body']);
        $this->assertStringNotContainsString('id="navbar-wrapper"', $response['body'], 'Layar Kiosk tidak boleh dibungkus layout admin');
    }

    public function test_api_queue_summary_returns_current_and_waiting_patients(): void
    {
        $today = new \DateTimeImmutable('today');

        // Daftarkan antrean 001 dan 002
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj, stts)
            VALUES ('2026/11/16/000001', '001', '{$today->format('Y-m-d')}', 'DR001', 'U01', '000123', 'A09', 'Awal')");
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj, stts)
            VALUES ('2026/11/16/000002', '002', '{$today->format('Y-m-d')}', 'DR001', 'U01', '000123', 'A09', 'Belum')");

        $response = $this->controller->apiQueueSummary(['date' => $today->format('Y-m-d')]);
        $this->assertSame(200, $response['status']);

        $data = json_decode($response['body'], true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Poli U01 sedang memanggil antrean 001
        $poliU01 = array_values(array_filter($data, fn($item) => $item['kd_poli'] === 'U01'))[0];
        $this->assertSame('001', $poliU01['current_queue']);
        $this->assertContains('002', $poliU01['waiting_queues']);
    }

    public function test_api_call_queue_broadcasts_sse_event(): void
    {
        $today = new \DateTimeImmutable('today');
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj, stts)
            VALUES ('2026/11/16/000001', '001', '{$today->format('Y-m-d')}', 'DR001', 'U01', '000123', 'A09', 'Belum')");

        // Tangkap event SSE dari broadcaster
        $capturedEvent = null;
        $this->broadcaster->subscribe('antrean', function (EventMessage $msg) use (&$capturedEvent) {
            $capturedEvent = $msg;
        });

        $response = $this->controller->apiCallQueue([
            'no_rawat' => '2026/11/16/000001',
            'room' => 'Ruang 1',
        ]);

        $this->assertSame(200, $response['status']);
        $result = json_decode($response['body'], true);
        $this->assertSame('success', $result['status']);
        $this->assertSame('001', $result['data']['no_reg']);

        // Verifikasi SSE Event ter-publish
        $this->assertNotNull($capturedEvent);
        $this->assertSame('queue.called', $capturedEvent->event);
        $this->assertSame('001', $capturedEvent->data['no_reg']);
        $this->assertSame('Poli Umum', $capturedEvent->data['nm_poli']);
        $this->assertStringContainsString('001', $capturedEvent->data['sound_text']);
    }
}
