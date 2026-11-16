---
name: frontend-ui-ux
description: >
  Standar Arsitektur Frontend, Template Engine (Templates.php), Komponen UI/UX AdminLTE
  & Bootstrap 3, Isolasi DOM & JavaScript (IIFE, Scoped Selectator/DataTables, Triple-Gate
  WebSocket), Hotkeys Operasional (POS/Kasir/Poli), serta Responsivitas Multi-Device
  (Desktop, Mobile, Kiosk, Display TV) pada aplikasi ksmlite. Panggil skill ini saat
  membuat atau mengubah file view HTML (.html), JS (plugins/*/js/), CSS, modal, tabel
  DataTables, atau komponen antarmuka di ksmlite.
---

# Standar Arsitektur Frontend & Desain UI/UX (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `backend-architecture` untuk integrasi AJAX ke controller `Admin.php`/`Site.php` dan format JSON balasan.
> - Padukan dengan `wa-gateway-pdf` jika menyentuh template cetak PDF `mPDF 8.x` atau tombol UI kirim WhatsApp (`{if: !empty($is_admin) || !empty($is_whitelist)}`).
> - Padukan dengan `kasir-billing-akuntansi` untuk spesifikasi POS Apotek Umum & Kasir (`data-selected_klaim`, selector `[T]`/`[K]`/`[NK]`).

---

## 1. Sintaks Template Engine mLite (`systems/lib/Templates.php`)

Seluruh berkas tampilan (`plugins/<modul>/view/admin/*.html` & `themes/admin/*.html`) diproses oleh `Templates.php`:

*   **Variabel & Ekspresi PHP Terbatas:**
    *   Cetak variabel: `{$nama_variabel}` atau akses array/properti `{$value.kolom}`.
    *   Ekspresi inline: `{?=url([ADMIN, 'nama_modul', 'aksi'])?}` atau `{?=isset_or($var, '-')?}`.
*   **Percabangan & Perulangan:**
    *   Kondisi: `{if: !empty($data)} ... {elseif: $status == 'aktif'} ... {else} ... {/if}`
    *   Perulangan: `{loop: $list_data} ... {$value.nama} ... {/loop}` (mendukung juga `{loop: $list_data as $key => $val}`).
*   **Keamanan XSS & Perlindungan Kurung Kurawal (`noParse`):**
    *   Sterilkan data input bebas dari database menggunakan `htmlspecialchars()` sebelum ditampilkan ke HTML.
    *   Apabila variabel berisi JSON mentah, CSS inline, atau script dengan karakter `{...}` yang tidak boleh dieksekusi template engine, lindungi di controller PHP menggunakan `$this->tpl->noParse($str)` atau `$this->tpl->noParse_array($arr)`.

---

## 2. Struktur Layout, Isolasi DOM & Manajemen Modal (`view/admin/`)

Aplikasi `ksmlite` menggunakan AdminLTE / Bootstrap 3 dengan pemuatan partial AJAX yang berdampingan. Mencegah tabrakan DOM adalah keharusan mutlak:

*   **Hirarki Kontainer Modul Baku:**
    ```html
    <div class="row" id="manage" data-module="nama_modul">
      <div class="col-md-12">
        <div class="panel panel-default">
          <div class="panel-heading">...</div>
          <div class="panel-body">
            <div id="form"></div>
            <div id="display">...</div>
          </div>
        </div>
      </div>
    </div>
    ```
    *   Selalu seleksi sub-elemen melalui kontainer induk: `$('#manage #display')`, `$('#manage #form')`. Jangan memakai `#display` telanjang.
*   **Penamaan ID Unik Berprefiks Modul:**
    *   Seluruh ID modal, form, dan tabel **wajib berprefiks spesifik modul** (contoh: `#modalPasienBaruIgd`, `#dokter_ralan_odontogram_modal`, `#tabel_stok_apotek_umum`). Dilarang menggunakan ID generik seperti `#myModal`, `#modal`, atau `#tabelData`.
*   **Pembersihan Siklus Hidup Modal (*Clean Modal Lifecycle*):**
    *   Setiap modal remote/AJAX wajib membersihkan isinya saat ditutup agar tidak meninggalkan duplikasi ID di DOM:
        ```javascript
        $('#modal_nama_modul').on('hidden.bs.modal', function () {
            $(this).find('.modal-content').empty();
        });
        ```

---

## 3. Standar JavaScript, Plugin UI & Cache-Busting (`js/admin/*.js`)

1. **Enkapsulasi IIFE Closure:**
   Bungkus seluruh script modul dalam IIFE agar variabel lokal tidak mencemari `window`:
   ```javascript
   (function ($) {
       'use strict';
       var _activeXHR = null;
       // Logika modul di sini
   })(jQuery);
   ```
   *(Ekspor ke `window.namaFungsi` hanya jika wajib dipanggil atribut inline HTML dari luar).*
2. **Scoped Event Delegation Ber-Namespace:**
   *   **Dilarang Keras:** `$(document).on('click', 'button, .btn', ...)` atau `$(document).on('click', '#submit', ...)`.
   *   **Wajib:**
       ```javascript
       $('#manage').off('.namaModul').on('click.namaModul', '.btn-simpan-item', function (e) { ... });
       ```
3. **Inisialisasi Scoped Plugin UI (`selectator`, `datetimepicker`, `DataTable`):**
   *   Batasi selektor ke form/tabel modul (`$('#form_nama_modul select').selectator()`, `$('#tabel_nama_modul').DataTable(...)`).
   *   **Larangan DataTables pada Tabel Accordion/Collapse (`_DT_CellIndex` Prevention):** Dilarang keras memberi `class="dataTables"` atau memanggil `.DataTable()` pada tabel yang memiliki baris anak ber-`colspan` (`<tr class="collapse"><td colspan="...">`), karena jumlah kolom `<th>` dan `<td>` tidak 1:1.
4. **Supresi Autofill Peramban:**
   *   Panggil `suppressBrowserAutofill()` pada `$(document).ready()`, `$(document).ajaxComplete()`, dan `shown.bs.modal`.
   *   Tambahkan atribut `autocomplete="off" data-lpignore="true" data-form-type="other"` pada seluruh input teks operasional.
5. **Triple-Gate WebSocket / Polling Protection:**
   *   Setiap handler `ws.onmessage` atau interval reload wajib mengecek 3 gerbang berurutan:
       1. **DOM Presence Gate:** `if ($('#manage[data-module="nama_modul"]').length === 0 || !$('#display').is(':visible')) return;`
       2. **Modal Active Shield:** `if ($('.modal').is(':visible')) return;`
       3. **Debounce & Abort:** Gunakan `setTimeout(..., 250)` dan batalkan request berjalan `_activeWSRefreshXHR.abort()`.
6. **Cache-Busting Aset Statis vs Rute Virtual:**
   *   Di template HTML: `<script src="{?=url()?}/plugins/nama_modul/js/admin/file.js?v={?=time()?}"></script>`
   *   Di controller PHP (aset fisik): `$this->core->addJS(url('plugins/nama_modul/js/admin/file.js?v=' . time()), 'footer');`
   *   Di controller PHP (rute virtual admin yang sudah membawa token): `$this->core->addJS(url([ADMIN, 'nama_modul', 'javascript']) . '&v=' . time(), 'footer');` (wajib pakai `&v=`, bukan `?v=`).

---

## 4. Standar UX Operasional Klinik & Responsivitas Multi-Device

1. **Pencegahan Double-Submit & Loading Feedback:**
   *   Pada setiap tombol simpan/proses AJAX transaksi (kasir, resep, registrasi, verifikasi), segera nonaktifkan tombol dan tampilkan spinner sebelum `$.ajax()`:
       ```javascript
       var $btn = $(this);
       var origHtml = $btn.html();
       $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');
       $.ajax({ /* ... */ }).always(function () {
           $btn.prop('disabled', false).html(origHtml);
       });
       ```
2. **Standar Keyboard Shortcuts (Hotkeys POS, Kasir & Poli Cepat):**
   *   Pertahankan konsistensi hotkey operasional bila modul mendukung alur cepat:
       *   `F2`: Simpan / Bayar Transaksi
       *   `F4`: Buka Modal Pencarian Pasien / Tarik E-Resep
       *   `F8`: Simpan Sementara / Pending / Hold
       *   `F9`: Reset / Batal Transaksi
       *   `Esc`: Tutup modal aktif
   *   *Aturan Keamanan Hotkey:* Pastikan listener `keydown` hanya aktif ketika modul target benar-benar berada di DOM (`$('#manage[data-module="..."]').length > 0`) dan selalu `e.preventDefault()` tombol fungsi browser agar tidak memicu dialog cetak/save browser secara tidak sengaja.
   *   *Auto-Focus Input Utama:* Saat modal transaksi/scanner dibuka (`shown.bs.modal`), fokuskan kursor ke input pencarian/barcode (`$('#input_cari').focus()`).
3. **Notifikasi & Pesan Error yang Informatif:**
   *   Pastikan respon JSON `{status: 'error', msg: '...'}` selalu ditampilkan jelas kepada petugas melalui komponen notifikasi modul (alert Bootstrap, SweetAlert, atau Toastr) tanpa membiarkan error gagal diam-diam di console browser.
4. **Standar Responsivitas 4 Tipe Layar:**
   *   **Desktop Admin & Kasir:** Gunakan grid Bootstrap `col-md-*` / `col-lg-*` dan bungkus tabel lebar dengan `<div class="table-responsive">`.
   *   **Mobile & Tablet Petugas:** Gunakan class visibilitas responsif (`hidden-xs`, `hidden-md`, `col-xs-12`) serta pertahankan shortcut menu cepat di `themes/admin/index.html` dan `plugins/dashboard/view/admin/main.html`.
   *   **Kiosk Mandiri Pasien:** Bungkus komponen dengan `.kiosk-wrapper`, gunakan target sentuh besar (minimal tinggi tombol `48px+`), font mudah dibaca, dan hindari scroll horizontal/autofill popup.
   *   **Display TV Antrean / LEAN / Gatekeeper:** Gunakan tipografi besar berkontras tinggi, hindari modal interaktif yang memblokir layar pantau, dan pastikan font ikon termuat bersih lintas-host.
