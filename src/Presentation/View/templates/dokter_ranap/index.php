<div class="container-fluid px-0" id="dokterRanapWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="ranapDocSearch" class="form-control" placeholder="Cari Pasien / Kamar Ranap...">
            </div>
            <button type="button" id="btnRanapDocRefresh" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <div class="small text-muted" id="activeRanapDocBanner">Pilih pasien rawat inap di sebelah kiri</div>
    </div>

    <div id="ranapDocAlertContainer" class="mt-2"></div>

    <div class="row g-3 mt-1">
        <!-- Kolom Kiri: Pasien Rawat Inap Aktif -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-bed-pulse me-1"></i> Pasien Rawat Inap Aktif
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 560px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Kamar</th><th>Pasien</th><th>No. Rawat</th></tr></thead>
                        <tbody id="tbodyRanapDocPatients"><tr><td colspan="3" class="text-center text-muted py-3">Memuat pasien ranap...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: CPPT / SOAP Visite & Riwayat -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">
                    <i class="fa fa-notes-medical me-1"></i> CPPT &amp; Visite Dokter Rawat Inap
                </div>
                <div class="card-body">
                    <form id="formCpptRanap" class="row g-2 mb-3">
                        <input type="hidden" id="cpptNoRawat">
                        <div class="col-6 col-md-2"><label class="form-label small mb-1">TD</label><input type="text" id="cpptTensi" class="form-control form-control-sm" placeholder="120/80"></div>
                        <div class="col-6 col-md-2"><label class="form-label small mb-1">Nadi</label><input type="text" id="cpptNadi" class="form-control form-control-sm" placeholder="80"></div>
                        <div class="col-6 col-md-2"><label class="form-label small mb-1">Suhu</label><input type="text" id="cpptSuhu" class="form-control form-control-sm" placeholder="36.5"></div>
                        <div class="col-6 col-md-2"><label class="form-label small mb-1">RR</label><input type="text" id="cpptRr" class="form-control form-control-sm" placeholder="20"></div>
                        <div class="col-6 col-md-2"><label class="form-label small mb-1">SpO2</label><input type="text" id="cpptSpo2" class="form-control form-control-sm" placeholder="98%"></div>
                        <div class="col-6 col-md-2"><label class="form-label small mb-1">GCS</label><input type="text" id="cpptGcs" class="form-control form-control-sm" value="15"></div>
                        <div class="col-md-6"><label class="form-label small mb-1">Subjective (S - Keluhan)</label><textarea id="cpptS" class="form-control form-control-sm" rows="2" required></textarea></div>
                        <div class="col-md-6"><label class="form-label small mb-1">Objective (O - Pemeriksaan)</label><textarea id="cpptO" class="form-control form-control-sm" rows="2"></textarea></div>
                        <div class="col-md-6"><label class="form-label small mb-1">Assessment (A - Penilaian)</label><textarea id="cpptA" class="form-control form-control-sm" rows="2"></textarea></div>
                        <div class="col-md-6"><label class="form-label small mb-1">Plan / Instruksi (P)</label><textarea id="cpptP" class="form-control form-control-sm" rows="2"></textarea></div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-sm btn-teal text-white">Simpan CPPT Visite</button>
                        </div>
                    </form>

                    <h6 class="small fw-bold text-medical-teal mb-2">Riwayat CPPT Pasien</h6>
                    <div id="cpptHistoryList" class="list-group list-group-flush small border rounded" style="max-height: 240px; overflow-y: auto;">
                        <div class="list-group-item text-muted">Pilih pasien untuk melihat riwayat CPPT.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/dokter-ranap.js?v=20261117b"></script>
