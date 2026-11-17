<!-- Toolbar Paritas ksmlite (Cari Kunjungan Home Care) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
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

<link rel="manifest" href="/homecare-manifest.json">
<div class="row g-3 mt-2" id="homecarePwaCarePlanPanel">
    <div class="col-lg-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-notes-medical me-1"></i> Care Plan Homecare, Sign-Off DPJP &amp; Pembayaran Midtrans / Kasera (PWA)</h6>
                <span class="badge bg-success">PWA Ready</span>
            </div>
            <div class="card-body">
                <form id="formHcCarePlan" class="row g-2 mb-3">
                    <div class="col-md-3"><input type="text" id="hcCpRawat" class="form-control form-control-sm" placeholder="No. Rawat" required></div>
                    <div class="col-md-5"><input type="text" id="hcCpPlan" class="form-control form-control-sm" placeholder="Rencana Asuhan (Care Plan)" required></div>
                    <div class="col-md-2"><input type="number" id="hcCpNominal" class="form-control form-control-sm" placeholder="Tagihan (Rp)" value="150000"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-medical-teal w-100">Simpan Care Plan</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                        <thead><tr><th>No. Rawat</th><th>Care Plan</th><th>Sign-Off DPJP</th><th>Tagihan</th><th>Pembayaran</th><th>Aksi</th></tr></thead>
                        <tbody id="hcCarePlanBody"><tr><td colspan="6" class="text-center text-muted">Belum ada care plan.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/homecare.js" defer></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/homecare-sw.js').catch(() => {});
}
document.addEventListener('DOMContentLoaded', function () {
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const api = window.MedarsApp && window.MedarsApp.apiFetch ? window.MedarsApp.apiFetch : null;
    if (!api) return;

    async function loadCarePlans() {
        const res = await api('/api/v1/homecare/careplans');
        const rows = res.data || [];
        document.getElementById('hcCarePlanBody').innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.no_rawat) + '</td><td>' + esc(r.care_plan) + '</td><td>' + (Number(r.dpjp_signed) === 1 ? '<span class="badge bg-success">Signed: ' + esc(r.dpjp_signed_by) + '</span>' : '<button type="button" class="btn btn-xs btn-outline-primary btn-hc-sign" data-id="' + esc(r.id) + '">Sign-Off DPJP</button>') + '</td><td>Rp ' + esc(r.nominal_tagihan) + '</td><td>' + esc(r.status_bayar) + ' (' + esc(r.payment_gateway) + ')</td><td>' + (r.status_bayar === 'Lunas' ? '<span class="text-success fw-bold">Lunas</span>' : '<button type="button" class="btn btn-xs btn-success me-1 btn-hc-pay" data-gw="Midtrans" data-id="' + esc(r.id) + '">Bayar Midtrans</button><button type="button" class="btn btn-xs btn-info btn-hc-pay" data-gw="Kasera" data-id="' + esc(r.id) + '">Bayar Kasera</button>') + '</td></tr>').join('') : '<tr><td colspan="6" class="text-center text-muted">Belum ada care plan.</td></tr>';
    }
    document.getElementById('formHcCarePlan')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/homecare/careplans-save', { method: 'POST', body: JSON.stringify({ no_rawat: hcCpRawat.value, care_plan: hcCpPlan.value, nominal_tagihan: hcCpNominal.value }) });
        if (res.status === 'success') { hcCpPlan.value = ''; loadCarePlans(); }
    });
    document.getElementById('hcCarePlanBody')?.addEventListener('click', async (e) => {
        const signBtn = e.target.closest('.btn-hc-sign');
        if (signBtn) {
            await api('/api/v1/homecare/careplans-signoff', { method: 'POST', body: JSON.stringify({ id: signBtn.dataset.id }) });
            loadCarePlans();
            return;
        }
        const payBtn = e.target.closest('.btn-hc-pay');
        if (payBtn) {
            await api('/api/v1/homecare/careplans-pay', { method: 'POST', body: JSON.stringify({ id: payBtn.dataset.id, payment_gateway: payBtn.dataset.gw }) });
            loadCarePlans();
        }
    });
    loadCarePlans();
});
</script>
