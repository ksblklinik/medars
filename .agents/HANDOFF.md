# 🔄 Session & Cross-Model Handoff Checkpoint — ksm-next (`medars`)

- **Waktu Pembaruan:** 2026-11-16 14:30 WIB
- **Status Operasional:** Fase 0 Tuntas & Hardened — Siap Eksekusi Fase 1
- **Tugas / Tujuan Utama:** Menyelesaikan fondasi arsitektur Fase 0 (`ksm-next`) dengan PHP 8.3 Clean Architecture, Dual-Connection ACL, Module Loader, Master Layout Shell paritas 100% `ksmlite`, SSE Engine, dan Hardening Keamanan sebelum memasuki Fase 1 (Pendaftaran & Antrean).

## 1. Apa yang Sudah Selesai (Done)
- [x] **PLN-01:** Inisialisasi Git, remote GitHub (`https://github.com/ksblklinik/medars.git`), clone pustaka referensi `.ref/ksmlite/` (di-ignore), 13 dokumen PRD (`00` s/d `12`), DDL oracle [`docs/database_schema_oracle.sql`](file:///c:/aplikasi/rea-mlite/docs/database_schema_oracle.sql), serta setup PHP 8.3 + PHPUnit 11.
- [x] **PLN-02:** `ConnectionFactory`, `PdoTransactionManager` (ACID + Nested Savepoints), dan `ReadOnlyConnectionGuard` (proteksi mutlak database legacy).
- [x] **PLN-03:** `ModuleManifest`, `DependencyResolver` (Topological Sort anti-circular), `ModuleLoader` lifecycle (`install`, `activate`, `deactivate`, `safe uninstall`), dan `PdoModuleRegistryRepository`.
- [x] **PLN-04:** Master Layout Shell Bootstrap 5 Medical Teal (`#00897b`), `ViewRenderer` (XSS-safe), *Dynamic Viewport Slot* (`#moduleViewportSlot`), *Single-Host Modal* (`#appModalHost`), Hotkeys (`F2`, `F4`, `F8`, `F9`, `Esc`), `Router`, dan `public/index.php`.
- [x] **PLN-05:** Real-Time Server-Sent Events (`EventMessage`, `InMemoryEventBroadcaster`, `SseStreamResponse`, endpoint `/api/stream`, dan `AppSSE` client dengan Triple-Gate).
- [x] **Hardening Fase 0:** Pengetatan `ReadOnlyConnectionGuard` (tahan komentar SQL, CTE mutasi, multi-statement, `INTO OUTFILE`, tanpa false-positive string literal). Seluruh **38 Test Cases (75 Assertions)** berstatus **100% PASS**.

## 2. Berkas & Baris Kode yang Aktif / Kunci
- [`AGENTS.md`](file:///c:/aplikasi/rea-mlite/AGENTS.md): Kontrak mutlak 5 pilar rekayasa, peta 13 PRD, dan mandat paritas visual 100%.
- [`.agents/ROADMAP.md`](file:///c:/aplikasi/rea-mlite/.agents/ROADMAP.md): Pelacak progress resmi (Fase 0 selesai, Fase 1 siap dikerjakan).
- [`src/Infrastructure/Database/ReadOnlyConnectionGuard.php`](file:///c:/aplikasi/rea-mlite/src/Infrastructure/Database/ReadOnlyConnectionGuard.php): Penjaga koneksi `legacy` read-only.
- [`src/Modules/Service/ModuleLoader.php`](file:///c:/aplikasi/rea-mlite/src/Modules/Service/ModuleLoader.php) & [`src/Modules/Infrastructure/PdoModuleRegistryRepository.php`](file:///c:/aplikasi/rea-mlite/src/Modules/Infrastructure/PdoModuleRegistryRepository.php): Inti manajemen modul (PRD 12).
- [`src/Presentation/View/templates/layout.php`](file:///c:/aplikasi/rea-mlite/src/Presentation/View/templates/layout.php) & [`public/assets/js/medars-app.js`](file:///c:/aplikasi/rea-mlite/public/assets/js/medars-app.js): Master shell anti tumpuk-tumpuk *hidden-show*.

## 3. Keputusan Arsitektur & Konteks Kritis (Decisions & Constraints)
- **Stack Terpilih:** PHP 8.3 Modern (`declare(strict_types=1);`, Native PDO, PHPUnit 11) + Bootstrap 5 + SSE (Server-Sent Events) pengganti WebSocket.
- **Database Strategy (Dual-Connection + ACL):** Koneksi `legacy` bersifat **READ-ONLY (`SELECT`)** ke database lama; koneksi `next` untuk skema bersih baru menuju migrasi 3 fase (Permenkes 24/2022).
- **UI/UX Mandate:** Tampilan mata harus **semirip mungkin dengan `ksmlite`** (*0-day learning curve*), namun di balik layar bebas dari tumpukan `display:none` berkat `#moduleViewportSlot` dan `#appModalHost`.
- **Catatan Lingkungan Windows Sandbox:** File `vendor/phpunit/phpunit/src/TextUI/Application.php` baris 339 diubah lokal (`is_readable` ➔ `file_exists`) agar PHPUnit berjalan di dalam sandbox Windows (folder `vendor/` ter-gitignore).

## 4. Langkah Selanjutnya (Next Actionable Steps)
1. [ ] Mulai **FASE 1 — PLN-06 (`docs/prd/01_registrasi_dan_antrean.md`)**: Tulis Unit Test TDD (RED Phase) untuk Value Objects `NoRkmMedis` (leading zeros `'000123'`) dan `NoRawat` (`'YYYY/MM/DD/000001'`), serta Entity `Pasien` & `RegPeriksa`.
2. [ ] Lanjutkan ke **PLN-07**: Implementasi Repository & mekanisme auto-regenerasi `no_rawat` saat terjadi race condition pendaftaran konkuren.
