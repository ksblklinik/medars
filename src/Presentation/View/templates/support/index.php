<!-- Toolbar Paritas ksmlite (Cari Order Penunjang, Filter Status/Klaim) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: 240px;">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. Order / Pasien..." data-ksmlite-search="labOrdersBody">
        </div>
        <select class="form-select form-select-sm" style="width: 160px;" data-ksmlite-status-filter="labOrdersBody">
            <option value="">Semua Status</option>
            <option value="terbuka">Siap Validasi</option>
            <option value="terkunci">Terkunci Kasir</option>
        </select>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border"><i class="fa fa-calendar me-1 text-medical-teal"></i>Periode: <?= date('d-m-Y') ?></span>
    </div>
</div>

<div class="row g-3" id="supportWorkspace" data-active-tab="<?= htmlspecialchars((string) ($active_tab ?? 'lab'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="col-12">
        <ul class="nav nav-tabs mb-3" id="supportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($active_tab ?? 'lab') === 'lab' ? 'active' : '' ?>" id="tab-lab-btn" data-bs-toggle="tab" data-bs-target="#tab-lab" type="button" role="tab">
                    <i class="fa fa-flask me-1"></i> Laboratorium
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($active_tab ?? '') === 'radiologi' ? 'active' : '' ?>" id="tab-rad-btn" data-bs-toggle="tab" data-bs-target="#tab-rad" type="button" role="tab">
                    <i class="fa fa-x-ray me-1"></i> Radiologi
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($active_tab ?? '') === 'laporan' ? 'active' : '' ?>" id="tab-rep-btn" data-bs-toggle="tab" data-bs-target="#tab-rep" type="button" role="tab">
                    <i class="fa fa-chart-bar me-1"></i> Laporan Operasional
                </button>
            </li>
        </ul>

        <div class="tab-content" id="supportTabContent">
            <!-- TAB 1: LABORATORIUM -->
            <div class="tab-pane fade <?= ($active_tab ?? 'lab') === 'lab' ? 'show active' : '' ?>" id="tab-lab" role="tabpanel">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-flask me-1"></i> Antrean Order Lab</h6>
                                <button type="button" id="btnRefreshLab" class="btn btn-xs btn-outline-secondary"><i class="fa fa-rotate"></i></button>
                            </div>
                            <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-2">No. Order</th>
                                            <th>Pasien</th>
                                            <th>Klaim</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="labOrdersBody">
                                        <tr><td colspan="4" class="text-center text-muted py-3 small">Memuat antrean lab...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-bottom py-2">
                                <h6 class="m-0 fw-bold text-medical-teal">Input &amp; Validasi Hasil Lab</h6>
                            </div>
                            <div class="card-body">
                                <form id="formLabResult">
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-semibold">No. Order Lab (PL)</label>
                                            <input type="text" class="form-control form-control-sm" id="labNoOrder" required>
                                        </div>
                                        <div class="col-md-7">
                                            <label class="form-label small fw-semibold">Status Kunci Kasir</label>
                                            <div id="labLockBadge" class="form-control form-control-sm bg-light text-muted">Pilih order di sebelah kiri</div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label small">Kode Item</label>
                                            <input type="text" class="form-control form-control-sm" id="labKdPrw" value="LAB01" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small">Parameter</label>
                                            <input type="text" class="form-control form-control-sm" id="labParam" value="Hemoglobin" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Nilai</label>
                                            <input type="text" class="form-control form-control-sm" id="labNilai" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Nilai Rujukan</label>
                                            <input type="text" class="form-control form-control-sm" id="labRujukan" value="13.0 - 17.0">
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-sm btn-medical-teal" id="btnSaveLabResult">
                                            <i class="fa fa-check-circle me-1"></i> Simpan &amp; Validasi Hasil
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnDeleteLabOrder">
                                            <i class="fa fa-trash me-1"></i> Hapus Order
                                        </button>
                                    </div>
                                </form>
                                <div id="labResultFeedback" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RADIOLOGI -->
            <div class="tab-pane fade <?= ($active_tab ?? '') === 'radiologi' ? 'show active' : '' ?>" id="tab-rad" role="tabpanel">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-x-ray me-1"></i> Antrean Order Radiologi</h6>
                                <button type="button" id="btnRefreshRad" class="btn btn-xs btn-outline-secondary"><i class="fa fa-rotate"></i></button>
                            </div>
                            <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-2">No. Order</th>
                                            <th>Pasien</th>
                                            <th>Klaim</th>
                                        </tr>
                                    </thead>
                                    <tbody id="radOrdersBody">
                                        <tr><td colspan="3" class="text-center text-muted py-3 small">Memuat antrean radiologi...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-bottom py-2">
                                <h6 class="m-0 fw-bold text-medical-teal">Input Expertise Radiologi</h6>
                            </div>
                            <div class="card-body">
                                <form id="formRadResult">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">No. Order Radiologi (PR)</label>
                                        <input type="text" class="form-control form-control-sm" id="radNoOrder" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Expertise / Kesan Klinis</label>
                                        <textarea class="form-control form-control-sm" id="radExpertise" rows="5" required placeholder="Cor dan pulmo dalam batas normal..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-medical-teal">
                                        <i class="fa fa-save me-1"></i> Simpan Expertise
                                    </button>
                                </form>
                                <div id="radResultFeedback" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LAPORAN OPERASIONAL -->
            <div class="tab-pane fade <?= ($active_tab ?? '') === 'laporan' ? 'show active' : '' ?>" id="tab-rep" role="tabpanel">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-end">
                        <div>
                            <label class="form-label small mb-1">Dari Tanggal</label>
                            <input type="date" class="form-control form-control-sm" id="repFrom" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div>
                            <label class="form-label small mb-1">Sampai Tanggal</label>
                            <input type="date" class="form-control form-control-sm" id="repTo" value="<?= date('Y-m-d') ?>">
                        </div>
                        <button type="button" class="btn btn-sm btn-medical-teal" id="btnLoadReports">
                            <i class="fa fa-search me-1"></i> Tampilkan Laporan
                        </button>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white fw-bold small text-medical-teal">Kunjungan per Poliklinik</div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0"><tbody id="repVisitsBody"></tbody></table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white fw-bold small text-medical-teal">Top 10 Penyakit (ICD-10)</div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0"><tbody id="repIcdBody"></tbody></table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white fw-bold small text-medical-teal">Pendapatan per Pilar Klaim</div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0"><tbody id="repRevenueBody"></tbody></table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/support.js" defer></script>
