<!-- Toolbar Paritas ksmlite (Cari Tagihan, Filter Status Bayar, Periode) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. Reg / Pasien..." data-ksmlite-search="billingQueueBody">
        </div>
        <select class="form-select form-select-sm toolbar-filter" data-ksmlite-status-filter="billingQueueBody">
            <option value="">Semua Status</option>
            <option value="belum">Belum Bayar</option>
            <option value="sudah">Sudah Bayar</option>
        </select>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: auto;">
            <span class="input-group-text bg-white text-muted">Dari</span>
            <input type="date" id="billingFilterFrom" class="form-control" value="<?= date('Y-m-d') ?>">
            <span class="input-group-text bg-white text-muted">s/d</span>
            <input type="date" id="billingFilterTo" class="form-control" value="<?= date('Y-m-d') ?>">
            <button type="button" id="btnFilterBilling" class="btn btn-outline-teal">Filter</button>
        </div>
    </div>
</div>

<div class="row g-3" id="billingWorkspace">
    <!-- Kolom Kiri: Daftar Tagihan Kunjungan -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-cash-register me-1"></i> Antrean Kasir Hari Ini</h6>
                <button type="button" id="btnRefreshBillingQueue" class="btn btn-xs btn-outline-secondary">
                    <i class="fa fa-rotate"></i>
                </button>
            </div>
            <div class="card-body p-0" style="max-height: 550px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">No. Reg</th>
                            <th>Pasien</th>
                            <th>Penjamin</th>
                            <th>Bayar</th>
                        </tr>
                    </thead>
                    <tbody id="billingQueueBody">
                        <tr><td colspan="4" class="text-center text-muted py-3 small">Memuat antrean kasir...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Split Billing 4-Pilar & POS Apotek Umum -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-medical-teal text-white py-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold"><i class="fa fa-file-invoice-dollar me-1"></i> Split Billing 4-Pilar & POS Retail</h6>
                    <small id="activeBillingBanner" class="text-white-50">Pilih kunjungan untuk melihat rincian tagihan</small>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" id="btnPrintFaktur" class="btn btn-sm btn-outline-light" disabled title="Cetak Kuitansi / Faktur">
                        <i class="fa fa-print me-1"></i> Faktur
                    </button>
                    <button type="button" id="btnSettlePiutang" class="btn btn-sm btn-warning text-dark" disabled title="Lunasi Piutang ke Kas">
                        <i class="fa fa-hand-holding-dollar me-1"></i> Lunasi Piutang
                    </button>
                    <button type="button" id="btnVoidBilling" class="btn btn-sm btn-danger" disabled>
                        <i class="fa fa-ban me-1"></i> Void (Anti-Fraud)
                    </button>
                    <button type="button" id="btnPayBilling" class="btn btn-sm btn-light fw-bold text-medical-teal" disabled>
                        <i class="fa fa-check-circle me-1"></i> Bayar (F2)
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="billingAlertContainer"></div>

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSplitBilling" type="button">Split Billing [T] / [K] / [NK] / [P]</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPosApotek" type="button">POS Apotek Umum (PO-APT-%)</button></li>
                </ul>

                <div class="tab-content">
                    <!-- Tab 1: Split Billing -->
                    <div class="tab-pane fade show active" id="tabSplitBilling">
                        <input type="hidden" id="activeBillingNoRawat" value="">
                        <div class="row g-2 mb-3 text-center">
                            <div class="col-3"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">[T] Tunai Pasien</small><strong id="sumTunai" class="text-danger">Rp 0</strong></div></div>
                            <div class="col-3"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">[K] Kapitasi</small><strong id="sumKapitasi" class="text-success">Rp 0</strong></div></div>
                            <div class="col-3"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">[NK] Non-Kapitasi</small><strong id="sumNonKapitasi" class="text-primary">Rp 0</strong></div></div>
                            <div class="col-3"><div class="border rounded p-2 bg-light"><small class="text-muted d-block">[P] Piutang</small><strong id="sumPiutang" class="text-warning-emphasis">Rp 0</strong></div></div>
                        </div>

                        <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                            <table class="table table-sm table-bordered small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item / Layanan</th>
                                        <th>Kategori</th>
                                        <th>Qty</th>
                                        <th>Pilar Klaim</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="billingItemsBody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada item tagihan.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2 d-flex justify-content-between align-items-center small text-muted">
                            <span>Hotkeys: <kbd>F2</kbd> Bayar &bull; <kbd>F8</kbd> Rekonsiliasi Pending &bull; <kbd>F9</kbd> Reset</span>
                            <button type="button" id="btnReconcileOrder" class="btn btn-xs btn-outline-warning text-dark" disabled>Rekonsiliasi Cancel (F8)</button>
                        </div>
                    </div>

                    <!-- Tab 2: POS Apotek Umum -->
                    <div class="tab-pane fade" id="tabPosApotek">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="posKodeBrng" placeholder="Kode Barang (mis. OBT001)"></div>
                            <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="posJml" value="1" min="1"></div>
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" id="posPaymentMethod">
                                    <option value="Tunai">Tunai</option>
                                    <option value="QRIS">QRIS</option>
                                    <option value="Debit EDC">Debit EDC</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="Piutang Rekanan">Piutang Rekanan</option>
                                </select>
                            </div>
                            <div class="col-md-2"><button type="button" id="btnSubmitPos" class="btn btn-sm btn-primary w-100" style="background:#00897b;border-color:#00897b;">Jual</button></div>
                        </div>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-sm table-bordered small mb-0">
                                <thead class="table-light"><tr><th>No. Nota</th><th>Tanggal</th><th>Cara Bayar</th><th class="text-end">Total</th><th class="text-center">Struk</th></tr></thead>
                                <tbody id="tbodyPosSales"><tr><td colspan="5" class="text-center text-muted py-2">Memuat penjualan POS...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/modules/billing.js?v=20261117c"></script>
