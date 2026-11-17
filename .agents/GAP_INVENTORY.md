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
| 3 | `kepegawaian` | 🟡 Parsial | UI & API CRUD Pegawai (`pegawai`), Petugas Medis (`petugas`), & cetak daftar SDM (`/kepegawaian`, `/kepegawaian/cetak`) sudah ada; Dokter di `/master`. Sisa: upload berkas digital SDM. |
| 4 | `surat` | 🟡 Parsial | Halaman UI daftar, terbit, & cetak (`/surat`, `/surat/cetak`) untuk Surat Sakit, Surat Hamil, & Surat Bebas Narkoba sudah ada. Sisa: Surat Sehat & Cuti Hamil. |
| 5 | `rawat_jalan` | 🟡 Parsial | Filter periode tanggal multi-hari (`from`–`to`) & status (`stts`) serta ubah status periksa (`ClinicalGuardShield`) sudah ada. Sisa: ubah dokter/poli di UI, SEP/rujukan internal, upload berkas digital, cetak rincian/antrean. |
| 6 | `dokter_ralan` | 🟡 Parsial | Input tindakan dokter (`rawat_jl_dr` + ledger), order lab/rad & E-Resep langsung dari layar dokter, serta Resume Medis (`resume_pasien`) sudah ada. Sisa: cetak PDF e-resep/resume medis & riwayat lengkap per tab. |
| 7 | `apotek_ralan` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`), Cetak Etiket (`/farmasi/etiket`), Cetak E-Resep (`/farmasi/cetak-resep`), & tombol Copy Resep UI sudah ada. Sisa: penyesuaian stok per batch/depo langsung dari layar apotek. |
| 8 | `kasir_rawat_jalan` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`), Cetak Kuitansi/Faktur 4-Pilar (`/kasir/faktur`), & Pelunasan Piutang (`/api/v1/kasir/settle-piutang`) sudah ada. Sisa: cetak thermal & email faktur. |
| 9 | `frontoffice` | ✅ Selesai | Dashboard Front Office & Gatekeeper (`/frontoffice`): KPI kunjungan/booking/belum bayar, konfirmasi check-in booking Mobile JKN, panggil antrean TV, & pintasan loket terpadu. |

---

## Tier 2 — IGD, Rawat Inap & Tindakan Medis

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 10 | `igd` | ✅ Selesai | Modul Pendaftaran & Triase IGD (`/igd`): antrean prioritas zona ATS/ESI (`Merah` &rarr; `Kuning` &rarr; `Hijau` &rarr; `Hitam`), form triase TTV/GCS/SpO2, & disposisi (`Pulang`/`Rawat Inap`/`Rujuk`). |
| 11 | `dokter_igd` | ✅ Selesai | Triase IGD ATS/ESI (`/igd`) terintegrasi dengan layar SOAP, tindakan, e-resep, & order penunjang dokter (`/dokter-ralan`). |
| 12 | `kasir_igd` | ✅ Selesai | Billing & pelunasan khusus pasien IGD terintegrasi di `/kasir` dengan filter unit `kd_poli=IGDK` (termasuk pasien lanjut Ranap). |
| 13 | `rawat_inap` | ✅ Selesai | Manajemen Kamar Inap (`/rawat-inap`): master bangsal & kamar, check-in kamar (`ISI` + `status_lanjut=Ranap`), & checkout pulang otomatis hitung lama inap + posting billing kamar ke `mlite_billing_ledger`. |
| 14 | `dokter_ranap` | 🟡 Parsial | Layar CPPT Dokter Ranap (`/dokter-ranap`, `pemeriksaan_ranap` UI & riwayat visite) sudah ada. Sisa: tab E-Resep Ranap & Resume Pulang langsung di layar Dokter Ranap. |
| 15 | `apotek_ranap` | ✅ Selesai | Pelayanan E-Resep Rawat Inap terintegrasi di `/apotek-ralan` dengan filter `status_layanan=ranap` (`resep_obat.status='ranap'`). |
| 16 | `kasir_rawat_inap` | ✅ Selesai | Billing akumulatif kamar (`ManageKamarInapUseCase`), tindakan, obat, & pelunasan pulang Ranap terintegrasi di `/kasir` dengan filter `status_lanjut=Ranap`. |
| 17 | `operasi` | ❌ Belum Ada | Jadwal Operasi, laporan operasi, paket tindakan & tim bedah. |
| 18 | `ruang_tindakan` | ❌ Belum Ada | Layar input tindakan medis/keperawatan terpisah di ruang tindakan. |
| 19 | `utd` | ❌ Belum Ada | Unit Transfusi Darah / Bank Darah (permintaan, crossmatch, stok kantong darah). |

