---
name: audit
description: >
  Rencana & Protokol Audit Keamanan, Integritas Data (ACID), Konkurensi, dan Disaster
  Recovery aplikasi ksmlite. Panggil skill ini saat user menyebut "audit", "fase audit",
  "lanjutkan audit", "mulai audit fase X", "status audit", atau "cek keamanan aplikasi".
  Berisi 6 fase audit bertahap di luar jam operasional RS beserta rekam jejak hasil
  audit terkini.
---

# 🔍 Protokol & Status Audit Keamanan & Integritas Data (ACID) — `ksmlite`

> **Relasi Lintas-Skill:**
> - Padukan dengan `bridging-ai-infra` untuk audit replikasi MySQL Server 2 (Hot Standby) & konfigurasi Nginx/Docker.
> - Padukan dengan `git-progress-handoff` saat mencatat hasil temuan/perbaikan audit ke `.agents/ROADMAP.md`.

## Ikhtisar & Prinsip Pelaksanaan

Dokumen ini adalah panduan pelaksanaan **Audit Keamanan, Integritas Data (ACID), Konkurensi, dan Disaster Recovery** untuk SIMRS **ksmlite**, baik untuk menindaklanjuti rekomendasi terbuka maupun menjalankan **Audit Regresi Berkelanjutan** pada modul-modul baru (seperti `apotek_umum`, `kasir_igd`, `home_care`, `smartagent`, `wagateway`).

- ⏰ **Waktu Eksekusi:** Wajib di luar jam operasional RS (Senin–Sabtu di luar 07:00–13:00 & 15:00–20:00 WIB, atau hari Minggu).
- 🛡️ **Zero Downtime:** Semua langkah pemindaian bersifat READ-ONLY. Eksekusi perubahan skema/kode wajib melalui konfirmasi eksplisit user.
- 🔄 **Incremental:** Setiap fase berdiri sendiri dan dicatat progresnya di bagian **Progress Tracking & Tindak Lanjut Terbuka** di bawah.

---

## Ringkasan Status 6 Fase Audit (Baseline Awal vs Status Terkini)

| Fase | Nama Fase | Prioritas | Status Scan Awal | Fokus Tindak Lanjut / Audit Regresi Berikutnya |
| :-: | :--- | :-: | :-: | :--- |
| **1** | Integritas Skema Database | 🔴 WAJIB | ✅ Selesai (08 Ags 2026) | Evaluasi 30 kolom VARCHAR numerik (`pemeriksaan_ralan`) & cek tabel modul baru |
| **2** | Keamanan Kode (SQLi, XSS, CSRF) | 🔴 WAJIB | ✅ Selesai (08 Ags 2026) | Escaping 8 variabel HTML/PDF (`{$alamat}`, `{$catatan}`) & scan modul baru |
| **3** | Transaksi & Atomisitas (ACID) | 🔴 WAJIB | ✅ Selesai (08 Ags 2026) | Verifikasi sekat `beginTransaction`/`rollBack` pada fungsi mutasi multi-tabel |
| **4** | Manajemen Konkurensi (Race Condition) | 🟡 DIANJURKAN | ✅ Selesai (08 Ags 2026) | Implementasi Pessimistic Locking (`SELECT ... FOR UPDATE`) pada mutasi stok obat |
| **5** | Konfigurasi Server & Hardening | 🟡 DIANJURKAN | ✅ Selesai (08 Ags 2026) | Hardening permission `config.php`/`.git`, hak akses user DB, & flag cookie PHP |
| **6** | Backup & Disaster Recovery | 🔴 KRITIS | ⚠️ Temuan Kritis (25 Sep 2026) | **Prioritas Utama:** Setup cron `mysqldump` harian, rotasi `mlite_query_logs` (>1,3 GB), & uji restore |

---

## Detail Prosedur Per Fase (Checklist Audit)

### Fase 1: Audit Integritas Skema Database 🗄️
1. **Pemetaan tipe data kolom kritis:** Identifikasi kolom `VARCHAR` yang isinya murni numerik pada tabel transaksi (`reg_periksa`, `resep_obat`, `detail_pemberian_obat`, `gudangbarang`, `mlite_antrian_loket`, `piutang_pasien`, `mlite_billing_ledger`).
2. **Pemeriksaan constraint & indeks:** Pastikan tabel transaksi harian memiliki `PRIMARY KEY`, `UNIQUE INDEX`, dan indeks pencarian pada kolom relasi (`no_rawat`, `no_resep`, `no_rkm_medis`).
3. **Deteksi data anomali (Orphan Records):** Periksa baris di tabel anak yang tidak memiliki pasangan induk di `reg_periksa` atau `resep_obat`.

### Fase 2: Audit Keamanan Kode (SQL Injection, XSS, CSRF & Secrets) 🔒
1. **Scan SQL Injection:** Cari konkatenasi langsung `$_POST` / `$_GET` ke string SQL tanpa `prepare()` / `bindValue()` atau tanpa whitelisting `array_intersect_key()`.
2. **Scan XSS:** Periksa output data pasien/catatan medis ke template HTML/PDF tanpa `htmlspecialchars()`.
3. **Scan Otorisasi & CSRF:** Pastikan seluruh endpoint tulis memeriksa hak akses (`$this->core->getUserInfo('role')`, `hasRole()`, `isManager()`, atau `_checkGatekeeperRole()`).
4. **Scan Kredensial Hardcoded:** Pastikan tidak ada API key, token WA, atau password DB yang ditulis hardcoded di kode sumber.

