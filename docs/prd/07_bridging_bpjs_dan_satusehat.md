# PRD 07: Integrasi Eksternal, Bridging BPJS & SatuSehat FHIR

## 1. Ikhtisar Domain
Domain ini mengelola integrasi eksternal ke BPJS Kesehatan (VClaim, PCare FKTP, Antrean HFIS, Aplicare, i-Care) dan Kementerian Kesehatan (SatuSehat FHIR ServiceRequest, Encounter, Condition).

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **Zero-Latency Circuit Breaker:**
   - Jika kredensial bridging (`consid`, `secretkey`, `user_key`, `url`) bernilai kosong, `-`, `0`, atau dummy, service bridging **wajib langsung mengembalikan respons error JSON mock dalam 0 milidetik** tanpa pernah menginisiasi koneksi cURL ke jaringan luar.
2. **Strict Timeout Limit:**
   - Batasi `CONNECTTIMEOUT <= 2 detik` dan total `TIMEOUT <= 4 detik`. Dilarang menggunakan timeout tak terbatas (`0`).
3. **Larangan Pemanggilan Sinkron di Alur Kritis Layanan:**
   - Dilarang keras menaruh pemanggilan API BPJS/SatuSehat secara sinkron di tengah alur cetak tiket antrean loket, pendaftaran pasien, atau validasi kasir.
   - Operasional lokal diselesaikan terlebih dahulu, pengiriman data eksternal dijalankan secara asinkron (background queue).
4. **Formula Jam Layanan HFIS LEAN Board:**
   - Membedakan jam operasional Front Office (07:00:00) vs jam layanan dokter riil (Pagi: 08:00:00, Sore Gigi: 15:00:00, Sore Non-Gigi: 16:00:00).
   - Perhitungan estimasi jam dilayani dan metrik kedatangan bertanda (*Net Signed Avg*).

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_return_error_mock_in_zero_ms_when_credentials_are_missing()`
- `test_should_enforce_strict_connect_and_execution_timeout()`
- `test_should_queue_satusehat_fhir_sync_asynchronously_without_blocking_ui()`
- `test_should_calculate_lean_estimated_time_based_on_hfis_doctor_start_hour()`