---

## Tier 3 — Gudang Farmasi, Keuangan & Aset

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 20 | `farmasi` | ✅ Selesai | Manajemen Gudang Obat (`/farmasi-gudang`): CRUD Master `databarang`, Penerimaan Stok/PO, Mutasi antar Depo (`InsufficientStockException` guard), & Kartu Stok (`riwayat_barang_medis`). |
| 21 | `apotek_umum` | ✅ Selesai | POS retail mandiri `PO-APT-` dengan 5 cara bayar, daftar riwayat penjualan (`/api/v1/apotek-umum/sales`), & cetak struk thermal (`/apotek-umum/struk`). |
| 22 | `penjualan` | ❌ Belum Ada | Modul penjualan non-resep / alkes / laporan omzet penjualan barang. |
| 23 | `keuangan` | ✅ Selesai | Modul Akuntansi & Keuangan (`/keuangan`): Jurnal Umum, Pengeluaran Kas, Buku Besar, Laba Rugi, & Master Akun Rekening (`mlite_rekening` + `mlite_jurnal`). |
| 24 | `jasa_medis` | ✅ Selesai | Rekap & cetak Jasa Medis Dokter (`/jasa-medis`, `/jasa-medis/cetak`): agregasi tindakan per dokter, rincian per pasien, & pemisahan pilar klaim (`Tunai`, `BPJS`, `Piutang`). |
| 25 | `inventaris` | ❌ Belum Ada | Manajemen inventaris barang non-medis & aset klinik/RS. |

---

