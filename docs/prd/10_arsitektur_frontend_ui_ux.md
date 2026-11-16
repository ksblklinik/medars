# PRD 10: Desain Antarmuka, Hotkeys Operasional & Pengalaman Pengguna (UI/UX)

## 1. Ikhtisar Domain
Domain ini menetapkan standar desain visual, tata kelola komponen antarmuka, responsivitas multi-perangkat (Desktop, Mobile, Kiosk, Display TV), dan konvensi pintasan keyboard untuk kecepatan layanan klinik.

## 2. Aturan Desain & Spesifikasi Kunci
1. **Pencegahan Double-Submission & Feedback Loading:**
   - Tombol transaksi (Simpan, Bayar, Validasi) wajib otomatis dinonaktifkan (`disabled`) dan menampilkan indikator loading spinner saat request berlangsung.
2. **Standar Pintasan Keyboard (Hotkeys Cepat POS & Kasir):**
   - `F2`: Bayar / Simpan Transaksi.
   - `F4`: Buka Modal Pencarian Pasien / E-Resep.
   - `F8`: Pending / Simpan Draf Transaksi.
   - `F9`: Batal / Reset Formulir.
   - `Esc`: Menutup modal aktif.
   - Kursor otomatis fokus (*auto-focus*) ke kolom pencarian utama saat modal terbuka.
3. **Supresi Autofill Peramban:**
   - Seluruh input form klinis dan administratif menyertakan atribut anti-autofill (`autocomplete="off"`) agar balon riwayat isian browser tidak menutupi antarmuka.
4. **Desain Multi-Device (4 Tipe Layar):**
   - *Desktop:* Lebar optimal, tabel responsif dengan scroll horizontal aman.
   - *Mobile / Tablet:* Komponen ringkas dengan tombol aksi yang mudah dijangkau satu tangan.
   - *Kiosk Mandiri:* Target sentuh besar (minimal 48px), visual kontras tinggi, navigasi ramah pasien lansia.
   - *Display TV Antrean:* Tipografi besar, keterbacaan dari jarak 5–10 meter, tanpa elemen interaktif yang memblokir tampilan antrean.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_disable_action_button_and_display_spinner_during_async_request()`
- `test_should_trigger_expected_actions_on_f2_f4_f8_f9_keyboard_events()`
- `test_should_auto_focus_primary_input_upon_modal_rendered()`
