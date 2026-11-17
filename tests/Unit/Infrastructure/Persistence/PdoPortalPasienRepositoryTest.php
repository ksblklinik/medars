<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoPortalPasienRepository;
use PHPUnit\Framework\TestCase;

final class PdoPortalPasienRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoPortalPasienRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoPortalPasienRepository($this->pdo);
    }

    public function testVerifyLookupAndCreateOnlineBooking(): void
    {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->pdo->exec("
            INSERT INTO pasien (no_rkm_medis, nm_pasien, tgl_lahir, jk) VALUES ('000140', 'Lia Amalia', '1992-05-20', 'P');
            INSERT INTO poliklinik (kd_poli, nm_poli, status) VALUES ('U01', 'Poli Umum', '1');
            INSERT INTO dokter (kd_dokter, nm_dokter, status) VALUES ('DR01', 'dr. Andi', '1');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj, stts, status_bayar)
            VALUES ('001', '2026/11/17/000140', '{$today}', '08:00:00', 'DR01', '000140', 'U01', 'A09', 'Sudah', 'Sudah Bayar');
        ");

        $this->assertNull($this->repo->verifyAndLoadPortal('000140', '1990-01-01'));

        $data = $this->repo->verifyAndLoadPortal('000140', '1992-05-20');
        $this->assertNotNull($data);
        $this->assertSame('Lia Amalia', $data['patient']['nm_pasien']);
        $this->assertCount(1, $data['visits']);

        $booking = $this->repo->createOnlineBooking('000140', '1992-05-20', $tomorrow, 'U01', 'DR01', 'A09');
        $this->assertSame('001', $booking['no_reg']);
    }
}