## Tier 4 — Penunjang, Analitik, Bridging & AI

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 26 | `laboratorium` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`) & cetak hasil lab (`/penunjang/lab/cetak`) sudah ada. Sisa: master paket/parameter nilai rujukan & input order baru langsung di modul lab. |
| 27 | `radiologi` | 🟡 Parsial | Filter periode multi-hari (`from`–`to`), hapus order radiologi (`DeleteRadiologyOrderUseCase`), & cetak expertise radiologi (`/penunjang/radiologi/cetak`) sudah ada. Sisa: viewer lampiran gambar. |
| 28 | `orthanc` | ❌ Belum Ada | Integrasi PACS / DICOM viewer Orthanc & interpretasi citra. |
| 29 | `laporan` | ✅ Selesai | Ringkasan kunjungan, top 10 ICD-10, pendapatan klaim, laporan rekonsiliasi order (`Pending`/`Cancelled`/`Void`), & export CSV/Excel (`/api/v1/laporan/export-csv`). |
| 30 | `manajemen` | ✅ Selesai | Dashboard Eksekutif Manajemen & LEAN Analytics Board (`/manajemen`): KPI kunjungan/pendapatan/lead time SOAP/overtime, metrik Net Signed Avg per poli (`HfisLeanCalculator`), & tren harian. |
| 31 | `vclaim` | 🟡 Parsial | Baru ada circuit breaker & dekripsi; belum ada UI buat/cetak SEP, Rujukan, Surat Kontrol, & Cari Peserta BPJS. |
| 32 | `pcare` | ❌ Belum Ada | Bridging khusus FKTP PCare BPJS (Pendaftaran, Kunjungan, Obat, Lab, Rujukan FKTP). |
| 33 | `icare` | ❌ Belum Ada | Integrasi tombol & viewer riwayat pelayanan i-Care JKN BPJS di layar dokter. |
| 34 | `bridging_hfis` | 🟡 Parsial | Baru ada kalkulator `HfisLeanCalculator`; belum ada sinkron jadwal dokter HFIS & antrean online BPJS (`antrol`). |
| 35 | `jkn_mobile` | 🟡 Parsial | Baru ada check-in dasar; belum ada endpoint penuh API Antrean Online Mobile JKN FKRTL. |
| 36 | `jkn_mobile_fktp` | ❌ Belum Ada | Endpoint API Mobile JKN khusus FKTP. |
| 37 | `satu_sehat` | 🟡 Parsial | Baru ada OAuth2 + async queue `Encounter` & `Condition`; belum ada UI mapping IHS ID, `Observation`, `MedicationRequest`, & `ServiceRequest`. |
| 38 | `vedika` | ✅ Selesai | Verifikasi `Layak`/`Perlu Perbaikan`, UI daftar pengajuan klaim BPJS (`/api/v1/bridging/vedika/claims`), & cetak bundel berkas Vedika (`/vedika/bundel`). |
| 39 | `veronisa` | ❌ Belum Ada | Modul verifikasi internal klaim / non-kapitasi (`veronisa`). |
| 40 | `sep_internal` | ✅ Selesai | Pembuatan & monitoring Rujukan / SEP Internal antar poli (`/api/v1/rujukan-internal` & tab di `/surat`). |
| 41 | `smartagent` | ❌ Belum Ada | Asisten AI Klinis, RAG Knowledge Vault (`knowledge-vault/`), & rekomendasi ICD-10 berbasis PDT. |
| 42 | `chat` | ❌ Belum Ada | Live Chat internal staf / integrasi percakapan CS. |

---

## Tier 5 — Layanan Tambahan, Portal, SDM & Utilitas Sistem

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 43 | `anjungan` | 🟡 Parsial | Sudah ada Display TV & Kiosk dasar; belum ada cetak tiket thermal, display LEAN TV, & antrean farmasi/kasir terpisah. |
| 44 | `wagateway` | 🟡 Parsial | Sudah ada kirim satuan, webhook QR, whitelist; belum ada UI live chat 2-arah sesi `cs`, template broadcast terjadwal, & AI draft CS. |
| 45 | `home_care` | 🟡 Parsial | Sudah ada jadwal, Privacy Shield & preceptor; belum ada PWA manifest/service worker, sign-off care plan DPJP, & pembayaran Midtrans/Kasera UI. |
| 46 | `portal_pasien` | ✅ Selesai | Portal mandiri pasien publik (`/portal-pasien`): verifikasi No. RM + Tanggal Lahir, lihat riwayat kunjungan, hasil lab, & booking antrean online. |
| 47 | `presensi` | ❌ Belum Ada | Modul absensi / presensi pegawai (lokasi/foto/jadwal shift). |
| 48 | `profil` | ✅ Selesai | Halaman profil & keamanan akun sendiri (`/profil`): ubah nama lengkap/email & ganti password terverifikasi (`password_verify` + `PASSWORD_BCRYPT`). |
| 49 | `riwayat_simgos` | ❌ Belum Ada | Viewer riwayat migrasi dari SIMGOS lama. |
| 50 | `users` | 🟡 Parsial | Sudah ada CRUD dasar & pencarian/filter tabel pengguna (`usersTableBody`). Sisa: reset password massal. |
| 51 | `settings` | 🟡 Parsial | Sudah ada pengaturan dasar; belum ada tab khusus Bridging/WA/Tarif/Logo instansi lengkap. |
| 52 | `mlite_logs` | ✅ Selesai | Sudah ada `/audit`, rotasi log operasional, & UI Audit Trail Viewer (`mlite_logs` berfilter di `/api/v1/audit/logs`). |
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
