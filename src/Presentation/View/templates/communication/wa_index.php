<!-- Toolbar Paritas ksmlite (Cari Outbox WA, Filter Sesi/Status) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. HP / Isi Pesan..." data-ksmlite-search="waOutboxBody">
        </div>
        <select class="form-select form-select-sm toolbar-filter" data-ksmlite-status-filter="waOutboxBody">
            <option value="">Semua Status</option>
            <option value="sent">Sent</option>
            <option value="failed">Failed</option>
        </select>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border"><i class="fa fa-shield-halved me-1 text-medical-teal"></i>10-Lapis Anti-Banned Meta</span>
    </div>
</div>

<div class="row g-3" id="waWorkspace">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-paper-plane me-1"></i> Kirim WhatsApp (Anti-Banned Meta)</h6>
            </div>
            <div class="card-body">
                <form id="formSendWa">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Sesi Pengirim</label>
                        <select class="form-select form-select-sm" id="waSession">
                            <option value="system">Sesi 1: System (Notifikasi 1-Arah)</option>
                            <option value="cs">Sesi 2: CS (Interaktif 2-Arah)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nomor Tujuan (628...)</label>
                        <input type="text" class="form-control form-control-sm" id="waPhone" placeholder="081234567890" required>
                        <div class="form-text small">Staf non-admin hanya diizinkan mengirim ke nomor Whitelist.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pesan</label>
                        <textarea class="form-control form-control-sm" id="waMessage" rows="4" placeholder="Halo Bpk/Ibu..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-medical-teal" id="btnSendWa">
                        <i class="fa fa-paper-plane me-1"></i> Kirim Pesan
                    </button>
                </form>
                <div id="waSendFeedback" class="mt-2"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-shield-halved me-1"></i> Tambah Kontak Whitelist</h6>
            </div>
            <div class="card-body">
                <form id="formAddWhitelist" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" class="form-control form-control-sm" id="wlPhone" placeholder="08..." required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control form-control-sm" id="wlName" placeholder="Nama Kontak" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Daftarkan Whitelist</button>
                    </div>
                </form>
                <div id="wlFeedback" class="mt-2"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-list me-1"></i> Log Pengiriman Terkini (Outbox)</h6>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnRefreshOutbox"><i class="fa fa-rotate"></i></button>
            </div>
            <div class="card-body p-0" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">Sesi</th>
                            <th>Tujuan</th>
                            <th>Pesan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="waOutboxBody">
                        <tr><td colspan="4" class="text-center text-muted py-3 small">Memuat outbox...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/wagateway.js" defer></script>
