<div class="container-fluid px-0" id="frontOfficeWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <input type="date" id="foFilterDate" class="form-control form-control-sm" style="width: auto;" value="<?= date('Y-m-d') ?>">
            <button type="button" id="btnFoRefresh" class="btn btn-sm btn-outline-teal"><i class="fa fa-rotate me-1"></i>Muat Ulang</button>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <a href="/pasien" class="btn btn-sm btn-outline-secondary">Master Pasien</a>
            <a href="/pendaftaran" class="btn btn-sm btn-teal text-white">Loket Pendaftaran</a>
            <a href="/kasir" class="btn btn-sm btn-outline-secondary">Kasir</a>
            <a href="/surat" class="btn btn-sm btn-outline-secondary">Surat Medis</a>
            <a href="/display-antrean" target="_blank" class="btn btn-sm btn-outline-teal"><i class="fa fa-tv me-1"></i>Display TV</a>
        </div>
    </div>

    <div id="foAlertContainer" class="mt-2"></div>

    <!-- KPI Ringkasan Loket -->
    <div class="row g-2 mt-1 mb-3 text-center">
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Total Kunjungan</small><h5 class="m-0 fw-bold text-medical-teal" id="foStatTotal">0</h5></div></div>
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Belum Periksa</small><h5 class="m-0 fw-bold text-warning-emphasis" id="foStatBelum">0</h5></div></div>
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Sudah Periksa</small><h5 class="m-0 fw-bold text-success" id="foStatSudah">0</h5></div></div>
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Booking Menunggu</small><h5 class="m-0 fw-bold text-primary" id="foStatBooking">0</h5></div></div>
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Belum Bayar</small><h5 class="m-0 fw-bold text-danger" id="foStatUnpaid">0</h5></div></div>
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Batal</small><h5 class="m-0 fw-bold text-secondary" id="foStatBatal">0</h5></div></div>
    </div>

    <div class="row g-3">
        <!-- Antrean Booking Online / Mobile JKN -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-calendar-check me-1"></i> Konfirmasi Booking / Mobile JKN
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 460px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Antrean</th><th>Pasien</th><th>Poli</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody id="tbodyFoBookings"><tr><td colspan="5" class="text-center text-muted py-3">Memuat booking...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Gatekeeper Antrean & Kunjungan Hari Ini -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">
                    <i class="fa fa-list-check me-1"></i> Gatekeeper Kunjungan &amp; Panggil Antrean TV
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 460px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Antrean</th><th>No. Rawat</th><th>Pasien</th><th>Poli / Dokter</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody id="tbodyFoVisits"><tr><td colspan="6" class="text-center text-muted py-3">Memuat kunjungan...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/frontoffice.js?v=20261117b"></script>
