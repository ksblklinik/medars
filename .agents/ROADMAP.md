# 🗺️ Pelacak Roadmap & Status Eksekusi SIMRS `ksm-next`

Dokumen ini adalah Single Source of Truth (SSOT) pelacakan progres pengembangan bertahap **ksm-next** sesuai protokol [`.agents/skills/git-progress-handoff/SKILL.md`](file:///c:/aplikasi/rea-mlite/.agents/skills/git-progress-handoff/SKILL.md).

---

## 🚧 Sedang Dikerjakan (IN PROGRESS)

### [FASE-0] Fondasi Kernel, Database & Layout Shell
- [x] **PLN-01: Inisialisasi Repositori Git & PHP 8.3 TDD Harness** (Selesai: 16-11-2026)
  - Remote origin GitHub terhubung: `https://github.com/ksblklinik/medars.git`.
  - PHPUnit 11 terpasang dan lolos pengujian smoke test.
  - Mandat paritas visual 100% dan eliminasi hidden-show tercatat di PRD 10 & AGENTS.md.
- [x] **PLN-02: Dual-Connection, Transaction Manager & ACL Adapter (PRD 00 & PRD 09)** (Selesai: 16-11-2026)
  - Dual-Connection: `legacy` (Read-Only SELECT ke database lama) vs `next` (Read-Write ke database baru).
  - PdoTransactionManager: Transaksi atomik ACID + Nested Savepoints (`SAVEPOINT sp_n`) lolos pengujian TDD.
  - ReadOnlyConnectionGuard: Proteksi mutlak database legacy (memblokir seluruh mutasi INSERT, UPDATE, DELETE, DROP, ALTER).
  - ConnectionFactory & ConnectionConfig: Strict PDO parameters (`ATTR_EMULATE_PREPARES => false`, UTF8MB4).
- [x] **PLN-03: Module Loader & Registry Kernel (PRD 12)** (Selesai: 16-11-2026)
  - `ModuleManifest` (validasi strict slug kebab-case & semver).
  - `DependencyResolver` (Topological sort & deteksi circular dependency).
  - `ModuleLoader` lifecycle (`install`, `activate`, `deactivate`, `safe uninstall`).
  - Dinamisasi navigasi menu antarmuka dari modul-modul aktif.
  - Lolos 8 skenario pengujian TDD Unit Test.
- [x] **PLN-04: Master Layout Shell Paritas Eksisting (PRD 10)** (Selesai: 16-11-2026)
  - Layout master Bootstrap 5 dengan warna khas Medical Teal (`#00897b`).
  - Topbar jam real-time & indikator status SSE.
  - Menu ikon kotak cepat atas (`.menu-icon`) identik dengan `ksmlite`.
  - Dynamic Viewport Slot (`#moduleViewportSlot`) untuk eliminasi tumpukan hidden-show.
  - Single-Host Dynamic Modal (`#appModalHost`) dengan auto-focus & cleanup DOM.
  - Hotkeys operasional klinik (`F2`, `F4`, `F8`, `F9`, `Esc`).
  - `ViewRenderer` dengan sanitasi XSS aman lolos pengujian TDD.
- [x] **PLN-05: Real-Time SSE Stream Engine** (Selesai: 16-11-2026)
  - `EventMessage` value object (event, data payload, event-id, retry).
  - `InMemoryEventBroadcaster` channel pub/sub publisher.
  - `SseStreamResponse` formatter sesuai standar W3C (headers, event, ping heartbeat).
  - Lolos 5 skenario pengujian TDD Unit Test.

---

## 🚧 Sedang Dikerjakan (IN PROGRESS)

### [FASE-1] Pendaftaran, Pasien & Kiosk Antrean (PRD 01)
- [x] **PLN-06: Entity & Value Objects Pasien & Registrasi (PRD 00 & PRD 01)** (Selesai & Hardened: 16-11-2026)
  - `NoRkmMedis` (string format leading zeros `000123`), `NoRawat` (`YYYY/MM/DD/000001`), `NoReg` (`001`, validasi digit, penolakan '000').
  - `PoliklinikSetting` (aturan data-driven `allowBpjs` per poli, tanpa hardcode `PEX`).
  - `Pasien` (perhitungan umur `Th`/`Bl`/`Hr`, proteksi tanggal lahir di masa depan) & `RegPeriksa` (immutable entity dengan atribut `prefik`, `room`, `shift`).
  - `BookingRegistrasi` (tracking status Belum/Terdaftar/Batal, validasi tanggal hari ini vs masa depan).
- [x] **PLN-07: Repository Dual-Connection ACL & Konkurensi Penomoran (TDD)** (Selesai: 16-11-2026)
  - Pencarian pasien lintas koneksi (`legacy` Read-Only via `ReadOnlyConnectionGuard` + `next` Read-Write).
  - Generator `no_rkm_medis` lintas DB (`MAX(legacy, next) + 1`) anti-tabrakan.
  - In-Site Queue Generator dengan reservasi antrean Mobile JKN (`MAX(reg_periksa, booking_registrasi) + 1`).
  - Auto-regenerasi atomik untuk **`no_rawat` DAN `no_reg`** (`tgl_registrasi + kd_poli + kd_dokter + shift`) saat race condition insert.
  - Deteksi tunggakan biaya kunjungan sebelumnya (`hasUnpaidPreviousVisit`) & guard double-submit (`hasActiveRegistrationToday`).
  - Clinical Guard check (`hasClinicalRecords` mendeteksi SOAP, tindakan medis/paramedis, resep obat, lab, radiologi).
- [x] **PLN-08: Use-Case Dua Jalur (In-Site vs Mobile JKN Check-In) & Clinical Guard (TDD)** (Selesai: 16-11-2026)
  - Jalur A (In-Site Walk-In): Reservasi `no_reg` terhadap `booking_registrasi`, validasi pengaturan izin penjamin poli, blokir tunggakan kasir kunjungan sebelumnya, idempotency guard (anti double-submit), dan routing otomatis tanggal masa depan ke booking.
  - Jalur B (Mobile JKN Check-In): Validasi tanggal hari ini & status booking belum terdaftar, eksekusi transaksi atomik konversi ke `reg_periksa` dengan mempertahankan `no_reg` asli dari booking, dan penanganan dokter pengganti.
  - Clinical Guard Shield: Pencegahan manipulasi antrean/dokter bagi pasien yang sudah tercatat SOAP dokter, tindakan medis/paramedis, resep obat, atau order penunjang, serta pengalihan otomatis tindakan awal konsultasi ke dokter pengganti.
- [x] **PLN-09: Tampilan Form Registrasi & Modal Cari Pasien F4 Identik ksmlite (TDD)** (Selesai: 16-11-2026)
  - Layout paritas 100% dua kolom: panel `#form` (Medical Teal header `#00897b`, field poliklinik/dokter/penjamin, display tarif) dan panel `#display` (tabel kunjungan hari ini, status badge `bg-belum`, `bg-awal`, `bg-pending`, `bg-selesai`, `bg-skip`).
  - Modal pencarian pasien `F4` Single-Host (`#appModalHost`) dengan live AJAX search (No. RM / Nama / NIK / No. BPJS) dan auto-populate form.
  - Hotkeys operasional: <kbd>F2</kbd> (Daftarkan Pasien), <kbd>F4</kbd> (Buka Modal Cari Pasien), <kbd>F9</kbd> (Kosongkan Form), dan <kbd>Esc</kbd> (Tutup Modal).
  - Controller API: `/pendaftaran`, `/api/v1/registration/patients/search`, `/api/v1/registration/insite`, `/api/v1/registration/checkin`, `/api/v1/registration/today`, `/api/v1/registration/shift-queue`.
- [x] **PLN-10: Display TV Antrean & Kiosk Mandiri Real-Time SSE (TDD)** (Selesai: 16-11-2026)
  - Display TV Antrean Poliklinik Full-HD / 4K responsif (`/display-antrean`, `/antrean`) tanpa vertical scrolling, large queue font-monospace, audio chime dan pengumuman suara Web Speech Synthesis API bahasa Indonesia otomatis saat antrean dipanggil (`queue.called`).
  - Layar Sentuh Kiosk Mandiri (`/kiosk`, `.kiosk-wrapper`) dengan virtual keypad layar sentuh, check-in cepat Mobile JKN, formulir Persetujuan Umum (*General Consent*) mandiri dengan digital signature canvas, serta simulasi cetak tiket antrean.
  - Controller API: `/api/v1/queue/summary` (ringkasan antrean aktif & menunggu per poli) dan `/api/v1/queue/call` (siaran SSE panggilan antrean real-time).
  - **FASE 1 (Pendaftaran, Pasien & Kiosk Antrean) TUNTAS 100% (86 Test PASS, 224 Assertions).**

---

## 🎯 Fase Aktif Saat Ini: FASE-2 (Rekam Medis Elektronik, SOAP Dinamis, IGD/Ranap & Surat Medis)

- [x] **PLN-10E: Pra-Fase 2 Hardening, Migrasi Skema & Wiring Integrasi (TDD)** (Selesai: 16-11-2026)
  - `SchemaMigrator` & `InitialSeeder` lintas-driver (`VARCHAR` keys MySQL 8.0 `utf8mb4_unicode_ci` & SQLite `PRAGMA foreign_keys = ON`), CLI `database/migrate.php`, serta refactor portabilitas `PdoJadwalRepository` & `PdoSettingRepository`.
  - `HttpApplication` dengan Global Exception Boundary, security headers, `NavigationBuilder` SSOT ber-RBAC, pemisahan rute publik Kiosk (`POST /api/v1/kiosk/checkin`, `GET /api/stream`) vs rute terproteksi Sesi/RBAC/CSRF, anti-IDOR kanal SSE dokter, serta validasi tanggal ketat anti-rollover.
  - Terverifikasi 154 tests, 508 assertions (termasuk 6 Integration Test E2E `HttpApplicationTest` PASS).
- [ ] **PLN-11: Value Objects, Entities RME, Triase & Odontogram (TDD)**
  - Entitas `PemeriksaanRalan` dan `PemeriksaanRanap` dengan composite primary key `(no_rawat, tgl_perawatan, jam_rawat)` serta sanitasi nilai default MySQL 8.0 Strict Mode (`'-'` untuk kolom teks kosong, `'Compos Mentis'` untuk kesadaran).
  - Value Object `TandaVital` (Suhu, TD Sistole/Diastole, Nadi, Respirasi, Tinggi, Berat, SpO2, GCS, Lingkar Perut) dan enum `Kesadaran`.
  - Entitas `RmeMetadata` (`mlite_pemeriksaan_ralan_metadata`, flag `isMetaDirty` untuk *Smart Peek Shield*), `DiagnosaPasien` (primer/sekunder), `TriaseIgd` (ATS/WHO), `Odontogram` (`mlite_odontogram` kode FDI 11–48 & status `UNE`), serta `SuratMedis`.
- [ ] **PLN-12: Repositories Dual-Connection RME, Smart Peek & Grounding ICD-10 (TDD)**
  - `PdoPemeriksaanRalanRepository` & `PdoPemeriksaanRanapRepository`: murni DML tanpa DDL runtime (mencegah *Metadata Lock* & *implicit commit* MySQL 8.0).
  - `PdoRmeHistoryRepository` (*Smart Peek*): pembacaan gabungan Dual-Connection (`next` + opsional `?ReadOnlyConnectionGuard $legacyGuard = null` konsisten dengan `PdoPasienRepository`, fallback mulus jika `legacy` belum terhubung) dengan query JOIN/batch bebas N+1 dan mempertahankan 100% histori kunjungan pasien.
  - `PdoIcdGroundingRepository`: grounding kode ICD-10 murni terhadap katalog `penyakit` SatuSehat Kemenkes + Knowledge Vault (`mlite_vault_nodes`, `mlite_vault_edges`, `mlite_vault_icd10_map`) tanpa memblokir diagnosa berdasarkan tingkat SKDI.
- [ ] **PLN-13: Use-Case SOAP (Ralan/IGD/Ranap), Resep PRB 30 Hari, Odontogram & Surat Medis (TDD)**
  - **DTO Whitelisting Boundary (`PRD 09`):** Seluruh input payload (`SaveSoapCommand`, `SaveCpptRanapCommand`, `SavePrbCommand`, `SaveTriaseIgdCommand`) divalidasi melalui validator DTO ketat yang menolak field liar (*unwhitelisted fields*) di pintu masuk sebelum menyentuh Use-Case.
  - `SaveSoapUseCase` (Ralan/IGD) & `SaveCpptRanapUseCase` (Rawat Inap): simpan/update SOAP dokter (`kd_dokter`) & perawat (`nip`) dalam 1 transaksi ACID dengan **identitas pemeriksa (`nip`) wajib diikat dari sesi user yang terautentikasi (anti-spoofing)** serta **validasi cakupan poliklinik (`RbacGuard::assertCanAccessPoli` terhadap `reg_periksa.kd_poli`) anti-IDOR lintas-poli**; untuk Ralan/IGD izinkan lintas hari maksimal `H-1` selama status bukan `'Batal'` (termasuk `'Belum'`, `'Awal'`, `'Berkas Diterima'`, dan **`'Sudah'`** agar dokter tetap dapat menambah/mengoreksi catatan SOAP multi-visite di hari yang sama), untuk Ranap izinkan multi-visite selama pasien belum pulang (`RanapStatusCheckerInterface`); tegakkan *Smart Peek Shield* (`isMetaDirty === false` atau `'{}'`) dan transisi status `reg_periksa.stts` ke `'Sudah'`.
  - `SaveTriaseIgdUseCase`: pencatatan skala kegawatdaruratan ATS/WHO (Resusitasi, Emergensi, Urgensi, Non-Urgent) terikat ke kunjungan IGD.
  - `SavePrbPrescriptionUseCase`: cari triplet `pemeriksaan_ralan` aktif hari ini atau auto-create record dasar (nilai sanitasi default `'-'`) dalam transaksi ACID lalu kembalikan triplet `(tgl_perawatan, jam_rawat)` agar form SOAP utama tidak membuat baris duplikat; catat resep kronis PRB 30 hari di metadata `meta_prb` & sinkronkan ke Planning (`rtl`) SOAP **tanpa memotong stok fisik gudang (`gudangbarang`)**.
  - `SaveOdontogramUseCase` & `DeleteOdontogramUseCase`: penghapusan berbasis Primary Key `id` (mengizinkan dokter jaga pengganti mengoreksi gigi sembuh).
  - `CreateSuratMedisUseCase`: pembuatan & penomoran urut atomik (anti-tabrakan nomor surat lintas poli dalam transaksi) untuk Surat Sakit, Surat Hamil/Cuti Hamil, Surat Bebas Narkoba, dan Rujukan Internal.
- [ ] **PLN-14: UI/UX RME Dinamis, Smart Peek & Mitigasi Lag Frontend (Paritas ksmlite)**
  - Antarmuka SOAP split view paritas `ksmlite` (tab Subjektif/Objektif/Asesmen/Planning, form spesifik poli KIA/Kronis/TB/Gigi, odontogram SVG interaktif, modal cetak surat medis & PRB). Seluruh tautan & endpoint API yang membawa `no_rawat` (`YYYY/MM/DD/000001`) **wajib menggunakan query parameter `?no_rawat=...` atau payload JSON** (dilarang menaruh `no_rawat` sebagai path segmen mentah karena karakter `/` akan memecah routing).
  - Pencarian AJAX ICD-10, tindakan, dan obat menggunakan debounce `200ms` + `AbortController` pembatalan request aktif (anti-lag), scoped dropdown initialization (tanpa destroy/re-init global), serta listener SSE kanal dokter.

---

## ⏳ Rencana Tertunda / Antrean Kerja (PENDING)

| Kode Fase | Domain / Modul Utama | PRD Rujukan | Target Luaran Kunci |
| :--- | :--- | :--- | :--- |
| **FASE-2** | RME, SOAP (Ralan/IGD/Ranap) & Surat Medis | [`PRD 02`](file:///c:/aplikasi/rea-mlite/docs/prd/02_rekam_medis_dan_soap.md) | Composite key `pemeriksaan_ralan`/`ranap`, Triase IGD, Form SOAP dinamis, Grounding ICD-10, Medical History Smart Peek, dan Cetak Surat Medis (`surat`). |
| **FASE-3** | E-Resep, Gudang Farmasi & Apotek | [`PRD 03`](file:///c:/aplikasi/rea-mlite/docs/prd/03_farmasi_dan_e_resep.md) | Resep non-racik vs racik (Ralan/IGD/Ranap), Offset jam racikan `+1 detik`, mutasi stok `gudangbarang`, isolasi `PO-APT-%`. |
| **FASE-4** | Kasir, Billing Ledger, Akuntansi & Rawat Inap | [`PRD 04`](file:///c:/aplikasi/rea-mlite/docs/prd/04_kasir_billing_dan_akuntansi.md) | Split Billing 4-Pilar (`[T]`, `[K]`, `[NK]`, `[P]`) untuk Ralan/IGD/Ranap (`kamar_inap`), Jurnal Keuangan anti-delete void, Hotkeys Kasir (`F2`,`F8`,`F9`). |
| **FASE-5** | Laboratorium, Radiologi & Laporan Operasional | [`PRD 05`](file:///c:/aplikasi/rea-mlite/docs/prd/05_laboratorium_dan_radiologi.md) | Order penunjang dari RME dokter, input hasil & nilai rujukan, posting tarif otomatis ke billing, serta Laporan Kunjungan, 10 Besar Penyakit & Pendapatan. |
| **FASE-6** | Bridging BPJS & SatuSehat | [`PRD 07`](file:///c:/aplikasi/rea-mlite/docs/prd/07_bridging_bpjs_dan_satusehat.md) | Zero-Latency Circuit Breaker, Antrean BPJS HFIS, VClaim/PCare, Verifikasi Klaim (`vedika`), dan Bundle FHIR SatuSehat Kemenkes. |
| **FASE-7** | WA Gateway CRM & Homecare PWA | [`PRD 06`](file:///c:/aplikasi/rea-mlite/docs/prd/06_whatsapp_gateway_crm.md), [`PRD 08`](file:///c:/aplikasi/rea-mlite/docs/prd/08_homecare_dan_pendidikan.md) | WA Dual-Session (`system` vs `cs`) anti-banned whitelist, Homecare PWA mandiri & Trias Preceptorship. |
| **FASE-8** | Audit ACID & Hardening Linux Staging | [`PRD 11`](file:///c:/aplikasi/rea-mlite/docs/prd/11_audit_acid_dan_keamanan.md) | Audit integritas multi-tabel, OWASP hardening (SQLi, XSS, CSRF), Uji drop-in di Linux Staging MySQL 8.0. |

---

## ✅ Riwayat Rencana Selesai (COMPLETED)

| ID | Modul / Scope | Rangkuman Hasil Eksekusi | Tanggal Selesai |
| :--- | :--- | :--- | :--- |
| **PLN-01** | Fondasi Repositori & Spesifikasi | Inisialisasi Git, remote GitHub, 13 PRD domain, DDL oracle database, clone pustaka referensi `.ref/ksmlite/`, konfigurasi PHP 8.3 & PHPUnit 11 test harness. | 16-11-2026 |
| **PLN-02** | Dual-Connection & Transaction Manager | Implementasi `PdoTransactionManager` (ACID + Nested Savepoints), `ReadOnlyConnectionGuard` (proteksi mutlak database eksisting), `ConnectionFactory` (strict flags & anti-cast), terverifikasi 13 unit test PASS. | 16-11-2026 |
| **PLN-03** | Module Loader & Registry Kernel | Implementasi arsitektur modular PRD 12 (`ModuleManifest`, `DependencyResolver`, `ModuleLoader` lifecycle, menu navigation dinamis, safe uninstall), terverifikasi 8 unit test PASS (Total 21 test PASS). | 16-11-2026 |
| **PLN-04** | Master Layout Shell Paritas 100% | Master layout Bootstrap 5 bertema Medical Teal (`#00897b`), Topbar, menu-icon, `ViewRenderer` XSS-safe, Dynamic Viewport Slot (`#moduleViewportSlot`), Single-Host Modal (`#appModalHost`), dan hotkeys operasional (`F2`-`F9`). Total 23 test PASS. | 16-11-2026 |
| **PLN-05** | Real-Time SSE Stream Engine | Implementasi engine Server-Sent Events (`EventMessage`, `InMemoryEventBroadcaster`, `SseStreamResponse`, heartbeat ping, SSE headers W3C), terverifikasi 5 test PASS (Total 28 test PASS). Seluruh Fase 0 Tuntas 100%. | 16-11-2026 |
| **PLN-06** | Value Objects & Entities Registrasi | Implementasi `NoRkmMedis`, `NoRawat`, `NoReg` (digit validation & anti-000), `Pasien` (umur SIMRS & guard lahir masa depan), `PoliklinikSetting` (data-driven penjamin), `RegPeriksa` (`prefik`/`room`/`shift`), dan `BookingRegistrasi`. 13 test PASS. | 16-11-2026 |
| **PLN-07** | Repository Dual-Connection ACL & Concurrency | `PdoPasienRepository` (fallback legacy Read-Only via `ReadOnlyConnectionGuard`, `getNextNoRkmMedis` lintas DB), `PdoBookingRegistrasiRepository`, `PdoRegPeriksaRepository` (reservasi antrean Walk-In vs Mobile JKN, auto-regenerasi atomik `no_rawat` & `no_reg`, clinical guard check). 11 test PASS (Total 62 test PASS). | 16-11-2026 |
| **PLN-08** | Use-Case Dua Jalur Registrasi & Clinical Guard | `RegisterInSiteUseCase` (Walk-In, validasi tarif/umur/penjamin, guard tunggakan & double-submit, route booking masa depan), `CheckInMobileJknUseCase` (preservasi `no_reg` booking asli, ACID tx konversi `Terdaftar`, dokter pengganti), `ClinicalGuardShieldUseCase` (blokir ubah antrean berstatus SOAP/tindakan/resep/lab/rad). 11 test PASS (Total 73 test PASS). | 16-11-2026 |
| **PLN-09** | Form Registrasi & Modal Pasien F4 | Paritas 100% tampilan visual & ergonomi ksmlite (Medical Teal `#00897b`, panel `#form` & `#display`), modal cari pasien `F4` Single-Host (`#appModalHost`), hotkeys <kbd>F2</kbd>, <kbd>F4</kbd>, <kbd>F9</kbd>, endpoint REST API lengkap. 9 test PASS (Total 82 test PASS). | 16-11-2026 |
| **PLN-10** | Display TV Antrean & Kiosk Mandiri | Layar Display TV Full-HD/4K audio chime & speech synthesis bahasa Indonesia real-time SSE (`/display-antrean`), Layar Kiosk Mandiri (`/kiosk`) virtual keypad & General Consent digital signature, endpoint `/api/v1/queue/summary` & `/api/v1/queue/call`. 4 test PASS (Total 86 test PASS). **FASE 1 SELESAI 100%**. | 16-11-2026 |
| **PLN-10A** | Autentikasi, Keamanan Sesi, CSRF & RBAC Middleware | `UserAccount` entity, `SessionAuthService` (rate-limiting 5x gagal, mitigasi session fixation via regenerate ID, audit log `mlite_logs`), `CsrfGuard` (token per sesi & validasi ketat POST), `RbacGuard` (role & cap poli). 20 test PASS. | 16-11-2026 |
| **PLN-10B** | Manajemen Pengguna & Kepegawaian SDM | `PdoUserRepository` (CRUD `mlite_users` + Anti-Lockout superadmin ID 1 & self-delete), `PdoDokterRepository`, `PdoPetugasRepository`, `PdoPegawaiRepository` dengan *Soft-Deactivation* (`status = '0'`) menjaga integritas FK rekam medis lampau. 13 test PASS. | 16-11-2026 |
| **PLN-10C** | Master Data Klinik, Jadwal Praktik & Penjamin | `PdoPenjabRepository`, `PdoJadwalRepository` dengan `ScheduleOverlapGuard` (deteksi tabrakan waktu multi-shift dokter pada hari yang sama), `PdoJenisPerawatanRepository`. 7 test PASS. | 16-11-2026 |
| **PLN-10D** | Pengaturan Sistem & UI/UX Administrasi | `SettingService` & `PdoSettingRepository` (`mlite_settings` key-value per modul ber-cache in-memory), `PdoAuditLogRepository` (`mlite_logs` viewer & filter), `AuthController` (login/logout view), `SettingsController` & `UsersController` (Medical Teal UI parity 99%). 12 test PASS (Total 143 test PASS). **FASE 1B SELESAI 100%**. | 16-11-2026 |
| **PLN-10E** | Pra-Fase 2 Hardening, Migrasi & Wiring E2E | `SchemaMigrator` & `InitialSeeder` lintas-driver (MySQL 8.0 `VARCHAR` keys & SQLite `PRAGMA foreign_keys = ON`), `HttpApplication` dengan Exception Boundary & Security Headers, `NavigationBuilder` SSOT ber-RBAC, pemisahan Kiosk vs RBAC API + `X-CSRF-Token` + SSE dokter, serta 6 Integration Test E2E (Total 154 test PASS). | 16-11-2026 |

---

## ✅ Riwayat Rencana Selesai (COMPLETED)

| ID | Modul / Scope | Rangkuman Hasil Eksekusi | Tanggal Selesai |
| :--- | :--- | :--- | :--- |
| **PLN-01** | Fondasi Repositori & Spesifikasi | Inisialisasi Git, remote GitHub, 13 PRD domain, DDL oracle database, clone pustaka referensi `.ref/ksmlite/`, konfigurasi PHP 8.3 & PHPUnit 11 test harness. | 16-11-2026 |
| **PLN-02** | Dual-Connection & Transaction Manager | Implementasi `PdoTransactionManager` (ACID + Nested Savepoints), `ReadOnlyConnectionGuard` (proteksi mutlak database eksisting), `ConnectionFactory` (strict flags & anti-cast), terverifikasi 13 unit test PASS. | 16-11-2026 |
| **PLN-03** | Module Loader & Registry Kernel | Implementasi arsitektur modular PRD 12 (`ModuleManifest`, `DependencyResolver`, `ModuleLoader` lifecycle, menu navigation dinamis, safe uninstall), terverifikasi 8 unit test PASS (Total 21 test PASS). | 16-11-2026 |
| **PLN-04** | Master Layout Shell Paritas 100% | Master layout Bootstrap 5 bertema Medical Teal (`#00897b`), Topbar, menu-icon, `ViewRenderer` XSS-safe, Dynamic Viewport Slot (`#moduleViewportSlot`), Single-Host Modal (`#appModalHost`), dan hotkeys operasional (`F2`-`F9`). Total 23 test PASS. | 16-11-2026 |
| **PLN-05** | Real-Time SSE Stream Engine | Implementasi engine Server-Sent Events (`EventMessage`, `InMemoryEventBroadcaster`, `SseStreamResponse`, heartbeat ping, SSE headers W3C), terverifikasi 5 test PASS (Total 28 test PASS). Seluruh Fase 0 Tuntas 100%. | 16-11-2026 |
| **PLN-06** | Value Objects & Entities Registrasi | Implementasi `NoRkmMedis`, `NoRawat`, `NoReg` (digit validation & anti-000), `Pasien` (umur SIMRS & guard lahir masa depan), `PoliklinikSetting` (data-driven penjamin), `RegPeriksa` (`prefik`/`room`/`shift`), dan `BookingRegistrasi`. 13 test PASS. | 16-11-2026 |
| **PLN-07** | Repository Dual-Connection ACL & Concurrency | `PdoPasienRepository` (fallback legacy Read-Only via `ReadOnlyConnectionGuard`, `getNextNoRkmMedis` lintas DB), `PdoBookingRegistrasiRepository`, `PdoRegPeriksaRepository` (reservasi antrean Walk-In vs Mobile JKN, auto-regenerasi atomik `no_rawat` & `no_reg`, clinical guard check). 11 test PASS (Total 62 test PASS). | 16-11-2026 |
| **PLN-08** | Use-Case Dua Jalur Registrasi & Clinical Guard | `RegisterInSiteUseCase` (Walk-In, validasi tarif/umur/penjamin, guard tunggakan & double-submit, route booking masa depan), `CheckInMobileJknUseCase` (preservasi `no_reg` booking asli, ACID tx konversi `Terdaftar`, dokter pengganti), `ClinicalGuardShieldUseCase` (blokir ubah antrean berstatus SOAP/tindakan/resep/lab/rad). 11 test PASS (Total 73 test PASS). | 16-11-2026 |
| **PLN-09** | Form Registrasi & Modal Pasien F4 | Paritas 100% tampilan visual & ergonomi ksmlite (Medical Teal `#00897b`, panel `#form` & `#display`), modal cari pasien `F4` Single-Host (`#appModalHost`), hotkeys <kbd>F2</kbd>, <kbd>F4</kbd>, <kbd>F9</kbd>, endpoint REST API lengkap. 9 test PASS (Total 82 test PASS). | 16-11-2026 |
| **PLN-10** | Display TV Antrean & Kiosk Mandiri | Layar Display TV Full-HD/4K audio chime & speech synthesis bahasa Indonesia real-time SSE (`/display-antrean`), Layar Kiosk Mandiri (`/kiosk`) virtual keypad & General Consent digital signature, endpoint `/api/v1/queue/summary` & `/api/v1/queue/call`. 4 test PASS (Total 86 test PASS). **FASE 1 SELESAI 100%**. | 16-11-2026 |
