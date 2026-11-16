---
name: tokenme
description: >
  Sistem efisiensi token 4-lapis khusus SIMRS ksmlite: komunikasi padat & jelas
  (clarity-first concision), kode tepat guna tanpa melanggar ACID/keamanan AGENTS.md,
  minimalisasi output tool/terminal (high-impact token saver), dan penjagaan konteks
  tetap ramping. Panggil skill ini saat user menyebut "tokenme", "hemat token",
  "efisien", "ringkas", atau saat konteks percakapan mulai padat.
---

# Standar Efisiensi Token & Konteks (`tokenme` — Edisi Khusus `ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `git-progress-handoff` saat konteks mulai penuh untuk menyimpan checkpoint ke `.agents/HANDOFF.md`.

---

## 🛡️ Aturan Besi SIMRS `ksmlite` (Iron Rules — Dilarang Dilanggar Demi Hemat Token)

1. **Keamanan & Integritas Data (ACID) di Atas Segalanya:**
   Penghematan baris kode **DILARANG KERAS** menghilangkan atau memotong:
   - Whitelisting eksplisit `array_intersect_key($_POST, array_flip($allowed))` sebelum `save()`/`update()`.
   - Blok transaksi `try { ... } catch (\Throwable $e)` beserta `rollBack()`.
   - Format 3 argumen `->where('kolom', '!=', $val)` & kualifikasi eksplisit `tabel.kolom` pada `JOIN`.
   - Proteksi UI/Backend Dual-Layer Guard WhatsApp (`WhatsAppService::isPhoneNumberWhitelisted`).
   - Enkapsulasi IIFE JS, Triple-Gate WebSocket, atribut anti-autofill (`autocomplete="off" data-lpignore="true" data-form-type="other"`), serta komponen UI resmi mLite (dilarang mengganti DateTimePicker/Selectator mLite dengan input bawaan browser yang mengubah format data).
2. **Kejelasan Mengalahkan Keringkasan:**
   Jangan pernah memotong nama fungsi, path file, kode ICD-10, pesan error, atau instruksi multi-langkah yang krusial.

---

## Layer 1 — Komunikasi Padat & Langsung ke Inti (*Clarity-First Concision*)

- Jawab langsung ke inti solusi atau temuan, baru sertakan alasan teknis seperlunya.
- Hilangkan kalimat basa-basi pembuka/penutup dan pengulangan narasi tool call.
- Jangan menempelkan log mentah yang panjang ke chat; kutip hanya baris penentu utamanya.
- Tetap gunakan kalimat lengkap yang jelas dan mudah dibaca dalam sekali baca (hindari singkatan telegrafis yang membingungkan).
- **Wajib ekspansi penuh (jangan diringkas)** saat menyampaikan peringatan keamanan, konfirmasi aksi destruktif/migrasi DB, atau kajian risiko.

---

## Layer 2 — Kode Tepat Guna (*Minimum Correct & Compliant Solution*)

- **YAGNI (*You Aren't Gonna Need It*):** Bangun tepat apa yang diminta user. Jangan membuat abstraksi spekulatif, class pembungkus yang hanya dipakai sekali, atau scaffolding fitur yang belum diminta.
- Gunakan helper dan service standar yang sudah ada di `ksmlite` (`QueryWrapper`, `PaymentService`, `WhatsAppService`, `BpjsService`, `VaultService`) daripada menulis ulang fungsi duplikat.
- Pertahankan diff sekecil dan sebersih mungkin agar mudah diaudit (*atomic diff*).

---

## Layer 3 — Minimalisasi Output Tool & Terminal (*Penghemat Token Terbesar: 50%–80%*)

Sebelum memanggil tool pembacaan file atau perintah shell, terapkan 2 prinsip ini:
1. **Hindari Pembacaan Ulang:** Jika isi file atau bagian baris tersebut sudah ada di dalam konteks percakapan dan belum berubah, jangan panggil `read_file` ulang.
2. **Saring di Sumbernya (Tarik Byte Sekecil Mungkin):**
   - **Baca Baris Spesifik:** Gunakan parameter `StartLine` dan `EndLine` pada `read_file` setelah menemukan nomor baris melalui `grep -n`. Jangan membaca 800 baris jika hanya butuh 40 baris fungsi terkait.
   - **Data Terstruktur (`JSON`/`SQL`):** Gunakan `jq` (misal pada `MANIFEST.json`), `grep -c` (hitung jumlah), atau `head`/`sed -n 'X,Yp'`. Jangan melakukan `cat` pada file besar.
   - **Pencarian Kode Presisi:** Gunakan `grep -rn "pola" path/ | head -n 30` atau `grep -rl` (hanya nama file).
   - **Git Ringkas:** Jalankan `git status -s` atau `git diff --stat` terlebih dahulu, lalu `git diff -- path/file` hanya untuk file yang relevan.
   - **Gabungkan Perintah Independen:** Rangkai perintah shell pemeriksaan dalam satu pemanggilan `run_command` menggunakan `&&` untuk menghemat *round-trip* konteks.

---

## Layer 4 — Penjagaan Konteks Tetap Ramping (*Lean Context*)

- **Hindari Retry Loop Buta:** Jika satu pendekatan gagal 2 kali, hentikan percobaan berulang dan analisis akar masalahnya terlebih dahulu.
- **Handoff Tepat Waktu:** Ketika konteks percakapan mulai panjang atau memasuki jam pelayanan aktif RS, gunakan skill `git-progress-handoff` untuk menulis ringkasan status ke [`.agents/HANDOFF.md`](file:///opt/app/ksmlite/.agents/HANDOFF.md) lalu sarankan `/compact` kepada user.
