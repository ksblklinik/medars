# PRD 09: Arsitektur Backend Clean, Basis Data & Transaksi Atomik

## 1. Ikhtisar Domain
Domain ini mengatur fondasi arsitektur perangkat lunak sisi server, desain skema database relasional modern, penanganan konkurensi, dan integritas ACID.

## 2. Aturan Rekayasa & Spesifikasi Kunci
1. **Pola Clean Architecture / Hexagonal:**
   - *Domain Entities:* Model data murni tanpa dependensi framework.
   - *Use Cases / Services:* Seluruh aturan bisnis dieksekusi di sini dengan transaksi atomik.
   - *Repository Pattern:* Antarmuka terpisah untuk operasi persistensi database.
2. **Kepatuhan ACID & Defensive Database Operations:**
   - Seluruh mutasi multi-tabel dibungkus transaksi database dengan blok `try-catch` dan otomatis `rollBack()`.
   - Whitelisting ketat seluruh payload input menggunakan DTO sebelum disimpan ke basis data.
   - Kualifikasi tabel eksplisit (`tabel.kolom`) pada seluruh query relasi.
3. **Desain Basis Data & Kompatibilitas MySQL 8.0 Eksisting:**
   - **Engine Database Wajib:** MySQL 8.0 (sesuai database live `ksmlite`).
   - **Preservasi Kunci String & Composite Primary Keys:** Mengikuti kontrak baku [`00_database_schema_dictionary_mapping.md`](file:///opt/app/ksmlite/starter-kit/docs/prd/00_database_schema_dictionary_mapping.md) dan DDL [`database_schema_oracle.sql`](file:///opt/app/ksmlite/starter-kit/docs/database_schema_oracle.sql).
   - Kolom kunci `no_rawat`, `no_rkm_medis`, `no_reg`, `kode_brng` wajib berformat String (`VARCHAR`) agar kompatibel 100% saat diuji berdampingan dengan database eksisting di Linux Staging.
   - Tabel komposit (`pemeriksaan_ralan`, `detail_pemberian_obat`) dipetakan dengan composite key handling di layer repository.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_rollback_entire_transaction_when_any_sub_operation_fails()`
- `test_should_reject_unwhitelisted_fields_at_dto_validation_boundary()`
- `test_should_enforce_foreign_key_constraints_on_delete_or_update()`
