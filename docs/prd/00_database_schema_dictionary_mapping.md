# PRD 00: Kamus Skema Basis Data & Kontrak Kompatibilitas Fisik (Database Oracle)

## 1. Ikhtisar & Mandat Kompatibilitas Mutlak
Aplikasi baru (**ksm-next**) dirancang untuk dapat diuji langsung dan berjalan berdampingan (*co-exist / drop-in testing*) dengan basis data eksisting **MySQL 8.0** di Linux Staging Server.

Oleh karena itu, di tingkat persistensi database (*Infrastructure/Repository Layer*), **NAMA TABEL, NAMA KOLOM, TIPE DATA KUNCI, DAN COMPOSITE PRIMARY KEY WAJIB 100% KOMPATIBEL DENGAN SKEMA EKSISTING**. Di tingkat Domain/Entity boleh menggunakan Clean Architecture, namun mapping ORM ke database wajib mematuhi kamus ini.

Berkas DDL skema fisik lengkap dapat dilihat pada: [`docs/database_schema_oracle.sql`](file:///opt/app/ksmlite/starter-kit/docs/database_schema_oracle.sql).

---

## 2. Peta 19 Tabel Inti Transaksional & Kontrak Pemetaan

| Entitas Domain | Nama Tabel Fisik MySQL | Primary Key Fisik | Catatan & Perangkap Kritis |
| :--- | :--- | :--- | :--- |
| **Kunjungan / Registrasi** | `reg_periksa` | `no_rawat` (`varchar(17)`) | Format string `YYYY/MM/DD/000001`. `no_rkm_medis` (`varchar(15)`), `no_reg` (`varchar(8)`). Jangan diubah ke INT. |
| **Identitas Pasien** | `pasien` | `no_rkm_medis` (`varchar(15)`) | Mengandung leading zeros (`000123`). Wajib string. |
| **Pemeriksaan Klinis (RME)**| `pemeriksaan_ralan` | **COMPOSITE (3 Kolom):**<br>`no_rawat` + `tgl_perawatan` + `jam_rawat` | Tidak ada kolom `id` auto-increment. Query dan update wajib menyertakan triplet kunci ini. |
| **Metadata RME Dinamis** | `mlite_pemeriksaan_ralan_metadata` | `id` (`int` auto-increment) | Kunci `meta_key` + `meta_value` (JSON). Dipakai untuk formulir poli spesifik & Resep PRB. |
| **Header Resep Dokter** | `resep_obat` | `no_resep` (`varchar(14)`) | Berelasi ke `reg_periksa.no_rawat`. |
| **Resep Obat Jadi (Non-Racik)**| **`resep_dokter_nonracikan`** | Foreign Key: `no_resep` + `kode_brng` | **PERINGATAN:** Nama tabel fisik adalah `resep_dokter_nonracikan` (BUKAN `resep_dokter`). |
| **Resep Racikan Header** | `resep_dokter_racikan` | `no_resep` + `no_racik` | Header nama racikan, aturan pakai, kuota racik. |
| **Resep Racikan Detail** | `resep_dokter_racikan_detail` | `no_resep` + `no_racik` + `kode_brng` | Komponen bahan obat penyusun racikan. |
| **Pemberian / Mutasi Obat**| `detail_pemberian_obat` | **COMPOSITE (6 Kolom):**<br>`tgl_perawatan` + `jam` + `no_rawat` + `kode_brng` + `no_batch` + `no_faktur` | Kolom `jam` wajib offset `+1 detik` untuk bahan racikan. Enum `jenis_klaim`: `'Kapitasi','Non-Kapitasi','Tunai'`. |
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

## 3. Aturan Teknis bagi ORM / Repository Layer Proyek Baru

1. **Gunakan Engine MySQL 8.0:**
   - Stack baru (Go GORM/SQLC, TypeScript Prisma/Drizzle, atau PHP Laravel Eloquent) **wajib menyetel dialek ke MySQL 8.0** (bukan PostgreSQL) agar kompatibel langsung dengan database eksisting.
2. **Pemetaan Composite Keys:**
   - Pada tabel tanpa single auto-increment ID (`pemeriksaan_ralan`, `detail_pemberian_obat`, `resep_dokter_racikan_detail`), ORM wajib dikonfigurasi mendukung *Composite Primary Key* atau menggunakan custom repository query.
3. **Penyimpanan Format String Kunci:**
   - Kolom `no_rawat`, `no_rkm_medis`, `no_reg`, `kode_brng`, `kd_dokter`, `kd_poli` wajib didefinisikan sebagai string (*String/Text Type*), dilarang di-cast ke integer di level persistensi database.
