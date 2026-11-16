---
name: homecare-preceptorship
description: >
  Standar Layanan Homecare PWA Mandiri & Subsistem Pendidikan Lapangan Trias Preceptorship
  (Siswa Magang, Nakes Preceptor Ber-SK, dan CI Supervisor Dosen Kampus dengan Patient
  Privacy Shield). Panggil skill ini saat menyentuh modul plugins/home_care.
---

# Standar Layanan Homecare & Subsistem Pendidikan Lapangan Preceptorship (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `git-progress-handoff` untuk memastikan sinkronisasi wajib [`plugins/home_care/README.md`](file:///opt/app/ksmlite/plugins/home_care/README.md) pada sesi kerja yang sama.

---

## 1. Standar Layanan Homecare & Subsistem Pendidikan Lapangan Preceptorship (Bab 20 / Bab 29)

Untuk menjaga integritas tata kelola layanan medis homecare dan kerja sama akademis stase klinik, seluruh pengembang dan Agen AI wajib mematuhi standar arsitektur pada [`plugins/home_care/README.md`](file:///opt/app/ksmlite/plugins/home_care/README.md):

*   **1.1 PWA Mandiri & Larangan Penggabungan ke Portal Pasien:**
    *   Layanan Home Care beroperasi penuh sebagai Progressive Web App (PWA) terisolasi (`siswa.html`, `nakes.html`, `dokter.html`, `supervisor.html`, `track.html`, `booking.html`). Dilarang keras melebur atau mencampur kode PWA Home Care ke dalam `portal_pasien` guna menjaga performa service worker, modul geolokasi offline, dan spesialisasi antarmuka nakes lapangan.

*   **1.2 Pemisahan Tegas DPJP Klinik vs. Trias Pendidikan Preceptorship & `HomeCareDpjpHelper`:**
    *   **DPJP Klinik (Klinisi Murni):** Bertanggung jawab mutlak atas aspek medis pasien (penelaahan asesmen, persetujuan *Care Plan*, e-resep berizin SIP, dan *sign-off* akhir) dengan alokasi otomatis melalui `HomeCareDpjpHelper`. **Dilarang keras membebani dokter DPJP dengan fungsi akademis, penilaian logbook, atau bimbingan mahasiswa.**
    *   **Trias Preceptorship Terisolasi:** Proses pendidikan stase lapangan hanya berlangsung di antara 3 pihak: **Siswa (Mahasiswa Magang)** <-> **Nakes Pembimbing (Preceptor Lapangan ber-SK)** <-> **CI Supervisor (Dosen Kampus)**.

*   **1.3 Patient Privacy Shield (De-Identifikasi & Pseudonimisasi RME):**
    *   Sesuai amanat **UU Kesehatan No. 17/2023** dan **Permenkes No. 24/2022**, CI Supervisor (Dosen Kampus) adalah pihak eksternal non-pemberi layanan yang tidak memiliki perikatan medis langsung (*patient-provider relationship*) dengan pasien klinik.
    *   **Aturan Wajib:** Seluruh tampilan RME di PWA Supervisor (`supervisor.html`) **DILARANG KERAS** menampilkan Nama Pasien asli dan Nomor Rekam Medis (No. RM). Sistem wajib menyamarkannya menjadi format subjek studi kasus: `[Salutation] [Initial]*** ([Usia] Th, [JK])` (contoh: `Ny. S*** (56 Th, P)`). Kolom `nm_pasien` dan `no_rkm_medis` wajib di-`unset()` di controller `Site.php` sebelum dikirim ke template view.

*   **1.4 Multi-Institusi Rekanan & Matriks Penugasan Preceptor:**
    *   Sistem mendukung kerja sama dengan lebih dari satu institusi pendidikan melalui tabel `mlite_homecare_institusi`.
    *   Setiap CI Supervisor hanya dapat melihat dan mengelola kelompok stase serta mahasiswa dari institusinya sendiri (`institusi_id`).
    *   Saat Supervisor menunjuk Preceptor Pembimbing, daftar pilihan nakes **wajib dibatasi hanya pada nakes mitra yang telah mengantongi SK Penugasan resmi dari klinik untuk institusi tersebut** (`mlite_homecare_institusi_preceptor`).

*   **1.5 Integrasi Penilaian Lapangan & Jam Terbang Riil:**
    *   Durasi menit layanan riil di rumah pasien saat checkout GPS otomatis menyinkronkan akumulasi jam terbang mahasiswa (`mlite_homecare_visit_asisten.durasi_menit`).
    *   Nakes Preceptor wajib mengisi evaluasi klinis lapangan (Skor Preceptor 0–100 dan catatan observasi aseptik/komunikasi) pada Layar 6 Checkout (`apiCheckout` / `apiSaveAsistenEvaluations`).
    *   Mahasiswa mengisi jurnal refleksi klinis mandiri (`apiSiswaSaveRefleksi`).
    *   CI Supervisor memvalidasi logbook (ACC / Revisi) dan dapat melakukan asesmen kompetensi terstandar menggunakan rubrik **Mini-CEX / DOPS** dengan 5 aspek penilaian (`apiSupervisorSubmitEvaluasi`).

*   **1.6 Arsitektur Multi-Gateway Pembayaran Online (Midtrans Snap & Kasera Pay):**
    *   Pembayaran layanan Home Care mendukung multi-gateway terisolasi (Midtrans Snap & Kasera Pay) dengan verifikasi callback/webhook idempoten sesuai dokumentasi Bab 7 `plugins/home_care/README.md`.

*   **1.7 Sinkronisasi Dokumentasi Wajib (Documentation Sync SSOT):**
    *   **Kewajiban Pengembang & Agen AI:** Setiap kali melakukan penambahan fitur, perubahan alur, penyesuaian endpoint API, atau migrasi skema database pada modul `plugins/home_care`, **WAJIB memperbarui dokumentasi teknis pada [`plugins/home_care/README.md`](file:///opt/app/ksmlite/plugins/home_care/README.md)** pada sesi kerja yang sama sebelum pekerjaan ditutup.
    *   Dokumentasi `plugins/home_care/README.md` adalah **Single Source of Truth (SSOT)** lintas sesi agar arsitektur tidak terdisrupsi dan konsistensi sistem tetap terjaga.
