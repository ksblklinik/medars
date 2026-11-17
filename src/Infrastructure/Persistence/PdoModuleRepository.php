<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\ModuleRepositoryInterface;
use InvalidArgumentException;
use PDO;

final class PdoModuleRepository implements ModuleRepositoryInterface
{
    /** @var list<string> */
    private const CORE_MODULES = ['dashboard', 'users', 'settings', 'modules', 'profil'];

    /** @var list<array{dir: string, name: string, category: string, description: string}> */
    private const CATALOG = [
        ['dir' => 'dashboard', 'name' => 'Dashboard', 'category' => 'Inti Sistem', 'description' => 'Halaman ringkasan KPI & peluncur modul utama.'],
        ['dir' => 'frontoffice', 'name' => 'Front Office & Antrean', 'category' => 'Layanan Pasien', 'description' => 'Gatekeeper check-in Mobile JKN, antrean loket & display TV.'],
        ['dir' => 'pasien', 'name' => 'Master Pasien', 'category' => 'Layanan Pasien', 'description' => 'Registrasi pasien baru, auto RM & cetak kartu berobat.'],
        ['dir' => 'portal_pasien', 'name' => 'Portal Pasien', 'category' => 'Layanan Pasien', 'description' => 'Portal mandiri pasien untuk riwayat kunjungan & booking online.'],
        ['dir' => 'rawat_jalan', 'name' => 'Rawat Jalan', 'category' => 'Layanan Pasien', 'description' => 'Pendaftaran kunjungan poliklinik & pengawasan status periksa.'],
        ['dir' => 'igd', 'name' => 'IGD & Triase ATS/ESI', 'category' => 'Layanan Pasien', 'description' => 'Triase zona ATS/ESI, TTV/GCS, & disposisi gawat darurat.'],
        ['dir' => 'rawat_inap', 'name' => 'Rawat Inap', 'category' => 'Layanan Pasien', 'description' => 'Manajemen bangsal, kamar, check-in & billing akumulatif ranap.'],
        ['dir' => 'dokter_ralan', 'name' => 'Dokter Poliklinik', 'category' => 'Klinis & Penunjang', 'description' => 'SOAP, ICD-10, tindakan, e-resep, & resume medis rawat jalan.'],
        ['dir' => 'dokter_ranap', 'name' => 'Dokter Rawat Inap', 'category' => 'Klinis & Penunjang', 'description' => 'CPPT visite dokter rawat inap & pemantauan klinis.'],
        ['dir' => 'laboratorium', 'name' => 'Laboratorium', 'category' => 'Klinis & Penunjang', 'description' => 'Order lab, input hasil pemeriksaan & cetak laporan lab.'],
        ['dir' => 'radiologi', 'name' => 'Radiologi', 'category' => 'Klinis & Penunjang', 'description' => 'Order radiologi, input expertise & cetak hasil radiologi.'],
        ['dir' => 'apotek_ralan', 'name' => 'Apotek & E-Resep', 'category' => 'Farmasi & Logistik', 'description' => 'Validasi e-resep ralan/ranap, dispensing, copy resep & etiket.'],
        ['dir' => 'apotek_umum', 'name' => 'Apotek Umum (POS)', 'category' => 'Farmasi & Logistik', 'description' => 'Penjualan retail bebas PO-APT 5 cara bayar & struk thermal.'],
        ['dir' => 'farmasi', 'name' => 'Gudang Farmasi', 'category' => 'Farmasi & Logistik', 'description' => 'Master obat, penerimaan stok, mutasi antar depo & kartu stok.'],
        ['dir' => 'kasir_rawat_jalan', 'name' => 'Kasir & Billing', 'category' => 'Keuangan & SDM', 'description' => 'Split billing 4 pilar klaim, pelunasan piutang & kuitansi.'],
        ['dir' => 'keuangan', 'name' => 'Keuangan & Akuntansi', 'category' => 'Keuangan & SDM', 'description' => 'Jurnal double-entry, buku besar, laba rugi & COA rekening.'],
        ['dir' => 'jasa_medis', 'name' => 'Jasa Medis Dokter', 'category' => 'Keuangan & SDM', 'description' => 'Rekapitulasi & cetak rincian jasa medis per dokter.'],
        ['dir' => 'kepegawaian', 'name' => 'Kepegawaian & SDM', 'category' => 'Keuangan & SDM', 'description' => 'Master pegawai, petugas medis & cetak direktori SDM.'],
        ['dir' => 'presensi', 'name' => 'Presensi Pegawai', 'category' => 'Keuangan & SDM', 'description' => 'Absensi clock-in/clock-out shift pegawai harian.'],
        ['dir' => 'surat', 'name' => 'Surat & Rujukan', 'category' => 'Klinis & Penunjang', 'description' => 'Penerbitan surat sakit, hamil, bebas narkoba & rujukan internal.'],
        ['dir' => 'home_care', 'name' => 'Home Care', 'category' => 'Layanan Pasien', 'description' => 'Kunjungan homecare & Trias Preceptorship dengan Privacy Shield.'],
        ['dir' => 'vedika', 'name' => 'Vedika BPJS', 'category' => 'Integrasi & Analitik', 'description' => 'Verifikasi berkas klaim BPJS & cetak bundel Vedika.'],
        ['dir' => 'laporan', 'name' => 'Laporan & Analitik', 'category' => 'Integrasi & Analitik', 'description' => 'Laporan kunjungan, rekonsiliasi order & export CSV.'],
        ['dir' => 'manajemen', 'name' => 'Dashboard Eksekutif', 'category' => 'Integrasi & Analitik', 'description' => 'KPI eksekutif & LEAN Analytics Board.'],
        ['dir' => 'master', 'name' => 'Master Data Klinik', 'category' => 'Manajemen & Sistem', 'description' => 'Poliklinik, dokter, penjamin, tarif & jadwal praktik.'],
        ['dir' => 'inventaris', 'name' => 'Inventaris & Aset', 'category' => 'Manajemen & Sistem', 'description' => 'Manajemen aset medis & non-medis klinik.'],
        ['dir' => 'mlite_api_key', 'name' => 'API Key Eksternal', 'category' => 'Manajemen & Sistem', 'description' => 'Manajemen token akses integrasi & whitelist IP.'],
        ['dir' => 'mlite_logs', 'name' => 'Audit & Log Sistem', 'category' => 'Manajemen & Sistem', 'description' => 'Audit integritas ledger, rotasi log & audit trail.'],
        ['dir' => 'users', 'name' => 'Manajemen Pengguna', 'category' => 'Inti Sistem', 'description' => 'Akun pengguna, peran & hak akses modul.'],
        ['dir' => 'settings', 'name' => 'Pengaturan Sistem', 'category' => 'Inti Sistem', 'description' => 'Konfigurasi identitas klinik, bridging & parameter sistem.'],
        ['dir' => 'modules', 'name' => 'Manajer Modul', 'category' => 'Inti Sistem', 'description' => 'Aktivasi, penonaktifan & urutan modul aplikasi.'],
        ['dir' => 'profil', 'name' => 'Profil Saya', 'category' => 'Inti Sistem', 'description' => 'Pengaturan akun pribadi & ganti password.'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listModules(string $filter = 'all', string $search = ''): array
    {
        $stmt = $this->pdo->query('SELECT dir, sequence, is_active, is_core, updated_at FROM mlite_modules');
        $dbRows = [];
        if ($stmt !== false) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $dbRows[(string) $row['dir']] = $row;
            }
        }

        $q = mb_strtolower(trim($search));
        $result = [];
        foreach (self::CATALOG as $index => $item) {
            $dir = $item['dir'];
            $isCore = in_array($dir, self::CORE_MODULES, true);
            $dbRow = $dbRows[$dir] ?? null;
            $isActive = $dbRow !== null ? ((int) $dbRow['is_active'] === 1) : true;
            $sequence = $dbRow !== null ? (int) $dbRow['sequence'] : ($index + 1);
            $updatedAt = $dbRow !== null ? (string) $dbRow['updated_at'] : '';

            if ($filter === 'active' && !$isActive) {
                continue;
            }
            if ($filter === 'inactive' && $isActive) {
                continue;
            }
            if ($q !== '') {
                $haystack = mb_strtolower($dir . ' ' . $item['name'] . ' ' . $item['category'] . ' ' . $item['description']);
                if (!str_contains($haystack, $q)) {
                    continue;
                }
            }

            $result[] = [
                'dir' => $dir,
                'name' => $item['name'],
                'category' => $item['category'],
                'description' => $item['description'],
                'sequence' => $sequence,
                'is_active' => $isActive,
                'is_core' => $isCore,
                'updated_at' => $updatedAt,
            ];
        }

        usort($result, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        return $result;
    }

    public function setActive(string $dir, bool $active): bool
    {
        $cleanDir = trim($dir);
        if ($cleanDir === '' || !$this->existsInCatalog($cleanDir)) {
            return false;
        }
        $isCore = in_array($cleanDir, self::CORE_MODULES, true);
        if ($isCore && !$active) {
            throw new InvalidArgumentException('Modul inti sistem tidak dapat dinonaktifkan.');
        }

        $now = date('Y-m-d H:i:s');
        $this->upsertModuleRow($cleanDir, null, $active ? 1 : 0, $isCore ? 1 : 0, $now);
        return true;
    }

    public function updateSequence(string $dir, int $sequence): bool
    {
        $cleanDir = trim($dir);
        if ($cleanDir === '' || !$this->existsInCatalog($cleanDir)) {
            return false;
        }
        $isCore = in_array($cleanDir, self::CORE_MODULES, true);
        $now = date('Y-m-d H:i:s');
        $this->upsertModuleRow($cleanDir, max(0, $sequence), null, $isCore ? 1 : 0, $now);
        return true;
    }

    /**
     * @return list<string>
     */
    public function getInactiveModuleDirs(): array
    {
        $stmt = $this->pdo->query('SELECT dir FROM mlite_modules WHERE is_active = 0');
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $rows ?: []));
    }

    private function existsInCatalog(string $dir): bool
    {
        foreach (self::CATALOG as $item) {
            if ($item['dir'] === $dir) {
                return true;
            }
        }
        return false;
    }

    private function upsertModuleRow(string $dir, ?int $sequence, ?int $isActive, int $isCore, string $now): void
    {
        $check = $this->pdo->prepare('SELECT sequence, is_active FROM mlite_modules WHERE dir = :dir LIMIT 1');
        $check->execute([':dir' => $dir]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing === false) {
            $defaultSeq = $sequence ?? $this->defaultSequence($dir);
            $defaultActive = $isActive ?? 1;
            $ins = $this->pdo->prepare(
                'INSERT INTO mlite_modules (dir, sequence, is_active, is_core, updated_at)
                 VALUES (:dir, :sequence, :is_active, :is_core, :updated_at)'
            );
            $ins->execute([
                ':dir' => $dir,
                ':sequence' => $defaultSeq,
                ':is_active' => $defaultActive,
                ':is_core' => $isCore,
                ':updated_at' => $now,
            ]);
            return;
        }

        $upd = $this->pdo->prepare(
            'UPDATE mlite_modules
             SET sequence = :sequence, is_active = :is_active, is_core = :is_core, updated_at = :updated_at
             WHERE dir = :dir'
        );
        $upd->execute([
            ':sequence' => $sequence ?? (int) $existing['sequence'],
            ':is_active' => $isActive ?? (int) $existing['is_active'],
            ':is_core' => $isCore,
            ':updated_at' => $now,
            ':dir' => $dir,
        ]);
    }

    private function defaultSequence(string $dir): int
    {
        foreach (self::CATALOG as $index => $item) {
            if ($item['dir'] === $dir) {
                return $index + 1;
            }
        }
        return 999;
    }
}
