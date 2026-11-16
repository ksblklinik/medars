# PRD 00: Kamus Skema Basis Data, Anti-Corruption Layer & Strategi Migrasi Legal

## 1. Ikhtisar & Paradigma Transisi Terkelola
Aplikasi baru (**ksm-next**) mengadopsi prinsip **Strangler Fig Pattern** dengan **Anti-Corruption Layer (ACL)**:
1. **Tidak Terkunci Kaku Selamanya:** Skema database baru (`next_db`) dirancang bersih, memiliki Primary Key modern, Foreign Key relasional sejati, dan kolom audit lengkap (`created_at`, `updated_at`, `created_by`), bebas dari utang teknis masa lalu.
2. **Interoperabilitas Tanpa Risiko:** Sistem baru mampu membaca langsung basis data eksisting (**MySQL 8.0**) secara aman tanpa risiko merusak data produksi yang sedang berjalan.
3. **Migrasi Bertahap Legal:** Data historis puluhan tahun dipersiapkan untuk dimigrasikan secara bertahap dengan jaminan kepatuhan hukum rekam medis (**Permenkes No. 24 Tahun 2022**).

Berkas DDL skema fisik eksisting lengkap: [`docs/database_schema_oracle.sql`](file:///c:/aplikasi/rea-mlite/docs/database_schema_oracle.sql).  
Repositori kode sistem eksisting sebagai referensi oracle: [`https://github.com/ksblklinik/ksmlite`](https://github.com/ksblklinik/ksmlite).

---

## 2. Peta 19 Tabel Inti Transaksional Eksisting (Kamus Rujukan ACL)

Tabel-tabel ini adalah sumber kebenaran data lama yang dibaca oleh **Anti-Corruption Layer (ACL)**:

| Entitas Domain | Nama Tabel Fisik MySQL | Primary Key Fisik | Catatan & Perangkap Kritis |
| :--- | :--- | :--- | :--- |
| **Kunjungan / Registrasi** | `reg_periksa` | `no_rawat` (`varchar(17)`) | Format string `YYYY/MM/DD/000001`. `no_rkm_medis` (`varchar(15)`), `no_reg` (`varchar(8)`). Wajib dibaca sebagai string. |
| **Identitas Pasien** | `pasien` | `no_rkm_medis` (`varchar(15)`) | Mengandung leading zeros (`000123`). Wajib string. |
| **Pemeriksaan Klinis (RME)**| `pemeriksaan_ralan` | **COMPOSITE (3 Kolom):**<br>`no_rawat` + `tgl_perawatan` + `jam_rawat` | Tidak ada kolom `id` auto-increment. Triplet kunci komposit. |
| **Metadata RME Dinamis** | `mlite_pemeriksaan_ralan_metadata` | `id` (`int` auto-increment) | Kunci `meta_key` + `meta_value` (JSON). Dipakai untuk formulir poli spesifik & Resep PRB. |
| **Header Resep Dokter** | `resep_obat` | `no_resep` (`varchar(14)`) | Berelasi ke `reg_periksa.no_rawat`. |
| **Resep Obat Jadi (Non-Racik)**| **`resep_dokter_nonracikan`** | Foreign Key: `no_resep` + `kode_brng` | **PERINGATAN:** Nama tabel fisik adalah `resep_dokter_nonracikan` (BUKAN `resep_dokter`). |
| **Resep Racikan Header** | `resep_dokter_racikan` | `no_resep` + `no_racik` | Header nama racikan, aturan pakai, kuota racik. |
| **Resep Racikan Detail** | `resep_dokter_racikan_detail` | `no_resep` + `no_racik` + `kode_brng` | Komponen bahan obat penyusun racikan. |
| **Pemberian / Mutasi Obat**| `detail_pemberian_obat` | **COMPOSITE (6 Kolom):**<br>`tgl_perawatan` + `jam` + `no_rawat` + `kode_brng` + `no_batch` + `no_faktur` | Kolom `jam` offset `+1 detik` untuk bahan racikan. Enum `jenis_klaim`: `'Kapitasi','Non-Kapitasi','Tunai'`. |
| **Buku Besar Tagihan** | `mlite_billing_ledger` | `id` (`int` auto-increment) | Kolom `claim_type`: `'Tunai','Kapitasi','Non-Kapitasi','Piutang'`. Status: `'Pending','Paid','Approved','Bundled','Cancelled','Expired'`. |
| **Master Obat & BHP** | `databarang` | `kode_brng` (`varchar(15)`) | Kolom tarif flat: `harga_tunai`, `harga_kapitasi`, `harga_nonkapitasi`, `jualbebas`, `dasar`, `h_beli`. |
| **Stok Multi-Depo** | `gudangbarang` | `kode_brng` + `kd_bangsal` + `no_batch` + `no_faktur` | `kd_bangsal = 'APT'` (Apotek Umum), `'IGD'` (Depo IGD). |
| **Retail POS Apotek Umum** | `mlite_harga_apotek_umum` | `kode_brng` | Menyimpan `harga_jual` & `stok_minimal` retail terisolasi. |
| **Tindakan Rawat Inap** | `jns_perawatan_inap` | `kd_jenis_prw` (`varchar(15)`) | Penentu tarif tindakan ranap. |
| **Pemeriksaan Laboratorium** | `jns_perawatan_lab` | `kd_jenis_prw` (`varchar(15)`) | Penentu tarif dan jenis pemeriksaan lab. |
| **Pemeriksaan Radiologi** | `jns_perawatan_radiologi` | `kd_jenis_prw` (`varchar(15)`) | Penentu tarif dan jenis rontgen/USG. |
| **Buku Jurnal Akuntansi** | `mlite_jurnal` | `id` (`int` auto-increment) | Jurnal keuangan double-entry. Dilarang dihapus saat void (wajib jurnal pembalik). |
| **Inbox WhatsApp** | `mlite_wagateway_inbox` | `id` (`int` auto-increment) | Mendukung `instance_id` (pemisahan Sesi Sistem vs Sesi CS). |
| **Whitelist Anti-Banned WA** | `mlite_wa_verified_numbers` | `id` (`int` auto-increment) | Whitelist pasien terisolasi per nomor gateway aktif (`no_sistem`). |

---

## 3. Arsitektur Dual-Connection & Anti-Corruption Layer (ACL)

Untuk mengamankan database produksi lama dari risiko korupsi data, sistem mengimplementasikan konfigurasi **Dual-Connection**:

```
[Aplikasi ksm-next]
       ├── Connection: "legacy" ──> MySQL Live DB (HAK AKSES HANYA: SELECT)
       └── Connection: "next"   ──> MySQL/PostgreSQL Next DB (HAK AKSES: FULL CRUD & ACID)
```

1. **Koneksi `legacy` (Read-Only Safety Net):**
   - Hanya memiliki wewenang `SELECT`.
   - Tidak memiliki izin `INSERT`, `UPDATE`, `DELETE`, `DROP`, atau `ALTER`.
   - **Jaminan:** Kesalahan kode baru mustahil merusak atau mengubah data operasional RS di sistem lama.
2. **Koneksi `next` (Read-Write Clean Schema):**
   - Basis data primer tempat data transaksi baru disimpan.
   - Menggunakan skema relasional modern tanpa utang teknis.
3. **Anti-Corruption Layer (ACL) Mappers:**
   - Menjembatani pembacaan data komposit dan format string lama menjadi entitas Domain murni di `ksm-next`.
   - Menghindari penyebaran dependensi struktur tabel lama ke dalam Application Service baru.

---

## 4. Strategi 3-Fase Menuju Migrasi Total & Kepatuhan Legal (Permenkes 24/2022)

Peralihan dari database lama ke database baru dilakukan secara bertahap tanpa downtime (*zero-downtime transition*):

### Fase A: Read-Through (Interoperabilitas Awal)
- Sistem baru membaca Master Pasien (`pasien`), Master Obat (`databarang`), dan Riwayat SOAP (`pemeriksaan_ralan`) langsung dari koneksi `legacy`.
- Pasien lama yang datang berobat **tidak perlu didaftarkan ulang**.

### Fase B: Parallel Co-existence & Dual-Write Adapter
- Transaksi baru (pendaftaran, resep, billing) disimpan secara atomik ke koneksi `next`.
- Adapter sinkronisasi opsional menduplikasi ringkasan transaksi ke koneksi `legacy` jika sistem lama masih diakses sebagian unit kerja selama masa uji coba.

### Fase C: Verifiable Total ETL Cutover (Migrasi Penuh & Legal)
- Menjalankan skrip batch migrasi satu arah (*one-way ETL pipeline*) untuk memindahkan seluruh rekaman data historis ke skema database baru.
- **Standar Kepatuhan Hukum RME (Permenkes No. 24 Tahun 2022):**
  - **Retensi 25 Tahun:** Memastikan kelengkapan 100% data riwayat medis pasien sejak klinik berdiri.
  - **Audit Checksum SHA-256:** Setiap rekam medis yang dimigrasikan dibuatkan tanda tangan digital (*hash checksum*). Hal ini membuktikan secara legal di mata hukum bahwa data hasil migrasi **identik secara substansi klinis** dengan data aslinya di sistem lama.
  - Setelah verifikasi lolos, koneksi `legacy` dinonaktifkan secara permanen dan sistem resmi mandiri di atas skema baru.
