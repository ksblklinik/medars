# PRD 09: Arsitektur Backend Clean, Dual-Connection & Transaksi Atomik

## 1. Ikhtisar Domain
Domain ini mengatur fondasi arsitektur perangkat lunak sisi server, arsitektur Dual-Connection (Koneksi Legacy Read-Only vs Koneksi Next Read-Write), Anti-Corruption Layer (ACL), transaksi atomik ACID, dan penanganan konkurensi.

## 2. Aturan Rekayasa & Spesifikasi Kunci

1. **Pola Clean Architecture / Hexagonal:**
   - *Domain Entities:* Model bisnis murni, bebas dari dependensi framework, ORM, atau driver SQL.
   - *Use Cases / Services:* Mengorkestrasi aturan bisnis, memvalidasi DTO (*Data Transfer Object*), dan memegang batas transaksi database.
   - *Repository Pattern:* Antarmuka terpisah antara kontrak domain dengan implementasi database konkret.

2. **Arsitektur Dual-Connection & Anti-Corruption Layer (ACL):**
   - **Koneksi `legacy` (Read-Only):**
     - Dikonfigurasi khusus hak akses `SELECT` ke basis data MySQL eksisting.
     - Digunakan oleh *ACL Repository Adapter* untuk membaca master pasien, master obat, dan riwayat klinis lama secara aman tanpa risiko modifikasi data.
   - **Koneksi `next` (Read-Write Primer):**
     - Menyimpan seluruh transaksi baru ke skema modern `ksm-next`.
     - Tipe data asli, foreign key relasional sejati, dan kolom audit otomatis (`created_at`, `updated_at`, `created_by`, `updated_by`).

3. **Kepatuhan ACID & Defensive Database Operations:**
   - Seluruh mutasi multi-tabel dibungkus transaksi database dengan `PdoTransactionManager`.
   - Otomatis `rollBack()` saat terjadi eksepsi pada sub-operasi transaksi.
   - Dukungan *Nested Transactions via Savepoints* (`SAVEPOINT sp_n`) untuk use-case komposit lintas-modul.
   - Whitelisting ketat seluruh payload input menggunakan DTO sebelum disimpan ke basis data.
   - Kualifikasi tabel eksplisit (`tabel.kolom`) pada seluruh query relasi.

4. **Kepatuhan Driver PDO Anti-Cast:**
   - PDO flag `ATTR_EMULATE_PREPARES => false` wajib aktif untuk menjaga tipe data native dan mencegah auto-cast nomor rekam medis ber-awalan nol (`'000123'`).

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_rollback_entire_transaction_when_any_sub_operation_fails()`
- `test_should_support_nested_transactions_using_savepoints()`
- `test_should_read_legacy_data_via_anti_corruption_layer_without_write_permission()`
- `test_should_reject_unwhitelisted_fields_at_dto_validation_boundary()`
- `test_should_enforce_foreign_key_constraints_on_delete_or_update()`
