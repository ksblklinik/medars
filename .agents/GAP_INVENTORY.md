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
| 2 | `master` | ✅ Selesai | UI & API CRUD Master Data (`/master`) lengkap untuk Poliklinik, Dokter, Penjamin (`penjab`), Tarif Tindakan (`jns_perawatan`), Jadwal Dokter, Bangsal, Kamar Inap, & Penyakit ICD-10 (`penyakit`). |
| 3 | `kepegawaian` | ✅ Selesai | UI & API CRUD Pegawai (`pegawai`), Petugas Medis (`petugas`), Berkas Digital SDM (`mlite_sdm_berkas` STR/SIP/KTP/Ijazah), & cetak daftar SDM (`/kepegawaian`, `/kepegawaian/cetak`). |
| 4 | `surat` | ✅ Selesai | Halaman UI daftar, terbit, & cetak (`/surat`, `/surat/cetak`) lengkap untuk Surat Sakit, Surat Hamil, Surat Bebas Narkoba, Surat Sehat (`surat_sehat`), & Surat Cuti Hamil (`surat_cuti_hamil`). |
| 5 | `rawat_jalan` | ✅ Selesai | Filter periode multi-hari, ubah status (`ClinicalGuardShield`), ubah dokter/poli (`/api/v1/registration/change-poli-dokter`), upload berkas digital (`berkas_digital_perawatan`), rujukan internal, & cetak antrean (`/pendaftaran/cetak`). |
| 6 | `dokter_ralan` | ✅ Selesai | SOAP, ICD-10, tindakan dokter + ledger, order lab/rad & E-Resep, Resume Medis (`resume_pasien`), cetak PDF/HTML Resume Medis (`/dokter-ralan/resume/cetak`) & E-Resep (`/dokter-ralan/eresep/cetak`), serta tab Riwayat Lengkap (`/api/v1/dokter-ralan/full-history`). |
| 7 | `apotek_ralan` | ✅ Selesai | Filter periode multi-hari (`from`–`to`), Cetak Etiket (`/farmasi/etiket`), Cetak E-Resep (`/farmasi/cetak-resep`), Copy Resep UI, & penyesuaian stok per batch/depo langsung dari layar apotek (`/api/v1/farmasi/stok-adjust` + `riwayat_barang_medis`). |
| 8 | `kasir_rawat_jalan` | ✅ Selesai | Filter periode multi-hari (`from`–`to`), Cetak Kuitansi/Faktur 4-Pilar (`/kasir/faktur`), Cetak Struk Thermal (`?format=thermal`), kirim email faktur (`/api/v1/kasir/email-faktur`), & Pelunasan Piutang (`/api/v1/kasir/settle-piutang`). |
| 9 | `frontoffice` | ✅ Selesai | Dashboard Front Office & Gatekeeper (`/frontoffice`): KPI kunjungan/booking/belum bayar, konfirmasi check-in booking Mobile JKN, panggil antrean TV, & pintasan loket terpadu. |

---

