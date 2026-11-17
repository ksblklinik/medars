<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoSuratMedisRepository;
use PHPUnit\Framework\TestCase;

final class PdoSuratMedisRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoSuratMedisRepository $repo;
    private CreateSuratMedisUseCase $useCase;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk, alamat)
            VALUES ('000040', 'Dian Permata', '1993-08-12', 'P', 'Jl. Anggrek 5');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, status_bayar)
            VALUES ('001', '2026/11/17/000040', '2026-11-17', '09:00:00', 'DR01', '000040', 'U01', 'A09', 'Belum Bayar');
        ");

        $this->repo = new PdoSuratMedisRepository($this->pdo);
        $this->useCase = new CreateSuratMedisUseCase($this->repo, new PdoTransactionManager($this->pdo));
    }

    public function testListAndFindSuratForPrint(): void
    {
        $sakit = $this->useCase->createSuratSakit(
            new NoRawat('2026/11/17/000040'),
            new \DateTimeImmutable('2026-11-17'),
            new \DateTimeImmutable('2026-11-19')
        );
        $hamil = $this->useCase->createSuratHamil(
            new NoRawat('2026/11/17/000040'),
            new \DateTimeImmutable('2026-11-17'),
            'Positif Hamil 12 Minggu'
        );
        $narkoba = $this->useCase->createSuratBebasNarkoba(
            new NoRawat('2026/11/17/000040'),
            new \DateTimeImmutable('2026-11-17'),
            'Melamar Pekerjaan',
            'DR01',
            'Syarat CPNS'
        );

        $listSakit = $this->repo->listSurat('sakit', 'Dian');
        $this->assertCount(1, $listSakit);
        $this->assertSame($sakit->noSurat, $listSakit[0]['no_surat']);

        $this->assertCount(1, $this->repo->listSurat('hamil', 'Dian'));
        $this->assertCount(1, $this->repo->listSurat('narkoba', 'Dian'));

        $print = $this->repo->findSuratForPrint('sakit', $sakit->noSurat);
        $this->assertNotNull($print);
        $this->assertSame('Dian Permata', $print['nm_pasien']);
        $this->assertSame('3 Hari', $print['lamasakit']);
    }
}
