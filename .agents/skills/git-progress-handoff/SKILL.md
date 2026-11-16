---
name: git-progress-handoff
description: >
  Protokol Manajemen Git Semi-Otomatis, Pelacakan Progress (ROADMAP.md & README.md modul),
  dan Session/Cross-Model Handoff (.agents/HANDOFF.md) untuk aplikasi SIMRS ksmlite.
  Panggil skill ini saat menyelesaikan siklus tugas (sebelum git commit), saat user menyebut
  "handoff", "simpan progress", "checkpoint", "lanjut nanti", "update roadmap", "git commit",
  atau saat pekerjaan harus di-pause karena memasuki Jam Pelayanan Aktif RS.
---

# Standar Manajemen Git, Pelacakan Progress & Session Handoff (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `tokenme` untuk efisiensi ringkasan konteks.
> - Padukan dengan `bridging-ai-infra` jika melakukan sinkronisasi kode (`rsync`) ke Server 2 (Hot Standby).
> - Padukan dengan `knowledge-vault-icd` jika handoff menyentuh progres injeksi dokumen di `/opt/app/ksmlite/knowledge-vault/MANIFEST.json`.

---

## 1. Standar Pelacakan Progress (`ROADMAP.md`, `.agents/plans/`, & `README.md` Modul) (Bab 20, 22, 23, 24)

