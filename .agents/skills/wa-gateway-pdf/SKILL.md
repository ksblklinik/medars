---
name: wa-gateway-pdf
description: >
  Standar Cetak PDF (mPDF 8.x) dan Kebijakan Pengiriman WhatsApp Gateway (Dual-Session,
  Proteksi Anti-Banned Meta 10-Lapis, SSOT Whitelist per Gateway Aktif, Conversational
  Spintax, Zero-Reply QR Check-In, dan Gatekeeper Display TV). Panggil skill ini saat
  menyentuh plugins/wagateway, anjungan, pasien, frontoffice, cetak PDF mPDF, atau
  tombol/endpoint kirim WhatsApp di modul mana pun.
---

# Standar Cetak PDF (`mPDF 8.x`) & WhatsApp Gateway Anti-Banned Meta (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `bridging-ai-infra` jika menyentuh Chatbot AI CS WhatsApp (`vLLM` lokal `gemma-4-12b` & fallback cloud di `plugins/wagateway/AIService.php`).
> - Padukan dengan `kasir-billing-akuntansi` atau `klinis-farmasi-klaim` saat menyentuh pengiriman PDF kwitansi kasir, hasil lab, atau e-resep via WA.

---

## 1. Panduan Cetak PDF (mPDF 8.x) & Penanganan Layout (Bab 4 & 12)

*   **Pewarisan Font (Font-Size Inheritance):** Selalu nyatakan `font-size` dan `font-family` inline secara langsung pada tag `<table>` dan tag `<td>` utama.
*   **Penonaktifan Autoshrink:** Wajib set opsi `'shrink_tables_to_fit' => 0` saat instansiasi `\Mpdf\Mpdf`.
*   **Pencegahan Layout Collapse:** Gunakan persentase lebar kolom yang jelas dan tegas pada kolom luar (contoh: `width="8%"` untuk kolom `R/` dan `width="92%"` untuk konten resep). Dilarang mencampur piksel tanpa unit dengan persentase atau memakai `width="1%"`.
*   **Metadata Judul Dokumen (Nama File Unduhan):** Wajib `$mpdf->SetTitle('Nama File Pasien - Nomor Resep');` sebelum di-output-kan menggunakan header manual agar browser mengunduh dengan nama yang benar saat mode inline / embed.

---

## 2. Arsitektur Dual-Session WhatsApp Gateway, Failover & Gatekeeper (`WhatsAppService.php` — PLN-12 & PLN-13)

