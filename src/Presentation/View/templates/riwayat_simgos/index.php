<div class="row g-3" id="riwayatSimgosPage">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-history me-1"></i> Viewer Riwayat Migrasi SIMGOS Lama</h6>
                <span class="badge bg-light text-dark border">Read-Only Archive &amp; Import</span>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><input type="text" id="sgSearchQ" class="form-control form-control-sm" placeholder="Cari NOPEN / No. RM / NIK / Nama / ICD-10"></div>
                    <div class="col-md-2"><input type="date" id="sgFrom" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><input type="date" id="sgTo" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><button type="button" id="btnSgSearch" class="btn btn-sm btn-medical-teal w-100">Cari Riwayat</button></div>
                </div>

                <form id="formSgImport" class="row g-2 mb-3 border rounded p-2 bg-light">
                    <div class="col-md-2"><input type="text" id="sgNopen" class="form-control form-control-sm" placeholder="NOPEN *" required></div>
                    <div class="col-md-2"><input type="text" id="sgRm" class="form-control form-control-sm" placeholder="No. RM Lama"></div>
                    <div class="col-md-2"><input type="text" id="sgNama" class="form-control form-control-sm" placeholder="Nama Pasien *" required></div>
                    <div class="col-md-2"><input type="date" id="sgTgl" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><input type="text" id="sgDiag" class="form-control form-control-sm" placeholder="Diagnosa ICD-10"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-success w-100">Import Arsip</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" style="font-size:12px;">
                        <thead class="table-light">
                            <tr><th>NOPEN</th><th>Tgl Kunjungan</th><th>No. RM</th><th>Nama Pasien</th><th>Unit / Dokter</th><th>Diagnosa</th><th>Catatan</th></tr>
                        </thead>
                        <tbody id="sgTableBody">
                            <tr><td colspan="7" class="text-center text-muted">Memuat arsip SIMGOS...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const api = window.MedarsApp && window.MedarsApp.apiFetch ? window.MedarsApp.apiFetch : null;
    if (!api) return;

    async function loadSimgos() {
        const q = encodeURIComponent(sgSearchQ.value.trim());
        const from = encodeURIComponent(sgFrom.value);
        const to = encodeURIComponent(sgTo.value);
        const res = await api('/api/v1/riwayat-simgos?q=' + q + '&from=' + from + '&to=' + to);
        const rows = res.data || [];
        sgTableBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.nopen) + '</td><td>' + esc(r.tgl_kunjungan) + '</td><td>' + esc(r.no_rkm_medis) + '</td><td>' + esc(r.nm_pasien) + '</td><td>' + esc(r.unit_layanan) + ' / ' + esc(r.dokter) + '</td><td>' + esc(r.diagnosa_icd10) + '</td><td>' + esc(r.catatan_klinis) + '</td></tr>').join('') : '<tr><td colspan="7" class="text-center text-muted">Tidak ada data riwayat SIMGOS.</td></tr>';
    }
    btnSgSearch?.addEventListener('click', loadSimgos);
    formSgImport?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/riwayat-simgos/import', { method: 'POST', body: JSON.stringify({ nopen: sgNopen.value, no_rkm_medis: sgRm.value, nm_pasien: sgNama.value, tgl_kunjungan: sgTgl.value, diagnosa_icd10: sgDiag.value }) });
        if (res.status === 'success') { sgNopen.value = ''; sgNama.value = ''; loadSimgos(); }
    });
    loadSimgos();
});
</script>
