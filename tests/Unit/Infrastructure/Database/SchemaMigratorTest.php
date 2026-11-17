<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use App\Infrastructure\Database\InitialSeeder;
use App\Infrastructure\Database\SchemaMigrator;
use PDO;
use PHPUnit\Framework\TestCase;

class SchemaMigratorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
    }

    public function testMigrateCreatesAllTablesAndIsIdempotent(): void
    {
        $migrator = new SchemaMigrator($this->pdo);
        $migrator->migrate();
        // Run kedua kali harus idempotent (tanpa error)
        $migrator->migrate();

        $expectedTables = [
            'mlite_users',
            'mlite_settings',
            'mlite_logs',
            'mlite_login_attempts',
            'dokter',
            'petugas',
            'pegawai',
            'poliklinik',
            'jadwal',
            'penjab',
            'jns_perawatan',
            'pasien',
            'reg_periksa',
            'booking_registrasi',
            'sse_events',
            'pemeriksaan_ralan',
            'pemeriksaan_ranap',
            'data_triase_igd',
            'mlite_pemeriksaan_ralan_metadata',
            'penyakit',
            'diagnosa_pasien',
            'mlite_odontogram',
            'surat_sakit',
            'surat_hamil',
            'surat_bebas_narkoba',
            'rujukan_internal',
            'databarang',
            'resep_obat',
            'resep_dokter_nonracikan',
            'resep_dokter_racikan',
            'resep_dokter_racikan_detail',
            'obat_racikan',
            'detail_pemberian_obat',
            'gudangbarang',
            'riwayat_barang_medis',
            'mlite_billing_ledger',
            'mlite_jurnal',
            'mlite_jurnal_detail',
            'bangsal',
            'kamar',
            'kamar_inap',
            'mlite_penjualan_apotek_umum',
            'mlite_penjualan_apotek_umum_detail',
            'permintaan_lab',
            'permintaan_pemeriksaan_lab',
            'detail_periksa_lab',
            'permintaan_radiologi',
            'permintaan_pemeriksaan_radiologi',
            'hasil_radiologi',
            'bridging_sync_queue',
            'mlite_vedika',
            'wagateway_whitelist',
            'wagateway_outbox',
            'wagateway_checkin',
            'mlite_homecare_kunjungan',
            'mlite_homecare_preceptor_sk',
            'rawat_jl_dr',
            'resume_pasien',
        ];

        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        $actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expectedTables as $table) {
            $this->assertContains($table, $actualTables, "Tabel {$table} tidak ditemukan setelah migrasi.");
        }
    }

    public function testInitialSeederCreatesSuperadminAndBasicMastersIdempotently(): void
    {
        $migrator = new SchemaMigrator($this->pdo);
        $migrator->migrate();

        $seeder = new InitialSeeder($this->pdo);
        $seeder->seed('SecretKsm2026!');
        // Run kedua kali tidak boleh gagal atau duplikat
        $seeder->seed('SecretKsm2026!');

        $stmt = $this->pdo->query("SELECT * FROM mlite_users WHERE id = 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($admin);
        $this->assertSame('admin', $admin['username']);
        $this->assertSame('admin', $admin['role']);
        $this->assertTrue(password_verify('SecretKsm2026!', (string) $admin['password']));

        $poliCount = (int) $this->pdo->query("SELECT COUNT(*) FROM poliklinik")->fetchColumn();
        $penjabCount = (int) $this->pdo->query("SELECT COUNT(*) FROM penjab")->fetchColumn();
        $dokterCount = (int) $this->pdo->query("SELECT COUNT(*) FROM dokter")->fetchColumn();

        $this->assertGreaterThanOrEqual(1, $poliCount);
        $this->assertGreaterThanOrEqual(1, $penjabCount);
        $this->assertGreaterThanOrEqual(1, $dokterCount);
    }
}
