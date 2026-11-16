# PRD 11: Audit Keamanan, Integritas ACID & Disaster Recovery

## 1. Ikhtisar Domain
Domain ini mengatur kebijakan keamanan informasi, pencegahan kerentanan (SQLi, XSS, CSRF), manajemen hak akses least-privilege, serta mitigasi pemulihan bencana (*Disaster Recovery*).

## 2. Aturan Keamanan & Spesifikasi Kunci
1. **Pencegahan Kerentanan Standar OWASP:**
   - Wajib Prepared Statement terparameter untuk seluruh kueri SQL (0 konkatenasi string mentah).
   - Sanitasi XSS pada seluruh output data yang dirender ke tampilan HTML/PDF.
   - Otorisasi berbasis peran (RBAC) pada setiap endpoint API dan controller backend.
2. **Audit Trail & Immutability:**
   - Seluruh mutasi data keuangan dan perubahan status order dicatat ke tabel log audit permanen beserta identitas eksekutor (`user_id`), IP, dan timestamp.
   - Dilarang menghapus record buku besar transaksi (`mlite_billing_ledger`).
3. **Mekanisme Otomasi Backup Harian & Rotasi Log:**
   - Jadwal cron backup database terkompresi harian di luar jam pelayanan aktif (jam 01:00 WIB).
   - Rotasi dan pembersihan log kueri berukuran masif agar ukuran file backup tetap sehat dan cepat dipulihkan (*low RTO/RPO*).

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_block_unauthorized_access_to_administrative_endpoints()`
- `test_should_record_audit_log_entry_for_every_financial_transaction()`
- `test_should_escape_malicious_script_tags_in_patient_notes_rendering()`
