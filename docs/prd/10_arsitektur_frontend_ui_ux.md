# PRD 10: Desain Antarmuka, Hotkeys Operasional & Arsitektur Frontend Modern (UI/UX)

## 1. Ikhtisar Domain
Domain ini menetapkan standar desain visual, tata kelola komponen antarmuka, responsivitas multi-perangkat (Desktop, Mobile, Kiosk, Display TV), konvensi pintasan keyboard, serta arsitektur rendering komponen yang bersih bebas dari masalah penumpukan DOM tersembunyi (*zero hidden-show DOM bloat*).

---

## 2. Pilihan Arsitektur: Opsi 3 (Modern Foundation with 100% Visual & Ergonomic Parity)

Sistem baru **ksm-next** memegang prinsip mutlak: **"Identik di Mata Pengguna, Unggul di Balik Layar"**:
- **Tampilan Visual Semirip Mungkin:** Warna khas Medical Teal (`#00897b`), tata letak topbar, susunan menu ikon cepat (`.menu-icon`), sidebar modul collapsible, posisi form input (`#form`) di atas/kiri dan tabel data (`#display`) di bawah/kanan dipertahankan semirip mungkin dengan `ksmlite`.
- **Zero Learning Curve:** Staf klinik (dokter, perawat, kasir, petugas apotek) dapat langsung bekerja tanpa perlu masa orientasi atau pelatihan ulang (*0-day learning curve*).
- **Mesin Modern di Balik Layar:** Di balik tampilan yang familiar, arsitektur teknisnya dimodernisasi total menggunakan Bootstrap 5 modern, CSS Grid/Flexbox asli, SSE real-time, dan pemuatan komponen bebas dari masalah tumpukan *hidden-show*.

---

## 3. Solusi Terhadap Masalah Arsitektur Eksisting ("Tumpuk-Tumpuk Hidden-Show")

Pada sistem lama, satu halaman memuat puluhan sub-form, modal, dan tabel tersembunyi (`display: none` / `.hidden`). Hal ini menimbulkan:
- **DOM Bloat & Memory Leak:** Ribuan node DOM mengendap di RAM browser, menyebabkan aplikasi melambat seiring bertambahnya pasien yang dilayani.
- **Konflik ID & Duplikasi Event:** Selector JavaScript salah sasaran ke elemen tersembunyi, atau event listener terikat berulang kali (*duplicate submissions*).
- **Cemaran Data Form (*Dirty Form State*):** Field input yang tersembunyi tidak sengaja ikut terkirim saat form disubmit.

### 🛡️ Arsitektur Solusi Baru di `ksm-next`:

1. **Dynamic Viewport Slot (Mount & Unmount Bersih):**
   - Area kerja modul (`#workspace`) hanya memiliki **satu container aktif**.
   - Sub-view (Form SOAP, Riwayat Pasien, E-Resep, Lab/Rad) **hanya dimuat (mount) ke DOM saat dipilih**.
   - Ketika pengguna berpindah tab/tampilan, komponen lama di-**unmount total** (elemen DOM dihapus dan event listener dilepas dari memori). Tidak ada lagi tumpukan elemen tersembunyi di latar belakang.

2. **Single-Host Dynamic Modal (`#appModalHost`):**
   - Dilarang membuat puluhan modal statis di HTML halaman.
   - Sistem hanya menyediakan **satu modal host universal** (`#appModalHost`).
   - Konten modal di-inject secara dinamis sesuai kebutuhan (misal saat tekan `F4` cari pasien), dan saat modal ditutup (`Esc` / backdrop), isi modal otomatis di-destroy (`empty()`) dan memori dibebaskan seketika.

3. **Client-Side State & Route Awareness (Hash Router):**
   - Setiap pergantian tampilan modul dikaitkan dengan hash URL (contoh: `#rawat-jalan/soap`, `#rawat-jalan/resep`).
   - Tombol *Back / Forward* browser berfungsi normal, dan jika halaman di-refresh, konteks pasien dan sub-tab aktif otomatis pulih.

---

## 4. Standar Desain Visual & Token Palet Warna Medis

Warna antarmuka mencerminkan indikator status klinis dan finansial baku:

| Token Warna | Nilai Hex | Penggunaan Operasional SIMRS |
| :--- | :--- | :--- |
| **Primary (Medical Teal)** | `#00897b` | Tombol utama, header aktif, navigasi modul. |
| **Danger (Triase Merah / Void)** | `#dc2626` | Status Gawat Darurat (IGD), Alergi Berat, Void Kasir. |
| **Warning (Triase Kuning / Pending)**| `#d97706` | Menunggu Dokter, Resep Belum Diracik, Order Pending Kasir. |
| **Success (Triase Hijau / Lunas)** | `#16a34a` | Pemeriksaan Selesai, Billing Lunas, Obat Sudah Diberikan. |
| **Kapitasi BPJS (Blue Badge)** | `#0284c7` | Badge tagihan `[K]` (Kapitasi) di Kasir dan E-Resep. |
| **Non-Kapitasi / Umum (Indigo)** | `#6366f1` | Badge tagihan `[NK]` (Non-Kapitasi) / Klaim Terpisah. |
| **Tunai Mandiri (Emerald)** | `#059669` | Badge tagihan `[T]` (Tunai). |

---

## 5. Standar Pintasan Keyboard (Hotkeys Cepat POS & Kasir)
- `F2`: Bayar / Simpan Transaksi.
- `F4`: Buka Modal Pencarian Pasien / E-Resep (auto-focus ke input pencarian).
- `F8`: Pending / Simpan Draf Transaksi.
- `F9`: Batal / Reset Formulir.
- `Esc`: Menutup modal aktif dan membersihkan host modal.

---

## 6. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_mount_view_component_on_demand_and_unmount_previous_component()`
- `test_should_clean_modal_host_dom_and_listeners_when_modal_is_closed()`
- `test_should_disable_action_button_and_display_spinner_during_async_request()`
- `test_should_trigger_expected_actions_on_f2_f4_f8_f9_keyboard_events()`
- `test_should_prevent_hidden_form_inputs_from_contaminating_payload()`
