# PRD 01: Pendaftaran Pasien, Manajemen Kunjungan & Kiosk Antrean

## 1. Ikhtisar Domain
Domain ini mengelola identitas pasien (Master Pasien / Rekam Medis), proses pendaftaran rawat jalan / rawat inap / IGD, pembuatan nomor registrasi antrean, integrasi Kiosk Anjungan Mandiri, serta siklus hidup status kunjungan.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **Pencegahan Race Condition Pendaftaran (`no_rawat`):**
   - Di jam sibuk pagi (07:00–08:30 WIB), banyak loket/kiosk mendaftar bersamaan.
   - Jika saat `INSERT` nomor rawat terdeteksi bentrok padahal `no_rkm_medis` berbeda, sistem wajib **secara atomik mencari dan meregenerasi nomor rawat unik berikutnya** (`YYYY/MM/DD/XXXXXX`), menyimpannya, dan mengembalikan nomor rawat riil ke antarmuka.
2. **Clinical Guard Shield Pendaftaran:**
   - Dilarang keras mengubah nomor antrean (`no_reg`) atau dokter (`kd_dokter`) pada pasien yang **sudah memiliki catatan SOAP dokter, tindakan medis/paramedis, e-resep apotek, permintaan lab/radiologi, atau sudah lunas di kasir**.
   - Pergeseran antrean manual hanya diperbolehkan jika pasien masih berstatus `Belum`/`Awal` dan memiliki 0 tindakan/order.
3. **Penyelarasan Dokter Pengganti:**
   - Jika dokter berhalangan dan digantikan dokter pengganti, tindakan konsultasi awal registrasi dan `provider_id` penagihan otomatis dialihkan ke dokter pengganti aktif.
4. **Alur Kiosk Mandiri & General Consent:**
   - Kiosk mandiri menampilkan antarmuka touch-friendly (`.kiosk-wrapper`), supresi keyboard virtual popup, dan tombol cepat cetak tiket.
   - Pendorongan Formulir Persetujuan Umum (*General Consent*) ke layar Kiosk bersifat mandiri tanpa ketergantungan modul anjungan.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_auto_regenerate_no_rawat_on_concurrent_insert()`
- `test_should_block_changing_no_reg_or_doctor_if_clinical_records_exist()`
- `test_should_allow_manual_queue_shift_only_for_unperformed_patient()`
- `test_should_reassign_consultation_action_to_replacement_doctor()`
