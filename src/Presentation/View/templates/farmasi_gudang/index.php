<div class="container-fluid px-0" id="gudangWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="gudangSearchInput" class="form-control" placeholder="Cari Kode / Nama Obat...">
            </div>
            <button type="button" id="btnGudangSearch" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <div class="small text-muted">Gudang Farmasi, Mutasi Depo &amp; Kartu Stok</div>
    </div>

    <div id="gudangAlertContainer" class="mt-2"></div>

    <ul class="nav nav-tabs mt-2 mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabMasterObat" type="button">Master Obat &amp; Stok</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMutasiStok" type="button">Penerimaan &amp; Mutasi Depo</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKartuStok" type="button">Kartu Stok</button></li>
    </ul>

    <div class="tab-content">
        <!-- Tab 1: Master Obat -->
        <div class="tab-pane fade show active" id="tabMasterObat">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Master Obat (databarang)</div>
                        <div class="card-body">
                            <form id="formSaveBarang" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">Kode Obat</label><input type="text" id="brgKode" class="form-control form-control-sm" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama Obat</label><input type="text" id="brgNama" class="form-control form-control-sm" required></div>
                                <div class="col-4"><label class="form-label small mb-1">Satuan</label><input type="text" id="brgSatuan" class="form-control form-control-sm" value="TAB"></div>
                                <div class="col-4"><label class="form-label small mb-1">H. Beli</label><input type="number" id="brgHBeli" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-4"><label class="form-label small mb-1">H. Tunai</label><input type="number" id="brgHTunai" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">H. Kapitasi</label><input type="number" id="brgHKap" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">H. Jual Bebas</label><input type="number" id="brgJBebas" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Simpan Master Obat</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Obat</th><th>Satuan</th><th class="text-end">H. Tunai</th><th class="text-end">Total Stok</th></tr></thead>
                                <tbody id="tbodyMasterBarang"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Penerimaan & Mutasi -->
        <div class="tab-pane fade" id="tabMutasiStok">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Penerimaan / Stok Masuk</div>
                        <div class="card-body">
                            <form id="formReceiveStock" class="row g-2">
                                <div class="col-6"><label class="form-label small mb-1">Kode Obat</label><input type="text" id="recKode" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Depo Tujuan</label><select id="recBangsal" class="form-select form-select-sm"><option value="FAR01">FAR01 - Induk</option><option value="APT">APT - Apotek Umum</option><option value="IGD">IGD</option></select></div>
                                <div class="col-4"><label class="form-label small mb-1">Qty Masuk</label><input type="number" id="recQty" class="form-control form-control-sm" value="10" min="1" required></div>
                                <div class="col-4"><label class="form-label small mb-1">No. Batch</label><input type="text" id="recBatch" class="form-control form-control-sm"></div>
                                <div class="col-4"><label class="form-label small mb-1">No. Faktur/PO</label><input type="text" id="recFaktur" class="form-control form-control-sm"></div>
                                <div class="col-12"><label class="form-label small mb-1">Keterangan</label><input type="text" id="recKet" class="form-control form-control-sm" value="Penerimaan Supplier"></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Catat Stok Masuk</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">Mutasi Antar Depo</div>
                        <div class="card-body">
                            <form id="formMutateStock" class="row g-2">
                                <div class="col-12"><label class="form-label small mb-1">Kode Obat</label><input type="text" id="mutKode" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Dari Depo</label><select id="mutFrom" class="form-select form-select-sm"><option value="FAR01">FAR01 - Induk</option><option value="APT">APT - Apotek Umum</option><option value="IGD">IGD</option></select></div>
                                <div class="col-6"><label class="form-label small mb-1">Ke Depo</label><select id="mutTo" class="form-select form-select-sm"><option value="APT">APT - Apotek Umum</option><option value="IGD">IGD</option><option value="FAR01">FAR01 - Induk</option></select></div>
                                <div class="col-4"><label class="form-label small mb-1">Qty Mutasi</label><input type="number" id="mutQty" class="form-control form-control-sm" value="10" min="1" required></div>
                                <div class="col-8"><label class="form-label small mb-1">Keterangan</label><input type="text" id="mutKet" class="form-control form-control-sm" value="Distribusi Depo"></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-outline-teal w-100">Proses Mutasi Depo</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Kartu Stok -->
        <div class="tab-pane fade" id="tabKartuStok">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Tanggal/Jam</th><th>Obat</th><th>Depo</th><th>Posisi</th><th class="text-end">Awal</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Akhir</th></tr></thead>
                        <tbody id="tbodyKartuStok"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/farmasi-gudang.js?v=20261117b"></script>
