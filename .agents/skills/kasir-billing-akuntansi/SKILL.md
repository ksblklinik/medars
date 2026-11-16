---
name: kasir-billing-akuntansi
description: >
  Standar Modul Kasir, Split Billing [T] vs [K], mlite_billing_ledger, Retur & Void
  Anti-Fraud, Jurnal Akuntansi (1101.01 vs 1103.xx), Isolasi Entitas Retail Apotek Umum
  (POS 5 Cara Bayar & PO-APT), dan Order Expiry SSOT (Rekonsiliasi Harian Order).
  Panggil skill ini saat menyentuh plugins/kasir_rawat_jalan, kasir_rawat_inap,
  kasir_igd, apotek_umum, keuangan, jasa_medis, laporanrekonsiliasiorder, atau PaymentService.php.
---

# Standar Kasir, Billing Ledger, Anti-Fraud, POS Apotek Umum & Akuntansi (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `klinis-farmasi-klaim` untuk aturan SSOT jenis klaim (`Tunai`, `Kapitasi`, `Non-Kapitasi`) dan pemisahan nomor order `PL` laboratorium.
> - Padukan dengan `wa-gateway-pdf` saat menyentuh cetak kuitansi/faktur PDF atau tombol kirim kuitansi via WhatsApp.

---

## 0. Arsitektur Core Billing Engine (`systems/lib/PaymentService.php` — PLN-06, PLN-07, PLN-08)

