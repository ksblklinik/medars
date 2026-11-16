# PRD 12: Manajemen Modul, Ekstensibilitas & Plugin Lifecycle

## 1. Ikhtisar Domain
Domain ini mengatur arsitektur modular *plug-and-play*, siklus hidup (*lifecycle*) modul atau plugin, resolusi dependensi antar-modul, pendaftaran rute dan menu navigasi dinamis, serta mekanisme *fault-isolation* agar kegagalan pada satu modul tidak melumpuhkan sistem operasional inti klinik.

## 2. Aturan Rekayasa & Spesifikasi Kunci

1. **Manifest & Anatomi Modul Standar:**
   - Setiap modul ditempatkan dalam direktori terisolasi (`plugins/<module_slug>/` atau `modules/<module_slug>/`).
   - Wajib memiliki berkas manifes deklaratif (`Info.php` atau `module.json`) dengan tipe data ketat:
     - `id` / `slug`: Pengenal unik format *kebab-case* (contoh: `apotek-ralan`, `kasir-rawat-jalan`).
     - `name`: Nama tampilan modul.
     - `version`: Format Semantic Versioning (`MAJOR.MINOR.PATCH`).
     - `dependencies`: Daftar prasyarat modul lain beserta batasan versinya.
     - `permissions`: Hak akses granular yang didaftarkan ke sistem RBAC (*Role-Based Access Control*).
     - `navigation`: Definisi menu dan sub-menu sidebar/antarmuka.

2. **Siklus Hidup Modul (*Module Lifecycle*):**
   - **Install:** Memvalidasi kompatibilitas versi core dan dependensi modul. Menjalankan skrip migrasi database transaksional (`migrations/` up) dan mendaftarkan metadata modul ke tabel registri (`mlite_modules`).
   - **Activate:** Mengaktifkan pemetaan rute URL, controller, hook event, dan menu navigasi. Aktivasi tidak memodifikasi struktur tabel.
   - **Deactivate:** Menyembunyikan rute dan menu secara instan tanpa menghapus skema database maupun rekaman data transaksi.
   - **Uninstall:** Opsi pencopotan dengan dua mode:
     - *Safe Uninstall (Default):* Menonaktifkan modul dan mencabut registrasi, tetapi mempertahankan tabel data demi kepatuhan audit medis (*data retention*).
     - *Purge Uninstall:* Menjalankan rollback migrasi (`down()`) untuk menghapus tabel dan skema database secara menyeluruh (hanya diperbolehkan jika tidak ada data buku besar/transaksi terkait).

3. **Resolusi Dependensi & Keamanan Pemuatan (*Dependency Resolution*):**
   - Modul tidak dapat diaktifkan jika dependensi prasyaratnya belum terpasang dan berstatus aktif (misal: modul `apotek_ralan` mewajibkan modul `farmasi` dan `rawat_jalan`).
   - Pengecekan dependensi sirkular (*circular dependency check*) untuk mencegah *infinite loop* saat inisialisasi modul.

4. **Isolasi Kesalahan & Safe Mode (*Fault Isolation*):**
   - Pemuatan modul dibungkus penanganan eksepsi (*isolated try-catch container*).
   - Jika sebuah modul mengalami error fatal (syntax error, missing library, atau unhandled exception), sistem otomatis menonaktifkan modul tersebut untuk sementara (*circuit breaker*), mencatat stack trace ke log audit sistem, dan mengizinkan modul inti lainnya tetap melayani pasien tanpa gangguan (*zero downtime core*).

5. **Pengendalian Akses & Otorisasi Modular (RBAC):**
   - Setiap endpoint controller modul (`Admin.php`, `Site.php`, atau API Controller) wajib diverifikasi melalui middleware otorisasi sentral.
   - Akses rute ditolak otomatis dengan status HTTP 403 / 404 jika modul induknya dalam kondisi non-aktif (*deactivated*).

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_register_module_when_manifest_is_valid()`
- `test_should_block_module_activation_if_required_dependency_is_missing()`
- `test_should_prevent_circular_dependencies_between_modules()`
- `test_should_execute_database_migrations_atomically_during_installation()`
- `test_should_isolate_routes_and_return_404_when_module_is_deactivated()`
- `test_should_isolate_failure_and_keep_core_running_when_module_throws_fatal_error()`
- `test_should_preserve_transactional_data_tables_during_safe_uninstall()`
