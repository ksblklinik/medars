<?php
$csrfToken = (string) ($csrf_token ?? '');
?>
<div class="container-fluid py-3" id="orthancApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header text-white fw-semibold" style="background-color:#00897b;">
                    <i class="fa-solid fa-x-ray me-1"></i> Tautkan Study DICOM Orthanc
                </div>
                <div class="card-body">
                    <form id="orStudyForm">
                        <div class="mb-2"><label class="form-label small">No. Rawat *</label><input type="text" id="orRawat" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label small">Study Instance UID *</label><input type="text" id="orUid" class="form-control form-control-sm" placeholder="1.2.840..." required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-5"><label class="form-label small">Modality</label><select id="orMod" class="form-select form-select-sm"><option value="CR">CR</option><option value="CT">CT</option><option value="DX">DX</option><option value="US">US</option><option value="MR">MR</option></select></div>
                            <div class="col-7"><label class="form-label small">Deskripsi Study</label><input type="text" id="orDesc" class="form-control form-control-sm" placeholder="Thorax PA"></div>
                        </div>
                        <div class="mb-2"><label class="form-label small">URL OHIF / DICOM Viewer</label><input type="url" id="orViewer" class="form-control form-control-sm" placeholder="http://localhost:8042/ohif/viewer?..."></div>
                        <div class="mb-3"><label class="form-label small">Hasil Interpretasi Awal</label><textarea id="orHasil" class="form-control form-control-sm" rows="2"></textarea></div>
                        <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Study PACS</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Daftar Study DICOM &amp; Interpretasi</span>
                    <div class="input-group input-group-sm" style="max-width:260px;">
                        <input type="text" id="orSearch" class="form-control" placeholder="Cari no. rawat / modality / UID...">
                        <button type="button" id="orSearchBtn" class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light"><tr><th>Tgl</th><th>No. Rawat</th><th>Mod</th><th>Deskripsi</th><th>Study UID</th><th>Hasil Baca</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody id="orBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/orthanc.js?v=<?= time() ?>"></script>