*   **Format Pesan Chat:** Dilarang keras menggunakan raw LaTeX (`$$...$$` atau `$..$`). Gunakan teks markdown dan panah teks (`➔`, `->`).
*   **Sinkronisasi `README.md` Modul:** Setiap modifikasi modul wajib memperbarui `README.md` plugin bersangkutan (`plugins/<nama_modul>/README.md` — bagian Riwayat Perubahan Terkini & Logika Kritis ACID) pada sesi kerja yang sama sebelum pekerjaan ditutup.
*   **Penyimpanan Dokumen Rencana Desain (`.agents/plans/`):**
    *   Untuk fitur besar atau perombakan arsitektur lintas-modul, simpan dokumen spesifikasi/rencana di [`.agents/plans/rencana_<nama_fitur>.md`](file:///opt/app/ksmlite/.agents/plans) lalu tautkan dari `ROADMAP.md`.
*   **Roadmap Pelacakan SSOT ([`.agents/ROADMAP.md`](file:///opt/app/ksmlite/.agents/ROADMAP.md)):**
    1. Periksa `.agents/ROADMAP.md` di awal sesi saat user menanyakan status rencana atau melanjutkan pekerjaan.
    2. Catat tugas yang ditunda (misal karena jam operasional RS aktif) di bagian `⏳ Rencana Tertunda / Antrean Kerja (PENDING)`.
    3. Pindahkan ke bagian `🚧 Sedang Dikerjakan (IN PROGRESS)` saat mulai mengeksekusi.
    4. Pindahkan ke tabel `✅ Riwayat Rencana Selesai (COMPLETED)` lengkap dengan kode ID berlanjut (contoh: `PLN-14`, `PLN-15`, dst. setelah `PLN-13`), tautan file utama, modul terkait, rangkuman hasil eksekusi bernomor, dan tanggal selesai (`DD-MM-YYYY`) saat tuntas.

---

## 2. Protokol Session & Cross-Model Handoff ([`.agents/HANDOFF.md`](file:///opt/app/ksmlite/.agents/HANDOFF.md))

Gunakan protokol ini ketika:
1. User meminta *"handoff"*, *"simpan progress"*, *"checkpoint"*, *"lanjut nanti"*, atau akan berganti sesi chat / berganti model AI (misal estafet antara Gemini Flash, Pro, atau Argon).
2. Pekerjaan pada modul pelayanan inti harus dihentikan sementara karena memasuki **Jam Pelayanan Aktif RS** (Senin–Sabtu 07:00–13:00 & 15:00–20:00 WIB).
3. Konteks percakapan sudah panjang dan perlu di-compact (`/compact`).

### Struktur Wajib Berkas `.agents/HANDOFF.md`:
Saat membuat atau memperbarui `/opt/app/ksmlite/.agents/HANDOFF.md`, tulis dengan format presisi berikut:

```markdown
# 🔄 Session & Cross-Model Handoff Checkpoint — ksmlite

- **Waktu Pembaruan:** YYYY-MM-DD HH:MM WIB
- **Model Terakhir:** <Nama Model>
- **Status Operasional:** <Selesai Sebagian / Pause Jam Pelayanan / Siap Eksekusi>
- **Tugas / Tujuan Utama:** <1 kalimat jelas tujuan yang sedang dikerjakan>

## 1. Apa yang Sudah Selesai (Done)
- [x] <Poin pekerjaan yang sudah tuntas dan terverifikasi>

## 2. Berkas & Baris Kode yang Sedang Disentuh (Active Files)
- `plugins/<modul>/<file>.php` (Baris X–Y): <penjelasan perubahan/status>

## 3. Keputusan Arsitektur & Konteks Kritis (Decisions & Constraints)
- <Aturan bisnis/skema DB/alasan teknis yang tidak boleh dilanggar oleh agen berikutnya>

## 4. Langkah Selanjutnya (Next Actionable Steps)
1. [ ] <Langkah konkret pertama yang harus langsung dieksekusi oleh agen berikutnya>
2. [ ] <Langkah verifikasi / testing yang harus dijalankan>
```

---

## 3. Standar & Protokol Pengelolaan Git (Semi-Otomatis) (Bab 22)

Dokumentasi ini menetapkan aturan dan alur kerja pengelolaan version control (Git) di repositori `ksmlite`:

*   **3.1 Model Kerja Semi-Otomatis:**
    *   **Komitmen Otomatis Asisten (Agent):** Setiap kali satu siklus tugas (penambahan fitur, perbaikan bug, refactoring, atau fase audit) selesai diimplementasikan dan telah lulus verifikasi/pengujian, Asisten AI **secara otomatis melakukan pembersihan berkas sementara lalu membuat `git commit` yang rapi dan terisolasi**.
    *   **Konfirmasi Remote Push:** Perintah `git push` ke remote repository (GitHub/GitLab) **hanya dijalankan atas instruksi atau konfirmasi eksplisit dari USER**.

*   **3.2 Prosedur Pembersihan Sebelum Commit (*Clean Workspace Policy*):**
    *   Sebelum melakukan `git add` atau `git commit`, Agen **wajib memeriksa dan membersihkan**:
        1. Skrip debug/testing sementara yang dibuat selama sesi (seperti `test_*.php`, `diag.php`, `diag_*.php`, `*.bak`, `*.tmp`).
        2. File log pengujian lokal (seperti `*.log`, `*_debug.log`) yang tidak seharusnya masuk ke repositori.
        3. Memastikan file kredensial sensitif (`.env`, secret token) tidak ikut ter-staging ke Git.

*   **3.3 Standar Format Pesan Commit (*Conventional Commits*):**
    *   Pesan commit **wajib** mengikuti struktur baku: `<tipe>(<lingkup/modul>): <deskripsi perubahan>`
    *   **Tipe Commit yang Digunakan:**
        *   `feat(nama_modul)`: Penambahan fitur baru, rute baru, atau endpoint baru.
        *   `fix(nama_modul)`: Perbaikan bug, penanganan error kueri, atau perbaikan tampilan.
        *   `refactor(nama_modul)`: Restrukturisasi atau pembersihan kode tanpa mengubah fungsionalitas.
        *   `docs(nama_modul)`: Pembaruan dokumentasi (`README.md`, `AGENTS.md`, `SKILL.md`, dsb.).
        *   `chore(nama_modul)`: Pembersihan file sampah, penyesuaian konfigurasi, atau manajemen dependensi.
        *   `style(nama_modul)`: Perubahan formatting CSS/tampilan tanpa mengubah logika bisnis.
    *   *Contoh:* `feat(laboratorium): implementasi pemisahan nomor order PL berdasarkan status kasir`
    *   *Contoh:* `fix(apotek_ralan): perbaiki pewarisan font mpdf pada cetak resep dokter`

*   **3.4 Prinsip Atomic Commit & Kerapihan Riwayat:**
    *   Hindari penggabungan perubahan banyak modul yang tidak saling berkaitan ke dalam satu commit raksasa (*mega-commit*).
    *   Setiap commit harus mencerminkan satu unit kerja logis (*atomic*) yang mempermudah pelacakan riwayat (*git log*) dan proses pemulihan (*git revert/rollback*) jika terjadi anomali di lingkungan produksi.

*   **3.5 Strategi Percabangan (*Branching*):**
    *   Branch utama (`main` / `master`) dijaga dalam kondisi stabil.
    *   Untuk pekerjaan berskala besar (seperti fase audit menyeluruh atau perombakan modul besar), buat percabangan baru (misal: `feature/nama-fitur` atau `audit/fase-x`) di awal pengerjaan.
