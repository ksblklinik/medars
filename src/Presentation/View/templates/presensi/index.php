<div class="container-fluid px-0" id="presensiWorkspace">
    <div id="presensiAlert"></div>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-clock me-1"></i> Absen Masuk / Pulang Shift
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Shift</label>
                        <select id="presShift" class="form-select form-select-sm">
                            <option value="Pagi">Pagi</option>
                            <option value="Siang">Siang</option>
                            <option value="Malam">Malam</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Lokasi / Unit</label>
                        <input type="text" id="presLokasi" class="form-control form-control-sm" value="Klinik Utama">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Catatan</label>
                        <input type="text" id="presKet" class="form-control form-control-sm" placeholder="Opsional">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" id="btnClockIn" class="btn btn-sm btn-teal text-white flex-fill">Absen Masuk</button>
                        <button type="button" id="btnClockOut" class="btn btn-sm btn-outline-teal flex-fill">Absen Pulang</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-medical-teal">Riwayat Presensi Pegawai</span>
                    <button type="button" id="btnRefreshPresensi" class="btn btn-xs btn-outline-secondary"><i class="fa fa-rotate"></i></button>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light"><tr><th>Tanggal</th><th>NIP / User</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Lokasi</th></tr></thead>
                        <tbody id="tbodyPresensi"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/presensi.js?v=20261117b"></script>
