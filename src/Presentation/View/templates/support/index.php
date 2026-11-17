<!-- Toolbar Paritas ksmlite (Cari Order Penunjang, Filter Status/Klaim) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. Order / Pasien..." data-ksmlite-search="labOrdersBody">
        </div>
        <select class="form-select form-select-sm toolbar-filter" data-ksmlite-status-filter="labOrdersBody">
            <option value="">Semua Status</option>
            <option value="terbuka">Siap Validasi</option>
            <option value="terkunci">Terkunci Kasir</option>
        </select>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: auto;">
            <span class="input-group-text bg-white text-muted">Dari</span>
            <input type="date" id="supportFilterFrom" class="form-control" value="<?= date('Y-m-d') ?>">
            <span class="input-group-text bg-white text-muted">s/d</span>
            <input type="date" id="supportFilterTo" class="form-control" value="<?= date('Y-m-d') ?>">
            <button type="button" id="btnFilterSupport" class="btn btn-outline-teal">Filter</button>
        </div>
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
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-sm btn-medical-teal" id="btnSaveLabResult">
                                            <i class="fa fa-check-circle me-1"></i> Simpan &amp; Validasi Hasil
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-teal" id="btnPrintLabResult">
                                            <i class="fa fa-print me-1"></i> Cetak Hasil
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

                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="card shadow-sm border-0">
                                    <div class="card-header bg-white py-2 fw-bold small text-medical-teal">Buat Order Lab Baru (Langsung)</div>
                                    <div class="card-body">
                                        <form id="formDirectLabOrder" class="row g-2 small">
                                            <div class="col-6"><input type="text" id="labOrdRawat" class="form-control form-control-sm" placeholder="No. Rawat *" required></div>
                                            <div class="col-6"><input type="text" id="labOrdKode" class="form-control form-control-sm" placeholder="Kode Paket (LAB01) *" required></div>
                                            <div class="col-8"><input type="text" id="labOrdInfo" class="form-control form-control-sm" placeholder="Informasi Tambahan / Klinis"></div>
                                            <div class="col-4"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Buat Order</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="card shadow-sm border-0">
                                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                        <span class="fw-bold small text-medical-teal">Master Parameter &amp; Nilai Rujukan Lab</span>
                                        <button type="button" id="btnLoadLabTemplates" class="btn btn-xs btn-outline-teal">Muat Parameter</button>
                                    </div>
                                    <div class="card-body p-2">
                                        <form id="formLabTemplateSave" class="row g-1 mb-2 small">
                                            <div class="col-md-3"><input type="text" id="tplLabKd" class="form-control form-control-sm" placeholder="Kode Paket *" required></div>
                                            <div class="col-md-3"><input type="text" id="tplLabPem" class="form-control form-control-sm" placeholder="Parameter *" required></div>
                                            <div class="col-md-2"><input type="text" id="tplLabSat" class="form-control form-control-sm" placeholder="Satuan"></div>
                                            <div class="col-md-2"><input type="text" id="tplLabRuj" class="form-control form-control-sm" placeholder="Rujukan"></div>
                                            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Tambah</button></div>
                                        </form>
                                        <div class="table-responsive" style="max-height:150px; overflow-y:auto;">
                                            <table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Paket</th><th>Parameter</th><th>Satuan</th><th>Nilai Rujukan</th></tr></thead><tbody id="tbodyLabTemplates"></tbody></table>
                                        </div>
                                    </div>
                                </div>
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
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-sm btn-medical-teal">
                                            <i class="fa fa-save me-1"></i> Simpan Expertise
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-teal" id="btnPrintRadResult">
                                            <i class="fa fa-print me-1"></i> Cetak Expertise
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnDeleteRadOrder">
                                            <i class="fa fa-trash me-1"></i> Hapus Order
                                        </button>
                                    </div>
                                </form>
                                <div id="radResultFeedback" class="mt-3"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold small text-medical-teal">Lampiran &amp; Viewer Gambar Radiologi</span>
                                <div class="d-flex gap-2">
                                    <a href="/orthanc" class="btn btn-xs btn-outline-primary">Buka Orthanc PACS</a>
                                    <button type="button" id="btnLoadRadImages" class="btn btn-xs btn-outline-teal">Muat Lampiran</button>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <form id="formRadImageSave" class="row g-2 mb-2 small">
                                    <div class="col-md-3"><input type="text" id="radImgRawat" class="form-control form-control-sm" placeholder="No. Rawat *" required></div>
                                    <div class="col-md-4"><input type="text" id="radImgUrl" class="form-control form-control-sm" placeholder="URL / Path Gambar Citra *" required></div>
                                    <div class="col-md-3"><input type="text" id="radImgKet" class="form-control form-control-sm" placeholder="Keterangan (Thorax PA)"></div>
                                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Simpan Gambar</button></div>
                                </form>
                                <div id="radImagesGallery" class="row g-2 small">
                                    <div class="col-12 text-muted">Klik "Muat Lampiran" untuk melihat gambar radiologi.</div>
                                </div>
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
                        <button type="button" class="btn btn-sm btn-outline-teal" id="btnExportLaporanCsv">
                            <i class="fa fa-file-csv me-1"></i> Export CSV/Excel
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
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white fw-bold small text-medical-teal">Rekonsiliasi Order (Pending / Cancelled / Void)</div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm mb-0 small">
                                    <thead class="table-light"><tr><th>No. Rawat</th><th>Pasien</th><th>Item Order</th><th>Status</th><th class="text-end">Nominal</th></tr></thead>
                                    <tbody id="repReconcileBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fetcher = (url, opts) => (window.MedarsApp && window.MedarsApp.apiFetch) ? window.MedarsApp.apiFetch(url, opts) : fetch(url, opts);
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const loadTpl = async () => {
        const res = await fetcher('/api/v1/penunjang/lab/templates');
        const json = await res.json();
        document.getElementById('tbodyLabTemplates').innerHTML = (json.data || []).map(r => `<tr>
            <td><code>${esc(r.kd_jenis_prw)}</code></td><td>${esc(r.pemeriksaan)}</td><td>${esc(r.satuan)}</td><td>${esc(r.nilai_rujukan_ld)}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center text-muted">Belum ada parameter rujukan.</td></tr>';
    };
    document.getElementById('btnLoadLabTemplates')?.addEventListener('click', loadTpl);
    document.getElementById('formLabTemplateSave')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await fetcher('/api/v1/penunjang/lab/templates-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                kd_jenis_prw: document.getElementById('tplLabKd').value.trim(),
                pemeriksaan: document.getElementById('tplLabPem').value.trim(),
                satuan: document.getElementById('tplLabSat').value.trim(),
                nilai_rujukan_ld: document.getElementById('tplLabRuj').value.trim() || '-',
            }),
        });
        document.getElementById('formLabTemplateSave').reset();
        loadTpl();
    });
    document.getElementById('formDirectLabOrder')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await fetcher('/api/v1/penunjang/lab/orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                no_rawat: document.getElementById('labOrdRawat').value.trim(),
                kd_jenis_prw: [document.getElementById('labOrdKode').value.trim()],
                informasi_tambahan: document.getElementById('labOrdInfo').value.trim(),
            }),
        });
        if (res.ok) {
            document.getElementById('formDirectLabOrder').reset();
            document.getElementById('btnRefreshLab')?.click();
        }
    });

    const loadRadImages = async () => {
        const nr = encodeURIComponent(document.getElementById('radImgRawat')?.value.trim() || '');
        const res = await fetcher('/api/v1/penunjang/radiologi/images?no_rawat=' + nr);
        const json = await res.json();
        document.getElementById('radImagesGallery').innerHTML = (json.data || []).map(img => `
            <div class="col-md-4"><div class="border rounded p-2 bg-light">
                <div class="fw-semibold">${esc(img.keterangan || 'Citra Radiologi')}</div>
                <div class="text-muted">${esc(img.no_rawat)} &middot; ${esc(img.tgl_periksa)}</div>
                <a href="${esc(img.lokasi_gambar)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary mt-1">Lihat Gambar</a>
            </div></div>
        `).join('') || '<div class="col-12 text-muted">Belum ada lampiran gambar radiologi.</div>';
    };
    document.getElementById('btnLoadRadImages')?.addEventListener('click', loadRadImages);
    document.getElementById('formRadImageSave')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await fetcher('/api/v1/penunjang/radiologi/images-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                no_rawat: document.getElementById('radImgRawat').value.trim(),
                lokasi_gambar: document.getElementById('radImgUrl').value.trim(),
                keterangan: document.getElementById('radImgKet').value.trim(),
            }),
        });
        loadRadImages();
    });
});
</script>
<script src="/assets/js/modules/support.js?v=20261117c" defer></script>
