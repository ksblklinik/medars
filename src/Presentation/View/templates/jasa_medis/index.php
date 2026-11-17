<div class="container-fluid px-0" id="jasaMedisWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white text-muted">Dari</span>
                <input type="date" id="jmFrom" class="form-control" value="<?= date('Y-m-d') ?>">
                <span class="input-group-text bg-white text-muted">s/d</span>
                <input type="date" id="jmTo" class="form-control" value="<?= date('Y-m-d') ?>">
                <input type="text" id="jmDokter" class="form-control" placeholder="Kode Dokter (opsional)" style="max-width: 150px;">
                <button type="button" id="btnJmFilter" class="btn btn-outline-teal">Hitung</button>
            </div>
        </div>
        <button type="button" id="btnJmPrint" class="btn btn-sm btn-outline-teal"><i class="fa fa-print me-1"></i>Cetak Rekap</button>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-user-doctor me-1"></i> Rekap Jasa Medis per Dokter &amp; Pilar Klaim
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Dokter</th>
                                <th class="text-center">Jml Tindakan</th>
                                <th class="text-end">Tunai</th>
                                <th class="text-end">BPJS</th>
                                <th class="text-end">Piutang</th>
                                <th class="text-end">Total Jasa Dokter</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyJmSummary"><tr><td colspan="6" class="text-center text-muted py-3">Memuat rekap...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">Rincian Tindakan Jasa Medis</div>
                <div class="card-body p-0 table-responsive" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr><th>Tanggal</th><th>No. Rawat</th><th>Pasien</th><th>Dokter</th><th>Tindakan</th><th>Klaim</th><th class="text-end">Jasa Dokter</th></tr>
                        </thead>
                        <tbody id="tbodyJmDetails"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/jasa-medis.js?v=20261117b"></script>
