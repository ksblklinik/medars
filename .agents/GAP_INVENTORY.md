# Tabel Inventaris Gap Fitur: `.ref/ksmlite/` (64 Plugin) vs `ksm-next`

Dokumen ini menjadi daftar kerja resmi untuk mengejar paritas fitur aplikasi lama (`ksmlite`) **satu per satu** secara terukur.

**Legenda Status:**
- `🟡 Parsial`: Sudah ada fondasi/rute utama, tetapi fitur/UI belum selengkap `ksmlite`.
- `❌ Belum Ada`: Belum memiliki controller/UI/alur kerja di `ksm-next`.
- `⚪ Infra/Legacy`: Utilitas internal/ekosistem lama yang tidak wajib dibuat ulang jika digantikan arsitektur baru (tetap dicatat agar 100% dari 64 plugin terpetakan).

---

## Tier 1 — Pondasi Data, Pasien & Pendalaman Layanan Harian

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 1 | `pasien` | ✅ Selesai | Master Pasien (`/pasien`): CRUD lengkap, Auto RM (`max-id`), guard duplikat NIK, guard hapus pasien ber-riwayat, modal riwayat kunjungan, pagination, & cetak Kartu Berobat (`/pasien/kartu`). |
| 2 | `master` | 🟡 Parsial | UI & API CRUD Master Data (`/master`) untuk Poliklinik, Dokter, Penjamin (`penjab`), Tarif Tindakan (`jns_perawatan`), & Jadwal Dokter sudah ada. Sisa: Bangsal/Kamar (Rawat Inap) & Penyakit ICD-10 UI. |
| 3 | `kepegawaian` | ❌ Belum Ada | UI CRUD Dokter, Petugas, Pegawai & berkas SDM. (Saat ini baru ada repo backend). |
| 4 | `surat` | 🟡 Parsial | Halaman UI daftar, terbit, & cetak (`/surat`, `/surat/cetak`) untuk Surat Sakit, Surat Hamil, & Surat Bebas Narkoba sudah ada. Sisa: Surat Sehat & Cuti Hamil. |
| 5 | `rawat_jalan` | 🟡 Parsial | Filter periode tanggal multi-hari (`from`–`to`) & status (`stts`) serta ubah status periksa (`ClinicalGuardShield`) sudah ada. Sisa: ubah dokter/poli di UI, SEP/rujukan internal, upload berkas digital, cetak rincian/antrean. |
| 6 | `dokter_ralan` | 🟡 Parsial | Input tindakan dokter (`rawat_jl_dr` + ledger), order lab/rad & E-Resep langsung dari layar dokter, serta Resume Medis (`resume_pasien`) sudah ada. Sisa: cetak PDF e-resep/resume medis & riwayat lengkap per tab. |
| 7 | `apotek_ralan` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`), Cetak Etiket (`/farmasi/etiket`), Cetak E-Resep (`/farmasi/cetak-resep`), & tombol Copy Resep UI sudah ada. Sisa: penyesuaian stok per batch/depo langsung dari layar apotek. |
| 8 | `kasir_rawat_jalan` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`), Cetak Kuitansi/Faktur 4-Pilar (`/kasir/faktur`), & Pelunasan Piutang (`/api/v1/kasir/settle-piutang`) sudah ada. Sisa: cetak thermal & email faktur. |
| 9 | `frontoffice` | ❌ Belum Ada | Dashboard khusus Front Office / Gatekeeper konfirmasi antrean & pendaftaran loket terpadu. |

---

