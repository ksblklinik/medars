<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Registration;

use App\Application\Registration\DTO\ReassignDoctorCommand;
use App\Application\Registration\DTO\ShiftQueueCommand;
use App\Application\Registration\Exception\ClinicalGuardBlockedException;
use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use PHPUnit\Framework\TestCase;

final class ClinicalGuardShieldUseCaseTest extends TestCase
{
    private \PDO $pdo;
    private PdoRegPeriksaRepository $regRepo;
    private ClinicalGuardShieldUseCase $useCase;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

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

        $this->pdo->exec('CREATE TABLE pemeriksaan_ralan (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE rawat_jl_dr (no_rawat VARCHAR(17), kd_jenis_prw VARCHAR(15), kd_dokter VARCHAR(20))');
        $this->pdo->exec('CREATE TABLE resep_obat (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE periksa_lab (no_rawat VARCHAR(17) PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE periksa_radiologi (no_rawat VARCHAR(17) PRIMARY KEY)');

        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);
        $this->useCase = new ClinicalGuardShieldUseCase($this->regRepo);

        // Seed kunjungan aktif
        $this->pdo->exec("INSERT INTO reg_periksa (no_rawat, no_reg, tgl_registrasi, kd_dokter, kd_poli, no_rkm_medis, kd_pj, stts)
            VALUES ('2026/11/16/000001', '001', '2026-11-16', 'DR001', 'U01', '000123', 'A09', 'Belum')");
    }

    public function test_should_block_changing_no_reg_or_doctor_if_clinical_records_exist(): void
    {
        // Pasien sudah diperiksa dokter (tercatat SOAP di pemeriksaan_ralan)
        $this->pdo->exec("INSERT INTO pemeriksaan_ralan (no_rawat) VALUES ('2026/11/16/000001')");

        $cmd = new ShiftQueueCommand('2026/11/16/000001', '010');

        $this->expectException(ClinicalGuardBlockedException::class);
        $this->useCase->shiftQueue($cmd);
    }

    public function test_should_allow_manual_queue_shift_only_for_unperformed_patient(): void
    {
        // Pasien belum dilayani dan belum memiliki tindakan
        $cmd = new ShiftQueueCommand('2026/11/16/000001', '008', '08:00:00');
        $this->useCase->shiftQueue($cmd);

        $newNoReg = $this->pdo->query("SELECT no_reg FROM reg_periksa WHERE no_rawat = '2026/11/16/000001'")->fetchColumn();
        $this->assertSame('008', $newNoReg);
    }

    public function test_should_reassign_consultation_action_to_replacement_doctor(): void
    {
        // Pasien belum ada SOAP klinis, tapi sudah tercatat tarif karcis konsultasi awal RJ002 untuk DR001
        $this->pdo->exec("INSERT INTO rawat_jl_dr (no_rawat, kd_jenis_prw, kd_dokter) 
            VALUES ('2026/11/16/000001', 'RJ002', 'DR001')");

        $cmd = new ReassignDoctorCommand('2026/11/16/000001', 'DR002');
        $this->useCase->reassignDoctor($cmd);

        // Dokter di reg_periksa berubah
        $dokterReg = $this->pdo->query("SELECT kd_dokter FROM reg_periksa WHERE no_rawat = '2026/11/16/000001'")->fetchColumn();
        $this->assertSame('DR002', $dokterReg);

        // Tindakan awal konsultasi dialihkan ke DR002
        $dokterTindakan = $this->pdo->query("SELECT kd_dokter FROM rawat_jl_dr WHERE no_rawat = '2026/11/16/000001' AND kd_jenis_prw = 'RJ002'")->fetchColumn();
        $this->assertSame('DR002', $dokterTindakan);
    }

    public function test_should_block_reassigning_doctor_if_clinical_records_exist(): void
    {
        $this->pdo->exec("INSERT INTO pemeriksaan_ralan (no_rawat) VALUES ('2026/11/16/000001')");

        $cmd = new ReassignDoctorCommand('2026/11/16/000001', 'DR002');

        $this->expectException(ClinicalGuardBlockedException::class);
        $this->useCase->reassignDoctor($cmd);
    }
}
