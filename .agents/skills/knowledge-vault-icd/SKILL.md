---
name: knowledge-vault-icd
description: >
  Arsitektur Obsidian Clinical Second Brain (/opt/app/ksmlite/knowledge-vault/),
  sinkronisasi graf VaultService, MANIFEST.json, dan Grounding ICD-10 SatuSehat pada
  modul dokter_ralan (IcdGroundingTest). Panggil skill ini saat bekerja di direktori
  knowledge-vault/, menyusun Pedoman Diagnosa Terapi (PDT), menguji grounding ICD-10,
  atau memodifikasi rekomendasi klinis AI pada SOAP dokter.
---

# Standar Arsitektur Knowledge Vault & ICD-10 Grounding (`ksmlite`)

> **Relasi Lintas-Skill:**
> - Padukan dengan `klinis-farmasi-klaim` saat mengintegrasikan rekomendasi diagnosa/terapi ke form SOAP `plugins/dokter_ralan`.
> - Padukan dengan `bridging-ai-infra` untuk standar pemanggilan provider AI (`OpenRouter` `google/gemini-2.5-flash` & fallback timeout 2 detik).
> - Padukan dengan `git-progress-handoff` saat mencatat checkpoint progres injeksi `MANIFEST.json`.

---

## 1. Empat Aturan Prinsipil Knowledge Vault (Wajib Dipatuhi Tanpa Pengecualian)

1. **File `.md` adalah SSOT Tunggal Pengetahuan Penyakit:**
   - Seluruh pengetahuan klinis hidup di `/opt/app/ksmlite/knowledge-vault/`.
   - Dokumen `.md` dapat ditambah, diupdate, atau dihapus kapan saja sesuai siklus hidup (*lifecycle*), dan `VaultService::syncVault()` akan menyinkronkan indeks graf (`mlite_vault_nodes`, `mlite_vault_edges`, `mlite_vault_icd10_map`) secara otomatis.
   - Dilarang menggunakan kembali kolom RAG lama di tabel `penyakit` (`subjective`, `objective`, `clinical_json`, `clinical_rules`) atau tabel `relasi_penyakit_gejala`.
2. **Grounding Lokal Murni Hanya ke Tabel `penyakit` (SatuSehat):**
   - Hanya kolom `kd_penyakit`, `nm_penyakit` (nomenklatur SatuSehat), `nm_indonesia`, dan `status` pada tabel `penyakit` yang menjadi acuan kode ICD-10.
   - **Pemeriksaan Penunjang (Lab/Rad) dan Terapi/Obat ditulis murni sesuai standar medis ideal (PPK Kemenkes / PNPK / WHO)**, TIDAK dibatasi oleh ketersediaan stok di `databarang` atau `jns_perawatan_*` klinik.
3. **Batas Kewenangan Hanya untuk Saran Rujukan (Tidak Menghalangi Diagnosa):**
   - Level kompetensi (misal SKDI 4A, 3A, 3B, 2, 1) **DILARANG KERAS** membatasi atau menyembunyikan penegakan diagnosa.
   - Dokter tetap harus dibantu menegakkan diagnosa yang paling tepat secara klinis. Batas kewenangan hanya digunakan pada seksi `## Indikasi & Alasan Rujukan` untuk memberikan saran kapan pasien perlu dirujuk ke FKRTL beserta tata laksana pra-rujukannya.
