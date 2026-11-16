# PRD 13: Manajemen Pengguna, RBAC, Kepegawaian, Master Data & Pengaturan Sistem

## 1. Ikhtisar Domain
Domain ini menyediakan fondasi administrasi, keamanan akses, data induk SDM medis/non-medis, master data operasional poliklinik/jadwal/tarif, serta konfigurasi global aplikasi SIMRS `ksm-next`. Seluruh modul klinis dan keuangan (Fase 2–7) bergantung pada identitas pengguna terautentikasi dan data master dari domain ini.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity + Mitigasi Modern)

1. **Autentikasi & Keamanan Sesi:**
   - Verifikasi kata sandi wajib menggunakan `password_verify()` (mendukung hash `PASSWORD_BCRYPT` / `PASSWORD_ARGON2ID` dari `ksmlite`).
   - Wajib melakukan `session_regenerate_id(true)` saat login berhasil untuk mencegah serangan *Session Fixation*.
   - **Rate Limiting Brute-Force:** Jika gagal login berturut-turut melebihi batas (misal 5 kali dalam 5 menit), akun/IP diblokir sementara dan dicatat ke `mlite_logs`.
   - **Proteksi CSRF:** Seluruh endpoint mutasi (`POST`/`PUT`/`DELETE`) yang menggunakan sesi web wajib memvalidasi token CSRF.

2. **Manajemen Pengguna & RBAC (`mlite_users`, `mlite_user_roles`):**
   - Mendukung `role` (`admin`, `medis`, `paramedis`, `apoteker`, `kasir`, `manajemen`) dan multi-role.
   - **`access` (Module-Level Guard):** Daftar modul yang boleh diakses user (atau `'all'` untuk administrator).
   - **`cap` (Poliklinik Scope Guard):** Daftar kode poliklinik (`kd_poli`) yang boleh diakses user (misal dokter/perawat poli tertentu hanya melihat pasien di poli yang diizinkan).
   - **Anti-Lockout Guard:** Sistem melarang penghapusan user ID `1` (Superadmin), melarang user menghapus dirinya sendiri, dan melarang penghapusan role admin terakhir.

3. **Kepegawaian (`pegawai`, `dokter`, `petugas`):**
   - Pengelolaan data induk `pegawai`, `dokter` (`kd_dokter`, `no_ijn_praktek`/SIP, spesialisasi), dan `petugas` (`nip`, jabatan).
   - **Soft-Deactivation:** Dokter atau petugas yang sudah pernah menangani kunjungan pasien dilarang dihapus fisik (*foreign key protection*); cukup dinonaktifkan (`status = '0'`) agar tidak muncul di pilihan pendaftaran baru namun histori RME/billing tetap utuh.

4. **Master Data Operasional (`poliklinik`, `jadwal`, `penjab`, `jns_perawatan`):**
   - **Master Poliklinik:** Mengelola `kd_poli`, `nm_poli`, tarif `registrasi` (baru), `registrasilama` (lama), `status`, dan kebijakan **`allow_bpjs`** secara *data-driven* (misal Poli Eksekutif `PEX` di-set `allow_bpjs = 0` dari UI Master tanpa hardcode kode).
   - **Jadwal Praktik Dokter (`jadwal`):** Mengelola `kd_dokter`, `kd_poli`, `hari_kerja`, `jam_mulai`, `jam_selesai`, `kuota`, `prefik`, dan `room`. Sistem menolak jadwal tumpang-tindih (*overlap*) untuk dokter yang sama pada hari yang sama.
   - **Master Penjamin (`penjab`) & Tarif Tindakan (`jns_perawatan`):** Pengaturan daftar asuransi/penjamin dan komponen tarif jasa sarana + jasa medis.

5. **Pengaturan Global (`mlite_settings`) & Jejak Audit (`mlite_logs`):**
   - Konfigurasi key-value `(module, field, value)` untuk profil instansi (nama klinik/RS, alamat, kontak, logo) dan parameter fitur per modul.
   - **Audit Trail (`mlite_logs`):** Setiap aktivitas login, gagal login, perubahan role/hak akses, perubahan tarif poliklinik/tindakan, dan perubahan pengaturan sistem wajib dicatat secara *append-only*.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_authenticate_valid_user_and_regenerate_session_on_login()`
- `test_should_rate_limit_consecutive_failed_login_attempts()`
- `test_should_enforce_rbac_module_access_and_poliklinik_cap_scope()`
- `test_should_block_deleting_superadmin_or_self_user()`
- `test_should_reject_overlapping_doctor_schedule_on_same_day()`
- `test_should_soft_deactivate_doctor_instead_of_hard_delete_when_visits_exist()`
- `test_should_toggle_poliklinik_allow_bpjs_setting_dynamically()`
- `test_should_record_audit_log_on_role_or_setting_mutation()`
