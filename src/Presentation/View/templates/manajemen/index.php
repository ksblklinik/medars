<div class="container-fluid px-0" id="manajemenWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white text-muted">Dari</span>
                <input type="date" id="mnjFrom" class="form-control" value="<?= date('Y-m-01') ?>">
                <span class="input-group-text bg-white text-muted">s/d</span>
                <input type="date" id="mnjTo" class="form-control" value="<?= date('Y-m-d') ?>">
                <button type="button" id="btnMnjFilter" class="btn btn-outline-teal">Analisa</button>
            </div>
        </div>
        <div class="small text-muted">Executive Dashboard &amp; HFIS LEAN Board</div>
    </div>

    <!-- KPI Eksekutif -->
    <div class="row g-2 mt-1 mb-3 text-center">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Total Kunjungan</small><h5 class="m-0 fw-bold text-medical-teal" id="mnjKpiVisits">0</h5></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Total Pendapatan</small><h5 class="m-0 fw-bold text-success" id="mnjKpiRev">Rp 0</h5></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Rata-Rata Lead Time SOAP</small><h5 class="m-0 fw-bold text-primary" id="mnjKpiLead">0 mnt</h5></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Kunjungan Overtime (&ge;21:00)</small><h5 class="m-0 fw-bold text-danger" id="mnjKpiOver">0</h5></div></div>
    </div>

    <div class="row g-3">
        <!-- LEAN Analytics per Poli -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-gauge-high me-1"></i> LEAN Analytics Board per Poliklinik
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Poliklinik</th>
                                <th class="text-center">Pasien</th>
                                <th class="text-center">Net Signed Avg</th>
                                <th class="text-center">Lead Time SOAP</th>
                                <th class="text-center">Overtime</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyMnjLean"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tren Harian & Pilar Klaim -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">Pendapatan per Pilar Klaim</div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0 small"><tbody id="tbodyMnjClaim"></tbody></table>
                </div>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">Tren Kunjungan Harian</div>
                <div class="card-body p-0 table-responsive" style="max-height: 240px; overflow-y: auto;">
                    <table class="table table-sm mb-0 small"><tbody id="tbodyMnjTrend"></tbody></table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/manajemen.js?v=20261117b"></script>
