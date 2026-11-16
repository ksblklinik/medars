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

## 🎯 Status Proyek: SEMUA FASE 0 s/d FASE 8 SELESAI (100% COMPLETED)

- [x] **PLN-34: Multi-Table ACID Transaction Audit & Data Integrity Checkers (TDD)** ✅ *(SELESAI — 244 tests, 972 assertions PASS)*
  - `DataIntegrityAuditService` (deteksi orphan records multi-tabel anak vs `reg_periksa`, audit konsistensi saldo split billing 4-pilar, verifikasi konsistensi stok `gudangbarang` vs mutasi `riwayat_barang_medis`).
- [x] **PLN-35: OWASP Hardening (Strict CSRF, SQLi Parameterization, Scoped XSS, Header Isolation) (TDD)** ✅ *(SELESAI)*
  - Strict parameterization pada seluruh kueri SQL, XSS escaping & `escapeHtml`, `Content-Security-Policy` & `Permissions-Policy` security headers, serta `LogRotationService` pemangkas log kadaluarsa.
- [x] **PLN-36: Verification & Final Polish across Linux/MySQL Staging Compatibility** ✅ *(SELESAI)*
  - `AuditController`, `templates/audit/index.php`, `public/assets/js/modules/audit.js`, rute `/audit`, `/api/v1/audit/*`, dan E2E `testAuditIntegrityAndLogRotationEndToEnd`. Seluruh 244 pengujian PHPUnit lulus 100%.

---

## ⏳ Rencana Tertunda / Antrean Kerja (PENDING)

