<?php
$csrfToken = (string) ($csrf_token ?? '');
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
?>
<div class="container-fluid py-3" id="penjualanApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabKasirPenjualan" type="button">Kasir &amp; Order Penjualan</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBarangPenjualan" type="button">Master Barang Alkes/Bebas</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLaporanPenjualan" type="button">Laporan Omzet</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabKasirPenjualan">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header text-white fw-semibold" style="background-color:#00897b;">Buat Order Penjualan Baru</div>
                        <div class="card-body">
                            <form id="pjCreateOrderForm" class="row g-2">
                                <div class="col-md-7"><input type="text" id="pjNamaPembeli" class="form-control form-control-sm" placeholder="Nama Pembeli / Umum" value="Umum"></div>
                                <div class="col-md-5"><input type="text" id="pjTelp" class="form-control form-control-sm" placeholder="No. Telp"></div>
                                <div class="col-12"><button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">+ Buat Order Baru</button></div>
                            </form>
                        </div>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Daftar Order (<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>)</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>#</th><th>Pembeli</th><th>Subtotal</th><th>Status</th><th></th></tr></thead>
                                <tbody id="pjOrdersBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <span class="fw-semibold" id="pjActiveOrderTitle">Pilih atau buat order untuk mengisi item</span>
                            <a id="pjPrintLink" href="#" target="_blank" class="btn btn-xs btn-outline-secondary d-none"><i class="fa-solid fa-print me-1"></i>Faktur</a>
                        </div>
                        <div class="card-body">
                            <form id="pjAddItemForm" class="row g-2 mb-3">
                                <div class="col-md-7"><select id="pjItemSelect" class="form-select form-select-sm"></select></div>
                                <div class="col-md-2"><input type="number" id="pjItemQty" class="form-control form-control-sm" min="1" value="1"></div>
                                <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary w-100">Tambah Item</button></div>
                            </form>
                            <table class="table table-sm table-bordered small mb-3">
                                <thead class="table-light"><tr><th>Barang</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Total</th><th></th></tr></thead>
                                <tbody id="pjCartBody"><tr><td colspan="5" class="text-center text-muted">Belum ada item</td></tr></tbody>
                            </table>
                            <form id="pjSettleForm" class="row g-2 align-items-end">
                                <div class="col-md-3"><label class="form-label small">Potongan (Rp)</label><input type="number" id="pjPotongan" class="form-control form-control-sm" value="0"></div>
                                <div class="col-md-3"><label class="form-label small">Cara Bayar</label><select id="pjCaraBayar" class="form-select form-select-sm"><option value="Tunai">Tunai</option><option value="QRIS">QRIS</option><option value="Transfer">Transfer</option><option value="Kartu Debit">Kartu Debit</option></select></div>
                                <div class="col-md-3"><label class="form-label small">Bayar (Rp)</label><input type="number" id="pjJumlahBayar" class="form-control form-control-sm" value="0"></div>
                                <div class="col-md-3"><button type="submit" class="btn btn-sm btn-success w-100">Lunasi Order</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabBarangPenjualan">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Tambah / Edit Barang Alkes</div>
                        <div class="card-body">
                            <form id="pjBarangForm">
                                <input type="hidden" id="pjbId">
                                <div class="mb-2"><label class="form-label small">Nama Barang *</label><input type="text" id="pjbNama" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Satuan</label><input type="text" id="pjbSatuan" class="form-control form-control-sm" value="Pcs"></div>
                                <div class="mb-2"><label class="form-label small">Stok</label><input type="number" id="pjbStok" class="form-control form-control-sm" value="0"></div>
                                <div class="mb-3"><label class="form-label small">Harga Jual (Rp)</label><input type="number" id="pjbHarga" class="form-control form-control-sm" value="0"></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Barang</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Katalog Barang Non-Resep &amp; Alkes</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Nama Barang</th><th>Satuan</th><th class="text-end">Stok</th><th class="text-end">Harga</th></tr></thead>
                                <tbody id="pjBarangBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabLaporanPenjualan">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <span class="fw-semibold">Laporan Omzet Penjualan Barang</span>
                    <div class="d-flex gap-2">
                        <input type="date" id="pjRepFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($monthStart, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="date" id="pjRepTo" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="button" id="pjRepBtn" class="btn btn-sm text-white" style="background-color:#00897b;">Tampilkan</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="small text-muted">Total Transaksi Lunas</div><div class="fs-4 fw-bold" id="pjRepCount">0</div></div></div>
                        <div class="col-md-6"><div class="p-3 bg-light rounded"><div class="small text-muted">Total Omzet</div><div class="fs-4 fw-bold text-success" id="pjRepOmzet">Rp 0</div></div></div>
                    </div>
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light"><tr><th>Nama Barang</th><th class="text-end">Qty Terjual</th><th class="text-end">Omzet</th></tr></thead>
                        <tbody id="pjRepTopBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/penjualan.js?v=<?= time() ?>"></script>
