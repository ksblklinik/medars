---
name: klinis-farmasi-klaim
description: >
  Standar Subsistem Klinis, RME Dinamis (Smart Peek), Pemetaan E-Resep & Apotek
  (Obat Jadi vs Racikan +1s), Order Lab/Radiologi, dan SSOT Penentuan Jenis Klaim
  & Tarif Layanan/Obat (Tunai, Kapitasi, Non-Kapitasi). Panggil skill ini saat
  menyentuh modul plugins/dokter_ralan, dokter_ranap, dokter_igd, rawat_jalan,
  rawat_inap, igd, apotek_ralan, apotek_ranap, farmasi, laboratorium, atau radiologi.
---

# Standar Subsistem Klinis, Farmasi, E-Resep & Klaim SSOT (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `kasir-billing-akuntansi` jika perubahan menyentuh `mlite_billing_ledger`, `PaymentService::syncToLedger()`, atau Apotek Umum (`plugins/apotek_umum`).
> - Padukan dengan `knowledge-vault-icd` jika menyentuh rekomendasi AI SOAP / grounding ICD-10.
> - **PERINGATAN JAM PELAYANAN:** Modul di dalam lingkup skill ini adalah modul pelayanan inti. Patuhi Operational Gate di `AGENTS.md` (wajib izin eksplisit user saat jam aktif Senin–Sabtu 07:00–13:00 & 15:00–20:00 WIB).

---

## 1. Pemetaan Tabel E-Resep, Resep PRB & Isolasi Depo (Bab 1, 2, 3, PLN-02, STK-01)

*   **1.1 Spesialisasi Tabel E-Resep:**
    *   **`resep_obat` (Header Induk):** Menyimpan `no_resep`, `no_rawat`, `kd_dokter`, `tgl_peresepan`/`jam_peresepan`, `tgl_perawatan`/`jam`, status penyerahan.
    *   **`resep_dokter` (Hanya Obat Jadi / Non-Racikan):** Detail item obat non-racikan, jumlah, aturan pakai.
    *   **`resep_dokter_racikan` (Header Racikan):** `nama_racik`, metode `kd_racik`, `jml_dr`, aturan pakai racikan.
    *   **`resep_dokter_racikan_detail` (Bahan Racikan):** Rincian bahan obat penyusun racikan (`no_resep`, `no_racik`, `kode_brng`, `jml`).
*   **1.2 Tabel Pelayanan / Billing Apotek:**
    *   **`obat_racikan` (Header Racikan Pelayanan):** Berelasi menggunakan kombinasi `no_rawat`, `tgl_perawatan`, dan `jam`.
    *   **`detail_pemberian_obat` (Semua Item Pelayanan):**
        *   **Offset Waktu (+1 detik):** Seluruh transaksi bahan obat racikan wajib diberikan offset waktu `+1 detik` pada kolom `jam` untuk membedakannya secara tegas dari obat non-racikan yang divalidasi bersamaan.
*   **1.3 Panduan Kueri:**
    *   Pencarian resep dari `obat_racikan` cukup mencocokkan `no_rawat` dan `tgl_perawatan` (tanggal saja).
    *   Bahan racikan pada `detail_pemberian_obat` diidentifikasi melalui kesamaan `tgl_perawatan` dan `jam` dengan record di `obat_racikan`.
*   **1.4 Resep PRB 30 Hari / Apotek Luar (`PLN-02`):**
    *   Disimpan di `mlite_pemeriksaan_ralan_metadata` (sub-panel SOAP kronis `dokter_ralan`) **tanpa memotong stok obat klinik**, serta otomatis menyinkronkan ringkasan obat PRB ke kolom Planning (`rtl`) SOAP. Dicetak sebagai lembar resep PRB ber-QR di poli atau Front Office.
