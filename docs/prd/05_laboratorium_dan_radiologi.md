# PRD 05: Laboratorium & Radiologi (Penunjang Diagnostik)

## 1. Ikhtisar Domain
Domain ini mengelola siklus hidup permintaan pemeriksaan penunjang (Laboratorium & Radiologi), registrasi sampel, entri hasil nilai kritis, integrasi billing, dan verifikasi persetujuan kasir.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **Pemisahan Nomor Order (PL) Berdasarkan Status Kasir:**
   - Jika nomor permintaan (PL) lama sudah lunas / di-approve di Kasir, setiap penambahan item pemeriksaan baru **WAJIB dibuatkan nomor order PL baru** agar kasir mendeteksi adanya tagihan susulan.
2. **Lock Tombol Validasi Sebelum Pelunasan:**
   - Tombol entri hasil dan validasi di unit laboratorium terkunci (*disabled*) jika order tunai belum dilunasi atau belum di-approve oleh Kasir.
3. **Pemisahan Order Berdasarkan Jenis Klaim:**
   - Item penunjang berstatus Kapitasi BPJS dan item berstatus Tunai dipisahkan ke nomor PL yang berbeda.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_generate_new_order_number_if_previous_order_is_already_paid()`
- `test_should_lock_sampling_validation_until_cashier_approves_payment()`
- `test_should_segregate_kapitasi_and_tunai_lab_items_into_distinct_orders()`
