<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoPresensiRepository;
use PHPUnit\Framework\TestCase;

final class PdoPresensiRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PdoPresensiRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoPresensiRepository($this->pdo);
    }

    public function testClockInAndClockOutPreventsDuplicateForSameDay(): void
    {
        $today = date('Y-m-d');
        $this->repo->clockIn('admin', 'Pagi', 'Loket Utama', 'Masuk tepat waktu', $today, '07:55:00');
        $rows = $this->repo->listPresensi($today, $today, 'admin');
        $this->assertCount(1, $rows);
        $this->assertSame('07:55:00', $rows[0]['jam_masuk']);

        $this->repo->clockOut('admin', $today, '16:05:00');
        $rowsAfter = $this->repo->listPresensi($today, $today, 'admin');
        $this->assertSame('16:05:00', $rowsAfter[0]['jam_pulang']);

        $this->expectException(\DomainException::class);
        $this->repo->clockIn('admin', 'Pagi', 'Loket Utama', '', $today, '08:00:00');
    }
}