*   **1.5 Isolasi Depo Farmasi (`STK-01` & `PLN-01`):**
    *   Transaksi resep/BHP IGD wajib terisolasi ke **Depo IGD (`kd_bangsal = 'IGD'`, setting `farmasi.igd = 'IGD'`)**.
    *   Transaksi Apotek Umum & pasien Non-BPJS terisolasi ke **Depo Apotek Umum (`kd_bangsal = 'APT'`)**.

---

## 2. Standar Penentuan Jenis Klaim & Tarif Layanan (SSOT) (Bab 21 & PLN-10)

*   Kolom `kd_pj` telah **dihapus permanen** dari seluruh master tindakan `jns_perawatan_*`. Dilarang keras menggunakannya sebagai gate tarif.
*   **SSOT Jenis Klaim:** Ditentukan semata-mata dari adanya nilai tarif non-zero:
    *   `harga_tunai > 0` ➔ Klaim **Tunai**
    *   `harga_kapitasi > 0` ➔ Klaim **Kapitasi**
    *   `harga_nonkapitasi > 0` ➔ Klaim **Non-Kapitasi**
*   **Larangan Gating `kd_pj` / `$is_bpjs` pada Query & Smart Claim Sorting (`PLN-10`):**
    *   Dilarang keras menyaring atau membatasi opsi baris tarif di endpoint pencarian tindakan/penunjang (`anyLayanan`, `anyLaboratorium`, `anyRadiologi`) menggunakan `$is_bpjs` atau `kd_pj` pasien.
    *   Setiap master item yang memiliki tarif non-zero wajib diekspansi menjadi baris pilihan mandiri (Tunai, Kapitasi, dan/atau Non-Kapitasi).
    *   **Smart Claim Sorting:** Untuk pasien BPJS, urutan baris hasil pencarian wajib mengutamakan **`Kapitasi` ➔ `Non-Kapitasi` ➔ `Tunai`** agar dokter tidak salah mengklik tarif tunai.
*   **Kontrak Template Frontend (`data-selected_klaim`):**
    *   Seluruh baris tabel hasil pencarian tindakan/penunjang (`layanan.html`, `laboratorium.html`, `radiologi.html`) wajib menyertakan atribut `data-selected_klaim="{$value.selected_klaim}"` pada tag `<tr>`.
*   **Integritas Penyimpanan & Sinkronisasi Ledger:**
    *   Backend `postSaveDetail()` wajib menyimpan `jenis_klaim` ke tabel header (`permintaan_lab`, `permintaan_radiologi`) dan tabel rincian (`permintaan_pemeriksaan_lab`, `permintaan_pemeriksaan_radiologi`), serta mencatat `claim_type` ke `mlite_billing_ledger`.
*   **Selektor Kasir Mandiri:**
    *   Tombol selector klaim `[T]`, `[K]`, dan `[NK]` pada modul Kasir Rawat Jalan/Inap wajib ditampilkan murni berdasarkan `harga_* > 0` tanpa dibungkus pengkondisian `kd_pj == KD_PJ_BPJS`.

*   **2.1 Standar Penentuan Tarif Obat & BHP Seragam (Uniform Flat Pricing SSOT):**
    *   Sesuai SOP Klinik, harga obat dan BHP di SIMRS ksmlite **tidak bertingkat per kelas kamar (*uniform flat pricing*)**.
    *   Seluruh kolom legacy rumah sakit besar (`ralan`, `kelas1`, `kelas2`, `kelas3`, `utama`, `vip`, `vvip`, `beliluar`, `karyawan`) telah **dihapus permanen** dari tabel master `databarang`.
    *   **SSOT Acuan Tarif Obat & BHP:**
        1. **`harga_tunai`**: Acuan tunggal tarif flat pasien Umum / Mandiri / Non-BPJS (berlaku seragam untuk Rawat Jalan maupun Rawat Inap).
        2. **`harga_kapitasi`**: Nilai penjaminan klaim internal Paket Kapitasi BPJS (ditagihkan Rp 0 ke pasien).
        3. **`harga_nonkapitasi`**: Nilai penjaminan klaim internal Non-Kapitasi / PRB / Klaim Khusus BPJS.
        4. **`jualbebas`** (dan `mlite_harga_apotek_umum.harga_jual`): Acuan tarif retail kasir POS Apotek Umum.
        5. **`dasar` / `h_beli`**: Acuan HPP modal pembelian PBF.

