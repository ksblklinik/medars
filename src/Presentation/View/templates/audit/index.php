<!-- Toolbar Paritas ksmlite (Audit ACID & Security) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari Nama Tabel Orphan..." data-ksmlite-search="orphanTableBody">
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border"><i class="fa fa-lock me-1 text-medical-teal"></i>Audit Trail Immutable</span>
    </div>
</div>

<div class="row g-3" id="auditWorkspace">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-database me-1"></i> Audit Orphan Records (Integritas Relasi)</h6>
                <button type="button" class="btn btn-xs btn-medical-teal" id="btnScanOrphans">
                    <i class="fa fa-magnifying-glass me-1"></i> Pindai Orphan
                </button>
            </div>
            <div class="card-body">
                <div id="orphanBadge" class="alert alert-secondary py-2 small mb-2">Klik tombol untuk memindai orphan records multi-tabel...</div>
                <div style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tabel Anak</th>
                                <th>Kolom Relasi</th>
                                <th>Jml Orphan</th>
                            </tr>
                        </thead>
                        <tbody id="orphanTableBody">
                            <tr><td colspan="3" class="text-center text-muted py-2 small">Belum dipindai</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-boxes-stacked me-1"></i> Audit Saldo Fisik vs Mutasi Riwayat Stok</h6>
            </div>
            <div class="card-body">
                <form id="formAuditStock" class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small">Kode Barang</label>
                        <input type="text" class="form-control form-control-sm" id="astKodeBrng" placeholder="OBT001" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Kode Bangsal/Gudang</label>
                        <input type="text" class="form-control form-control-sm" id="astKdBangsal" value="FAR01" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cek Konsistensi Saldo</button>
                    </div>
                </form>
                <div id="stockAuditResult" class="small text-muted"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-scale-balanced me-1"></i> Audit Split Billing Balance (4-Pilar)</h6>
            </div>
            <div class="card-body">
                <form id="formAuditBilling" class="row g-2 mb-2">
                    <div class="col-md-9">
                        <input type="text" class="form-control form-control-sm" id="ablNoRawat" placeholder="No. Rawat (contoh: 2026/11/16/000001)" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-medical-teal w-100">Audit</button>
                    </div>
                </form>
                <div id="billingAuditResult" class="small text-muted"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-trash-can me-1"></i> Rotasi &amp; Pembersihan Log Kadaluarsa</h6>
            </div>
            <div class="card-body">
                <form id="formRotateLogs" class="row g-2 mb-2">
                    <div class="col-md-8">
                        <label class="form-label small">Retensi Log Operasional (Min 90 Hari)</label>
                        <input type="number" class="form-control form-control-sm" id="rotDays" value="90" min="90" required>
                        <div class="form-text small">Log komunikasi/antrean lebih tua dari retensi dipangkas (Audit trail `mlite_logs` permanen).</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Pangkas Log</button>
                    </div>
                </form>
                <div id="rotateLogsResult" class="small text-muted"></div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/audit.js" defer></script>
