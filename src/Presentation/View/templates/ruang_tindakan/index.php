<?php
$csrfToken = (string) ($csrf_token ?? '');
$today = date('Y-m-d');
?>
<div class="container-fluid py-3" id="ruangTindakanApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header text-white fw-semibold" style="background-color:#00897b;">
                    <i class="fa-solid fa-kit-medical me-1"></i> Input Tindakan Ruang Tindakan
                </div>
                <div class="card-body">
                    <form id="rtForm">
                        <div class="mb-2"><label class="form-label small fw-semibold">No. Rawat *</label><input type="text" id="rtRawat" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label small fw-semibold">Kode Tindakan *</label><input type="text" id="rtKode" class="form-control form-control-sm" placeholder="Contoh: TIND-01" required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label small">Tgl Tindakan</label><input type="date" id="rtTgl" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"></div>
                            <div class="col-6"><label class="form-label small">Status</label><select id="rtStatus" class="form-select form-select-sm"><option value="Menunggu">Menunggu</option><option value="Draft">Draft</option><option value="Selesai">Selesai</option></select></div>
                        </div>
                        <div class="mb-2"><label class="form-label small">Diagnosis</label><input type="text" id="rtDiagnosis" class="form-control form-control-sm"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label small">Kode Dokter</label><input type="text" id="rtDokter" class="form-control form-control-sm" placeholder="DR01"></div>
                            <div class="col-6"><label class="form-label small">Paramedis</label><input type="text" id="rtParamedis" class="form-control form-control-sm" placeholder="P01"></div>
                        </div>
                        <div class="mb-2"><label class="form-label small">Uraian Tindakan</label><textarea id="rtLaporan" class="form-control form-control-sm" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label small">Catatan Follow-Up</label><textarea id="rtCatatan" class="form-control form-control-sm" rows="2"></textarea></div>
                        <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Tindakan</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span class="fw-semibold">Daftar Follow-Up &amp; Ruang Tindakan</span>
                    <div class="d-flex gap-2">
                        <input type="date" id="rtFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="date" id="rtTo" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="text" id="rtSearch" class="form-control form-control-sm" placeholder="Cari no. rawat / diagnosis...">
                        <button type="button" id="rtFilterBtn" class="btn btn-sm btn-outline-secondary">Filter</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light"><tr><th>Tanggal</th><th>No. Rawat</th><th>Tindakan</th><th>Diagnosis</th><th>Petugas</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody id="rtTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/ruang-tindakan.js?v=<?= time() ?>"></script>
