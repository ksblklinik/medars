# PRD 04: Kasir, Billing Ledger Murni, Akuntansi & POS Retail

## 1. Ikhtisar Domain
Domain ini mengelola kasir rawat jalan, rawat inap, IGD, kalkulasi split billing, buku besar transaksi (*pure billing ledger*), jurnal umum akuntansi otomatis, anti-fraud void/retur, dan isolasi POS retail Apotek Umum.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **4 Pilar Murni `claim_type` di Billing Ledger:**
   - Seluruh tagihan dicatat di buku besar ledger dengan 4 kategori murni: `Tunai`, `Kapitasi`, `Non-Kapitasi`, dan `Piutang`.
   - Status tagihan mendukung status `Bundled` untuk paket tindakan yang dijamin BPJS.
2. **Split Billing `[T]` vs `[K]` & Isolasi Kas Fisik:**
   - Tagihan pasien BPJS murni (100% Kapitasi) bernilai Rp 0 bagi pasien, dan **DILARANG KERAS mendebet Akun Kas Fisik (`1101.01`)**. Hanya membentuk jurnal penjaminan piutang manajerial (`1103.01`).
   - Pada kasus Split Billing (pasien BPJS mengambil layanan/obat tunai di luar paket), **hanya porsi item Tunai `[T]` yang ditagihkan mandiri dan mendebet Kas Fisik Kasir (`1101.01`)**.
3. **Prosedur Retur / Void Anti-Fraud & Jurnal Pembalik:**
   - Pembatalan penuh (*Full Void*) hanya diizinkan jika pasien memiliki **0 layanan** (belum diperiksa dokter, 0 obat, 0 lab/rad).
   - Dilarang keras menghapus record jurnal (`DELETE FROM mlite_jurnal`). Wajib menerbitkan **Jurnal Pembalik berpasangan seimbang**.
   - Cetak ulang kuitansi void wajib membubuhkan stempel/banner tebal: `FAKTUR / KUITANSI TIDAK BERLAKU`.
4. **Isolasi Penuh POS Apotek Umum (`APT` & `PO-APT-%`):**
   - Entitas mandiri dengan pembukuan laba-rugi terpisah dari klinik.
   - Mendukung 5 cara bayar: `Tunai`, `QRIS`, `Debit EDC`, `Transfer`, dan `Piutang Rekanan`.
   - Surat Pemesanan Apotek Umum wajib berprefiks `PO-APT-%` dan terisolasi dari modul pengadaan farmasi klinik.
5. **Kebijakan Rekonsiliasi Harian Sadar (Order Expiry SSOT):**
   - Dilarang menghapus tagihan secara diam-diam di background (*No Blind Silent Cron Deletes*).
   - Order yang belum dilayani diselesaikan secara sadar oleh PIC unit melalui modul rekonsiliasi harian dengan mencatat identitas user dan alasan pembatalan ke log audit, lalu status diubah menjadi `'Cancelled'`.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_never_debit_physical_cash_account_for_pure_bpjs_claim()`
- `test_should_debit_physical_cash_only_for_tunai_portion_in_split_billing()`
- `test_should_create_balanced_reversal_journal_on_void_instead_of_delete()`
- `test_should_block_void_if_any_clinical_service_was_already_performed()`
- `test_should_isolate_apotek_umum_transactions_from_clinic_billing()`
