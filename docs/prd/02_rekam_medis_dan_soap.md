# PRD 02: Rekam Medis Elektronik (RME), SOAP & Grounding ICD-10

## 1. Ikhtisar Domain
Domain ini mengelola pencatatan anamnesis, pemeriksaan fisik, diagnosis ICD-10 SatuSehat Kemenkes, terapi klinis, odontogram, formulir poli spesifik, serta integrasi AI Clinical Copilot berbasis Knowledge Vault.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **Dynamic Metadata RME & Smart Peek Protection:**
   - Formulir spesifik poli (Kebidanan, Kronis/PRB, TB, Jiwa, Mata) disimpan menggunakan model Key-Value Store / JSON metadata (`meta_key`, `meta_value`).
   - *Smart Peek Shield:* Tenaga medis yang hanya membuka/mengintip riwayat medis masa lalu tanpa mengubah input hari ini **dilarang menghasilkan record metadata baru** (`isMetaDirty === false`).
2. **Knowledge Vault SSOT & Grounding ICD-10 SatuSehat:**
   - Seluruh pedoman terapi hidup dalam berkas Markdown di `knowledge-vault/` yang disinkronkan ke graf relasi (`nodes`, `edges`, `icd10_map`).
   - Kode ICD-10 digrounding murni terhadap katalog `penyakit` SatuSehat Kemenkes.
   - Level kompetensi (SKDI 4A, 3A, dll.) **DILARANG membatasi atau menyembunyikan diagnosa**, melainkan hanya digunakan untuk mengisi saran di seksi `Indikasi & Alasan Rujukan`.
3. **Resep PRB 30 Hari di RME:**
   - Peresepan PRB (Program Rujuk Balik BPJS) untuk 9 penyakit kronis dicatat di metadata formulir poli ralan tanpa memotong stok gudang fisik klinik, dan otomatis disinkronkan ke kolom *Planning* (`rtl`) SOAP.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_not_save_metadata_when_doctor_only_peeks_history()`
- `test_should_ground_icd10_only_to_satusehat_master()`
- `test_should_never_block_diagnosis_based_on_skdi_competency_tier()`
- `test_should_sync_prb_prescription_to_soap_planning_without_deducting_stock()`
