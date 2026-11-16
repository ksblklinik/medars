# PRD 01: Pendaftaran Pasien, Manajemen Kunjungan & Kiosk Antrean

## 1. Ikhtisar Domain
Domain ini mengelola identitas pasien (Master Pasien / Rekam Medis), dua jalur pendaftaran (In-Site Walk-In vs Check-In Booking Mobile JKN), pembuatan nomor registrasi antrean, pengaturan kebijakan per poliklinik secara dinamis, serta siklus hidup status kunjungan.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity + Mitigasi Modern)

1. **Dua Jalur Pendaftaran yang Tegas:**
   - **Jalur A — Pasien Datang Langsung (*In-Site / Walk-In*):**
     - Mendaftar langsung di loket/kiosk pada hari kunjungan.
     - Sistem menerbitkan nomor antrean (`no_reg`) baru berdasarkan urutan dokter, poli, dan `shift` pada jadwal hari tersebut.
     - **Reservasi Nomor Booking:** Generator `no_reg` In-Site wajib memperhitungkan nomor antrean tertinggi di `reg_periksa` maupun `booking_registrasi` (`MAX(reg_periksa, booking_registrasi) + 1`) agar nomor antrean pasien Mobile JKN tidak terambil.
     - Jika memilih tanggal mendatang (`tgl_registrasi > hari ini`), transaksi otomatis dialihkan ke tabel booking (`booking_registrasi`).
   - **Jalur B — Pasien Booking / Mobile JKN (*Check-In*):**
     - Pasien melakukan *check-in* dengan kode booking / QR / NIK / No. Kartu BPJS.
     - Sistem memvalidasi bahwa `tanggal_periksa == hari ini` dan `status == 'Belum'` (menolak jika tanggal berbeda atau sudah `'Terdaftar'`).
     - Sistem **mempertahankan `no_reg` asli dari booking** (tidak membuat antrean baru), menerbitkan `no_rawat` hari ini, lalu mengubah status booking menjadi `'Terdaftar'` dalam 1 transaksi atomik.

2. **Kebijakan Poliklinik Berbasis Pengaturan (Data-Driven):**
   - Aturan pembatasan penjamin (misal Poli Eksekutif tidak menerima BPJS) **dilarang di-hardcode** di dalam kode PHP.
   - Setiap poliklinik memiliki konfigurasi mandiri (`allow_bpjs`, tarif baru/lama) di Master Poliklinik.

3. **Mitigasi Konkurensi, Lintas-Database & Idempotensi:**
   - **Race Condition `no_rawat` & `no_reg`:** Di jam sibuk pagi (07:00–08:30 WIB), jika terjadi bentrok saat `INSERT`, sistem **secara atomik meregenerasi `no_rawat` (`YYYY/MM/DD/XXXXXX`) dan `no_reg` berikutnya** dalam transaksi yang sama berdasar `(tgl_registrasi, kd_poli, kd_dokter, shift)`.
   - **Anti-Tabrakan `no_rkm_medis` Lintas DB:** Selama masa transisi Dual-Connection, penerbitan `no_rkm_medis` baru wajib mengambil `MAX(legacy_db, next_db) + 1`.
   - **Idempotency Guard (Anti Double-Submit):** Tolak pendaftaran ganda apabila pasien sudah memiliki kunjungan aktif berstatus `'Belum'` pada tanggal, poli, dokter, dan shift yang sama.
   - **Pemblokiran Tunggakan:** Pasien dengan kunjungan sebelumnya berstatus `'Belum Bayar'` (dan bukan `'Batal'`) diblokir mendaftar hingga tagihan diselesaikan di kasir.
   - **Validasi Identitas:** Tanggal lahir pasien tidak boleh di masa depan; `RegPeriksa` menyimpan informasi `prefik`, `room`, dan `shift`.

4. **Clinical Guard Shield & Dokter Pengganti:**
   - Dilarang keras mengubah nomor antrean (`no_reg`), poliklinik, atau dokter (`kd_dokter`) pada pasien yang **sudah memiliki catatan SOAP dokter (`pemeriksaan_ralan`), tindakan medis/paramedis (`rawat_jl_dr` di luar konsul awal `RJ002`/`RJ003`/`RJ008`, `rawat_jl_pr`, `rawat_jl_drpr`), e-resep (`resep_obat`), order lab/radiologi, atau status `Sudah`/`Selesai`**.
   - Jika dokter berhalangan dan digantikan sebelum pelayanan, tindakan konsultasi awal otomatis dialihkan ke dokter pengganti aktif.

5. **Alur Kiosk Mandiri & General Consent:**
   - Kiosk mandiri menampilkan antarmuka touch-friendly (`.kiosk-wrapper`), supresi keyboard virtual popup, dan tombol cepat cetak tiket.
   - Pendorongan Formulir Persetujuan Umum (*General Consent*) ke layar Kiosk bersifat mandiri tanpa ketergantungan modul anjungan.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_auto_regenerate_no_rawat_and_no_reg_on_concurrent_insert()`
- `test_should_generate_next_no_rkm_medis_above_both_legacy_and_next_databases()`
- `test_should_not_assign_insite_no_reg_that_collides_with_mobile_jkn_booking()`
- `test_should_reject_duplicate_active_registration_for_same_patient_poli_doctor_shift()`
- `test_should_reject_penjamin_when_poliklinik_setting_disallows_it()`
- `test_should_checkin_mobile_jkn_booking_preserving_original_no_reg()`
- `test_should_reject_checkin_if_booking_date_is_not_today_or_already_registered()`
- `test_should_block_changing_no_reg_or_doctor_if_clinical_records_exist()`
- `test_should_allow_manual_queue_shift_only_for_unperformed_patient()`
- `test_should_reassign_consultation_action_to_replacement_doctor()`