4. **Eksekusi Bertahap & Resumable (`MANIFEST.json`):**
   - Status injeksi setiap dokumen dicatat di [`/opt/app/ksmlite/knowledge-vault/MANIFEST.json`](file:///opt/app/ksmlite/knowledge-vault/MANIFEST.json) (struktur kunci utama: `version`, `vault_root`, `rules`, `syndromes`, dan `pdt_clusters`; saat ini mencakup 8 `syndromes` dan 66 `pdt_clusters`).
   - Sebelum menulis file `.md` baru, periksa item di `MANIFEST.json`, tulis file `.md`-nya, jalankan `VaultService::syncVault()`, lalu pastikan statusnya `"done"`.

---

## 2. Struktur Direktori Vault (`/opt/app/ksmlite/knowledge-vault/`)

- `00_MOC_Indeks/` — Peta navigasi per Poli/KSM (`type: moc`)
- `01_Pendekatan_Sindrom/` — Alur pikir dari keluhan utama/kode `R` ke kandidat penyakit (`type: sindrom`)
- `02_Pedoman_Diagnosa_Terapi/` — Dokumen inti PPK/PDT penyakit & aturan pemilihan kode ICD-10 SatuSehat (`type: pdt`)
- `03_Protokol_Terapi_Obat/` — Panduan farmakoterapi & kontraindikasi (`type: obat`)
- `04_Penunjang_Diagnostik/` — Interpretasi pemeriksaan penunjang (`type: penunjang`)
- `05_Draft_Sentinel/` — Area karantina draf otomatis (`generateSentinelDraftPDT`) sebelum kurasi ke folder produksi

---

## 3. Standar 8 Heading Markdown pada Dokumen PDT (`02_Pedoman_Diagnosa_Terapi/`)

Agar parser `VaultService.php` mengenali tipe relasi (`relation_type`) dari setiap `[[Wikilink]]`, gunakan 8 heading baku berikut:
1. `## Pendekatan & Gejala Klinis` -> menghasilkan edge `HAS_SYMPTOM`
2. `## Tanda Bahaya (Red Flags)` -> menghasilkan edge `HAS_RED_FLAG`
3. `## Kriteria Pemilihan Kode ICD-10 SatuSehat` -> memetakan ke tabel `penyakit` (`mlite_vault_icd10_map`)
4. `## Diagnosis Banding` -> menghasilkan edge `DIFFERENTIAL_DX`
5. `## Pemeriksaan Penunjang` -> menghasilkan edge `REQUIRES_WORKUP`
6. `## Tata Laksana & Terapi` -> menghasilkan edge `THERAPY_FOR`
7. `## Kontraindikasi & Peringatan` -> menghasilkan edge `CONTRAINDICATED_IN`
8. `## Indikasi & Alasan Rujukan` -> menghasilkan edge `REFERRAL_RULE`

---

## 4. Peta Service Engine & Pengujian (`VaultService.php` & `IcdGroundingTest.php`)

- **Lokasi Class Utama:** [`plugins/smartagent/VaultService.php`](file:///opt/app/ksmlite/plugins/smartagent/VaultService.php) (namespace `Plugins\Smartagent\VaultService`) dan [`plugins/smartagent/SemanticService.php`](file:///opt/app/ksmlite/plugins/smartagent/SemanticService.php).
- **Method Inti `VaultService` yang Wajib Digunakan (Jangan Duplikasi):**
  - `syncVault(bool $forceFull = false): array` — Sinkronisasi file `.md` ke `mlite_vault_nodes`, `mlite_vault_edges`, dan `mlite_vault_icd10_map`.
  - `validateIcd10SatuSehat(array $codesOrKeywords, int $limit = 15): array` — Validasi kode/kata kunci terhadap tabel `penyakit` SatuSehat.
  - `searchAndTraverseVault(string $query, int $limit = 5): array` & `rankVaultDocumentsBM25(...)` — Pencarian hibrida BM25 + traversal graf.
  - `buildSoapCuratedContext(string $query, int $limit = 2): string` & `buildClinicalRagContext(string $query, int $limit = 3): array` — Penyusun konteks RAG terkurasi untuk injeksi prompt SOAP dokter.
  - `detectUncoveredDiagnoses(): array` & `generateSentinelDraftPDT(string $prefix3, array $clusterData): array` — Deteksi otomatis diagnosa yang belum punya PDT.
- **Kontrak Output AI SOAP & Unit Test:**
  - Handler `anyAIProcess()` pada [`plugins/dokter_ralan/Admin.php`](file:///opt/app/ksmlite/plugins/dokter_ralan/Admin.php) wajib menghasilkan seksi `[ASSESSMENT]`, `[PLAN]`, `[INSTRUCTION]`, dan `[EVALUATION]`.
  - Setiap perubahan pada logika injeksi prompt ICD-10 atau grounding Knowledge Vault di `dokter_ralan` wajib diverifikasi dengan menjalankan [`plugins/dokter_ralan/tests/IcdGroundingTest.php`](file:///opt/app/ksmlite/plugins/dokter_ralan/tests/IcdGroundingTest.php).
