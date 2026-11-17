<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Rme;

use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Application\Registration\UseCase\UpdateRegistrationStatusUseCase;
use App\Application\Rme\UseCase\DeleteTindakanDokterUseCase;
use App\Application\Rme\UseCase\SaveResumeMedisUseCase;
use App\Application\Rme\UseCase\SaveTindakanDokterUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoDokterTindakanRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PoliDeepeningTest extends TestCase
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
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum'), ('GIG01', 'Poli Gigi');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi'), ('DR002', 'drg. Ani');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('UMU', 'Umum');
            INSERT INTO jns_perawatan (kd_jenis_prw, nm_perawatan, kd_kategori, material, tarif_tindakandr, total_byrdr, status)
            VALUES ('TND01', 'Konsultasi & Pemeriksaan Dokter', 'KONSUL', 10000, 40000, 50000, '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'UMU', 'Belum Bayar');
        ");
    }

    public function testSaveAndDeleteTindakanDokterUpdatesBillingLedgerAtomically(): void
    {
        $tindakanRepo = new PdoDokterTindakanRepository($this->pdo);
        $ledgerRepo = new PdoBillingLedgerRepository($this->pdo);

        $save = new SaveTindakanDokterUseCase($tindakanRepo, $ledgerRepo, $this->tx);
        $delete = new DeleteTindakanDokterUseCase($tindakanRepo, $ledgerRepo, $this->tx);
        $noRawat = new NoRawat('2026/11/17/000001');

        $saved = $save->execute(
            noRawat: $noRawat,
            kdJenisPrw: 'TND01',
            kdDokter: 'DR001',
            claimType: 'Tunai',
            at: new \DateTimeImmutable('2026-11-17 09:00:00'),
        );

        $this->assertSame(50000.0, (float) $saved['biaya_rawat']);
        $this->assertCount(1, $tindakanRepo->listByNoRawat($noRawat));
        $this->assertCount(1, $ledgerRepo->findByNoRawat($noRawat));

        $delete->execute($noRawat, 'TND01', 'DR001', '2026-11-17', '09:00:00');
        $this->assertCount(0, $tindakanRepo->listByNoRawat($noRawat));
        $this->assertSame('Cancelled', $ledgerRepo->findByNoRawat($noRawat)[0]->status->value());
    }

    public function testSaveAndFetchResumeMedis(): void
    {
        $tindakanRepo = new PdoDokterTindakanRepository($this->pdo);
        $saveResume = new SaveResumeMedisUseCase($tindakanRepo, $this->tx);
        $noRawat = new NoRawat('2026/11/17/000001');

        $saveResume->execute([
            'no_rawat' => '2026/11/17/000001',
            'kd_dokter' => 'DR001',
            'keluhan_utama' => 'Demam 3 hari',
            'diagnosa_utama' => 'ISPA Akut',
            'kd_diagnosa_utama' => 'J06.9',
            'kondisi_pulang' => 'Membaik',
            'obat_pulang' => 'Paracetamol 3x1',
        ]);

        $resume = $tindakanRepo->findResume($noRawat);
        $this->assertNotNull($resume);
        $this->assertSame('J06.9', $resume['kd_diagnosa_utama']);
    }

    public function testUpdateRegistrationStatusRespectsClinicalGuardOnPoliChange(): void
    {
        $regRepo = new PdoRegPeriksaRepository($this->pdo);
        $guard = new ClinicalGuardShieldUseCase($regRepo);
        $updateStatus = new UpdateRegistrationStatusUseCase($regRepo, $guard, $this->tx);
        $noRawat = new NoRawat('2026/11/17/000001');

        // Ubah status ke Sudah diperbolehkan
        $updateStatus->execute($noRawat, 'Sudah', null, null);
        $row = $regRepo->findByNoRawat($noRawat);
        $this->assertSame('Sudah', $row->stts);

        // Sisipkan SOAP -> ubah poli harus diblokir ClinicalGuardShield!
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi, tinggi, berat, spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi, lingkar_perut, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/17/000001', '{$today}', '08:15:00', '36.5', '120/80', '80', '20', '170', '65', '98', '15', 'Compos Mentis', 'Batuk', 'Normal', '-', '-', '-', '-', '-', '-', 'DR001')
        ");

        $this->expectException(\DomainException::class);
        $updateStatus->execute($noRawat, 'Belum', 'GIG01', 'DR002');
    }
}
