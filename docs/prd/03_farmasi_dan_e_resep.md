# PRD 03: Farmasi, E-Resep, Racikan & Multi-Depo Gudang

## 1. Ikhtisar Domain
Domain ini mengelola katalog obat & BHP, penerimaan faktur PBF, stok opname multi-depo, validasi e-resep dokter, pemrosesan obat jadi vs racikan, dan isolasi entitas retail Apotek Umum.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **Pemisahan Tabel Obat Jadi vs Obat Racikan:**
   - Resep obat jadi disimpan di header e-resep biasa.
   - Resep obat racikan memiliki header racikan (`nama_racik`, metode, aturan pakai) dan detail komponen bahan penyusun racikan.
2. **Offset Waktu Transaksi (+1 Detik):**
   - Transaksi mutasi dan pemberian bahan racikan **wajib diberi offset waktu +1 detik** pada kolom timestamp jam guna membedakannya secara tegas dan deterministik dari obat non-racikan yang divalidasi pada detik yang sama.
3. **Uniform Flat Pricing (Tarif Flat Seragam):**
   - SIMRS ksmlite tidak menerapkan tarif obat bertingkat per kelas kamar (*no class-based drug markup*).
   - Menggunakan acuan tarif flat: `harga_tunai` (pasien umum/mandiri seragam ralan & ranap), `harga_kapitasi` (klaim paket BPJS Rp 0), `harga_nonkapitasi` (klaim khusus), `jualbebas` (retail kasir apotek umum), dan `dasar/h_beli` (HPP modal PBF).
4. **Isolasi Mutasi Multi-Depo:**
   - Depo Farmasi Klinik Induk: `kd_bangsal != 'APT'`.
   - Depo Apotek Umum Mandiri: `kd_bangsal = 'APT'`.
   - Depo IGD: `kd_bangsal = 'IGD'`.
   - Seluruh mutasi barang dan resep wajib terisolasi strictly per depo bangsal aktif.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_enforce_plus_one_second_offset_for_compounded_drugs()`
- `test_should_apply_uniform_flat_pricing_regardless_of_room_class()`
- `test_should_isolate_stock_mutation_to_specific_depo()`
- `test_should_prevent_stock_deduction_below_zero_with_pessimistic_lock()`
