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
- [ ] **PLN-05: Real-Time SSE Stream Engine**
  - Endpoint `GET /api/stream` untuk push antrean dan notifikasi real-time.

---

## ⏳ Rencana Tertunda / Antrean Kerja (PENDING)

| Kode Fase | Domain / Modul Utama | PRD Rujukan | Target Luaran Kunci |
| :--- | :--- | :--- | :--- |
| **FASE-1** | Pendaftaran, Pasien & Kiosk Antrean | [`PRD 01`](file:///c:/aplikasi/rea-mlite/docs/prd/01_registrasi_dan_antrean.md) | Entity Pasien, Auto-Regenerate race condition `no_rawat`, Form Pendaftaran & Display TV Antrean SSE. |
| **FASE-2** | RME & SOAP Rawat Jalan | [`PRD 02`](file:///c:/aplikasi/rea-mlite/docs/prd/02_rekam_medis_dan_soap.md) | Composite key `pemeriksaan_ralan` (3 kolom), Form SOAP dinamis, Grounding ICD-10 & Medical History. |
| **FASE-3** | E-Resep, Gudang Farmasi & Apotek | [`PRD 03`](file:///c:/aplikasi/rea-mlite/docs/prd/03_farmasi_dan_e_resep.md) | Resep non-racik vs racik, Offset jam racikan `+1 detik`, mutasi stok `gudangbarang`, isolasi `PO-APT-%`. |
| **FASE-4** | Kasir, Billing Ledger & Akuntansi | [`PRD 04`](file:///c:/aplikasi/rea-mlite/docs/prd/04_kasir_billing_dan_akuntansi.md) | Split Billing 4-Pilar (`[T]`, `[K]`, `[NK]`, `[P]`), Jurnal Keuangan anti-delete void, Hotkeys Kasir (`F2`,`F8`,`F9`). |
| **FASE-5** | Laboratorium & Radiologi | [`PRD 05`](file:///c:/aplikasi/rea-mlite/docs/prd/05_laboratorium_dan_radiologi.md) | Order penunjang dari RME dokter, input hasil & nilai rujukan, posting tarif otomatis ke billing. |
| **FASE-6** | Bridging BPJS & SatuSehat | [`PRD 07`](file:///c:/aplikasi/rea-mlite/docs/prd/07_bridging_bpjs_dan_satusehat.md) | Zero-Latency Circuit Breaker, Antrean BPJS HFIS, VClaim/PCare, dan Bundle FHIR SatuSehat Kemenkes. |
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
