---
name: backend-architecture
description: >
  Panduan arsitektur Backend SIMRS ksmlite (mLite Core & Vibe Framework): anatomi plugin
  (Admin.php, Site.php, Info.php, Service/, migrations/), konvensi routing (getIndex,
  anyManage, anyDisplay, postSave), QueryWrapper & Native PDO (systems/lib/QueryWrapper.php),
  pola Thin Controller & Fat Service, ExportHelper, Migration, serta standar JSON API response.
  Panggil skill ini saat membuat modul/plugin baru, merancang endpoint controller/service,
  menulis query database/JOIN/transaksi, atau membuat migrasi skema di ksmlite.
---

# Standar Arsitektur Backend (`mLite Core` & `Vibe Framework`) — `ksmlite`

> **Relasi Lintas-Skill:**
> - Padukan dengan `frontend-ui-ux` saat menyambungkan endpoint controller ke view template & AJAX JS.
> - Padukan dengan `kasir-billing-akuntansi` / `klinis-farmasi-klaim` / `wa-gateway-pdf` jika menyentuh tabel transaksi domain terkait.
> - Padukan dengan `audit` untuk checklist keamanan SQLi, XSS, CSRF, dan ACID transaksi.

---

## 1. Anatomi & Siklus Hidup Plugin (`plugins/<nama_modul>/`)

Setiap modul di direktori `/opt/app/ksmlite/plugins/<nama_modul>/` mengikuti struktur standar berikut:

*   **`Info.php`**: Berisi metadata modul (`name`, `description`, `author`, `version`, `compatibility`, `icon`, `pages`), serta fungsi `install()` dan `uninstall()` (misal untuk inisialisasi tabel atau pengaturan default di `mlite_settings`).
*   **`Admin.php` (`extends \Systems\AdminModule`)**: Controller utama halaman Backoffice/Admin.
    *   `init()`: Inisialisasi dependensi atau helper internal modul.
    *   `navigation()`: Mengembalikan array peta navigasi sidebar/menu modul (`'Kelola' => 'manage'`, dll.).
    *   **Konvensi Method Routing HTTP:**
        *   `getIndex()` / `anyManage()`: Halaman utama modul (`#manage`).
        *   `anyDisplay()`: Fragment tabel/list data (`#display`) yang sering dipanggil ulang via AJAX.
        *   `anyForm()`: Fragment form input/edit.
        *   `postSave()` / `postHapus()`: Handler mutasi data (POST).
        *   `getJavascript()` / `getCss()`: Endpoint virtual untuk melayani aset JS/CSS modul (pemuatannya wajib memakai `&v=' . time()`).
        *   `_addHeaderFiles()`: Hook untuk mendaftarkan aset statis JS/CSS via `$this->core->addJS(...)` / `$this->core->addCSS(...)`.
*   **`Site.php` (`extends \Systems\SiteModule`)**: Controller untuk rute publik/tanpa login admin (seperti Kiosk Anjungan, Display TV, PWA Home Care, API Webhook WhatsApp). Gunakan `_addHeaderFiles()` untuk menyuntikkan aset publik.
*   **`Service/`**: Direktori opsional (wajib pada arsitektur baru/Vibe Framework) untuk menampung Business Logic & Transaction Layer terpisah dari controller.
*   **`migrations/`**: Direktori migrasi skema database bernomor urut (`001_create_table.php`) yang menyediakan method `up(\PDO $pdo)` dan `down(\PDO $pdo)`.
*   **`view/admin/` & `js/admin/`**: Berkas template HTML dan JavaScript terisolasi milik modul.

---

## 2. Referensi Lengkap `QueryWrapper` (`systems/lib/QueryWrapper.php`) & Native PDO

Pemanggilan QueryWrapper di controller menggunakan `$this->db('nama_tabel')`.

*   **2.1 Method Pembentuk Kueri:**
    *   **Seleksi & Relasi:** `select($columns)`, `join($table, $condition)`, `leftJoin($table, $condition)`.
    *   **Filter `WHERE` (Aturan 3 Argumen Wajib):**
        *   Sama dengan (`=`): `->where('kolom', $val)`
        *   Operator lain (`!=`, `>`, `<`, `>=`, `<=`): **WAJIB 3 argumen** `->where('kolom', '!=', $val)` (JANGAN `->where('kolom !=', $val)`).
        *   Varian lain: `orWhere()`, `in($col, $arr)`, `orIn()`, `notIn($col, $arr)`, `orNotIn()`, `like($col, $val)`, `orLike()`, `notLike()`, `orNotLike()`, `isNull($col)`, `isNotNull($col)`, `orIsNull()`, `orIsNotNull()`.
    *   **Pengelompokan & Pengurutan:** `group($columns)`, `having($agg, $op, $val)`, `orHaving()`, `asc($col)`, `desc($col)`, `orderByRightNumber($col, $len, 'ASC'|'DESC')`, `nextRightNumber($col, $len)`, `rand()`, `limit($num)`, `offset($num)`.
    *   **Eksekusi Read:** `toArray()`, `toObject()`, `toJson()`, `oneArray($col, $val)`, `oneObject()`, `oneJson()`, `count()`, `toSql($type = 'default')` (untuk debug SQL).
    *   **Eksekusi Write:** `set($col, $val)`, `save($data)`, `update($data)`, `delete($col, $val)`, `lastInsertId()`.