Seluruh operasional pengiriman dan verifikasi WhatsApp berpusat di [`plugins/wagateway/WhatsAppService.php`](file:///opt/app/ksmlite/plugins/wagateway/WhatsAppService.php):

*   **2.1 Arsitektur Dual-Session (`system` vs `cs` — `PLN-12`):**
    1. **Sesi 1 — Sistem Transaksional (`channel = 'system'`, setting `wagateway.phonenumber_system` / `getInstanceSystem()`):** Khusus pengiriman notifikasi satu arah SIMRS (kwitansi kasir, e-resep, hasil lab, pengingat booking) dan penerimaan verifikasi QR check-in (Zero-Reply).
    2. **Sesi 2 — Customer Service (`channel = 'cs'`, setting `wagateway.phonenumber_cs` / `getInstanceCs()`):** Khusus obrolan dua arah Live Chat CS & Chatbot AI (`wagateway.ai_active_instance`). **Bot AI dilarang keras merespons pesan masuk pada nomor Sistem.**
    3. **Multi-Session Inbox:** Tabel `mlite_wagateway_inbox` menyimpan `instance_id` agar balasan CS otomatis di-route melalui sesi yang sesuai.
*   **2.2 Failover Antar-Sesi Outbound & Dynamic QR Failover Inbound (`PLN-12`):**
    *   **Outbound Failover (`canFailoverToCs`):** Jika nomor Sistem terputus saat mengirim notifikasi darurat (kwitansi/e-resep), pengiriman otomatis dialihkan sementara ke nomor CS (`last_failover_to`) dengan **Non-Admin Whitelist Gate** (role non-admin hanya diizinkan failover jika nomor tujuan berstatus Whitelist terverifikasi demi melindungi reputasi nomor CS).
    *   **Inbound QR Failover (`getHealthyVerificationGateway` & `checkMultiConnectionHealth`):** Jika nomor Sistem offline, pembuatan QR verifikasi di Gatekeeper/Loket/Kiosk otomatis mengalihkan tautan `wa.me` ke nomor CS yang sehat, disertai sinkronisasi ganda whitelist pasien ke nomor Sistem & CS.
    *   **Graceful Skip QR Saat Semua Sesi Offline (`all_offline` / `skip_qr = true`):** Jika kedua sesi (Sistem & CS) terputus, backend mengembalikan `skip_qr = true` sehingga Loket Pendaftaran (`booking.html`), Pos Gatekeeper (`display.gatekeeper.html`), dan Kiosk Pasien (`pasien/form-validation.js`) otomatis melewati blokir QR dan mengizinkan input/check-in langsung.
*   **2.3 Self-Contained Kiosk Push General Consent (`PLN-13`):**
    *   Pendorong tampilan Persetujuan Umum (*General Consent*) ke layar Kiosk mandiri di [`plugins/pasien/Admin.php`](file:///opt/app/ksmlite/plugins/pasien/Admin.php) menggunakan handler internal `postPushKioskDisplay()` (`pasien/pushkioskdisplay`) tanpa ketergantungan hak akses ke modul `anjungan`.
*   **2.4 Pengiriman WA Lampiran PDF Terisolasi:**
    ```php
    if (class_exists('Plugins\Wagateway\WhatsAppService')) {
        $wa = new \Plugins\Wagateway\WhatsAppService($this->settings);
        $wa->sendMedia($no_wa, $filePath, $caption, 'document');
    }
    ```
*   **2.5 Standar Klasifikasi Kontak WhatsApp (SSOT 4-Tier CRM):**
    *   🟢 **Whitelist (Hijau):** Kontak terpercaya dengan riwayat interaksi riil (pernah chat masuk di `mlite_wagateway_inbox` dengan `key_from_me = 0`) atau telah terverifikasi pendaftaran resmi di `mlite_wa_verified_numbers`. Wajib menjadi target prioritas utama dengan risiko blokir 0%.
    *   🟡 **Aktif WA (Kuning):** Nomor yang terdaftar di WhatsApp berdasarkan status `pasien.is_wa_aktif = '1'`, namun belum memiliki riwayat obrolan dua arah dengan klinik.
    *   ⚪ **Lead (Putih / Abu-abu):** Seluruh nomor pendaftaran pasien di database SIMRS (`pasien.no_tlp`) yang belum diverifikasi status WhatsApp-nya.
    *   🔴 **Opt-Out / DND (Merah):** Pasien yang meminta berhenti berlangganan di `mlite_wa_broadcast_optout`. **WAJIB DIEKSKLUSI OTOMATIS** dari seluruh broadcast promosi.
    *   **Helper Terpusat:** Seluruh filter query klasifikasi WhatsApp wajib memanggil `\Plugins\Wagateway\WhatsAppService::getWhitelistSqlCondition($phoneColumn, $gatewayNumber)` dan `\Plugins\Wagateway\WhatsAppService::getTierSqlCondition($tier, $phoneColumn, $activeFlag)`.
*   **2.6 Jadwal Praktek SSOT & Ruangan Murni (Display TV):**
    *   Dokter otomatis ditutup dari display TV jika di luar jam shift (`is_active_time == false`).
    *   Tampilan TV wajib 100% merefleksikan nilai kolom `room` tabel `jadwal` tanpa manipulasi index.

---

## 3. Kebijakan Pengiriman WhatsApp & Proteksi Anti-Banned Meta (SSOT) (Bab 13.1)

1. **Penguncian Permanen Pengiriman Massal (Bulk Booking/Broadcast):**
   * Seluruh antarmuka pengiriman massal wajib mengunci sasaran ke nomor **Whitelist (Tier 1)** secara permanen (`checked disabled` pada UI dan `isWhitelistActive = true` di JS/Backend).
   * Dilarang keras menyediakan tombol atau dialog konfirmasi untuk membuka pengiriman massal ke nomor Tier 2 (Aktif Non-Whitelist) atau Tier 3 (Lead).

2. **Otorisasi Pengiriman WhatsApp Satuan (Single WA Dual-Layer Guard):**
   * **Role Admin:** Berwenang penuh mengirim pesan ke nomor mana pun (bebas).
   * **Role Non-Admin (Rekam Medis, Paramedis, Dokter, Apotek, Kasir, Manajemen):** **HANYA** diizinkan mengirim ke nomor yang telah berstatus Whitelist.
   * **Proteksi UI (Frontend):** Tombol pemicu kirim WhatsApp satuan (termasuk pada `antrian.html`, `booking.html`, `display.html`, `booking.periksa.html`, `rincian.html`, kwitansi kasir `billing.besar.html`, lembar hasil `cetakhasil.html`, surat rujukan `bridgingpcare.rujukan.cetak.html`) **WAJIB** dibungkus kondisi:
     ```html
     {if: !empty($is_admin) || !empty($is_whitelist)}
     <!-- Tombol Kirim WA & Modal WA -->
     {/if}
     ```
     Atau jika di dalam loop DataTables/tabel per baris:
     ```html
     {if: $this->core->getUserInfo('role') == 'admin' || !empty($value.is_whitelist)}
     ```
   * **Proteksi Backend (Controller):** Seluruh endpoint controller penerima pengiriman WA (`api/kirimwa`, `api/kirimPesan`, `rawat_jalan/kirimwabooking`, `pasien/kirimwapasien`, `apotek_ralan/kirimwaeresep`, `dokter_ralan/kirimwaeresep`, `rawat_jalan/kirimlinkpersetujuanmedis`, `dokter_igd/kirimlinkpersetujuanmedis`, `manajemen/sendwabookingreminder`, `laporan/kirimwadokter`) **wajib memvalidasi**:
     ```php
     $isAdmin = ($this->core->getUserInfo('role') === 'admin');
     if (!$isAdmin) {
         $isWhitelisted = \Plugins\Wagateway\WhatsAppService::isPhoneNumberWhitelisted($this->db()->pdo(), $no_wa);
         if (!$isWhitelisted) {
             echo json_encode(['status' => 'error', 'msg' => "Pengiriman ditolak: Nomor ($no_wa) belum terverifikasi (Bukan Whitelist). Pengiriman ke nomor non-whitelist hanya dapat dilakukan oleh Admin."]);
             exit;
         }
     }
     ```

3. **Definisi SSOT Whitelist:**
   * Ditentukan secara terpusat oleh helper `WhatsAppService::isPhoneNumberWhitelisted(\PDO $pdo, string $phoneNumber, ?string $gatewayNumber = null): bool`.
   * Mengakomodasi normalisasi format nomor (`08xxx` dan `628xxx`) yang terikat pada **Nomor Gateway Aktif (`no_sistem`)** via `WhatsAppService::getActiveGatewayNumber($pdo, $channel)`:
     1. `mlite_wa_verified_numbers` (`status_verifikasi = 'terverifikasi'` DAN `no_sistem IN (:g08, :g62)`).
     2. Kontak internal klinik (`dokter.no_telp` dan `petugas.no_telp`) sebagai kontak resmi dokter/pegawai internal.

4. **Isolasi Whitelist Multi-Gateway (Active Gateway Scoping):**
   * **SSOT Gateway Aktif:** Nilai `WhatsAppService::getActiveGatewayNumber($pdo, 'system')` (`wagateway.phonenumber_system` / `wagateway.phonenumber`) adalah Single Source of Truth penentu identitas pengirim sekaligus batas lingkup whitelist yang berlaku saat itu.
   * **Relasi Sender-Recipient:** Whitelist bukanlah properti global pasien, melainkan relasi pasangan: `(Nomor Gateway Aktif <-> Nomor Pasien)`. Pasien yang pernah berinteraksi dengan gateway lama tidak otomatis aman dikirimi pesan oleh gateway baru.
   * **Larangan Query Telanjang:** Dilarang keras melakukan query `SELECT ... FROM mlite_wa_verified_numbers` tanpa klausa penyaring `no_sistem IN (:g08, :g62)` sesuai gateway aktif. Wajib menggunakan `WhatsAppService::isPhoneNumberWhitelisted()` atau helper `getWhitelistSqlCondition()`.
   * **Auto-Sync Inbound:** Setiap pesan masuk dari pasien (`key_from_me = 0`) atau verifikasi via Gatekeeper QR wajib disinkronkan ke `mlite_wa_verified_numbers` dengan `no_sistem = activeGateway`.
   * **Zero Migration Friction:** Pergantian nomor gateway di menu pengaturan tidak memerlukan migrasi database dan tidak menghapus histori whitelist gateway lain. Riwayat lama otomatis aktif kembali jika nomor lama dipasang ulang.

5. **Kebijakan Verifikasi Tanpa Balasan Keluar (Zero-Reply Verification Policy):**
   * Saat pasien memindai QR code verifikasi WhatsApp (`REG-XXXXXX`) di loket pendaftaran atau pos gatekeeper, gateway **DILARANG KERAS** membalas pesan keluar apapun ke HP pasien (0 outbound response).
   * Webhook cukup menangkap pesan masuk, mencatat nomor pasien ke `mlite_wa_verified_numbers`, dan memperbarui status sesi token menjadi `'verified'`.
   * Layar monitor loket/gatekeeper petugas wajib mendeteksi verifikasi secara *real-time* via WebSocket atau interval polling.
   * **Tujuan:** Mencegah lonjakan trafik bot sintetis berkecepatan 0ms yang langsung dicurigai dan diblokir oleh algoritma keamanan Meta pada nomor baru (*cold account*).

6. **Pola Teks Masuk Alami & Dinamis (Conversational Spintax Inbound Gate):**
   * Tautan QR Code verifikasi WhatsApp yang ditampilkan di layar Anjungan, Loket Pendaftaran, Pos Gatekeeper, dan Form Pendaftaran Pasien Baru **DILARANG KERAS** menggunakan format teks pendek seragam/kaku seperti `REG-XXXXXX` telanjang atau `Verifikasi nomor HP kode REG-XXXXXX`.
   * Wajib menggunakan helper resmi `WhatsAppService::generateConversationalInboundText($token, $clinicName)` yang menghasilkan variasi salam, maksud tujuan, dan kalimat penutup sopan secara acak (*spintax dinamis*) dengan tetap menyematkan token `REG-XXXXXX`.
   * **Tujuan:** Mengeliminasi deteksi anti-fraud Meta terhadap pola banjir teks seragam (*scam/phishing token drop collector*) pada pesan masuk jam operasional pagi.

7. **Peralihan 100% General Consent Fisik & Larangan Tautan Keluar Sepihak:**
   * Persetujuan Umum (*General Consent*) dan edukasi pasien wajib 100% dilakukan melalui Kiosk atau Layar Monitor fisik Loket Pendaftaran.
   * Dilarang keras menyediakan tombol pengiriman tautan persetujuan umum atau dokumen eksternal via pesan WhatsApp keluar ke pasien baru.
   * Pengiriman URL keluar secara sepihak ke kontak yang belum menyimpan nomor klinik memicu klasifikasi pesan sebagai *phishing / malicious link* oleh Meta.

8. **Validasi Penerima Global Sebelum Pengiriman (Global Pre-Send On-WhatsApp & Syntax Gate):**
   * Di dalam kelas inti `\Plugins\Wagateway\WhatsAppService`, method `sendText()` dan `sendMedia()` (`validateRecipientOnWhatsApp($number)`) **wajib menerapkan validasi pra-kirim otomatis** sebelum melakukan panggilan HTTP ke instance:
     1. **Filter Sintaks Seluler Indonesia:** Format nomor wajib valid seluler `^628[1-9][0-9]{7,12}$`. Nomor telepon kabel rumah (PSTN 021/022/dll.), nomor kurang digit, atau nomor dummy otomatis ditolak seketika (0ms) di tingkat server lokal tanpa sempat ditembakkan ke Meta.
     2. **Live On-WhatsApp Check:** Memverifikasi status aktif nomor ke server WhatsApp via `/chat/whatsappNumbers/{instance}` dengan batas timeout ringkas (5 detik). Jika `exists === false` (nomor tidak punya akun WA), pengiriman dibatalkan seketika.
     3. **Auto-Mark Unreachable:** Nomor yang terbukti tidak aktif di WhatsApp otomatis diubah statusnya menjadi `'unreachable'` di `mlite_wa_verified_numbers` agar tidak diproses lagi di hari-hari berikutnya.
     4. **Pencegahan Alarm Palsu Circuit Breaker:** Pada modul pengiriman massal, nomor yang tidak aktif di WA dilewati secara anggun (*skip*) dan **tidak dihitung sebagai kegagalan teknis gateway**, sehingga Circuit Breaker tidak terpicu secara salah.

9. **Proteksi Anti-Spam Detected & Circuit Breaker Pengiriman Massal:**
   * **Pre-Flight Gateway Check:** Sebelum pengiriman massal dimulai, sistem wajib memvalidasi status koneksi gateway (`checkConnectionHealth`). Jika status tidak `open` atau mengalami error sesi (seperti `Code 401`), pengiriman massal seketika dibatalkan otomatis.
   * **Circuit Breaker Auto-Stop:** Loop pengiriman massal wajib seketika berhenti darurat (`auto-abort`) jika:
     - Backend mendeteksi respon error dari Meta (HTTP 401, 403, 428, 429 Rate Limit, `Connection Closed`, `device_removed`, `conflict`, `banned`, atau `PENDING`).
     - Terjadi kegagalan beruntun pada 3 nomor berturut-turut (`consecutiveFailures >= 3`).
   * **Variasi Pesan Dinamis (Anti-Fingerprinting):** Menggunakan variasi salam pembuka (*Spintax*), sinonim kalimat himbauan, dan menyertakan token referensi acak unik (`🔖 _Ref: #BKG-XXXXXX_`) di baris paling bawah untuk mencegah Meta mendeteksi kesamaan byte-for-byte isi pesan broadcast.
   * **Humanized Throttling & Cooling Pause:** Jeda acak antar-pesan 4–8 detik untuk meniru ritme manusia, ditambah jeda pendinginan selama 20 detik setiap kelipatan 10 pesan terkirim.