## Tier 2 — IGD, Rawat Inap & Tindakan Medis

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 10 | `igd` | ❌ Belum Ada | Modul Pendaftaran & Pelayanan IGD (Triase ATS/ESI, status masuk, rujuk/rawat inap). |
| 11 | `dokter_igd` | ❌ Belum Ada | Layar SOAP, Triase IGD, tindakan emergensi, e-resep IGD, & order penunjang IGD. |
| 12 | `kasir_igd` | ❌ Belum Ada | Layar billing & pelunasan khusus pasien IGD (termasuk pasien masuk ranap). |
| 13 | `rawat_inap` | ❌ Belum Ada | Manajemen Kamar Inap (`kamar_inap`), check-in/pindah kamar, mutasi pasien ranap, pulang/status keluar. |
| 14 | `dokter_ranap` | ❌ Belum Ada | Layar CPPT Dokter Ranap (`pemeriksaan_ranap` UI), visite, e-resep ranap, resume pulang. |
| 15 | `apotek_ranap` | ❌ Belum Ada | Pelayanan E-Resep & UDD/ODD pasien Rawat Inap. |
| 16 | `kasir_rawat_inap` | ❌ Belum Ada | Billing akumulatif kamar, visite, tindakan, obat, & pelunasan pulang Rawat Inap. |
| 17 | `operasi` | ❌ Belum Ada | Jadwal Operasi, laporan operasi, paket tindakan & tim bedah. |
| 18 | `ruang_tindakan` | ❌ Belum Ada | Layar input tindakan medis/keperawatan terpisah di ruang tindakan. |
| 19 | `utd` | ❌ Belum Ada | Unit Transfusi Darah / Bank Darah (permintaan, crossmatch, stok kantong darah). |

---

## Tier 3 — Gudang Farmasi, Keuangan & Aset

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 20 | `farmasi` | ❌ Belum Ada | Manajemen Gudang Obat: CRUD Master `databarang`, Penerimaan/PO Supplier, Mutasi antar Depo, Stok Opname, Kartu Stok. |
| 21 | `apotek_umum` | 🟡 Parsial | Backend POS `PO-APT-` sudah ada; UI kasir POS retail mandiri, cetak struk, & riwayat penjualan bebas belum lengkap. |
| 22 | `penjualan` | ❌ Belum Ada | Modul penjualan non-resep / alkes / laporan omzet penjualan barang. |
| 23 | `keuangan` | ❌ Belum Ada | UI Jurnal Umum, Buku Besar, Neraca, Laba Rugi, Pengeluaran Kas, & Master Akun Rekening (`mlite_jurnal`). |
| 24 | `jasa_medis` | ❌ Belum Ada | Perhitungan & rekap pembagian Jasa Medis Dokter / Paramedis per periode & pilar klaim. |
| 25 | `inventaris` | ❌ Belum Ada | Manajemen inventaris barang non-medis & aset klinik/RS. |

---

## Tier 4 — Penunjang, Analitik, Bridging & AI

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 26 | `laboratorium` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`) & cetak hasil lab (`/penunjang/lab/cetak`) sudah ada. Sisa: master paket/parameter nilai rujukan & input order baru langsung di modul lab. |
| 27 | `radiologi` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`), hapus order radiologi (`DeleteRadiologyOrderUseCase`), & cetak expertise radiologi (`/penunjang/radiologi/cetak`) sudah ada. Sisa: viewer lampiran gambar. |
| 28 | `orthanc` | ❌ Belum Ada | Integrasi PACS / DICOM viewer Orthanc & interpretasi citra. |
| 29 | `laporan` | 🟡 Parsial | Baru ada ringkasan kunjungan, top 10 ICD, & pendapatan klaim; belum ada export Excel/PDF & laporan rekonsiliasi order. |
| 30 | `manajemen` | ❌ Belum Ada | Dashboard Eksekutif Manajemen & LEAN Analytics Board penuh (grafik tren, overtime dokter, net signed avg per poli). |
| 31 | `vclaim` | 🟡 Parsial | Baru ada circuit breaker & dekripsi; belum ada UI buat/cetak SEP, Rujukan, Surat Kontrol, & Cari Peserta BPJS. |
| 32 | `pcare` | ❌ Belum Ada | Bridging khusus FKTP PCare BPJS (Pendaftaran, Kunjungan, Obat, Lab, Rujukan FKTP). |
| 33 | `icare` | ❌ Belum Ada | Integrasi tombol & viewer riwayat pelayanan i-Care JKN BPJS di layar dokter. |
| 34 | `bridging_hfis` | 🟡 Parsial | Baru ada kalkulator `HfisLeanCalculator`; belum ada sinkron jadwal dokter HFIS & antrean online BPJS (`antrol`). |
| 35 | `jkn_mobile` | 🟡 Parsial | Baru ada check-in dasar; belum ada endpoint penuh API Antrean Online Mobile JKN FKRTL. |
| 36 | `jkn_mobile_fktp` | ❌ Belum Ada | Endpoint API Mobile JKN khusus FKTP. |
| 37 | `satu_sehat` | 🟡 Parsial | Baru ada OAuth2 + async queue `Encounter` & `Condition`; belum ada UI mapping IHS ID, `Observation`, `MedicationRequest`, & `ServiceRequest`. |
| 38 | `vedika` | 🟡 Parsial | Baru ada verifikasi `Layak`/`Perlu Perbaikan`; belum ada UI daftar pengajuan klaim & bundel berkas PDF Vedika. |
| 39 | `veronisa` | ❌ Belum Ada | Modul verifikasi internal klaim / non-kapitasi (`veronisa`). |
| 40 | `sep_internal` | ❌ Belum Ada | Pembuatan & monitoring SEP Internal / Rujukan Internal antar poli. |
| 41 | `smartagent` | ❌ Belum Ada | Asisten AI Klinis, RAG Knowledge Vault (`knowledge-vault/`), & rekomendasi ICD-10 berbasis PDT. |
| 42 | `chat` | ❌ Belum Ada | Live Chat internal staf / integrasi percakapan CS. |

