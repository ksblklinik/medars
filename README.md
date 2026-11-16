# 🚀 Starter Kit Proyek Baru SIMRS (ksm-next)

Paket ini disiapkan sebagai fondasi awal bagi pengembangan aplikasi SIMRS generasi baru (**ksm-next**) secara mandiri di laptop lokal Anda (Windows) dengan alur backup ke GitHub dan pengujian berkala ke Linux Server Staging.

---

## 📁 Struktur Berkas Starter Kit

```
starter-kit/
├── .gitattributes          <- Mencegah bug line-ending Windows (CRLF vs LF)
├── .gitignore              <- Mengabaikan secrets, build artifacts & dependencies
├── AGENTS.md               <- Pedoman Arsitektur Baru, TDD-First & Rules Antigravity
├── README.md               <- Petunjuk Setup Langkah Demi Langkah
├── docs/
│   ├── database_schema_oracle.sql  <- DDL Fisik Skema 19 Tabel Basis Data Eksisting
│   └── prd/                        <- 13 Dokumen PRD Spesifikasi Domain & Database Oracle
│       ├── 00_database_schema_dictionary_mapping.md
│       ├── 01_registrasi_dan_antrean.md
│       ├── 02_rekam_medis_dan_soap.md
│       ├── 03_farmasi_dan_e_resep.md
│       ├── 04_kasir_billing_dan_akuntansi.md
│       ├── 05_laboratorium_dan_radiologi.md
│       ├── 06_whatsapp_gateway_crm.md
│       ├── 07_bridging_bpjs_dan_satusehat.md
│       ├── 08_homecare_dan_pendidikan.md
│       ├── 09_arsitektur_backend_clean.md
│       ├── 10_arsitektur_frontend_ui_ux.md
│       ├── 11_audit_acid_dan_keamanan.md
│       └── 12_manajemen_modul_dan_ekstensibilitas.md
└── .agents/
    └── skills/             <- 11 Skill Domain Modular untuk Agen AI Antigravity
        ├── audit/
        ├── backend-architecture/
        ├── bridging-ai-infra/
        ├── frontend-ui-ux/
        ├── git-progress-handoff/
        ├── homecare-preceptorship/
        ├── kasir-billing-akuntansi/
        ├── klinis-farmasi-klaim/
        ├── knowledge-vault-icd/
        ├── tokenme/
        └── wa-gateway-pdf/
```

---

## 🛠️ Langkah Memulai di Laptop Windows Anda

### 1. Salin Folder Ini ke Laptop Anda
Unduh atau salin seluruh isi folder `/opt/app/ksmlite/starter-kit/` ini ke laptop Windows Anda (misal ke `D:\projects\ksm-next\` atau `C:\Users\<user>\projects\ksm-next\`).

### 2. Inisialisasi Git & Hubungkan ke GitHub
Buka terminal (PowerShell / Git Bash) di folder proyek baru tersebut di laptop Anda:
```bash
cd D:\projects\ksm-next

# Inisialisasi git repository
git init

# Pastikan gitattributes terbaca
git add .gitattributes
git add .

# Buat initial commit
git commit -m "chore: initial starter kit with AGENTS.md, PRD, and Antigravity skills"

# Ganti ke branch main
git branch -M main

# Hubungkan ke repository GitHub Anda (buat repo private baru di github.com)
git remote add origin https://github.com/<username-anda>/<nama-repo-baru>.git

# Push ke GitHub
git push -u origin main
```

### 3. Buka Workspace di Antigravity
1. Buka aplikasi **Antigravity** di laptop Windows Anda.
2. Buka folder `ksm-next` tersebut sebagai workspace aktif.
3. Antigravity akan otomatis mendeteksi file [`AGENTS.md`](file:///opt/app/ksmlite/starter-kit/AGENTS.md) dan seluruh 11 skill di `.agents/skills/`.

### 4. Mulai Siklus TDD-First Bersama Agen AI
Anda dapat langsung memerintahkan Agen AI di laptop Anda:
> *"Baca PRD `docs/prd/01_registrasi_dan_antrean.md`. Buat Unit Test terlebih dahulu untuk use-case pendaftaran pasien dan auto-regenerasi no rawat saat terjadi race condition (TDD Red Phase)."*

Agen akan menuliskan test case, lalu setelah Anda review, agen akan menulis implementasi kode bersihnya sampai test menjadi hijau (*Green Phase*).

---

## 🚢 Alur Pengujian ke Linux Staging Server

1. Kembangkan fitur di laptop Windows secara lokal hingga seluruh unit test lolos.
2. Commit dan push ke GitHub: `git push origin main`.
3. Di Linux Staging Server (server terpisah dari produksi):
   ```bash
   git pull origin main
   # Jalankan test runner di lingkungan Linux
   npm test # atau go test ./... / php artisan test
   ```
4. Verifikasi bahwa tidak ada masalah huruf besar/kecil (*case-sensitivity*) atau jalur berkas (*paths*) antara Windows dan Linux.