*   **2.2 Guardrail Wajib QueryWrapper & SQL:**
    1. **Explicit Whitelisting sebelum `save()` / `update()`:**
       ```php
       $allowed = ['no_rawat', 'tgl_perawatan', 'jam_rawat', 'biaya'];
       $data = array_intersect_key($_POST, array_flip($allowed));
       $this->db('nama_tabel')->save($data);
       ```
    2. **Primary Key Exclusivity:** Pengecekan eksistensi baris sebelum `save()` hanya boleh memfilter kolom **Primary Key murni** (jangan campur kolom non-PK seperti `nip`).
    3. **Fallback Nilai Default `NOT NULL`:** Pastikan seluruh kolom wajib non-null memiliki nilai fallback aman (`'-'`, `0`, atau nilai ENUM valid) sebelum `save()`.
    4. **Kualifikasi Kolom Eksplisit pada `JOIN`:** Setiap query dengan `join()` / `leftJoin()` wajib memakai prefiks tabel/alias (`pasien.no_ktp`, `reg_periksa.no_rawat`) di `SELECT`, `WHERE`, `GROUP BY`, `ORDER BY`, serta sub-ekspresi SQL dinamis untuk menghindari error *1052 Column ambiguous*.
*   **2.3 Native PDO & Transaksi Atomik (ACID):**
    *   Panggil instance PDO melalui `$pdo = $this->db()->pdo();` (**DILARANG** memanggil `$this->db->prepare()`).
    *   Seluruh operasi penulisan multi-tabel wajib menggunakan *Prepared Statement* (`prepare()` + `execute()`/`bindValue()`) dan dibungkus transaksi atomik:
        ```php
        $pdo = $this->db()->pdo();
        $pdo->beginTransaction();
        try {
            // Operasi multi-tabel dengan prepared statement
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Kembalikan pesan error aman tanpa membocorkan stack trace ke publik
        }
        ```

---

## 3. Standar Response JSON / AJAX Endpoint & Otorisasi

*   **Terminasi Mutlak (`exit;`):** Setiap endpoint yang dipanggil via AJAX/API di `Admin.php` maupun `Site.php` wajib menyetel header JSON (bila sesuai), mengirim JSON terstruktur, dan **wajib diakhiri `exit;`** agar template wrapper HTML admin (`themes/admin/index.html`) tidak ikut tercetak di bawah output JSON:
    ```php
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success', // atau 'error'
        'msg'    => 'Data berhasil disimpan.',
        'data'   => $result
    ]);
    exit;
    ```
*   **Pemeriksaan Wewenang & RBAC:**
    *   Selalu validasi peran menggunakan `$this->core->hasRole('medis')`, `$this->core->hasRole(['medis', 'paramedis'])`, atau `$this->core->isManager()` di controller.
    *   Pada endpoint AI atau endpoint eksternal, selalu jaga agar kegagalan upstream mengembalikan JSON valid dengan status HTTP/JSON yang terkelola.

---

## 4. Core Helper Libraries (`systems/lib/` & `systems/Migration.php`)

Jangan menulis ulang utilitas yang sudah tersedia di inti sistem:
*   **`ExportHelper` ([`systems/lib/ExportHelper.php`](file:///opt/app/ksmlite/systems/lib/ExportHelper.php)):**
    *   `ExportHelper::toExcel(string $filename, array $headers, array $rows, ?string $title = null)`
    *   `ExportHelper::toCsv(string $filename, array $headers, array $rows, string $delimiter = ',')`
    *   `ExportHelper::sanitizeFilename(string $filename)`
*   **`Migration` ([`systems/Migration.php`](file:///opt/app/ksmlite/systems/Migration.php)):**
    *   `migrate(string $directory = 'systems/migrations/')`, `getExecuted()`, `getPending()`, `rollback(string $directory, int $steps = 1)`.
*   **Library Inti Lainnya di `systems/lib/`:**
    *   `Event` (`Event::listen()`, `Event::dispatch()`), `Upload`, `Image`, `QRCode`, `Pagination`, `RealtimeService`, `PaymentService`, `BpjsService`, `PcareService`.

---

## 5. Arsitektur Vibe Framework (`vibe-framework/systems/lib/`)

Untuk pengembangan modul baru atau refactoring berbasis **Vibe Framework**, terapkan pola **Thin Controller & Fat Service**:
*   **Controller (`Admin.php` / `Site.php`):** Bertugas tipis menangkap input HTTP, memeriksa otorisasi (`$this->can('modul.aksi')` / `$this->authorize('modul.aksi')`), meneruskan ke kelas Service, lalu mengembalikan view `$this->draw()` atau `ApiResponse::ok()` / `ApiResponse::error()`.
*   **Service Layer (`plugins/<modul>/Service/`):** Merangkum validasi aturan bisnis (`Validator`), eksekusi transaksi (`QueryWrapper::transaction()`), pencatatan jejak audit (`AuditLog::record()`), pengamanan konkurensi (`Idempotency`, `SequenceGenerator`, `CircuitBreaker`, `RateLimiter`), isolasi cabang (`BranchScope` / `->allBranches()`), serta pemancaran event decoupled (`Event::dispatch()`).
*   **Daftar Library Vibe Framework (`vibe-framework/systems/lib/`):** `ApiAuth`, `ApiResponse`, `ApiRouter`, `AuditLog`, `BranchScope`, `CircuitBreaker`, `Env`, `Event`, `ExportHelper`, `HealthCheck`, `HttpRequest`, `Idempotency`, `Migration`/Migrations, `Queue`, `RateLimiter`, `Realtime`, `Sequence`, `SequenceGenerator`, `Validator`, `WebhookDispatcher`, `WebhookVerifier`.
