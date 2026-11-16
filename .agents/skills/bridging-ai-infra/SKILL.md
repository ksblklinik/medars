---
name: bridging-ai-infra
description: >
  Standar Integrasi Eksternal & Bridging BPJS/SatuSehat (Zero-Latency Circuit Breaker,
  Strict Timeout), Pemetaan Model AI (vLLM Lokal vs OpenRouter Cloud & Hardware Fallback),
  Algoritma LEAN Board (HFIS BPJS), dan High Availability Server 1 & Server 2 (Hot Standby).
  Panggil skill ini saat menyentuh BpjsService.php, PcareService.php, RealtimeService.php,
  vclaim, pcare, jkn_mobile, jkn_mobile_fktp, bridging_hfis, icare, satu_sehat, orthanc,
  veronisa, vedika, smartagent, manajemen (LEAN), atau infrastruktur Docker/Nginx/MySQL Standby.
---

# Standar Bridging Eksternal, AI Routing, LEAN Analytics & High Availability (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `knowledge-vault-icd` saat menyentuh prompt AI klinis & grounding ICD-10 SatuSehat di `dokter_ralan` / `plugins/smartagent/VaultService.php`.
> - Padukan dengan `wa-gateway-pdf` saat menyentuh AI Chatbot Customer Service di `plugins/wagateway/AIService.php`.
> - Padukan dengan `git-progress-handoff` saat melakukan sinkronisasi kode (`rsync`) dari Server 1 ke Server 2.

---

## 0. Peta Service Library Infrastruktur & Bridging (`systems/lib/` & `plugins/`)

