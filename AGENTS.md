# Pedoman Standar Rekayasa & Arsitektur Proyek Baru (ksm-next)

Dokumen ini adalah pedoman teknis resmi dan kontrak mutlak bagi Agen AI (Antigravity) dan Pengembang dalam membangun aplikasi SIMRS generasi baru (**ksm-next**). Proyek ini dibangun dari nol (*clean slate*) dengan mempertahankan **99% proses bisnis nyata** yang telah teruji di sistem eksisting (`ksmlite`), namun mengadopsi standar arsitektur modern (*Clean Architecture*), *Test-Driven Development* (TDD), dan *strict type-safety*.

---

## 🏛️ 5 PILAR PRINSIP REKAYASA SISTEM BARU

### 1. TDD-First (Test-Driven Development) & Verifikasi Edge Case
* **Aturan Mutlak:** Sebelum menulis 1 baris kode fungsional/service, Agen AI **WAJIB menulis unit test atau integration test terlebih dahulu** (*RED Phase*).
* Test suite harus mencakup:
  1. *Happy Path* (alur normal transaksi).
  2. *Edge Cases Klinis:* Race condition nomor antrean, split billing tunai vs kapitasi, offset waktu racikan farmasi, circuit breaker timeout, dan validasi data kosong/anomali.
* Kode implementasi hanya dianggap selesai jika **100% testsuite berwarna HIJAU (PASS)** (*GREEN Phase*) tanpa menurunkan kualitas pengujian.

### 2. Clean Architecture / Hexagonal Pattern
* **Domain Layer (Entities & Rules):** Murni aturan bisnis klinik, bebas dari dependensi framework, ORM, atau library HTTP.
* **Application Layer (Use Cases / Services):** Mengorkestrasi aliran data antar-entitas, memvalidasi DTO (*Data Transfer Object*), dan mengelola batas transaksi database.
* **Infrastructure Layer:** Implementasi konkret database repository, bridging HTTP eksternal (BPJS/SatuSehat), WhatsApp Gateway, dan Redis cache.
* **Presentation Layer:** Controller API (REST/gRPC) dan antarmuka pengguna (Frontend/PWA).

### 3. Strict Type-Safety & Zero Technical Debt
* **Larangan Tipe Data Liar (`any` / Dynamic Loose Typing):** Seluruh variabel, parameter fungsi, dan nilai kembalian **wajib memiliki tipe data eksplisit** (TypeScript strict, Go structs, atau PHP 8.4 strict types).
* **Validasi Skema di Pintu Masuk:** Setiap request HTTP masuk wajib divalidasi menggunakan skema deklaratif ketat (Zod, Pydantic, atau Form Request validator) sebelum menyentuh Application Layer.
* **Integritas Basis Data:** Terapkan `PRIMARY KEY`, `FOREIGN KEY` relasional, `UNIQUE CONSTRAINT`, dan tipe numerik asli (dilarang menggunakan `VARCHAR` untuk penomoran antrean atau kalkulasi uang).

### 4. Disiplin Lintas-Platform (Windows Local Dev ➔ Linux Server Staging)
* **Sensitivitas Huruf (Case-Sensitivity):** Gunakan konvensi penamaan seragam (*PascalCase* untuk class/tipe, *camelCase* untuk fungsi/variabel, *kebab-case* untuk nama berkas/direktori). Dilarang mengandalkan sifat *case-insensitive* Windows.
* **Line Endings (LF):** Seluruh file teks wajib berakhiran `LF` (`\n`), dijaga oleh berkas `.gitattributes`.
* **Path Separator:** Selalu gunakan separator standar POSIX (`/`) dalam kode sumber, hindari hardcode backslash Windows (`\`).

### 5. Paritas Proses Bisnis 99% Terhadap `ksmlite` (Reference Oracle)
* Proyek lama (`ksmlite`) adalah **Single Source of Truth (SSOT)** proses bisnis.
* Jangan pernah mengubah aturan bisnis klinik yang sudah berjalan (seperti alur 4 pilar klaim ledger, pemisahan PO Apotek Umum `PO-APT-%`, dan pembatasan jam HFIS LEAN Board) tanpa instruksi atau persetujuan eksplisit dari Pengguna.

---

## ⚡ EXECUTIVE PRE-FLIGHT CHECKLIST & PETA PRD DOMAIN

Sebelum menulis kode atau test suite untuk modul tertentu, Agen AI **WAJIB membaca dokumen PRD di `docs/prd/` dan skill domain terkait di `.agents/skills/`**:

| Domain Sistem | Dokumen PRD Rujukan (`docs/prd/`) | Skill Domain Terkait (`.agents/skills/`) |
| :--- | :--- | :--- |
| **Pendaftaran, Pasien & Kiosk Antrean** | [`01_registrasi_dan_antrean.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/01_registrasi_dan_antrean.md) | `klinis-farmasi-klaim`, `bridging-ai-infra` |
| **Rekam Medis Elektronik (RME) & SOAP** | [`02_rekam_medis_dan_soap.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/02_rekam_medis_dan_soap.md) | `knowledge-vault-icd`, `klinis-farmasi-klaim` |
| **Farmasi, E-Resep & Gudang Obat** | [`03_farmasi_dan_e_resep.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/03_farmasi_dan_e_resep.md) | `klinis-farmasi-klaim`, `kasir-billing-akuntansi` |
| **Kasir, Billing Ledger & Akuntansi** | [`04_kasir_billing_dan_akuntansi.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/04_kasir_billing_dan_akuntansi.md) | `kasir-billing-akuntansi` |
| **Laboratorium & Radiologi** | [`05_laboratorium_dan_radiologi.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/05_laboratorium_dan_radiologi.md) | `klinis-farmasi-klaim`, `kasir-billing-akuntansi` |
| **WhatsApp Gateway & Multi-Session CRM** | [`06_whatsapp_gateway_crm.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/06_whatsapp_gateway_crm.md) | `wa-gateway-pdf`, `bridging-ai-infra` |
| **Bridging BPJS & SatuSehat Kemenkes** | [`07_bridging_bpjs_dan_satusehat.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/07_bridging_bpjs_dan_satusehat.md) | `bridging-ai-infra` |
| **Layanan Homecare & Trias Preceptorship** | [`08_homecare_dan_pendidikan.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/08_homecare_dan_pendidikan.md) | `homecare-preceptorship` |
| **Fondasi Backend, Database & Transaksi** | [`09_arsitektur_backend_clean.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/09_arsitektur_backend_clean.md) | `backend-architecture`, `audit` |
| **Desain Antarmuka, Hotkeys & UI/UX** | [`10_arsitektur_frontend_ui_ux.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/10_arsitektur_frontend_ui_ux.md) | `frontend-ui-ux` |
| **Audit Integritas Data & Anti-Fraud** | [`11_audit_acid_dan_keamanan.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/11_audit_acid_dan_keamanan.md) | `audit` |

---

## 📝 PROTOKOL GIT & COMMIT

1. **Format Pesan Commit (*Conventional Commits*):**
   `<tipe>(<domain>): <deskripsi perubahan singkat>` (contoh: `feat(billing): implementasi perhitungan split billing tunai vs kapitasi`, `test(registration): tambahkan unit test auto-regenerate race condition no rawat`).
2. **Clean Workspace:** Dilarang men-commit berkas sementara (`*.tmp`, `*.bak`, kredensial `.env`, atau log pengujian).
3. **Atomic Commit:** 1 commit mencerminkan 1 unit pekerjaan teruji (lengkap antara berkas test dan implementasinya).