## Tier 2 — IGD, Rawat Inap & Tindakan Medis

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 10 | `igd` | ✅ Selesai | Modul Pendaftaran & Triase IGD (`/igd`): antrean prioritas zona ATS/ESI (`Merah` &rarr; `Kuning` &rarr; `Hijau` &rarr; `Hitam`), form triase TTV/GCS/SpO2, & disposisi (`Pulang`/`Rawat Inap`/`Rujuk`). |
| 11 | `dokter_igd` | ✅ Selesai | Triase IGD ATS/ESI (`/igd`) terintegrasi dengan layar SOAP, tindakan, e-resep, & order penunjang dokter (`/dokter-ralan`). |
| 12 | `kasir_igd` | ✅ Selesai | Billing & pelunasan khusus pasien IGD terintegrasi di `/kasir` dengan filter unit `kd_poli=IGDK` (termasuk pasien lanjut Ranap). |
| 13 | `rawat_inap` | ✅ Selesai | Manajemen Kamar Inap (`/rawat-inap`): master bangsal & kamar, check-in kamar (`ISI` + `status_lanjut=Ranap`), & checkout pulang otomatis hitung lama inap + posting billing kamar ke `mlite_billing_ledger`. |
| 14 | `dokter_ranap` | ✅ Selesai | Layar Dokter Ranap (`/dokter-ranap`): CPPT `pemeriksaan_ranap` & riwayat visite, tab E-Resep Ranap (`/api/v1/dokter-ranap/eresep/create`), serta tab Resume Pulang & cetak (`/api/v1/dokter-ranap/resume/save`, `/dokter-ranap/resume/cetak`). |
| 15 | `apotek_ranap` | ✅ Selesai | Pelayanan E-Resep Rawat Inap terintegrasi di `/apotek-ralan` dengan filter `status_layanan=ranap` (`resep_obat.status='ranap'`). |
| 16 | `kasir_rawat_inap` | ✅ Selesai | Billing akumulatif kamar (`ManageKamarInapUseCase`), tindakan, obat, & pelunasan pulang Ranap terintegrasi di `/kasir` dengan filter `status_lanjut=Ranap`. |
| 17 | `operasi` | ✅ Selesai | Modul Kamar Operasi (`/operasi`): jadwal booking OK (`booking_operasi`), laporan operasi & tim bedah (`laporan_operasi`), master paket tindakan (`paket_operasi`), & auto-posting ke `mlite_billing_ledger`. |
| 18 | `ruang_tindakan` | ✅ Selesai | Layar Ruang Tindakan Medis & Keperawatan (`/ruang-tindakan`, `/ruang-tindakan/cetak`): input follow-up operatif, guard approval paramedis->dokter (`Draft` vs `Selesai`), & cetak laporan (`mlite_followup_operatif`). |
| 19 | `utd` | ✅ Selesai | Unit Transfusi Darah / Bank Darah (`/utd`): master komponen (`utd_komponen_darah`), data pendonor (`utd_pendonor`), stok kantong (`utd_stok_darah`), serta permintaan & crossmatch (`utd_permintaan_darah`). |

---

## Tier 3 — Gudang Farmasi, Keuangan & Aset

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 20 | `farmasi` | ✅ Selesai | Manajemen Gudang Obat (`/farmasi-gudang`): CRUD Master `databarang`, Penerimaan Stok/PO, Mutasi antar Depo (`InsufficientStockException` guard), & Kartu Stok (`riwayat_barang_medis`). |
| 21 | `apotek_umum` | ✅ Selesai | POS retail mandiri `PO-APT-` dengan 5 cara bayar, daftar riwayat penjualan (`/api/v1/apotek-umum/sales`), & cetak struk thermal (`/apotek-umum/struk`). |
| 22 | `penjualan` | ✅ Selesai | Modul Penjualan Non-Resep & Alkes (`/penjualan`, `/penjualan/faktur`): katalog barang (`mlite_penjualan_barang`), order & pemotongan stok atomik, pelunasan, serta laporan omzet (`/api/v1/penjualan/report`). |
| 23 | `keuangan` | ✅ Selesai | Modul Akuntansi & Keuangan (`/keuangan`): Jurnal Umum, Pengeluaran Kas, Buku Besar, Laba Rugi, & Master Akun Rekening (`mlite_rekening` + `mlite_jurnal`). |
| 24 | `jasa_medis` | ✅ Selesai | Rekap & cetak Jasa Medis Dokter (`/jasa-medis`, `/jasa-medis/cetak`): agregasi tindakan per dokter, rincian per pasien, & pemisahan pilar klaim (`Tunai`, `BPJS`, `Piutang`). |
| 25 | `inventaris` | ✅ Selesai | Manajemen inventaris barang medis & non-medis (`/inventaris`): CRUD aset, kategori, lokasi, kondisi, & nilai perolehan (`mlite_inventaris`). |

---

