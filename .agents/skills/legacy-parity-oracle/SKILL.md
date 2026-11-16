---
name: legacy-parity-oracle
description: >
  Protokol Wajib Rujukan Aplikasi Lama (.ref/ksmlite/) sebelum merancang, menguji, atau
  mengimplementasikan fitur/modul apa pun di ksm-next. Panggil skill ini setiap kali memulai
  pengerjaan modul dari GAP_INVENTORY.md, membuat fitur baru, atau memeriksa paritas UI/UX dan
  proses bisnis terhadap ksmlite.
---

# 🏛️ Protokol Wajib Rujukan Aplikasi Lama (`.ref/ksmlite/` Oracle)

> **Aturan Mutlak:** Dilarang menulis test, UseCase, Controller, atau Template UI hanya dari ingatan atau asumsi. Setiap fitur wajib terlebih dahulu membedah implementasi nyatanya di `.ref/ksmlite/`.

---

## Langkah Wajib Sebelum Menulis Kode (Pre-Flight Oracle Check)

1. **Identifikasi Plugin Rujukan di `.ref/ksmlite/plugins/<nama_plugin>/`:**
   - Periksa daftar file di folder plugin terkait (`Admin.php`, `Site.php`, `Info.php`, `view/admin/`, `js/admin/`).
2. **Audit Endpoint & Proses Bisnis (`Admin.php` / `Site.php`):**
   - Catat semua metode `get*`, `post*`, `any*` (misal `getManage`, `anyDisplay`, `anyForm`, `postSave`, `postHapus`, `getCetak*`).
   - Periksa aturan validasi, kalkulasi, guard penghapusan, dan nama kolom tabel database persis seperti yang dipakai `ksmlite`.
3. **Audit Antarmuka & Ergonomi (`view/admin/*.html` & `js/admin/*.js`):**
   - Catat field form apa saja yang ada di `form.html`, kolom apa saja yang tampil di `display.html`, tombol aksi per baris (Edit, Hapus, Cetak, Modal Riwayat), serta filter/toolbar di `manage.html`.
   - Pastikan seluruh field & aksi penting di `ksmlite` ikut hadir di UI `ksm-next` (baik di desktop 2-bar `ksmlite` maupun di Mobile App Shell `< 768px`).
4. **Terapkan ke Arsitektur `ksm-next` (TDD-First + Clean Architecture):**
   - Tulis unit/controller test (RED) yang memverifikasi seluruh aturan bisnis hasil bedah `.ref/ksmlite/`.
   - Implementasikan ke Domain/Application/Infrastructure/Presentation tanpa dependensi `PDO` langsung di Domain/Application.
   - Uji di PHPUnit hingga 100% GREEN, lalu perbarui `.agents/GAP_INVENTORY.md`.
