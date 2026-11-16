<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Rme;

use App\Application\Rme\DTO\SavePrbCommand;
use App\Application\Rme\DTO\SaveSoapCommand;
use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Application\Rme\UseCase\DeleteOdontogramUseCase;
use App\Application\Rme\UseCase\RanapStatusCheckerInterface;
use App\Application\Rme\UseCase\SaveCpptRanapUseCase;
use App\Application\Rme\UseCase\SaveOdontogramUseCase;
use App\Application\Rme\UseCase\SavePrbPrescriptionUseCase;
use App\Application\Rme\UseCase\SaveSoapUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Entity\Odontogram;
use App\Domain\Rme\Entity\SuratSakit;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoOdontogramRepository;
use App\Infrastructure\Persistence\PdoPemeriksaanRalanRepository;
use App\Infrastructure\Persistence\PdoPemeriksaanRanapRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Infrastructure\Persistence\PdoSuratMedisRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class RmeUseCasesTest extends TestCase
{
    private PDO $pdo;
    private PdoTransactionManager $tx;
    private PdoPemeriksaanRalanRepository $ralanRepo;
    private PdoPemeriksaanRanapRepository $ranapRepo;
    private PdoRegPeriksaRepository $regRepo;
    private PdoOdontogramRepository $odoRepo;
    private PdoSuratMedisRepository $suratRepo;
    private UserAccount $dokterUmum;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->tx = new PdoTransactionManager($this->pdo);
        $this->ralanRepo = new PdoPemeriksaanRalanRepository($this->pdo);
        $this->ranapRepo = new PdoPemeriksaanRanapRepository($this->pdo);
        $this->regRepo = new PdoRegPeriksaRepository($this->pdo);
        $this->odoRepo = new PdoOdontogramRepository($this->pdo);
        $this->suratRepo = new PdoSuratMedisRepository($this->pdo);

        $this->dokterUmum = new UserAccount(
            id: 10,
            username: 'DR001',
            passwordHash: 'hash',
            email: 'dr001@medars.local',
            fullname: 'dr. Jaga Umum',
            role: 'dokter',
            access: ['dokter_ralan'],
            cap: ['UMU01'],
            status: '1'
        );

        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000001', 'Pasien A', '1990-01-01', 'L');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum'), ('GIG01', 'Poli Gigi');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Jaga Umum'), ('DR002', 'drg. Gigi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('UMU', 'Umum');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut)
            VALUES ('001', '2026/11/16/000001', '{$today}', '08:00:00', 'DR001', '000001', 'UMU01', 'UMU', 'Belum', 'Ralan');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut)
            VALUES ('002', '2026/11/16/000002', '{$today}', '08:10:00', 'DR002', '000001', 'GIG01', 'UMU', 'Belum', 'Ralan');
        ");
    }

    public function testDtoWhitelistingRejectsUnwhitelistedFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field tidak diizinkan pada payload SOAP: hacked_field');

        SaveSoapCommand::fromWhitelistedPayload([
            'no_rawat' => '2026/11/16/000001',
            'keluhan' => 'Demam',
            'hacked_field' => 'malicious',
        ]);
    }

    public function testSaveSoapBindsNipToSessionAndEnforcesSmartPeekAndTransitionsStatus(): void
    {
        $useCase = new SaveSoapUseCase($this->ralanRepo, $this->regRepo, $this->tx);

        // Walaupun payload mencoba spoofing nip, sistem harus mengikat ke $this->dokterUmum->getUsername() ('DR001')
        $cmd = SaveSoapCommand::fromWhitelistedPayload([
            'no_rawat' => '2026/11/16/000001',
            'tgl_perawatan' => date('Y-m-d'),
            'jam_rawat' => '09:00:00',
            'keluhan' => 'Batuk pilek 3 hari',
            'pemeriksaan' => 'Faring hiperemis',
            'penilaian' => 'ISPA',
            'rtl' => 'Paracetamol 3x500mg',
            'meta_key' => 'meta_kebidanan',
            'meta_value' => '{"gravida":1}',
            'is_meta_dirty' => false, // Smart Peek! Tidak boleh simpan metadata
        ]);

        $useCase->execute($cmd, $this->dokterUmum);

        $saved = $this->ralanRepo->findLatestByNoRawat(new NoRawat('2026/11/16/000001'));
        $this->assertNotNull($saved);
        $this->assertSame('DR001', $saved->nip);

        // Pastikan metadata TIDAK tersimpan karena is_meta_dirty === false
        $metaCount = (int) $this->pdo->query("SELECT COUNT(*) FROM mlite_pemeriksaan_ralan_metadata")->fetchColumn();
        $this->assertSame(0, $metaCount);

        // Pastikan status reg_periksa berubah menjadi 'Sudah'
        $stts = $this->pdo->query("SELECT stts FROM reg_periksa WHERE no_rawat = '2026/11/16/000001'")->fetchColumn();
        $this->assertSame('Sudah', $stts);

        // Multi-visite: Simpan SOAP ke-2 pada hari yang sama saat status sudah 'Sudah' HARUS DIIZINKAN
        $cmd2 = SaveSoapCommand::fromWhitelistedPayload([
            'no_rawat' => '2026/11/16/000001',
            'tgl_perawatan' => date('Y-m-d'),
            'jam_rawat' => '10:30:00',
            'keluhan' => 'Observasi ulang demam turun',
            'pemeriksaan' => 'Suhu 36.8C',
        ]);
        $useCase->execute($cmd2, $this->dokterUmum);
        $this->assertCount(2, $this->ralanRepo->findByNoRawat(new NoRawat('2026/11/16/000001')));
    }

    public function testSaveSoapBlocksCrossPoliIdor(): void
    {
        $useCase = new SaveSoapUseCase($this->ralanRepo, $this->regRepo, $this->tx);

        // Dokter DR001 (hanya cap UMU01) mencoba mengisi SOAP pasien poli GIG01 milik DR002
        $cmd = SaveSoapCommand::fromWhitelistedPayload([
            'no_rawat' => '2026/11/16/000002',
            'keluhan' => 'Sakit gigi',
        ]);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('poliklinik');
        $useCase->execute($cmd, $this->dokterUmum);
    }

    public function testSavePrbAutoCreatesBaseSoapAndSyncsRtlWithoutDeductingStock(): void
    {
        $useCase = new SavePrbPrescriptionUseCase($this->ralanRepo, $this->regRepo, $this->tx);

        $cmd = SavePrbCommand::fromWhitelistedPayload([
            'no_rawat' => '2026/11/16/000001',
            'diagnosa_kronis' => 'Hipertensi Esensial',
            'items' => [
                ['nama_obat' => 'Amlodipine 10mg', 'jumlah' => 30, 'aturan_pakai' => '1x1 pagi'],
                ['nama_obat' => 'Candesartan 8mg', 'jumlah' => 30, 'aturan_pakai' => '1x1 malam'],
            ],
        ]);

        $triplet = $useCase->execute($cmd, $this->dokterUmum);

        // Auto-created base SOAP dan mengembalikan triplet agar tidak duplikat
        $this->assertArrayHasKey('tgl_perawatan', $triplet);
        $this->assertArrayHasKey('jam_rawat', $triplet);

        $soap = $this->ralanRepo->findByTriplet(
            new NoRawat('2026/11/16/000001'),
            $triplet['tgl_perawatan'],
            $triplet['jam_rawat']
        );
        $this->assertNotNull($soap);
        $this->assertStringContainsString('Amlodipine 10mg', $soap->rtl);
        $this->assertStringContainsString('PRB 30 Hari', $soap->rtl);

        $metaVal = $this->pdo->query("SELECT meta_value FROM mlite_pemeriksaan_ralan_metadata WHERE meta_key = 'meta_prb'")->fetchColumn();
        $this->assertNotFalse($metaVal);
        $this->assertStringContainsString('Hipertensi Esensial', (string) $metaVal);
    }

    public function testSaveCpptRanapWithDecoupledStatusChecker(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_lanjut)
            VALUES ('003', '2026/11/16/000003', '{$today}', '08:30:00', 'DR001', '000001', 'UMU01', 'UMU', 'Belum', 'Ranap');
        ");

        $checker = new class implements RanapStatusCheckerInterface {
            public function isPatientStillAdmitted(NoRawat $noRawat): bool
            {
                return true;
            }
        };

        $useCase = new SaveCpptRanapUseCase($this->ranapRepo, $checker, $this->tx);
        $cmd = \App\Application\Rme\DTO\SaveCpptRanapCommand::fromWhitelistedPayload([
            'no_rawat' => '2026/11/16/000003',
            'keluhan' => 'Kondisi stabil',
            'rtl' => 'Teruskan infus RL 20 tpm',
        ]);

        $useCase->execute($cmd, $this->dokterUmum);
        $this->assertCount(1, $this->ranapRepo->findByNoRawat(new NoRawat('2026/11/16/000003')));
    }

    public function testOdontogramAndAtomicSuratSakit(): void
    {
        $saveOdo = new SaveOdontogramUseCase($this->odoRepo);
        $delOdo = new DeleteOdontogramUseCase($this->odoRepo);

        $saveOdo->execute(new Odontogram(
            id: null,
            noRkmMedis: new NoRkmMedis('000001'),
            noRawat: new NoRawat('2026/11/16/000001'),
            tglPemeriksaan: date('Y-m-d'),
            nomorGigi: '18',
            kondisi: 'UNE',
            catatan: 'Belum tumbuh',
            idUser: 10
        ));

        $list = $this->odoRepo->findByNoRkmMedis(new NoRkmMedis('000001'));
        $this->assertCount(1, $list);
        $delOdo->execute((int) $list[0]->id);
        $this->assertCount(0, $this->odoRepo->findByNoRkmMedis(new NoRkmMedis('000001')));

        $suratUseCase = new CreateSuratMedisUseCase($this->suratRepo, $this->tx);
        $surat1 = $suratUseCase->createSuratSakit(
            new NoRawat('2026/11/16/000001'),
            new \DateTimeImmutable('2026-11-16'),
            new \DateTimeImmutable('2026-11-18')
        );
        $surat2 = $suratUseCase->createSuratSakit(
            new NoRawat('2026/11/16/000002'),
            new \DateTimeImmutable('2026-11-16'),
            new \DateTimeImmutable('2026-11-17')
        );

        $this->assertInstanceOf(SuratSakit::class, $surat1);
        $this->assertNotSame($surat1->noSurat, $surat2->noSurat);
        $this->assertStringEndsWith('0001', $surat1->noSurat);
        $this->assertStringEndsWith('0002', $surat2->noSurat);
    }
}
