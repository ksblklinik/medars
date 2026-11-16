<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

final class InitialSeeder
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function seed(?string $adminPassword = null): void
    {
        $this->seedSuperadmin($adminPassword);
        $this->seedPoliklinik();
        $this->seedPenjab();
        $this->seedDokter();
    }

    private function seedSuperadmin(?string $adminPassword): void
    {
        $exists = (int) $this->pdo->query("SELECT COUNT(*) FROM mlite_users WHERE id = 1")->fetchColumn();
        if ($exists > 0) {
            return;
        }

        $plainPassword = $adminPassword ?: (getenv('ADMIN_INIT_PASSWORD') ?: 'AdminMedars2026!');
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO mlite_users (id, username, password, email, no_telp, fullname, role, cap, access, avatar, status, created_at, updated_at)
            VALUES (1, 'admin', :password, 'admin@medars.local', '', 'Super Administrator', 'admin', 'all', 'all', '', '1', :created_at, :updated_at)
        ");
        $stmt->execute([
            ':password' => $hash,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    private function seedPoliklinik(): void
    {
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM poliklinik")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->pdo->exec("
            INSERT INTO poliklinik (kd_poli, nm_poli, registrasi, registrasilama, status, allow_bpjs)
            VALUES ('UMU01', 'Poliklinik Umum', 25000, 15000, '1', 1)
        ");
        $this->pdo->exec("
            INSERT INTO poliklinik (kd_poli, nm_poli, registrasi, registrasilama, status, allow_bpjs)
            VALUES ('GIG01', 'Poliklinik Gigi & Mulut', 35000, 25000, '1', 1)
        ");
    }

    private function seedPenjab(): void
    {
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM penjab")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->pdo->exec("
            INSERT INTO penjab (kd_pj, png_jawab, status, nama_perusahaan, alamat_asuransi, no_telp)
            VALUES ('UMU', 'Umum / Tunai', '1', '', '', '')
        ");
        $this->pdo->exec("
            INSERT INTO penjab (kd_pj, png_jawab, status, nama_perusahaan, alamat_asuransi, no_telp)
            VALUES ('BPJ', 'BPJS Kesehatan', '1', 'BPJS Kesehatan', '', '1500400')
        ");
    }

    private function seedDokter(): void
    {
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM dokter")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->pdo->exec("
            INSERT INTO dokter (kd_dokter, nm_dokter, jk, no_ijn_praktek, status, almt_tgl, no_telp)
            VALUES ('DR001', 'dr. Jaga Umum', 'L', 'SIP/2026/001', '1', '', '')
        ");
    }
}