## Tier 4 — Penunjang, Analitik, Bridging & AI

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 26 | `laboratorium` | ✅ Selesai | Filter periode multi-hari (`from`–`to`), cetak hasil lab (`/penunjang/lab/cetak`), input order lab langsung di modul lab, & master parameter nilai rujukan (`template_laboratorium`, `/api/v1/penunjang/lab/templates*`). |
| 27 | `radiologi` | ✅ Selesai | Filter periode multi-hari (`from`–`to`), hapus order radiologi (`DeleteRadiologyOrderUseCase`), cetak expertise (`/penunjang/radiologi/cetak`), serta upload & viewer lampiran gambar radiologi (`gambar_radiologi`, `/api/v1/penunjang/radiologi/images*`) + tautan Orthanc PACS. |
| 28 | `orthanc` | ✅ Selesai | Integrasi PACS & DICOM Viewer Orthanc (`/orthanc`): tautan Study UID (`bridging_orthanc`), URL OHIF Viewer, filter modality, & simpan interpretasi radiologi. |
| 29 | `laporan` | ✅ Selesai | Ringkasan kunjungan, top 10 ICD-10, pendapatan klaim, laporan rekonsiliasi order (`Pending`/`Cancelled`/`Void`), & export CSV/Excel (`/api/v1/laporan/export-csv`). |
| 30 | `manajemen` | ✅ Selesai | Dashboard Eksekutif Manajemen & LEAN Analytics Board (`/manajemen`): KPI kunjungan/pendapatan/lead time SOAP/overtime, metrik Net Signed Avg per poli (`HfisLeanCalculator`), & tren harian. |
| 31 | `vclaim` | ✅ Selesai | Lengkap: tabel `bridging_sep`, `bridging_rujukan_bpjs`, `bridging_surat_kontrol_bpjs`, `VclaimController`, panel UI VClaim di `/bridging` (Cari Peserta BPJS, Buat & Cetak SEP, Rujukan, Surat Kontrol). |
| 32 | `pcare` | ✅ Selesai | Lengkap: modul Bridging FKTP PCare BPJS (`/pcare`, `/pcare/rujukan/cetak`, `/api/v1/pcare/*`) untuk Pendaftaran, Kunjungan, Obat DPHO, Lab/Tindakan, & Rujukan FKTP. |
| 33 | `icare` | ✅ Selesai | Integrasi tombol `i-Care JKN` di layar dokter (`/dokter-ralan`), viewer riwayat i-Care (`/icare/riwayat`), & endpoint `/api/v1/icare/riwayat` (`FetchIcareHistoryUseCase`). |
| 34 | `bridging_hfis` | ✅ Selesai | Lengkap: `HfisLeanCalculator` + sinkron jadwal dokter HFIS (`mlite_hfis_jadwal`, `/api/v1/bridging/hfis/jadwal*`) & Antrean Online BPJS `antrol` Task ID 1–7 (`mlite_antrol_log`, `/api/v1/bridging/antrol/*`) di `/bridging`. |
| 35 | `jkn_mobile` | ✅ Selesai | Lengkap: API penuh Antrean Online Mobile JKN FKRTL (`/api/v1/jkn-mobile/*`: `token`, `antrean`, `status-antrean`, `sisa-antrean`, `batal-antrean`, `checkin`, `pasien-baru`, `antrean-farmasi`, `status-antrean-farmasi`, `jadwal-operasi-rs`, `jadwal-operasi-pasien`) dengan tabel `referensi_mobilejkn_bpjs`. |
| 36 | `jkn_mobile_fktp` | ✅ Selesai | Lengkap: API Mobile JKN khusus FKTP (`/api/v1/jkn-mobile-fktp/*`: `auth`, `antrean`, `status-antrean`, `sisa-antrean`, `batal-antrean`, `peserta`). |
| 37 | `satu_sehat` | 🟡 Parsial | Baru ada OAuth2 + async queue `Encounter` & `Condition`; belum ada UI mapping IHS ID, `Observation`, `MedicationRequest`, & `ServiceRequest`. |
| 38 | `vedika` | ✅ Selesai | Verifikasi `Layak`/`Perlu Perbaikan`, UI daftar pengajuan klaim BPJS (`/api/v1/bridging/vedika/claims`), & cetak bundel berkas Vedika (`/vedika/bundel`). |
| 39 | `veronisa` | ✅ Selesai | Modul Veronisa (`/veronisa`, `/veronisa/pdf`): verifikasi internal klaim non-kapitasi/PRB (`mlite_veronisa`), mapping obat DPHO (`mlite_veronisa_obat_mapping`), & cetak berkas verifikasi. |
| 40 | `sep_internal` | ✅ Selesai | Pembuatan & monitoring Rujukan / SEP Internal antar poli (`/api/v1/rujukan-internal` & tab di `/surat`). |
| 41 | `smartagent` | ✅ Selesai | Asisten AI Klinis & Knowledge Vault (`/smartagent`): manajemen pedoman PDT (`mlite_smartagent_knowledge`), profil model AI vLLM/Cloud (`mlite_smartagent_profiles`), & rekomendasi klinis + grounding ICD-10 (`RecommendClinicalPdtUseCase`). |
| 42 | `chat` | ✅ Selesai | Live Chat internal staf (`/chat`): kanal percakapan (`mlite_chat_conversations`), pengiriman pesan (`mlite_chat_messages`), & template balasan cepat (`mlite_chat_quick_responses`). |

