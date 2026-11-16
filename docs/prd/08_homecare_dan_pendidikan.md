# PRD 08: Layanan Home Care Mandiri & Trias Preceptorship Pendidikan

## 1. Ikhtisar Domain
Domain ini mengelola operasional kunjungan medis ke rumah pasien (PWA Home Care), alokasi dokter DPJP, dan subsistem pendidikan lapangan mahasiswa magang bersama preceptor nakes dan dosen pembimbing.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **PWA Standalone Terisolasi:**
   - Modul Home Care beroperasi sebagai PWA mandiri (tidak dilebur ke portal pasien umum).
2. **Pemisahan DPJP vs Trias Preceptorship:**
   - Dokter DPJP murni bertindak sebagai klinisi medis (asesmen, e-resep berizin SIP, sign-off care plan via `HomeCareDpjpHelper`).
   - Alur pendidikan stase lapangan terisolasi antara Siswa Magang <-> Nakes Preceptor Lapangan ber-SK <-> CI Supervisor (Dosen Kampus).
3. **Patient Privacy Shield (De-Identifikasi RME):**
   - CI Supervisor (pihak kampus eksternal) **DILARANG melihat nama dan nomor rekam medis asli pasien**.
   - Nama disamarkan menjadi studi kasus: `Ny. S*** (56 Th, P)`.
4. **Matriks Penugasan Preceptor Ber-SK:**
   - Pilihan preceptor dibatasi hanya pada nakes yang memiliki SK Penugasan resmi klinik untuk institusi kampus terkait.
5. **Multi-Gateway Pembayaran:**
   - Mendukung integrasi online payment gateway mandiri (Midtrans Snap & Kasera Pay).

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_pseudonymize_patient_identity_for_academic_supervisor_view()`
- `test_should_restrict_preceptor_selection_to_institution_sk_holders()`
- `test_should_prevent_dpjp_from_being_assigned_educational_grading_tasks()`
