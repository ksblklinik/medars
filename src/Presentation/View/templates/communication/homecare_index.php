<!-- Toolbar Paritas ksmlite (Cari Kunjungan Home Care) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: 240px;">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. Rawat / Preceptor..." data-ksmlite-search="supervisorVisitsBody">
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border"><i class="fa fa-user-shield me-1 text-medical-teal"></i>Privacy Shield Aktif</span>
    </div>
</div>

<div class="row g-3" id="homeCareWorkspace">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-calendar-plus me-1"></i> Jadwalkan Home Care Baru</h6>
            </div>
            <div class="card-body">
                <form id="formNewHomeCare">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">No. Rawat</label>
                        <input type="text" class="form-control form-control-sm" id="hcNoRawat" placeholder="2026/11/16/000001" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small">Tgl Kunjungan</label>
                            <input type="date" class="form-control form-control-sm" id="hcDate" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Jam</label>
                            <input type="time" class="form-control form-control-sm" id="hcTime" value="09:00" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Dokter DPJP</label>
                        <input type="text" class="form-control form-control-sm" id="hcDpjp" value="DR001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Diagnosis Asuhan</label>
                        <textarea class="form-control form-control-sm" id="hcDiag" rows="2" placeholder="Perawatan luka..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-medical-teal"><i class="fa fa-save me-1"></i> Simpan Jadwal</button>
                </form>
                <div id="hcCreateFeedback" class="mt-2"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-graduation-cap me-1"></i> Penugasan Preceptor (Trias Preceptorship)</h6>
            </div>
            <div class="card-body">
                <form id="formAssignPreceptor">
                    <div class="mb-2">
                        <label class="form-label small">No. Rawat</label>
                        <input type="text" class="form-control form-control-sm" id="asNoRawat" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small">NIP Preceptor Ber-SK</label>
                            <input type="text" class="form-control form-control-sm" id="asPreceptorNip" placeholder="NIP001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Institusi Kampus</label>
                            <input type="text" class="form-control form-control-sm" id="asInstitusi" placeholder="STIKES Harapan" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small">NIM Mahasiswa Magang</label>
                            <input type="text" class="form-control form-control-sm" id="asNim" placeholder="MHS001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">ID Supervisor Dosen (CI)</label>
                            <input type="text" class="form-control form-control-sm" id="asSupervisor" placeholder="DOSEN01" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Tugaskan Preceptor</button>
                </form>
                <div id="asFeedback" class="mt-2"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-user-shield me-1"></i> Pandangan CI Supervisor Dosen (Patient Privacy Shield)</h6>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnRefreshSupervisor"><i class="fa fa-rotate"></i></button>
            </div>
            <div class="card-body p-0" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">No. Rawat</th>
                            <th>Pseudonim Pasien</th>
                            <th>Preceptor / Mhs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="supervisorVisitsBody">
                        <tr><td colspan="4" class="text-center text-muted py-3 small">Memuat data asuhan...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/homecare.js" defer></script>
