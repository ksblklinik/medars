<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoOperasiRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoOperasiRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoOperasiRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoOperasiRepository($this->pdo);
    }

    public function testPaketBookingAndLaporanOperasi(): void
    {
        $paket = $this->repo->savePaket([
            'kode_paket' => 'OP-01',
            'nm_perawatan' => 'Apendektomi',
            'kategori' => 'Bedah Umum',
            'tarif_total' => 4500000,
        ]);
        self::assertSame('OP-01', $paket['kode_paket']);
        self::assertCount(1, $this->repo->listPaket('Apendektomi'));

        $today = date('Y-m-d');
        $booking = $this->repo->saveBooking([
            'no_rawat' => '2026/11/17/000001',
            'kode_paket' => 'OP-01',
            'tanggal' => $today,
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:30',
            'kd_dokter' => 'DR01',
            'kd_ruang_ok' => 'OK-1',
        ]);
        $bookingId = (int) $booking['id'];
        self::assertCount(1, $this->repo->listBookings($today, $today));
        self::assertTrue($this->repo->updateBookingStatus($bookingId, 'Selesai'));

        $laporan = $this->repo->saveLaporan([
            'no_rawat' => '2026/11/17/000001',
            'kode_paket' => 'OP-01',
            'tanggal' => $today . ' 10:30:00',
            'diagnosa_preop' => 'Apendisitis Akut',
            'diagnosa_postop' => 'Post Apendektomi',
            'jaringan_dieksekusi' => 'Apendiks',
            'permintaan_pa' => 'Ya',
            'operator1' => 'dr. Bedah',
            'asisten_operator1' => 'Ns. Budi',
            'dokter_anestesi' => 'dr. Anestesi',
            'laporan_operasi' => 'Operasi berjalan lancar tanpa komplikasi.',
        ]);
        self::assertSame('Apendisitis Akut', $laporan['diagnosa_preop']);
        self::assertCount(1, $this->repo->listLaporan('2026/11/17/000001'));

        // Verify billing ledger posted from paket_operasi
        $ledgerTotal = (float) $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM mlite_billing_ledger WHERE no_rawat = '2026/11/17/000001' AND category = 'Operasi'")->fetchColumn();
        self::assertSame(4500000.0, $ledgerTotal);
    }
}
