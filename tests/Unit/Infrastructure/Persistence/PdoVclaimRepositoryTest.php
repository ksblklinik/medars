<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoVclaimRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoVclaimRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoVclaimRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoVclaimRepository($this->pdo);
    }

    public function testSepRujukanSuratKontrolAndPesertaLookup(): void
    {
        $this->pdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, alamat, no_peserta)
            VALUES ('000010', 'Siti Aminah', '3201010101850010', 'P', 'Bogor', '1985-01-01', 'Jl. Bogor', '0001999888777')");

        $peserta = $this->repo->findPesertaLocal('0001999888777');
        self::assertNotNull($peserta);
        self::assertSame('Siti Aminah', $peserta['nm_pasien']);

        $today = date('Y-m-d');
        $sep = $this->repo->saveSep([
            'no_rawat' => '2026/11/17/000010',
            'tglsep' => $today,
            'no_kartu' => '0001999888777',
            'nama_pasien' => 'Siti Aminah',
            'kdpolitujuan' => 'INT',
            'nmpolitujuan' => 'Penyakit Dalam',
            'diagawal' => 'E11.9',
            'nmdiagnosaawal' => 'Type 2 diabetes mellitus',
        ]);
        self::assertNotEmpty($sep['no_sep']);
        self::assertNotNull($this->repo->findSep($sep['no_sep']));
        self::assertCount(1, $this->repo->listSep($today, $today, 'Siti'));

        $ruj = $this->repo->saveRujukan([
            'no_sep' => $sep['no_sep'],
            'ppk_dirujuk' => '0112R001',
            'nm_ppk_dirujuk' => 'RSUD Cibinong',
            'diag_rujukan' => 'E11.9',
        ]);
        self::assertNotEmpty($ruj['no_rujukan']);
        self::assertCount(1, $this->repo->listRujukan('RSUD'));

        $sk = $this->repo->saveSuratKontrol([
            'no_sep' => $sep['no_sep'],
            'tgl_rencana' => date('Y-m-d', strtotime('+7 days')),
            'kd_dokter_bpjs' => '12345',
            'nm_dokter_bpjs' => 'dr. Spesialis PD',
            'kd_poli_bpjs' => 'INT',
            'nm_poli_bpjs' => 'Penyakit Dalam',
        ]);
        self::assertNotEmpty($sk['no_surat']);
        self::assertCount(1, $this->repo->listSuratKontrol($sep['no_sep']));
    }
}