| Kode | Scope / Tindak Lanjut | Catatan Status |
| :--- | :--- | :--- |
| **STG-01** | Uji Integrasi MySQL 8.0 & Linux Staging | Semua 253 test saat ini berjalan di SQLite in-memory; DDL & query belum diuji di instance MySQL 8.0 nyata. |
| **STG-02** | OAuth2 SatuSehat & Dekripsi AES+LZString BPJS (T3/T4) | Menunggu kredensial sandbox & acuan implementasi dari `ksmlite`. |
| **STG-03** | Verifikasi Paritas Visual Modul Baru vs `ksmlite` | Cek layout & ergonomi UI Fase 5–8 terhadap `.ref/ksmlite/`. |

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
| **PLN-11** | Value Objects & Entities RME, Triase, Odontogram | `TandaVital`, `Kesadaran`, `PemeriksaanRalan`, `PemeriksaanRanap` (composite key `(no_rawat, tgl_perawatan, jam_rawat)` & sanitasi MySQL 8.0 Strict Mode), `RmeMetadata` (*Smart Peek Shield*), `DiagnosaPasien`, `TriaseIgd`, `Odontogram` (FDI + `UNE`), dan `SuratMedis`. 8 test PASS. | 16-11-2026 |
| **PLN-12** | Repository Dual-Connection RME & ICD-10 | `PdoPemeriksaanRalanRepository`, `PdoPemeriksaanRanapRepository` (murni DML anti-MDL), `PdoRmeHistoryRepository` (*Smart Peek* `next` + opsional `ReadOnlyConnectionGuard` `legacy` bebas N+1), `PdoIcdGroundingRepository` (SatuSehat tanpa blokir SKDI), `PdoOdontogramRepository`, dan `PdoSuratMedisRepository`. 6 test PASS. | 16-11-2026 |
| **PLN-13** | Use-Case SOAP, PRB 30 Hari, Odontogram & Surat | DTO Whitelisting (`SaveSoapCommand`, `SaveCpptRanapCommand`, `SavePrbCommand`), `SaveSoapUseCase` (anti-spoofing `nip`, anti-IDOR poli, H-1/multi-visite `'Sudah'`, *Smart Peek Shield*), `SavePrbPrescriptionUseCase` (triplet alignment tanpa potong stok), `SaveCpptRanapUseCase`, `DeleteOdontogramUseCase` (PK `id`), & `CreateSuratMedisUseCase` (penomoran atomik). 6 test PASS. | 16-11-2026 |
| **PLN-14** | UI/UX RME Dinamis & Smart Peek Dokter Poli | `DokterRalanController`, `dokter_ralan/index.php` & `dokter-ralan.js` (split view Antrean + Form SOAP + *Smart Peek*, ICD-10 debounce `200ms` + `AbortController`, PRB 30 hari, Odontogram, Surat Sakit, SSE kanal dokter, query param `?no_rawat=...`). 3 test PASS (Total 177 test, 598 assertions PASS). **FASE 2 SELESAI 100%**. | 16-11-2026 |
| **PLN-15** | Value Objects & Entities Farmasi SSOT | `UniformPricing` (flat pricing & Smart Claim Sorting BPJS tanpa gate `kd_pj`), `DepoBangsal` (`IGD`/`APT`/Induk), `NoResep`, `JenisKlaim`, `DataBarang`, `GudangBarang` (anti-stok-negatif), `ResepObat`, `ResepDokterNonRacikan`, `ResepDokterRacikan`, & `DetailPemberianObat` (composite key 6-kolom). 5 test PASS. | 16-11-2026 |
| **PLN-16** | Migrasi & Repository Farmasi Multi-Depo | Penambahan 9 tabel farmasi ke `SchemaMigrator` (MySQL 8.0 `VARCHAR` keys), `PdoDataBarangRepository` (mendukung keyword pendek `B6`/`Zn`), `PdoGudangBarangRepository` (isolasi depo & split batch atomik + `riwayat_barang_medis`), dan `PdoResepRepository`. 4 test PASS. | 16-11-2026 |
| **PLN-17** | Use-Case Validasi E-Resep, Racikan +1s & Copy | `CreateResepUseCase` (`YYYYMMDD0001` atomik), `DispenseResepUseCase` (offset `+1 detik` via `DateTimeImmutable::modify('+1 second')` aman pada `23:59:59`, rollback ACID bila stok kurang), `DeleteResepUseCase` (blokir resep sudah diserahkan), & `SaveCopyResepUseCase`. 3 test PASS. | 16-11-2026 |
| **PLN-18** | UI/UX E-Resep & Validasi Apotek | `PharmacyController`, `pharmacy/index.php` & `pharmacy.js` (antrean E-Resep, pencarian obat debounce `200ms` + `AbortController`, `data-selected_klaim`, validasi/hapus resep), rute `/farmasi` & E2E integration test di `HttpApplicationTest`. 2 test PASS (Total 192 test, 682 assertions PASS). **FASE 3 SELESAI 100%**. | 16-11-2026 |
| **PLN-19** | Value Objects & Entities Billing & Akuntansi | `ClaimType` (4-Pilar: `Tunai`, `Kapitasi`, `Non-Kapitasi`, `Piutang`), `LedgerStatus`, `PosPaymentMethod` (5 cara bayar), `BillingLedgerItem`, `JurnalEntry`/`JurnalDetail` (seimbang & `createReversal()`), `KamarInap`, & `PosApotekUmumSale` (`PO-APT-%`). 4 test PASS. | 16-11-2026 |
| **PLN-20** | Migrasi & Repository Kasir/Ranap/POS | Penambahan 6 tabel billing/jurnal/ranap/POS ke `SchemaMigrator` (MySQL 8.0 `VARCHAR` keys), `PdoBillingLedgerRepository`, `PdoKamarInapRepository` (mengimplementasikan `RanapStatusCheckerInterface`), & `PdoPosApotekUmumRepository`. 3 test PASS. | 16-11-2026 |
| **PLN-21** | Use-Case Split Billing, Void & POS Apotek | `ProcessSplitPaymentUseCase` (isolasi `1101.01` hanya untuk porsi `Tunai`, BPJS murni Rp 0), `VoidBillingTransactionUseCase` (jurnal pembalik seimbang & blokir bila ada layanan klinis), `ProcessPosApotekUmumUseCase` (isolasi Depo `APT`), & `ReconcilePendingOrderUseCase`. 5 test PASS. | 16-11-2026 |
| **PLN-22** | UI/UX Kasir 4-Pilar & POS Apotek Umum | `BillingController`, `billing/index.php` & `billing.js` (Split Billing `[T]`/`[K]`/`[NK]`/`[P]`, hotkeys `F2`/`F8`/`F9`, Void stempel `FAKTUR / KUITANSI TIDAK BERLAKU`, POS Apotek Umum), rute `/kasir` & E2E integration test. 2 test PASS (Total 206 test, 766 assertions PASS). **FASE 4 SELESAI 100%**. | 16-11-2026 |
| **PLN-23..26** | Laboratorium, Radiologi & Laporan Operasional | Skema 6 tabel penunjang, pemisahan order `PL`/`PR` per pilar klaim + retry PK, kunci validasi kasir, evaluasi rentang rujukan otomatis (`Rendah`/`Tinggi`/`Normal`), hapus atomik + cancel ledger pending, `SupportController` & UI. | 16-11-2026 |
| **PLN-27..30** | Bridging BPJS, SatuSehat FHIR & HFIS LEAN | Circuit breaker 0ms, `TimeoutPolicy` (`<=2s`/`<=4s`), `HfisLeanCalculator`, `QueueSatuSehatSyncUseCase`, `ProcessBridgingQueueUseCase`, `VerifyVedikaClaimUseCase` (ACID tx), `BridgingController` & UI. | 16-11-2026 |
| **PLN-31..33** | WA Gateway CRM & Homecare Preceptorship | Dual-session `system` vs `cs`, whitelist non-admin, circuit breaker 3x gagal, webhook token + `wagateway_checkin` Zero-Reply, `HomeCarePrivacyShield`, validasi SK & guard DPJP otomatis, `CommunicationController` & UI. | 16-11-2026 |
| **PLN-34..36** | Audit ACID, Security Hardening & Log Rotation | `DataIntegrityAuditService` (orphan, billing balance, stok), `LogRotationService` (min 90 hari, `mlite_logs` immutable), CSP CDN/Fonts + Security Headers, `AuditController` & UI. | 16-11-2026 |