---

## Tier 5 — Layanan Tambahan, Portal, SDM & Utilitas Sistem

| No | Plugin `ksmlite` | Status | Kekurangan Spesifik vs `.ref/ksmlite/` |
| :-: | :--- | :---: | :--- |
| 43 | `anjungan` | 🟡 Parsial | Sudah ada Display TV & Kiosk dasar; belum ada cetak tiket thermal, display LEAN TV, & antrean farmasi/kasir terpisah. |
| 44 | `wagateway` | 🟡 Parsial | Sudah ada kirim satuan, webhook QR, whitelist; belum ada UI live chat 2-arah sesi `cs`, template broadcast terjadwal, & AI draft CS. |
| 45 | `home_care` | 🟡 Parsial | Sudah ada jadwal, Privacy Shield & preceptor; belum ada PWA manifest/service worker, sign-off care plan DPJP, & pembayaran Midtrans/Kasera UI. |
| 46 | `portal_pasien` | ✅ Selesai | Portal mandiri pasien publik (`/portal-pasien`): verifikasi No. RM + Tanggal Lahir, lihat riwayat kunjungan, hasil lab, & booking antrean online. |
| 47 | `presensi` | ✅ Selesai | Modul absensi / presensi shift pegawai (`/presensi`): clock-in, clock-out, anti-duplikasi harian, & riwayat presensi (`mlite_presensi`). |
| 48 | `profil` | ✅ Selesai | Halaman profil & keamanan akun sendiri (`/profil`): ubah nama lengkap/email & ganti password terverifikasi (`password_verify` + `PASSWORD_BCRYPT`). |
| 49 | `riwayat_simgos` | ❌ Belum Ada | Viewer riwayat migrasi dari SIMGOS lama. |
| 50 | `users` | 🟡 Parsial | Sudah ada CRUD dasar & pencarian/filter tabel pengguna (`usersTableBody`). Sisa: reset password massal. |
| 51 | `settings` | ✅ Selesai | Pengaturan Sistem lengkap (`/settings`): tab Profil Instansi (`kode_rs`, `logo_url`), Pendaftaran, Farmasi, Bridging BPJS & SatuSehat, WhatsApp Gateway, serta Tarif & Billing. |
| 52 | `mlite_logs` | ✅ Selesai | Sudah ada `/audit`, rotasi log operasional, & UI Audit Trail Viewer (`mlite_logs` berfilter di `/api/v1/audit/logs`). |
| 53 | `mlite_api_key` | ✅ Selesai | Manajemen API Key Eksternal (`/api-keys`): generate key ter-hash SHA-256 (`raw_key` tampil sekali), whitelist IP, scope method, toggle aktif/nonaktif, & hapus (`mlite_api_keys`). |
| 54 | `modules` | ✅ Selesai | UI Manajer Modul SIMRS (`/modules`): daftar modul aktif/nonaktif/semua, pencarian, proteksi modul inti (`dashboard`, `users`, `settings`, `modules`, `profil`), toggle aktivasi, & atur urutan (`mlite_modules`). |
| 55 | `dashboard` | ✅ Selesai | Dashboard utama (`/dashboard`): 4 KPI real-time, grid menu modul, grafik tren kunjungan & pendapatan 7 hari, serta pengumuman internal staf (`mlite_pengumuman`). |
| 56 | `website` | ⚪ Opsional | CMS halaman depan website klinik publik. |
| 57 | `bantuan` | ⚪ Opsional | Halaman dokumentasi/panduan penggunaan internal. |
| 58 | `afm` | ⚪ Legacy/Khusus | Modul integrasi spesifik legacy `afm`. |
| 59 | `agent_api` | ⚪ Legacy/Khusus | Endpoint API agent legacy. |
| 60 | `api` | ⚪ Infra | Digantikan oleh REST API `/api/v1/*` di `ksm-next`. |
| 61 | `apps` | ⚪ Legacy/Khusus | Launcher aplikasi eksternal legacy. |
| 62 | `dokter_app` | ⚪ Legacy/Khusus | Wrapper aplikasi mobile dokter lama (digantikan Mobile App Shell responsif). |
| 63 | `verifikasi` | ✅ Selesai | Verifikasi keaslian surat medis publik via QR/hash token atau nomor surat (`/verifikasi` & `/api/v1/verifikasi`). |
