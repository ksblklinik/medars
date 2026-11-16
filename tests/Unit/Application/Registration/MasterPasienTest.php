<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Registration;

use App\Application\Registration\UseCase\DeleteMasterPasienUseCase;
use App\Application\Registration\UseCase\SaveMasterPasienUseCase;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoPasienRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class MasterPasienTest extends TestCase
{
    private PDO $pdo;
    private PdoPasienRepository $repo;
    private PdoTransactionManager $tx;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->repo = new PdoPasienRepository($this->pdo);
        $this->tx = new PdoTransactionManager($this->pdo);

        $this->pdo->exec("
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO penjab (kd_pj, png_jawab) VALUES ('UMU', 'Umum'), ('BPJ', 'BPJS Kesehatan');
        ");
    }

    public function testCreateAndUpdateMasterPasienWithAutoNoRkmMedisAndDuplicateNikGuard(): void
    {
        $useCase = new SaveMasterPasienUseCase($this->repo, $this->tx);

        // 1. Create baru tanpa no_rkm_medis -> auto-generate 000001
        $created = $useCase->execute([
            'nm_pasien' => 'Siti Aminah',
            'no_ktp' => '3201010101900001',
            'jk' => 'P',
            'tmp_lahir' => 'Bandung',
            'tgl_lahir' => '1990-01-01',
            'alamat' => 'Jl. Merdeka No. 10',
            'no_tlp' => '081234567890',
            'kd_pj' => 'BPJ',
            'no_peserta' => '0001234567890',
        ]);

        $this->assertSame('000001', $created->noRkmMedis->value());
        $this->assertSame('Siti Aminah', $created->nmPasien);

        // 2. Update pasien yang sama (no_rkm_medis 000001)
        $updated = $useCase->execute([
            'no_rkm_medis' => '000001',
            'nm_pasien' => 'Siti Aminah Updated',
            'no_ktp' => '3201010101900001',
            'jk' => 'P',
            'tgl_lahir' => '1990-01-01',
            'alamat' => 'Jl. Asia Afrika No. 99',
            'no_tlp' => '081234567890',
        ]);
        $this->assertSame('Siti Aminah Updated', $updated->nmPasien);
        $this->assertSame('Jl. Asia Afrika No. 99', $updated->alamat);

        // 3. Cegah duplikasi NIK pada pasien baru yang berbeda
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('NIK');
        $useCase->execute([
            'nm_pasien' => 'Orang Lain',
            'no_ktp' => '3201010101900001',
            'jk' => 'L',
            'tgl_lahir' => '1992-05-05',
        ]);
    }

    public function testDeleteMasterPasienBlocksIfPatientHasVisitHistory(): void
    {
        $save = new SaveMasterPasienUseCase($this->repo, $this->tx);
        $delete = new DeleteMasterPasienUseCase($this->repo, $this->tx);

        $p1 = $save->execute([
            'nm_pasien' => 'Pasien Tanpa Kunjungan',
            'no_ktp' => '3201010101900002',
            'jk' => 'L',
            'tgl_lahir' => '1988-03-12',
        ]);
        $delete->execute($p1->noRkmMedis);
        $this->assertNull($this->repo->findByNoRkmMedis($p1->noRkmMedis));

        $p2 = $save->execute([
            'nm_pasien' => 'Pasien Sudah Berkunjung',
            'no_ktp' => '3201010101900003',
            'jk' => 'P',
            'tgl_lahir' => '1991-07-20',
        ]);
        $today = date('Y-m-d');
        $this->pdo->exec("
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000001', '{$today}', '08:00:00', 'DR001', '{$p2->noRkmMedis->value()}', 'UMU01', 'UMU', 'Belum Bayar')
        ");

        $history = $this->repo->getVisitHistory($p2->noRkmMedis);
        $this->assertCount(1, $history);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('riwayat kunjungan');
        $delete->execute($p2->noRkmMedis);
    }
}
