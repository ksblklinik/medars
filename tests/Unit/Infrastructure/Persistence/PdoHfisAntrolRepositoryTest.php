<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoHfisAntrolRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoHfisAntrolRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoHfisAntrolRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoHfisAntrolRepository($this->pdo);
    }

    public function testSyncJadwalHfisAndPushAntrolTask(): void
    {
        $saved = $this->repo->saveJadwalHfis([
            'kd_poli_bpjs' => 'INT',
            'nm_poli_bpjs' => 'Penyakit Dalam',
            'kd_dokter_bpjs' => '12345',
            'nm_dokter_bpjs' => 'dr. Andi Sp.PD',
            'hari' => 1,
            'nama_hari' => 'SENIN',
            'jadwal' => '08:00-12:00',
            'kapasitas_pasien' => 30,
        ]);

        $this->assertSame('INT', $saved['kd_poli_bpjs']);
        $this->assertSame('08:00-12:00', $saved['jadwal']);
        $this->assertCount(1, $this->repo->listJadwalHfis('INT'));

        $task = $this->repo->pushAntrolTask([
            'kodebooking' => '20261117INT001',
            'no_rawat' => '2026/11/17/000120',
            'task_id' => 3,
        ]);
        $this->assertSame('20261117INT001', $task['kodebooking']);
        $this->assertSame(3, $task['task_id']);
        $this->assertSame('sent', $task['status_sync']);
        $this->assertCount(1, $this->repo->listAntrolTasks('20261117INT001'));
    }
}
