<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\ReadOnlyConnectionGuard;
use App\Infrastructure\Persistence\PdoPasienRepository;
use PHPUnit\Framework\TestCase;

final class PdoPasienRepositoryTest extends TestCase
{
    private \PDO $nextPdo;
    private \PDO $legacyPdo;
    private PdoPasienRepository $repository;

    protected function setUp(): void
    {
        // 1. Next DB (Read-Write)
        $this->nextPdo = new \PDO('sqlite::memory:');
        $this->nextPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createPasienTable($this->nextPdo);

        // 2. Legacy DB (Read-Only)
        $this->legacyPdo = new \PDO('sqlite::memory:');
        $this->legacyPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createPasienTable($this->legacyPdo);

        $guard = new ReadOnlyConnectionGuard($this->legacyPdo);

        $this->repository = new PdoPasienRepository($this->nextPdo, $guard);
    }

    private function createPasienTable(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE pasien (
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
    }

    public function test_should_generate_next_no_rkm_medis_above_both_legacy_and_next_databases(): void
    {
        // Di legacy max RM = 000100
        $this->legacyPdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir) 
            VALUES ('000100', 'Pasien Legacy', '3201', 'L', '1990-01-01')");

        // Di next max RM = 000105
        $this->nextPdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir) 
            VALUES ('000105', 'Pasien Next', '3202', 'P', '1995-02-02')");

        // Harusnya menghasilkan MAX(100, 105) + 1 = 106 -> '000106'
        $nextRm = $this->repository->getNextNoRkmMedis();
        $this->assertSame('000106', $nextRm->value());
    }

    public function test_find_by_no_rkm_medis_returns_next_db_first_or_falls_back_to_legacy(): void
    {
        $this->legacyPdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir) 
            VALUES ('000001', 'Pasien Kuno', '3201', 'L', '1980-01-01')");

        $this->nextPdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir) 
            VALUES ('000002', 'Pasien Baru', '3202', 'P', '1990-01-01')");

        $foundNext = $this->repository->findByNoRkmMedis(new NoRkmMedis('000002'));
        $this->assertNotNull($foundNext);
        $this->assertSame('Pasien Baru', $foundNext->nmPasien);

        $foundLegacy = $this->repository->findByNoRkmMedis(new NoRkmMedis('000001'));
        $this->assertNotNull($foundLegacy);
        $this->assertSame('Pasien Kuno', $foundLegacy->nmPasien);
    }

    public function test_save_inserts_only_into_next_db(): void
    {
        $pasien = new Pasien(
            noRkmMedis: new NoRkmMedis('000200'),
            nmPasien: 'Pasien Anyar',
            nik: '3201019999990001',
            jk: 'L',
            tglLahir: new \DateTimeImmutable('2000-01-01'),
            alamat: 'Jl. Merdeka No. 10',
            noTelp: '08123456789'
        );

        $this->repository->save($pasien);

        $found = $this->repository->findByNoRkmMedis(new NoRkmMedis('000200'));
        $this->assertNotNull($found);
        $this->assertSame('Pasien Anyar', $found->nmPasien);

        // Verifikasi legacy DB tetap bersih (zero write)
        $countLegacy = (int) $this->legacyPdo->query("SELECT COUNT(*) FROM pasien WHERE no_rkm_medis = '000200'")->fetchColumn();
        $this->assertSame(0, $countLegacy);
    }

    public function test_search_and_find_by_nik(): void
    {
        $this->nextPdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, no_peserta, jk, tgl_lahir) 
            VALUES ('000300', 'Siti Aminah', '3201055555550001', '0009988776655', 'P', '1992-05-15')");

        $byNik = $this->repository->findByNik('3201055555550001');
        $this->assertNotNull($byNik);
        $this->assertSame('Siti Aminah', $byNik->nmPasien);

        $results = $this->repository->search('Aminah');
        $this->assertCount(1, $results);
        $this->assertSame('000300', $results[0]->noRkmMedis->value());

        // Pencarian F4 via NIK
        $resultsByNik = $this->repository->search('3201055555550001');
        $this->assertCount(1, $resultsByNik);
        $this->assertSame('000300', $resultsByNik[0]->noRkmMedis->value());

        // Pencarian F4 via No. BPJS
        $resultsByBpjs = $this->repository->search('0009988776655');
        $this->assertCount(1, $resultsByBpjs);
        $this->assertSame('000300', $resultsByBpjs[0]->noRkmMedis->value());
    }

    public function test_hydrates_legacy_patient_with_dash_gender(): void
    {
        $this->legacyPdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir) 
            VALUES ('000099', 'Pasien Legacy Tanpa JK', '-', '-', '1985-01-01')");

        $found = $this->repository->findByNoRkmMedis(new NoRkmMedis('000099'));
        $this->assertNotNull($found);
        $this->assertSame('-', $found->jk);
    }
}