Seluruh sinkronisasi tagihan klinik wajib melalui [`systems/lib/PaymentService.php`](file:///opt/app/ksmlite/systems/lib/PaymentService.php):
*   **Daftar Method Inti `PaymentService`:**
    *   `syncToLedger($no_rawat, $db, $settings, $user_info, $kd_billing = '')`
    *   `syncBillingSummary($no_rawat, $db)`
    *   `checkAndFinalize($no_rawat, $db, $settings, $user_info)`
    *   `autoEksekusi($no_rawat, $db, $settings, $user_info)`
    *   `generateAutoJournal($no_rawat, $db, $settings, $user_info)`
    *   `hasRequests($no_rawat, $db)` & `hasUnpaidTunai($no_rawat, $db, $settings)`
    *   `settlePointOfCarePayment($no_rawat, $db, $settings, $user_info, $tindakan_data, $nama_poli = 'Poli')`
*   **4 Pilar `claim_type` Murni di `mlite_billing_ledger` (`PLN-07` & `PLN-08`):**
    *   Hanya gunakan 4 nilai baku pada kolom `claim_type`: **`Tunai`**, **`Kapitasi`**, **`Non-Kapitasi`**, dan **`Piutang`**. Dilarang menggunakan istilah legacy `'Mandiri'`.
    *   Kolom `status` pada `mlite_billing_ledger` mendukung ENUM `'Bundled'` (sinkron dengan `mlite_sep_internal`).
    *   Tipe item pemeriksaan radiologi di ledger dan jurnal wajib dinormalisasi menjadi `'radiologi'` (bukan `'rad'`).
    *   `syncBillingSummary()` murni menghitung `grand_total_mandiri` dari item ber-`claim_type = 'Tunai'` (agar tidak salah menagih tunai pada pasien piutang rekanan).
    *   `syncToLedger()` otomatis mengatribusikan `provider_id` ke **dokter pengganti aktif** (`PLN-06`) demi akurasi perhitungan `plugins/jasa_medis`.

---

## 1. Standar Modul Kasir, Retur & Refund, Anti-Fraud & Verifikasi Publik (Bab 26)

*   **1.1 Retur Pembayaran Penuh (Full Void):**
    *   **Syarat Mutlak Nol Layanan:** Status pasien `Belum`, tidak ada SOAP medis, tidak ada tindakan medis/paramedis, tidak ada obat apotek, dan belum ada sampling lab/radiologi.
    *   **Integritas Void:** Update `reg_periksa` (`stts = 'Batal'`, `status_bayar = 'Belum Bayar'`), hapus tagihan `mlite_billing`, `mlite_billing_ledger`, `mlite_kasir_item_lunas`, `mlite_sep_internal`, dan fisik PDF invoice. Catat jejak audit permanen ke `mlite_retur_billing`.
*   **1.2 Refund Sebagian Item Penunjang (Partial Refund):**
    *   Hanya untuk item order penunjang (Lab/Radiologi) berstatus `LUNAS` yang belum diambil sampelnya (`tgl_sampel = '0000-00-00'`). Kurangi billing, terbitkan jurnal pembalik, dan cetak Bukti Retur bertipe Partial.
*   **1.3 Prosedur Jurnal Pembalik (Reversal Entry):**
    *   Dilarang menghapus record di `mlite_jurnal`. Wajib terbitkan Jurnal Pembalik berpasangan seimbang (Debet Pendapatan vs Kredit Kas Kasir `1101.01`).
*   **1.4 Proteksi Anti-Fraud:**
    *   Nomor Invoice stabil `INV/{YYYYMMDD}/{NoRawat}`.
    *   Jika transaksi void dicetak ulang, wajib tampilkan banner tebal `FAKTUR / KUITANSI TIDAK BERLAKU`.
*   **1.5 Transaksi Pasien BPJS Murni (100% Kapitasi / Bundled):**
    *   Tagihan mandiri wajib Rp 0 di `mlite_billing`. Struk kasir thermal menyembunyikan nominal klaim internal dan mencetak `(DIJAMIN BPJS)`.
    *   **Isolasi Kas Fisik:** Hanya membentuk Jurnal Manajerial (Debet `1103.01` vs Kredit Pendapatan `4101.xx`) dan **DILARANG KERAS** mendebet Akun Kas Fisik (`1101.01`).
*   **1.6 Split Billing BPJS (Campuran `[K]` & `[T]`):**
    *   Kasir dapat memilah selector per item (`[T]`, `[K]`, `[NK]`). Hanya porsi Tunai `[T]` yang ditagihkan mandiri dan dijurnal mendebet Kas Fisik Kasir (`1101.01`).
*   **1.7 Isolasi Penuh Pembukuan Keuangan & Pengadaan Apotek Umum (100% Standalone Retail Entity — PLN-01 & PLN-09):**
    *   **Entitas Pembukuan Mandiri:** Modul `plugins/apotek_umum` memiliki pembukuan keuangan, laporan laba rugi, HPP, margin, dan arus kas yang **100% terpisah dari pembukuan klinik/rawat jalan**.
    *   **Smart POS QR Scanner Auto-Load (`PLN-09`):** Pasien dari poli cukup membawa struk antrean ber-QR No. RM ke Apotek Umum; scanner di `#apt_pos_search` / modal `[F4]` memanggil `anyLoadresep` untuk menarik obat resep ke keranjang POS dengan tarif retail `mlite_harga_apotek_umum` tanpa mencemari billing klinik.
    *   **Partisi Dua Arah Surat Pemesanan (Two-Way PO Isolation):**
        *   Surat Pemesanan (PO) Apotek Umum wajib menggunakan format nomor `PO-APT-YYYYMMDD-XXXX`.
        *   Modul Apotek Umum hanya menampilkan dan memproses PO dengan prefiks `PO-APT-%`.
        *   Modul Farmasi Klinik (`plugins/farmasi`) wajib mengecualikan seluruh PO Apotek Umum (`WHERE no_pemesanan NOT LIKE 'PO-APT-%'`).
    *   **Partisi Dua Arah Penerimaan & Faktur PBF (Two-Way Purchase & Stock Isolation):**
        *   Seluruh barang masuk dari faktur PBF Apotek Umum dicatat dengan mutasi eksklusif ke **Depo Apotek Umum (`kd_bangsal = 'APT'`)** pada `gudangbarang` dan `riwayat_barang_medis`.
        *   Modul Apotek Umum hanya menampilkan faktur yang mutasinya masuk ke Depo `APT`.
        *   Modul Farmasi Klinik (`plugins/farmasi`) hanya menampilkan faktur gudang klinik induk (`kd_bangsal != 'APT'`).
    *   **Isolasi Tabel, Kas & Stok Minimal:** Transaksi Apotek Umum dicatat pada tabel retail (`mlite_penjualan`, `mlite_penjualan_detail`, `mlite_penjualan_billing`, `mlite_harga_apotek_umum`, `mlite_kasir_shift`) dan **DILARANG KERAS** mencemari `mlite_billing`, `mlite_billing_ledger`, ataupun akun kas kasir klinik (`1101.01`). Stok minimal peringatan Apotek Umum diisolasi pada `mlite_harga_apotek_umum.stok_minimal` terpisah dari `databarang.stokminimal`.
    *   **Proteksi Master Data Klinik dari Apotek Umum:** Pada formulir edit produk di Apotek Umum, jika obat merupakan Master Obat Klinik Induk (kode non-`APT%` seperti `B...`), kolom identitas fisik (`nama_brng`, `kode_kategori`, `sediaan`, `kode_sat`, `zat_aktif`) **WAJIB DIKUNCI (READONLY)** dan hanya kolom retail (`h_beli`, `margin_persen`, `stok_minimal`, `harga_jual`) yang dapat diedit oleh Apotek Umum demi mencegah perusakan master obat klinik.
    *   **Jalur Obat Tambahan Non-BPJS:** Resep obat tambahan/non-formularium yang dibeli pasien BPJS di Apotek Umum diperlakukan murni sebagai transaksi retail bebas tanpa mengubah tagihan BPJS klinik (tetap Rp 0).
*   **1.8 Standar Perlakuan Pasien Asuransi Swasta & Instansi Kerjasama (Non-BPJS — PLN-10):**
    *   **Prinsip Kesetaraan Klinis Pasien Umum:** Seluruh pasien dengan penjamin non-BPJS (seperti Mandiri Inhealth `INH`, BPJS Ketenagakerjaan `BTK`, Stikes Budi Luhur `SBL`, SD Budi Luhur `SDB`, dll.) **wajib diperlakukan persis sama dengan pasien Umum/Retail** di seluruh modul klinis (`dokter_ralan`, `dokter_ranap`, `igd`, `laboratorium`, `radiologi`, dan `apotek_umum`).
    *   **Apotek & Resep:** Pasien non-BPJS dilayani dari Depo Apotek Umum (`APT`) menggunakan katalog retail bebas tanpa batasan formularium JKN BPJS Ralan.
    *   **Penyelesaian Kasir & Piutang (Settlement Isolation):** Perbedaan dengan pasien Umum **hanya terjadi pada saat pelunasan di Kasir (Kasir Ralan, Kasir Ranap, atau POS Apotek Umum)**, di mana transaksi diselesaikan sebagai **Piutang Penjamin** (`claim_type = 'Piutang'`).
    *   **Jurnal Keuangan:** Sistem kasir wajib mendebet akun Piutang Penjamin terdaftar di `mlite_akun_penjab` (`1103.xx`, misal `1103.05` INH, `1103.06` BTK, atau fallback `keuangan.akun_debet_piutang` / `1103.01`) dan **DILARANG** mendebet Kas Fisik Kasir (`1101.01`).
    *   **Multi-Payment POS Apotek Umum:** Kasir POS Apotek Umum wajib menyediakan 5 opsi pembayaran (`Tunai`, `QRIS`, `Debit EDC`, `Transfer`, `Piutang/Klaim`) lengkap dengan data isian spesifiknya.

---

## 2. Pengelolaan Order & Integrasi Billing Laboratorium (Bab 15 & 16)

*   **2.1 Pemisahan Nomor Order (PL) Berdasarkan Status Kasir:**
    *   Jika nomor PL lama **sudah Paid/Approved** di Kasir, penambahan rincian lab baru **WAJIB dibuatkan nomor PL baru** (contoh: `PL202608030002`) agar kasir mendeteksi tagihan tambahan tersebut. Dilarang menulis langsung ke billing tanpa nomor PL baru.
*   **2.2 Lock Tombol Validasi:**
    *   Tombol Validasi di laboratorium wajib terkunci jika order/item belum diapprove/dilunasi di Kasir.
*   **2.3 Pemisahan PL Berdasarkan Jenis Klaim:**
    *   Item dengan klaim berbeda (Kapitasi vs Tunai) wajib dipisahkan ke nomor PL yang berbeda. Petugas laboratorium dilarang memilih klaim Kapitasi (wajib dari modul dokter ralan).
*   **2.4 Pencocokan Record Ledger:**
    *   Pencocokan eksekusi klinis di `PaymentService::syncToLedger()` wajib menggunakan pasangan kolom `no_rawat` dan `kd_jenis_prw` (jangan bergantung pada `tgl_sampel`/`jam_sampel`).

---

## 3. Siklus Hidup Status Bayar & Kebijakan Kedaluwarsa Order (Order Expiry SSOT) (Bab 11)

*   **3.1 Trigger Perubahan Kembali ke Status 'Belum Bayar':**
    *   Pasien yang sebelumnya telah lunas / `Sudah Bayar` (misal registrasi awal/konsultasi sudah dilunasi, atau pasien 100% BPJS) sah berbalik menjadi `Belum Bayar` jika dan hanya jika terdapat **order layanan tunai baru**, seperti:
        1. Resep obat tunai baru / split billing di luar paket.
        2. Permintaan pemeriksaan laboratorium atau radiologi berbayar tunai.
        3. Tindakan medis/tindakan keperawatan tambahan di poli dengan tarif tunai non-zero yang belum dilunasi.
    *   **Dilarang Keras:** Membalikkan status pasien menjadi `Belum Bayar` secara buta hanya karena dokter menyimpan SOAP RME atau menyimpan pemeriksaan/tindakan 100% BPJS (klaim Kapitasi Rp 0) atau tindakan konsultasi awal yang sudah lunas.
*   **3.2 Kebijakan Rekonsiliasi Sadar Penanggung Jawab Unit (*Conscious Daily Reconciliation*):**
    *   **Anti-Moral Hazard SSOT:** Untuk mencegah celah pelepasan tanggung jawab (*moral hazard*) dan kebocoran pendapatan (*revenue leakage*), sistem **DILARANG MENGHAPUS TAGIHAN SECARA DIAM-DIAM DI LATAR BELAKANG (*NO BLIND SILENT CRON DELETES*)**.
    *   Penyelesaian order layanan yang belum dilayani (*unperformed*) wajib dilakukan **secara sadar oleh Penanggung Jawab (PIC) masing-masing unit** melalui antarmuka **Rekonsiliasi Harian Order & Piutang Menggantung** di modul pelaporan (`plugins/laporan` rute `laporanrekonsiliasiorder`).
    *   **Verifikasi Fisik Mutlak Sebelum Pembatalan:**
        1. **Farmasi (E-Resep):** PIC Farmasi wajib memastikan obat fisik belum diserahkan (`tgl_penyerahan = '0000-00-00'`) dan kartu stok tidak berkurang sebelum membatalkan order. Jika fisik terlanjur diserahkan karena lupa input, PIC wajib menginstruksikan validasi susulan agar pendapatan klinik tidak bocor.
        2. **Laboratorium:** PIC Lab wajib memastikan darah/urin belum diambil (`tgl_sampel = '0000-00-00'`) dan tidak ada hasil tes.
        3. **Radiologi:** PIC Radiologi wajib memastikan foto rontgen belum diambil (`tgl_sampel = '0000-00-00'`).
    *   **Kewajiban Input Alasan Pembatalan & Catatan Audit Permanen:**
        *   Setiap tindakan pembatalan wajib mengisi formulir alasan pembatalan resmi (contoh: *"Pasien menolak obat racikan"*, *"Pasien batal cek lab karena tidak bawa biaya"*).
        *   Sistem otomatis mencatat identitas eksekutor (`user_id`), peran (`role`), waktu, dan alasan pembatalan ke tabel jejak audit permanen `mlite_order_reconciliation_logs`.
*   **3.3 Integritas Buku Besar Keuangan (*Ledger Immutability*):**
    *   Item transaksi pada `mlite_billing_ledger` **DILARANG KERAS DI-DELETE**.
    *   Status item yang dibatalkan sah diubah menjadi **`'Cancelled'`** (atau `'Expired'`) dengan timestamp `updated_at = NOW()`.
    *   Nilai tagihan pending dinolkan dari total perhitungan kasir aktif, namun nilai historis, nama item, dokter pengorder, dan nominal transaksi tetap tersimpan utuh selamanya demi keperluan audit keuangan klinik.
    *   **Pemulihan Status Kunjungan:** Pasien otomatis dipulihkan menjadi `'Sudah Bayar'` jika dan hanya jika seluruh sisa piutang tunai pending telah bernilai 0 dan kunjungan awal telah lunas atau dijamin BPJS.
*   **3.4 Pembatasan Wewenang Per Unit (*Segregation of Duties & RBAC Matrix*):**
    *   Izin akses dikendalikan melalui RBAC Matrix `plugins/laporan` (`laporan.role_permissions` izin `laporanrekonsiliasiorder`).
    *   Petugas Farmasi hanya berwenang membatalkan order resep obat.
    *   Petugas Laboratorium hanya berwenang membatalkan order laboratorium.
    *   Petugas Radiologi hanya berwenang membatalkan order radiologi.
    *   Hanya Administrator dan Manajemen yang berwenang membatalkan order lintas unit.
