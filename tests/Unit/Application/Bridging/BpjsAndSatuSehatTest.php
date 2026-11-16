<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Bridging;

use App\Application\Bridging\UseCase\ProcessBridgingQueueUseCase;
use App\Application\Bridging\UseCase\QueueSatuSehatSyncUseCase;
use App\Application\Bridging\UseCase\VerifyVedikaClaimUseCase;
use App\Domain\Bridging\Service\BpjsGatewayService;
use App\Domain\Bridging\Service\HfisLeanCalculator;
use App\Domain\Bridging\Service\HttpClientInterface;
use App\Domain\Bridging\ValueObject\BridgingCredentials;
use App\Domain\Bridging\ValueObject\TimeoutPolicy;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBridgingQueueRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class BpjsAndSatuSehatTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

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

    public function testShouldReturnErrorMockInZeroMsWhenCredentialsAreMissing(): void
    {
        $spyClient = new class implements HttpClientInterface {
            public int $calls = 0;

            public function request(string $method, string $url, array $headers, ?string $body, TimeoutPolicy $timeout): array
            {
                $this->calls++;
                return ['status' => 200, 'body' => '{"metaData":{"code":200,"message":"OK"}}'];
            }
        };

        $dummySets = [
            new BridgingCredentials('', 'secret', 'userkey', 'https://apijkn.bpjs-kesehatan.go.id'),
            new BridgingCredentials('-', 'secret', 'userkey', 'https://apijkn.bpjs-kesehatan.go.id'),
            new BridgingCredentials('12345', 'secret', 'userkey', 'https://apijkn.bpjs-kesehatan.go.id'),
            new BridgingCredentials('99887', '0', 'userkey', 'https://apijkn.bpjs-kesehatan.go.id'),
        ];

        foreach ($dummySets as $creds) {
            $gateway = new BpjsGatewayService($creds, $spyClient, new TimeoutPolicy(2, 4));
            $start = hrtime(true);
            $res = $gateway->get('/Peserta/nokartu/0001234567890/tglSEP/2026-11-16');
            $elapsedMs = (hrtime(true) - $start) / 1_000_000;

            $this->assertFalse($res['ok']);
            $this->assertSame(503, $res['metaData']['code']);
            $this->assertTrue($res['circuit_open']);
            $this->assertLessThan(10.0, $elapsedMs);
        }

        // Socket / HTTP client TIDAK PERNAH dipanggil saat kredensial dummy/kosong
        $this->assertSame(0, $spyClient->calls);
    }

    public function testShouldEnforceStrictConnectAndExecutionTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimeoutPolicy(connectTimeoutSeconds: 0, executionTimeoutSeconds: 4);
    }

    public function testShouldRejectExcessiveExecutionTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimeoutPolicy(connectTimeoutSeconds: 2, executionTimeoutSeconds: 10);
    }

    public function testShouldQueueSatuSehatFhirSyncAsynchronouslyWithoutBlockingUi(): void
    {
        $queueRepo = new PdoBridgingQueueRepository($this->pdo);
        $spyClient = new class implements HttpClientInterface {
            public int $calls = 0;

            public function request(string $method, string $url, array $headers, ?string $body, TimeoutPolicy $timeout): array
            {
                $this->calls++;
                return ['status' => 200, 'body' => json_encode(['id' => 'fhir-bundle-123'])];
            }
        };

        $queueUseCase = new QueueSatuSehatSyncUseCase($queueRepo);
        $queued = $queueUseCase->execute(
            noRawat: new NoRawat('2026/11/16/000001'),
            patientIhsId: 'P000123456',
            practitionerIhsId: 'N100001',
            icd10Code: 'J06.9',
            icd10Display: 'Acute upper respiratory infection',
        );

        // Tidak boleh memanggil HTTP client saat enqueue (non-blocking)
        $this->assertSame(0, $spyClient->calls);
        $this->assertSame('Pending', $queued['status']);
        $bundle = json_decode($queued['payload_json'], true);
        $this->assertSame('Bundle', $bundle['resourceType']);
        $this->assertSame('transaction', $bundle['type']);

        // Sekarang jalankan worker background queue dengan kredensial valid
        $creds = new BridgingCredentials('org-1', 'client-id', 'client-secret', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1');
        $worker = new ProcessBridgingQueueUseCase($queueRepo, $spyClient, $creds, new TimeoutPolicy(2, 4));
        $summary = $worker->processPending(10);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['synced']);
        $this->assertSame(1, $spyClient->calls);
    }

    public function testShouldCalculateLeanEstimatedTimeBasedOnHfisDoctorStartHour(): void
    {
        $calc = new HfisLeanCalculator();

        // Shift Pagi: FO 07:00, dokter riil 08:00, no_reg=6, durasi=5 menit -> 08:30:00
        $pagi = $calc->calculateEstimate(
            shift: 'Pagi',
            kdPoli: 'UMU01',
            nmPoli: 'Poli Umum',
            noReg: 6,
            durationMinutes: 5,
            date: '2026-11-16',
        );
        $this->assertSame('08:00:00', $pagi['doctor_start_time']);
        $this->assertSame('08:30:00', $pagi['estimated_service_time']);
        $this->assertSame('08:00:00', $pagi['suggested_arrival_time']);

        // Shift Sore Gigi -> dokter riil 15:00:00, no_reg=2, durasi=15 menit -> 15:30:00
        $soreGigi = $calc->calculateEstimate(
            shift: 'Sore',
            kdPoli: 'GIG01',
            nmPoli: 'Poli Gigi',
            noReg: 2,
            durationMinutes: 15,
            date: '2026-11-16',
        );
        $this->assertSame('15:00:00', $soreGigi['doctor_start_time']);
        $this->assertSame('15:30:00', $soreGigi['estimated_service_time']);

        // Shift Sore Non-Gigi -> dokter riil 16:00:00
        $soreUmum = $calc->calculateEstimate(
            shift: 'Sore',
            kdPoli: 'UMU01',
            nmPoli: 'Poli Umum',
            noReg: 4,
            durationMinutes: 5,
            date: '2026-11-16',
        );
        $this->assertSame('16:00:00', $soreUmum['doctor_start_time']);
        $this->assertSame('16:20:00', $soreUmum['estimated_service_time']);

        // Net Signed Avg: datang 08:15 vs estimasi 08:30 -> -15m (lebih awal)
        $signed = $calc->calculateSignedArrivalDeltaMinutes('2026-11-16 08:15:00', '2026-11-16 08:30:00');
        $this->assertSame(-15, $signed);
    }

    public function testVedikaClaimVerificationChecksCompleteness(): void
    {
        $verify = new VerifyVedikaClaimUseCase($this->pdo);
        $result = $verify->execute(
            noRawat: new NoRawat('2026/11/16/000001'),
            noSep: '0123R0011126V000001',
            verifiedBy: 'verifikator1',
        );

        $this->assertSame('Layak', $result['status']);
        $this->assertSame('0123R0011126V000001', $result['no_sep']);
    }
}
