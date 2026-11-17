<!-- Toolbar Paritas ksmlite (Cari Antrean Bridging, Filter Status) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. Rawat / Resource..." data-ksmlite-search="bridgingQueueBody">
        </div>
        <select class="form-select form-select-sm toolbar-filter" data-ksmlite-status-filter="bridgingQueueBody">
            <option value="">Semua Status</option>
            <option value="pending">Pending</option>
            <option value="synced">Synced</option>
            <option value="failed">Failed</option>
        </select>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border"><i class="fa fa-shield-halved me-1 text-medical-teal"></i>Zero-Latency Circuit Breaker</span>
    </div>
</div>

<div class="row g-3" id="bridgingWorkspace">
    <!-- Panel 1: Zero-Latency Circuit Breaker & Antrean SatuSehat FHIR -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-network-wired me-1"></i> Bridging BPJS &amp; Circuit Breaker</h6>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnRefreshBridging">
                    <i class="fa fa-rotate"></i>
                </button>
            </div>
            <div class="card-body">
                <div id="bpjsCircuitBadge" class="alert alert-secondary py-2 small mb-2">Memeriksa status Circuit Breaker...</div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold">Antrean Sinkronisasi SatuSehat FHIR</span>
                    <button type="button" class="btn btn-xs btn-medical-teal" id="btnProcessQueue">
                        <i class="fa fa-play me-1"></i> Proses Antrean
                    </button>
                </div>
                <div style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Provider</th>
                                <th>No. Rawat</th>
                                <th>Resource</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="bridgingQueueBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel 2: HFIS LEAN Board & Verifikasi Klaim Vedika -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-clock me-1"></i> Kalkulator HFIS LEAN Board</h6>
            </div>
            <div class="card-body">
                <form id="formLeanEstimate" class="row g-2 mb-2">
                    <div class="col-md-3">
                        <label class="form-label small">Shift</label>
                        <select class="form-select form-select-sm" id="leanShift">
                            <option value="Pagi">Pagi</option>
                            <option value="Sore">Sore</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Kode Poli</label>
                        <input type="text" class="form-control form-control-sm" id="leanKdPoli" value="UMU01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">No. Reg</label>
                        <input type="number" class="form-control form-control-sm" id="leanNoReg" value="5" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Durasi (m)</label>
                        <input type="number" class="form-control form-control-sm" id="leanDuration" value="5" min="1">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-medical-teal">Hitung Estimasi HFIS</button>
                    </div>
                </form>
                <div id="leanEstimateOutput" class="small text-muted"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-file-medical-alt me-1"></i> Verifikasi Klaim Vedika BPJS</h6>
            </div>
            <div class="card-body">
                <form id="formVedikaVerify" class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">No. Rawat</label>
                        <input type="text" class="form-control form-control-sm" id="vedikaNoRawat" placeholder="2026/11/16/000001" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">No. SEP BPJS</label>
                        <input type="text" class="form-control form-control-sm" id="vedikaNoSep" placeholder="0123R001..." required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-medical-teal">Verifikasi Berkas Vedika</button>
                    </div>
                </form>
                <div id="vedikaFeedback" class="mt-2"></div>
                <hr class="my-3">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <input type="date" id="vedikaFrom" class="form-control form-control-sm" style="width: auto;" value="<?= date('Y-m-01') ?>">
                    <input type="date" id="vedikaTo" class="form-control form-control-sm" style="width: auto;" value="<?= date('Y-m-d') ?>">
                    <button type="button" id="btnLoadVedikaClaims" class="btn btn-sm btn-outline-teal">Muat Klaim BPJS</button>
                </div>
                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light"><tr><th>No. Rawat</th><th>Pasien</th><th>Status</th><th class="text-center">Bundel</th></tr></thead>
                        <tbody id="tbodyVedikaClaims"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12" id="vclaimPanel">
        <div class="panel panel-default">
            <div class="panel-heading" style="background:#00897b;color:#fff;font-weight:bold;">
                VClaim BPJS (Cari Peserta, Buat &amp; Cetak SEP, Rujukan &amp; Surat Kontrol)
            </div>
            <div class="panel-body">
                <div class="row" style="margin-bottom:14px;">
                    <div class="col-md-5">
                        <label>Cari Peserta BPJS (No. Kartu / NIK / No. RM)</label>
                        <div class="input-group">
                            <input type="text" id="vclaimCariPeserta" class="form-control input-sm" placeholder="Contoh: 0001234567890">
                            <span class="input-group-btn">
                                <button type="button" id="btnVclaimCariPeserta" class="btn btn-primary btn-sm">Cari Peserta</button>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-7" id="vclaimPesertaResult" style="padding-top:22px;font-size:13px;"></div>
                </div>

                <ul class="nav nav-tabs" style="margin-bottom:12px;">
                    <li class="active"><a href="#tabVclaimSep" data-toggle="tab">SEP BPJS</a></li>
                    <li><a href="#tabVclaimRujukan" data-toggle="tab">Rujukan Keluar</a></li>
                    <li><a href="#tabVclaimKontrol" data-toggle="tab">Surat Kontrol</a></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane active" id="tabVclaimSep">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formVclaimSep">
                                    <div class="form-group"><label>No. Rawat *</label><input type="text" id="sepNoRawat" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>No. Kartu BPJS *</label><input type="text" id="sepNoKartu" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Nama Peserta</label><input type="text" id="sepNama" class="form-control input-sm"></div>
                                    <div class="row">
                                        <div class="col-xs-5 form-group"><label>Kode Poli</label><input type="text" id="sepKdPoli" class="form-control input-sm" placeholder="INT"></div>
                                        <div class="col-xs-7 form-group"><label>Nama Poli</label><input type="text" id="sepNmPoli" class="form-control input-sm" placeholder="Penyakit Dalam"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-xs-4 form-group"><label>ICD-10</label><input type="text" id="sepDiag" class="form-control input-sm" placeholder="J00"></div>
                                        <div class="col-xs-8 form-group"><label>Diagnosa Awal</label><input type="text" id="sepNmDiag" class="form-control input-sm"></div>
                                    </div>
                                    <div class="form-group"><label>Catatan</label><input type="text" id="sepCatatan" class="form-control input-sm"></div>
                                    <button type="submit" class="btn btn-success btn-sm">Simpan &amp; Terbitkan SEP</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <div class="form-inline" style="margin-bottom:8px;">
                                    <input type="text" id="sepFilterQ" class="form-control input-sm" placeholder="Cari No. SEP / Rawat / Nama">
                                    <button type="button" id="btnReloadSep" class="btn btn-default btn-sm">Muat SEP</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. SEP</th><th>Tgl SEP</th><th>No. Rawat</th><th>Peserta</th><th>Poli / Diagnosa</th><th>Aksi</th></tr></thead>
                                        <tbody id="vclaimSepBody"><tr><td colspan="6" class="text-center text-muted">Belum ada data SEP.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="tabVclaimRujukan">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formVclaimRujukan">
                                    <div class="form-group"><label>No. SEP Asal *</label><input type="text" id="rujNoSep" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>PPK Dirujuk (Kode)</label><input type="text" id="rujPpk" class="form-control input-sm" placeholder="0112R002"></div>
                                    <div class="form-group"><label>Nama PPK Dirujuk</label><input type="text" id="rujNmPpk" class="form-control input-sm" placeholder="RSUD Rujukan"></div>
                                    <div class="form-group"><label>Diagnosa Rujukan</label><input type="text" id="rujDiag" class="form-control input-sm" placeholder="I10"></div>
                                    <div class="form-group"><label>Catatan</label><input type="text" id="rujCatatan" class="form-control input-sm"></div>
                                    <button type="submit" class="btn btn-success btn-sm">Buat Rujukan</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadRujukan" class="btn btn-default btn-sm" style="margin-bottom:8px;">Muat Rujukan</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. Rujukan</th><th>Tgl</th><th>No. SEP</th><th>PPK Dirujuk</th><th>Diagnosa</th></tr></thead>
                                        <tbody id="vclaimRujukanBody"><tr><td colspan="5" class="text-center text-muted">Belum ada rujukan.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="tabVclaimKontrol">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formVclaimKontrol">
                                    <div class="form-group"><label>No. SEP *</label><input type="text" id="skNoSep" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Tgl Rencana Kontrol</label><input type="date" id="skTglRencana" class="form-control input-sm"></div>
                                    <div class="form-group"><label>Nama Dokter BPJS</label><input type="text" id="skNmDokter" class="form-control input-sm"></div>
                                    <div class="form-group"><label>Nama Poli BPJS</label><input type="text" id="skNmPoli" class="form-control input-sm"></div>
                                    <button type="submit" class="btn btn-success btn-sm">Buat Surat Kontrol</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadKontrol" class="btn btn-default btn-sm" style="margin-bottom:8px;">Muat Surat Kontrol</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. Surat</th><th>Tgl Rencana</th><th>No. SEP</th><th>Dokter</th><th>Poli</th></tr></thead>
                                        <tbody id="vclaimKontrolBody"><tr><td colspan="5" class="text-center text-muted">Belum ada surat kontrol.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/bridging.js?v=20261117b" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const api = window.MedarsApp && window.MedarsApp.apiFetch ? window.MedarsApp.apiFetch : null;
    if (!api) return;

    document.getElementById('btnVclaimCariPeserta')?.addEventListener('click', async () => {
        const q = document.getElementById('vclaimCariPeserta').value.trim();
        const box = document.getElementById('vclaimPesertaResult');
        const res = await api('/api/v1/bridging/vclaim/peserta?no_kartu=' + encodeURIComponent(q));
        if (res.status === 'success' && res.data) {
            box.innerHTML = '<span class="label label-success">Aktif</span> <strong>' + esc(res.data.nm_pasien) + '</strong> | No. RM: ' + esc(res.data.no_rkm_medis) + ' | Kartu: ' + esc(res.data.no_peserta || '-');
            document.getElementById('sepNoKartu').value = res.data.no_peserta || q;
            document.getElementById('sepNama').value = res.data.nm_pasien || '';
        } else {
            box.innerHTML = '<span class="text-danger">' + esc(res.message || 'Peserta tidak ditemukan') + '</span>';
        }
    });

    async function loadSep() {
        const q = document.getElementById('sepFilterQ').value.trim();
        const res = await api('/api/v1/bridging/vclaim/sep?q=' + encodeURIComponent(q));
        const tb = document.getElementById('vclaimSepBody');
        const rows = res.data || [];
        tb.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.no_sep) + '</td><td>' + esc(r.tglsep) + '</td><td>' + esc(r.no_rawat) + '</td><td>' + esc(r.nama_pasien) + ' (' + esc(r.no_kartu) + ')</td><td>' + esc(r.nmpolitujuan || r.kdpolitujuan) + ' / ' + esc(r.diagawal) + '</td><td><a class="btn btn-xs btn-info" target="_blank" href="/bridging/vclaim/sep/cetak?no_sep=' + encodeURIComponent(r.no_sep) + '">Cetak</a></td></tr>').join('') : '<tr><td colspan="6" class="text-center text-muted">Belum ada data SEP.</td></tr>';
    }

    async function loadRujukan() {
        const res = await api('/api/v1/bridging/vclaim/rujukan');
        const tb = document.getElementById('vclaimRujukanBody');
        const rows = res.data || [];
        tb.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.no_rujukan) + '</td><td>' + esc(r.tgl_rujukan) + '</td><td>' + esc(r.no_sep) + '</td><td>' + esc(r.nm_ppk_dirujuk || r.ppk_dirujuk) + '</td><td>' + esc(r.diag_rujukan) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada rujukan.</td></tr>';
    }

    async function loadKontrol() {
        const res = await api('/api/v1/bridging/vclaim/surat-kontrol');
        const tb = document.getElementById('vclaimKontrolBody');
        const rows = res.data || [];
        tb.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.no_surat) + '</td><td>' + esc(r.tgl_rencana) + '</td><td>' + esc(r.no_sep) + '</td><td>' + esc(r.nm_dokter_bpjs) + '</td><td>' + esc(r.nm_poli_bpjs) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada surat kontrol.</td></tr>';
    }

    document.getElementById('formVclaimSep')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/vclaim/sep-save', {
            method: 'POST',
            body: JSON.stringify({
                no_rawat: document.getElementById('sepNoRawat').value,
                no_kartu: document.getElementById('sepNoKartu').value,
                nama_pasien: document.getElementById('sepNama').value,
                kdpolitujuan: document.getElementById('sepKdPoli').value,
                nmpolitujuan: document.getElementById('sepNmPoli').value,
                diagawal: document.getElementById('sepDiag').value,
                nmdiagnosaawal: document.getElementById('sepNmDiag').value,
                catatan: document.getElementById('sepCatatan').value,
            }),
        });
        if (res.status === 'success') { loadSep(); }
    });

    document.getElementById('formVclaimRujukan')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/vclaim/rujukan-save', {
            method: 'POST',
            body: JSON.stringify({
                no_sep: document.getElementById('rujNoSep').value,
                ppk_dirujuk: document.getElementById('rujPpk').value,
                nm_ppk_dirujuk: document.getElementById('rujNmPpk').value,
                diag_rujukan: document.getElementById('rujDiag').value,
                catatan: document.getElementById('rujCatatan').value,
            }),
        });
        if (res.status === 'success') { loadRujukan(); }
    });

    document.getElementById('formVclaimKontrol')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/vclaim/surat-kontrol-save', {
            method: 'POST',
            body: JSON.stringify({
                no_sep: document.getElementById('skNoSep').value,
                tgl_rencana: document.getElementById('skTglRencana').value,
                nm_dokter_bpjs: document.getElementById('skNmDokter').value,
                nm_poli_bpjs: document.getElementById('skNmPoli').value,
            }),
        });
        if (res.status === 'success') { loadKontrol(); }
    });

    document.getElementById('btnReloadSep')?.addEventListener('click', loadSep);
    document.getElementById('btnReloadRujukan')?.addEventListener('click', loadRujukan);
    document.getElementById('btnReloadKontrol')?.addEventListener('click', loadKontrol);
    loadSep();
    loadRujukan();
    loadKontrol();
});
</script>