---

## 3. Arsitektur Metadata RME Dinamis, Smart Peek & Clinical Guard (Bab 18, PLN-05, PLN-06, PLN-11)

*   **Key-Value Store (`mlite_pemeriksaan_ralan_metadata`):** Formulir spesifik poli (Kebidanan, Kronis/PRB, TB, Jiwa, Mata) wajib disimpan ke tabel metadata berbasis `meta_key` & `meta_value` JSON. Dilarang menambah kolom baru ke tabel inti `pemeriksaan_ralan`.
*   **Smart Peek Protection (`isMetaDirty`):** Tenaga medis yang hanya membuka/mengintip riwayat medis masa lalu tanpa mengubah input hari ini **dilarang menghasilkan metadata baru**. Kirim `meta_key` kosong jika `isMetaDirty === false`.
*   **Clinical Guard Pendaftaran (`PLN-11`):** Pada `rawat_jalan/Admin.php` (`postSave`), **dilarang mengubah nomor antrean (`no_reg`) atau dokter (`kd_dokter`)** pada kunjungan pasien yang sudah memiliki SOAP dokter, tindakan medis/paramedis, e-resep, permintaan lab/radiologi, atau sudah lunas kasir. Geser manual hanya diizinkan jika status masih `Belum`/`Awal` dengan 0 layanan.
*   **Sinkronisasi Dokter Pengganti (`PLN-06`):** Saat fitur dokter pengganti diaktifkan (`movePatientsForReplacement`) atau di-revert (`postRevertDokterPengganti`), tindakan konsultasi registrasi di `rawat_jl_dr` (`RJ002`/`RJ003`/`RJ008`) dan `provider_id` di `mlite_billing_ledger` wajib otomatis disinkronkan ke dokter yang bertugas riil.
*   **Mode Sweeping & Pembatalan Terisolasi (`PLN-05`):** Pembatalan kunjungan wajib melalui pengecekan *Clinical Guard*; jika sudah ada transaksi kasir/obat/sampel lab, wajib diarahkan ke prosedur void/restorasi stok resmi agar tidak meninggalkan status menggantung.

---

## 4. Pengelolaan Order & Integrasi Billing Laboratorium / Penunjang (Bab 15 & 16)

*   **4.1 Pemisahan Nomor Order (PL) Berdasarkan Status Kasir:**
    *   Jika nomor PL lama **sudah Paid/Approved** di Kasir, penambahan rincian lab baru **WAJIB dibuatkan nomor PL baru** (contoh: `PL202608030002`) agar kasir mendeteksi tagihan tambahan tersebut. Dilarang menulis langsung ke billing tanpa nomor PL baru.
*   **4.2 Lock Tombol Validasi:**
    *   Tombol Validasi di laboratorium wajib terkunci jika order/item belum diapprove/dilunasi di Kasir.
*   **4.3 Pemisahan PL Berdasarkan Jenis Klaim:**
    *   Item dengan klaim berbeda (Kapitasi vs Tunai) wajib dipisahkan ke nomor PL yang berbeda. Petugas laboratorium dilarang memilih klaim Kapitasi (wajib dari modul dokter ralan).
*   **4.4 Pencocokan Record Ledger:**
    *   Pencocokan eksekusi klinis di `PaymentService::syncToLedger()` wajib menggunakan pasangan kolom `no_rawat` dan `kd_jenis_prw` (jangan bergantung pada `tgl_sampel`/`jam_sampel`).