### Fase 3: Audit Transaksi & Atomisitas (ACID Compliance) ⚛️
1. **Identifikasi operasi multi-tabel tanpa transaksi:** Cari proses backend yang melakukan 2+ operasi `INSERT`/`UPDATE`/`DELETE` berurutan (`apotek_ralan`, `apotek_umum`, `kasir_rawat_jalan`, `kasir_rawat_inap`, `kasir_igd`, `rawat_jalan`, `farmasi`, `dokter_ralan`, `laboratorium`).
2. **Verifikasi blok `try-catch` & `rollBack()`:** Pastikan setiap `beginTransaction()` memiliki pasangan `commit()` dan `rollBack()` di dalam `catch (\Throwable $e)`.

### Fase 4: Audit Manajemen Konkurensi (Race Condition) 🏎️
1. **Generator Nomor Otomatis:** Periksa titik pembuatan `no_rawat`, `no_resep`, `no_reg`, `no_nota`, `no_pemesanan` (`PO-APT-%`), dan nomor order `PL`/`PR`.
2. **Mutasi Stok Konkuren:** Pastikan pengurangan stok di `gudangbarang` saat validasi resep/POS terlindungi dari *race condition* ketika beberapa kasir/apoteker memvalidasi obat yang sama secara bersamaan.

### Fase 5: Audit Konfigurasi & Server 🖥️
1. **Keamanan akses publik:** Pastikan `.env`, `.git/`, `config.php`, dan direktori internal tidak dapat diakses publik via HTTP.
2. **Database & PHP Hardening:** Evaluasi privilege user MySQL (batasi `DROP`/`GRANT` untuk user aplikasi), pastikan `display_errors = Off`, `expose_php = Off`, `session.cookie_httponly = 1`, dan `session.cookie_secure = 1`.

### Fase 6: Audit Backup & Disaster Recovery 💾
1. **Mekanisme Backup Otomatis:** Verifikasi jadwal cron backup harian database `mlite` dan retensi arsip terkompresi (`.sql.gz`).
2. **Pemangkasan Log Masif:** Pantau ukuran tabel log seperti `mlite_query_logs` agar tidak membebani ukuran dump backup harian.
3. **Uji Pemulihan (Recovery Test):** Uji restore berkala ke environment staging/standby dan ukur waktu pemulihan (RPO/RTO).

---

## 📋 Rekam Jejak Hasil Audit & Daftar Tindak Lanjut (Progress Tracking)

### Perbaikan Fondasi yang Telah Selesai
- ✅ **Antrian Farmasi** — Fix `noantrian` `VARCHAR` -> `INT`, renumber 281 duplikat F10 (13 Jul 2026).
- ✅ **Pendaftaran Rawat Jalan** — Server-side auto-regenerate `no_rawat` saat terjadi race condition.
- ✅ **Audit Keamanan & ACID Siklus 1 (`AUD-01`)** — Tuntas pemindaian 6 fase pada 14 Ags 2026 & 25 Sep 2026.

### Detail Temuan Per Fase (Status Terkini)
- **Fase 1 (Skema DB — Selesai 08 Ags 2026):**
  - [x] Constraint & Index: 0 isu pada tabel transaksi utama.
  - [x] Orphan Records: 0 orphan record pada relasi `no_rawat` & `no_resep`.
  - [ ] *Tindak Lanjut Opsional:* 30 kolom `VARCHAR` bernilai numerik di `pemeriksaan_ralan` (`nadi`, `respirasi`, `suhu_tubuh`, `spo2`, `gcs`, `diastole`, `sistole`).
- **Fase 2 (Keamanan Kode — Selesai 08 Ags 2026):**
  - [x] SQL Injection: 0 SQLi pada kode produksi aktif.
  - [x] Hardcoded Secret: 0 secret bocor di kode produksi.
  - [ ] *Tindak Lanjut Terbuka:* Sanitasi 8 variabel template unescaped (`{$alamat}`, `{$catatan}`) pada berkas cetak PDF/HTML.
- **Fase 3 (Transaksi ACID — Selesai 08 Ags 2026):**
  - [x] Pemetaan titik transaksi multi-tabel selesai.
  - [ ] *Tindak Lanjut Terbuka:* Memastikan sekat `beginTransaction` & `rollBack` penuh pada `postHapusRacikanEresep`/`postHapusObatRacikan` (`apotek_ralan`), `postHapusObat` (`kasir_rawat_jalan`), `postSaveBookingPeriksa` (`rawat_jalan`), dan `postSetStok`/`postReStok` (`farmasi`).
- **Fase 4 (Race Condition — Selesai 08 Ags 2026):**
  - [x] 22 titik generator nomor otomatis telah dipetakan (`no_rawat` auto-regenerate aktif).
  - [ ] *Tindak Lanjut Terbuka:* Penambahan *Pessimistic Locking* (`SELECT ... FOR UPDATE`) pada mutasi stok obat `apotek_ralan`.
- **Fase 5 (Konfigurasi Server — Selesai 08 Ags 2026):**
  - [x] SSL/HTTPS aktif pada domain produksi.
  - [ ] *Tindak Lanjut Terbuka:* Pengetatan permission `config.php` & `.git`, pembatasan host/privilege akun DB `ksbl`, dan pengaktifan `session.cookie_httponly`/`cookie_secure`.
- **Fase 6 (Backup & Recovery — Diperiksa 25 Sep 2026):**
  - [ ] 🔴 **TINDAK LANJUT PRIORITAS UTAMA:** Belum ada cron backup DB otomatis harian (ukuran DB `mlite` ~1,8 GB, di mana `mlite_query_logs` memakan 1.331 MB / 3,11 juta baris). Perlu dibuat cron `mysqldump` harian pukul 01:00 WIB dengan rotasi `mlite_query_logs` > 30 hari dan retensi backup 7 hari.
