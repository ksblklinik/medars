<!-- Toolbar Paritas ksmlite (Cari E-Resep, Filter Status, Periode) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. Resep / Pasien..." data-ksmlite-search="resepQueueBody">
        </div>
        <select class="form-select form-select-sm toolbar-filter" data-ksmlite-status-filter="resepQueueBody">
            <option value="">Semua Status</option>
            <option value="menunggu">Menunggu</option>
            <option value="selesai">Sudah Diserahkan</option>
        </select>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: auto;">
            <span class="input-group-text bg-white text-muted">Dari</span>
            <input type="date" id="farmasiFilterFrom" class="form-control" value="<?= date('Y-m-d') ?>">
            <span class="input-group-text bg-white text-muted">s/d</span>
            <input type="date" id="farmasiFilterTo" class="form-control" value="<?= date('Y-m-d') ?>">
            <select id="farmasiFilterLayanan" class="form-select" style="max-width: 130px;">
                <option value="">Ralan + Ranap</option>
                <option value="ralan">Rawat Jalan</option>
                <option value="ranap">Rawat Inap</option>
            </select>
            <button type="button" id="btnFilterResep" class="btn btn-outline-teal">Filter</button>
        </div>
    </div>
</div>

<div class="row g-3" id="pharmacyWorkspace">
    <!-- Kolom Kiri: Antrean E-Resep Masuk -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-prescription-bottle-medical me-1"></i> Antrean E-Resep Hari Ini</h6>
                <button type="button" id="btnRefreshResepQueue" class="btn btn-xs btn-outline-secondary">
                    <i class="fa fa-rotate"></i>
                </button>
            </div>
            <div class="card-body p-0" style="max-height: 550px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">No. Resep</th>
                            <th>Pasien</th>
                            <th>Penjamin</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="resepQueueBody">
                        <tr><td colspan="4" class="text-center text-muted py-3 small">Memuat antrean E-Resep...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Pencarian Obat (Uniform Flat Pricing) & Validasi / Penyerahan -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-medical-teal text-white py-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold"><i class="fa fa-pills me-1"></i> Validasi & Penyerahan E-Resep</h6>
                    <small id="activeResepBanner" class="text-white-50">Pilih resep dari antrean untuk memvalidasi, atau input resep baru</small>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" id="btnPrintEtiket" class="btn btn-sm btn-outline-light" disabled title="Cetak Etiket Obat">
                        <i class="fa fa-tag me-1"></i> Etiket
                    </button>
                    <button type="button" id="btnPrintResep" class="btn btn-sm btn-outline-light" disabled title="Cetak E-Resep">
                        <i class="fa fa-print me-1"></i> E-Resep
                    </button>
                    <button type="button" id="btnCopyResep" class="btn btn-sm btn-outline-light" disabled title="Buat Salinan / Copy Resep">
                        <i class="fa fa-copy me-1"></i> Copy
                    </button>
                    <button type="button" id="btnDeleteResep" class="btn btn-sm btn-danger" disabled>
                        <i class="fa fa-trash me-1"></i> Hapus
                    </button>
                    <button type="button" id="btnDispenseResep" class="btn btn-sm btn-light fw-bold text-medical-teal" disabled>
                        <i class="fa fa-check-double me-1"></i> Validasi & Serahkan
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="pharmacyAlertContainer"></div>

                <div class="row g-2 mb-3 bg-light p-2 rounded border">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">No. Resep Aktif</label>
                        <input type="text" class="form-control form-control-sm" id="activeNoResep" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Depo Asal Stok</label>
                        <select class="form-select form-select-sm" id="dispenseDepo">
                            <option value="FAR01">FAR01 - Depo Farmasi Induk</option>
                            <option value="IGD">IGD - Depo Gawat Darurat</option>
                            <option value="APT">APT - Depo Apotek Umum</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Jenis Klaim SSOT</label>
                        <select class="form-select form-select-sm" id="dispenseJenisKlaim">
                            <option value="Tunai">Tunai</option>
                            <option value="Kapitasi">Kapitasi</option>
                            <option value="Non-Kapitasi">Non-Kapitasi</option>
                        </select>
                    </div>
                </div>

                <!-- Rincian Item E-Resep Terpilih -->
                <div class="mb-3 border rounded p-2 bg-white">
                    <h6 class="small fw-bold text-medical-teal mb-2"><i class="fa fa-list-check me-1"></i> Rincian Obat Jadi & Racikan</h6>
                    <div id="resepDetailContainer" class="small text-muted">Pilih salah satu E-Resep di sebelah kiri untuk melihat detail itemnya.</div>
                </div>

                <!-- Pencarian Obat Uniform Flat Pricing + Smart Claim Sorting -->
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold m-0">Cek Katalog Obat & Stok Depo (Mendukung B6, Zn, CTM)</label>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="toggleSearchBpjs">
                            <label class="form-check-label small" for="toggleSearchBpjs">Prioritas Klaim BPJS</label>
                        </div>
                    </div>
                    <input type="text" class="form-control form-control-sm" id="obatSearchInput" placeholder="Ketik nama atau kode obat..." autocomplete="off">
                </div>
                <div id="obatSearchResults" class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Obat</th>
                                <th>Stok Depo</th>
                                <th>Opsi Klaim & Tarif Seragam</th>
                            </tr>
                        </thead>
                        <tbody id="obatSearchBody">
                            <tr><td colspan="4" class="text-center text-muted py-2">Ketik kata kunci obat untuk melihat harga & stok depo.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/modules/pharmacy.js?v=20261117b"></script>