---

## Tier 5 — Layanan Tambahan, Portal, SDM & Utilitas Sistem

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 43 | `anjungan` | 🟡 Parsial | Sudah ada Display TV & Kiosk dasar; belum ada cetak tiket thermal, display LEAN TV, & antrean farmasi/kasir terpisah. |
| 44 | `wagateway` | 🟡 Parsial | Sudah ada kirim satuan, webhook QR, whitelist; belum ada UI live chat 2-arah sesi `cs`, template broadcast terjadwal, & AI draft CS. |
| 45 | `home_care` | 🟡 Parsial | Sudah ada jadwal, Privacy Shield & preceptor; belum ada PWA manifest/service worker, sign-off care plan DPJP, & pembayaran Midtrans/Kasera UI. |
| 46 | `portal_pasien` | ❌ Belum Ada | Portal mandiri pasien (lihat riwayat, hasil lab, booking online). |
| 47 | `presensi` | ❌ Belum Ada | Modul absensi / presensi pegawai (lokasi/foto/jadwal shift). |
| 48 | `profil` | ❌ Belum Ada | Halaman ubah profil, foto, & ganti password pengguna sendiri. |
| 49 | `riwayat_simgos` | ❌ Belum Ada | Viewer riwayat migrasi dari SIMGOS lama. |
| 50 | `users` | 🟡 Parsial | Sudah ada CRUD dasar; belum ada filter/cari & reset password massal. |
| 51 | `settings` | 🟡 Parsial | Sudah ada pengaturan dasar; belum ada tab khusus Bridging/WA/Tarif/Logo instansi lengkap. |
| 52 | `mlite_logs` | 🟡 Parsial | Sudah ada `/audit` & rotasi log operasional; belum ada UI viewer `mlite_logs` berfilter di browser. |
| 53 | `mlite_api_key` | ❌ Belum Ada | Manajemen API Key eksternal & rate-limit token. |
| 54 | `modules` | ❌ Belum Ada | UI Module Manager (aktifkan/nonaktifkan/install plugin dari antarmuka admin). |
| 55 | `dashboard` | 🟡 Parsial | Sudah ada 4 KPI + grid menu; belum ada grafik kunjungan/pendapatan & pengumuman internal. |
| 56 | `website` | ⚪ Opsional | CMS halaman depan website klinik publik. |
| 57 | `bantuan` | ⚪ Opsional | Halaman dokumentasi/panduan penggunaan internal. |
| 58 | `afm` | ⚪ Legacy/Khusus | Modul integrasi spesifik legacy `afm`. |
| 59 | `agent_api` | ⚪ Legacy/Khusus | Endpoint API agent legacy. |
| 60 | `api` | ⚪ Infra | Digantikan oleh REST API `/api/v1/*` di `ksm-next`. |
| 61 | `apps` | ⚪ Legacy/Khusus | Launcher aplikasi eksternal legacy. |
| 62 | `dokter_app` | ⚪ Legacy/Khusus | Wrapper aplikasi mobile dokter lama (digantikan Mobile App Shell responsif). |
| 63 | `verifikasi` | ❌ Belum Ada | Verifikasi keaslian surat medis/dokumen publik via QR code. |