Sebelum menulis kode komunikasi eksternal, AI, atau event realtime, selalu gunakan class resmi berikut:
1. **[`systems/lib/BpjsService.php`](file:///opt/app/ksmlite/systems/lib/BpjsService.php):**
   - `isBridgingActive($consid, $secretkey, $user_key, $url)`
   - `get(...)`, `post(...)`, `put(...)`, `delete(...)`, `postAplicare(...)`, `getStatus()`
2. **[`systems/lib/PcareService.php`](file:///opt/app/ksmlite/systems/lib/PcareService.php):**
   - `get(...)`, `post(...)`, `put(...)`, `delete(...)`, `postIcare(...)`, `getStatus()` (khusus integrasi FKTP PCare & i-Care BPJS).
3. **[`systems/lib/RealtimeService.php`](file:///opt/app/ksmlite/systems/lib/RealtimeService.php):**
   - `publish(\PDO $pdo, string $channel, string $event, array $payload = []): bool`
   - `getEvents(\PDO $pdo, array $channels, int $lastEventId = 0, int $limit = 20): array`
   - `cleanup(\PDO $pdo, int $keepMinutes = 60): void`
4. **AI & Semantic Services:**
   - [`plugins/wagateway/AIService.php`](file:///opt/app/ksmlite/plugins/wagateway/AIService.php) — Layanan AI Chatbot & draf balasan CS WhatsApp.
   - [`plugins/smartagent/VaultService.php`](file:///opt/app/ksmlite/plugins/smartagent/VaultService.php) & [`plugins/smartagent/SemanticService.php`](file:///opt/app/ksmlite/plugins/smartagent/SemanticService.php) — Layanan RAG Klinis, BM25, & embedding vektor.
   - [`plugins/satu_sehat/src/ServiceRequest.php`](file:///opt/app/ksmlite/plugins/satu_sehat/src/ServiceRequest.php) — Payload FHIR SatuSehat Kemenkes.

---

## 1. Pemetaan & Standar Model AI (vLLM & OpenRouter) (Bab 12, 15, 17)

*   **vLLM Lokal (`gemma-4-12b`):** Chatbot Live CS WA, Simulasi Chatbot Admin, Analisis Sentimen, Pemformat Pesan WA, Penyempurna Draf CS (`wagateway`).
*   **OpenRouter Cloud (`google/gemini-2.5-flash`):** Audit Mutu & Efisiensi Lab (`laporan`), Rekomendasi Diagnosis & ICD-10 (`dokter_ralan`), Evaluasi Resep Obat (`apotek_ralan`), Generator Edukasi Obat KIE (`apotek_ralan`), Pemetaan FHIR (`satu_sehat`), Interpretasi Rontgen/PACS (`orthanc`).
*   **Lightweight / Fixed Engines:** Face Recognition (Port 8002), OCR KTP/BPJS (Port 8001), Speech-to-Text (Port 8085), CAPTCHA BPJS (`ddddocr`).
*   **Standar Output AI Controller:** Endpoint AI wajib memanggil `exit;` setelah `echo json_encode(...)` untuk mencegah penempelan wrapper template HTML yang merusak parsing JSON peramban.

---

## 2. Standar Analitik & Algoritma Alur LEAN Board (Bab 17 / Bab 28)

Untuk menjaga keseragaman data analitik alur pasien antara Display Monitor TV (`plugins/anjungan/view/display.lean.html`) dan Dashboard Eksekutif Manajemen (`plugins/manajemen/view/admin/lean.html`), seluruh perhitungan metrik LEAN wajib mengikuti standar baku:

*   **2.1 Formula Estimasi Waktu Layanan Antrean Booking (SSOT HFIS BPJS):**
    *   **Pembedaan Jam Operasional Front Office vs Jam Layanan Dokter:**
        *   Jam mulai di tabel `jadwal` (misal `07:00:00`) adalah waktu dibukanya layanan Front Office, pendaftaran loket, dan skrining awal perawat.
        *   Layanan Dokter (sesuai HFIS BPJS) memiliki jam mulai riil sebagai berikut:
            1. **Shift Pagi (Semua Poli):** Jam **`08:00:00`** s.d. **`12:00:00`** *(Admin/FO buka 07:00:00)*.
            2. **Shift Sore Poli Gigi:** Jam **`15:00:00`** s.d. **`19:00:00`** *(Admin/FO buka 14:30:00)*.
            3. **Shift Sore Non-Gigi (Poli Umum, KIA, Bidan, dll):** Jam **`16:00:00`** s.d. **`20:00:00`** *(Admin/FO buka 15:30:00)*.
    *   **Standar Alokasi Durasi Waktu per Nomor Registrasi:**
        *   Dapat dikonfigurasi per slot jadwal dokter melalui kolom `DL` di menu *Jadwal Dokter* (`mlite_jadwal_durasi`).
        *   Fallback standar: dr. H. Gisana = `3 menit`, Poli Gigi = `15 menit`, Poli KIA = `10 menit`, Dokter Umum lain = `5 menit`.
    *   **Rumus Perkiraan Jam Dilayani:**
        `Perkiraan Jam Dilayani = Jam Mulai Dokter Riil + (no_reg * durasi_menit)`
        `Saran Jam Tiba = max(Jam Mulai Dokter Riil, Perkiraan Jam Dilayani - 30 Menit)`
*   **2.2 Kriteria Overtime / Keterlambatan Pasien Booking:**
    *   Pasien booking hanya diklasifikasikan sebagai **Overtime / Stuck** jika:
        1. Waktu saat ini melewati perkiraan jam layanannya (`CURRENT_TIME() > perkiraan_jam_dilayani`).
        2. Status booking masih `Belum` (belum check-in/registrasi di loket).
        3. Shift jadwal dokter bersangkutan sedang aktif berjalan (`:time_now BETWEEN j.jam_mulai AND j.jam_selesai`).
*   **2.3 Standar Rata-rata Kedatangan Pasien Bertanda (Net Signed Avg):**
    *   Dihitung dari selisih: `Waktu Registrasi Riil Pasien - Perkiraan Jam Dilayani`.
    *   **Tanda Minus (`-`):** Pasien datang **Lebih Awal (*Early Arrival*)** sebelum estimasi gilirannya (contoh: `-131m`).
    *   **Tanda Plus (`+`):** Pasien datang **Terlambat (*Late Arrival*)** setelah estimasi gilirannya terlewat (contoh: `+15m`).
*   **2.4 Isolasi Shift Multi-Praktek:**
    *   Kueri booking wajib menyertakan filter `AND (j.jam_mulai = b.shift OR b.shift IS NULL OR b.shift = '')` agar nomor antrean pada shift sore tidak bocor atau dihitung pada jadwal shift pagi dokter yang sama.

---

## 3. Standar Integrasi Eksternal & Mitigasi Zero-Latency Bridging BPJS (Bab 18)

Untuk menjamin SIMRS tetap berjalan sangat cepat (*blazing fast*), bebas hambatan (*non-blocking*), dan tidak pernah membeku (*freeze*) akibat latensi jaringan pihak ketiga (terutama saat bridging belum aktif atau server eksternal BPJS/SatuSehat *down*):

*   **3.1 Zero-Latency Circuit Breaker (`BpjsService::isBridgingActive` & `PcareService`):**
    *   Seluruh gerbang pemanggilan HTTP di [`systems/lib/BpjsService.php`](file:///opt/app/ksmlite/systems/lib/BpjsService.php) dan [`systems/lib/PcareService.php`](file:///opt/app/ksmlite/systems/lib/PcareService.php) (`get`, `post`, `put`, `delete`, `postAplicare`, `postIcare`) **wajib memvalidasi kelengkapan kredensial terlebih dahulu**.
    *   Jika salah satu dari `consid`, `secretkey`, `user_key`, atau `url` bernilai kosong (`""`), tanda strip (`"-"`), `0`, atau dummy (`"12345"`), fungsi **wajib langsung mengembalikan respons error JSON mock dalam 0 milidetik** tanpa pernah menginisiasi *socket connection* atau cURL ke jaringan luar.
*   **3.2 Strict Connect & Execution Timeout:**
    *   Dilarang keras menyetel `CURLOPT_CONNECTTIMEOUT` ke `0` (unlimited). Wajib dibatasi maksimal **2 detik** (`CURLOPT_CONNECTTIMEOUT => 2`).
    *   Batas waktu eksekusi total cURL wajib dibatasi maksimal **4 detik** (`CURLOPT_TIMEOUT => 4`) agar proses PHP-FPM tidak menumpuk dan tidak menguras alokasi *worker pool*.
*   **3.3 Larangan Pemanggilan Sinkron pada Alur Kritis Layanan:**
    *   Dilarang menaruh pemanggilan API BPJS/SatuSehat secara sinkron di tengah alur cetak tiket anjungan mandiri (*kiosk*), pendaftaran rawat jalan/IGD, check-in apotek, validasi resep, atau kasir.
    *   Seluruh transaksi operasional lokal (nomor antrean, cetak tiket, simpan resep) wajib selesai dan merespons peramban terlebih dahulu. Pengiriman data ke BPJS/SatuSehat harus dilakukan secara asinkron atau non-blocking.

---

## 4. Standar High Availability (Hot Standby), Multi-Host & Mitigasi Hardware (Bab 19 / Bab 28)

Sistem SIMRS ksmlite menerapkan arsitektur **Active–Passive (Hot Standby)** antara Server 1 (Primary / Live Production) dan Server 2 (Replica Standby / *vibe*):

*   **4.1 Topologi 3-Pilar Server Standby (Server 2):**
    *   **Pilar 1 (Hot Standby Web & DB):**
        *   MySQL Standby (`standby_mysql`, port 3306) berjalan dalam mode `read_only = 1`, streaming binlog dari Server 1 dengan `server-id = 2`.
        *   Wajib terhubung ke Docker network `app-network` dengan alias `mlite_mysql` agar koneksi PHP tidak memerlukan perubahan file konfigurasi.
        *   Container Nginx Standby **DILARANG** membinding port `8080` (hanya port `80` dan `443`), karena port 8080 dialokasikan untuk Nextcloud.
    *   **Pilar 2 (Monitoring 24/7):** `uptime-kuma` berjalan di port `3001` Server 2.
    *   **Pilar 3 (Cloud Office & SOP Klinis):** Nextcloud Hub (`nextcloud_app` + `nextcloud_db`) berjalan di port `8080` Server 2 dengan plugin *Collectives*.

*   **4.2 Multi-Host Auto-Detection & CORS Web Font Shield:**
    *   **Dynamic BASE_URL Detection:** Berkas `config.php` wajib mengenali secara dinamis request host yang berasal dari domain publik, hostname internal (`vibe`, `simgos`, `*.lite`, `*.lan`), IP LAN (`192.168.*`, `10.*`, `172.*`), maupun Tailscale (`100.*`). Dilarang mem-fallback request LAN secara kaku ke domain publik karena memicu pemblokiran CORS oleh peramban.
    *   **Web Font CORS Header:** Nginx wajib menyertakan blok konfigurasi font dengan header `Access-Control-Allow-Origin "*"` untuk mencegah ikon FontAwesome dan Glyphicon berubah menjadi kotak kosong `[]` saat diakses antar-host.
    *   **Dynamic WebSocket Proxy (`systems/Admin.php`):** Variabel `websocket_proxy` wajib mendeteksi host aktif client (`wss://<host>/ws`) jika diakses di luar domain publik agar fitur antrean dan notifikasi realtime tidak terputus saat failover ke Server 2.

*   **4.3 Mitigasi Perbedaan Spesifikasi Hardware (GPU vs CPU):**
    *   Server 1 memiliki GPU NVIDIA (Core Ultra 7) untuk model AI lokal (vLLM, Whisper, Face Engine). Server 2 adalah mesin CPU murni (Xeon E-2224).
    *   **CPU-Native Standby Compose:** `docker-compose.standby.yml` di Server 2 wajib mengecualikan container GPU (`mlite_vllm`, `whisper-cuda`, `face-engine`, `ocr-engine`).
    *   **Fail-Fast Circuit Breaker & Cloud Fallback:** Seluruh pemanggilan modul AI lokal (`plugins/smartagent`, `plugins/dokter_ralan`, `plugins/wagateway`) wajib menyetel `CURLOPT_CONNECTTIMEOUT => 2`. Jika socket lokal tidak merespons dalam 2 detik, sistem wajib otomatis mengalihkan prompt ke OpenRouter Cloud (`gemini-2.5-flash`) secara transparan tanpa memblokir antarmuka dokter.

*   **4.4 Prosedur Sinkronisasi Kode Sumber & Keamanan Direktori Blob:**
    *   Pembaruan kode di Server 1 wajib disinkronkan ke Server 2 menggunakan skrip `scripts/sync_to_standby.sh` atau Git post-commit hook.
    *   **Larangan Transfer Data Masif:** Sinkronisasi rsync **WAJIB MENGECUALIKAN** direktori blob media dan model AI:
        `--exclude="docker/media/"` (data WA ~21 GB)
        `--exclude="docker/ollama/"` (model AI ~10 GB)
        `--exclude="docker/models/"`
        `--exclude=".git/"`
        `--exclude="backup_simgos/"`
