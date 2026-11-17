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

    <!-- Panel 3: VClaim BPJS -->
    <div class="col-12" id="vclaimPanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-id-card me-1"></i> VClaim BPJS (Cari Peserta, Buat &amp; Cetak SEP, Rujukan &amp; Surat Kontrol)</h6>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Cari Peserta BPJS (No. Kartu / NIK / No. RM)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="vclaimCariPeserta" class="form-control" placeholder="Contoh: 0001234567890">
                            <button type="button" id="btnVclaimCariPeserta" class="btn btn-medical-teal">Cari Peserta</button>
                        </div>
                    </div>
                    <div class="col-md-7 small" id="vclaimPesertaResult"></div>
                </div>

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabVclaimSep" type="button">SEP BPJS</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVclaimRujukan" type="button">Rujukan Keluar</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVclaimKontrol" type="button">Surat Kontrol</button></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tabVclaimSep">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formVclaimSep" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. Rawat *</label><input type="text" id="sepNoRawat" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">No. Kartu BPJS *</label><input type="text" id="sepNoKartu" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Peserta</label><input type="text" id="sepNama" class="form-control form-control-sm"></div>
                                    <div class="col-4"><label class="form-label small mb-0">Kode Poli</label><input type="text" id="sepKdPoli" class="form-control form-control-sm" placeholder="INT"></div>
                                    <div class="col-8"><label class="form-label small mb-0">Nama Poli</label><input type="text" id="sepNmPoli" class="form-control form-control-sm" placeholder="Penyakit Dalam"></div>
                                    <div class="col-4"><label class="form-label small mb-0">ICD-10</label><input type="text" id="sepDiag" class="form-control form-control-sm" placeholder="J00"></div>
                                    <div class="col-8"><label class="form-label small mb-0">Diagnosa Awal</label><input type="text" id="sepNmDiag" class="form-control form-control-sm"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Catatan</label><input type="text" id="sepCatatan" class="form-control form-control-sm"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Simpan &amp; Terbitkan SEP</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <div class="d-flex gap-2 mb-2">
                                    <input type="text" id="sepFilterQ" class="form-control form-control-sm" style="max-width:240px;" placeholder="Cari No. SEP / Rawat / Nama">
                                    <button type="button" id="btnReloadSep" class="btn btn-outline-secondary btn-sm">Muat SEP</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. SEP</th><th>Tgl SEP</th><th>No. Rawat</th><th>Peserta</th><th>Poli / Diagnosa</th><th>Aksi</th></tr></thead>
                                        <tbody id="vclaimSepBody"><tr><td colspan="6" class="text-center text-muted">Belum ada data SEP.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tabVclaimRujukan">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formVclaimRujukan" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. SEP Asal *</label><input type="text" id="rujNoSep" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">PPK Dirujuk (Kode)</label><input type="text" id="rujPpk" class="form-control form-control-sm" placeholder="0112R002"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama PPK Dirujuk</label><input type="text" id="rujNmPpk" class="form-control form-control-sm" placeholder="RSUD Rujukan"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Diagnosa Rujukan</label><input type="text" id="rujDiag" class="form-control form-control-sm" placeholder="I10"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Catatan</label><input type="text" id="rujCatatan" class="form-control form-control-sm"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Buat Rujukan</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadRujukan" class="btn btn-outline-secondary btn-sm mb-2">Muat Rujukan</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. Rujukan</th><th>Tgl</th><th>No. SEP</th><th>PPK Dirujuk</th><th>Diagnosa</th></tr></thead>
                                        <tbody id="vclaimRujukanBody"><tr><td colspan="5" class="text-center text-muted">Belum ada rujukan.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tabVclaimKontrol">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formVclaimKontrol" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. SEP *</label><input type="text" id="skNoSep" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Tgl Rencana Kontrol</label><input type="date" id="skTglRencana" class="form-control form-control-sm"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Dokter BPJS</label><input type="text" id="skNmDokter" class="form-control form-control-sm"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Poli BPJS</label><input type="text" id="skNmPoli" class="form-control form-control-sm"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Buat Surat Kontrol</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadKontrol" class="btn btn-outline-secondary btn-sm mb-2">Muat Surat Kontrol</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. Surat</th><th>Tgl Rencana</th><th>No. SEP</th><th>Dokter</th><th>Poli</th></tr></thead>
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

    <!-- Panel 4: HFIS & Antrol -->
    <div class="col-12" id="hfisAntrolPanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-calendar-check me-1"></i> HFIS Jadwal Dokter &amp; Antrean Online BPJS (Antrol Task ID 1–7)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="small fw-bold">Sinkron Jadwal Dokter HFIS</h6>
                        <form id="formHfisSync" class="d-flex flex-wrap gap-2 mb-2">
                            <input type="text" id="hfisKdPoli" class="form-control form-control-sm" style="max-width:110px;" placeholder="Kd Poli (INT)" required>
                            <input type="text" id="hfisNmPoli" class="form-control form-control-sm" style="max-width:140px;" placeholder="Nama Poli">
                            <input type="text" id="hfisKdDokter" class="form-control form-control-sm" style="max-width:120px;" placeholder="Kd Dokter BPJS" required>
                            <input type="text" id="hfisNmDokter" class="form-control form-control-sm" style="max-width:150px;" placeholder="Nama Dokter">
                            <input type="text" id="hfisNamaHari" class="form-control form-control-sm" style="max-width:100px;" value="SENIN" placeholder="Hari">
                            <input type="text" id="hfisJadwal" class="form-control form-control-sm" style="max-width:120px;" value="08:00-12:00" placeholder="Jam">
                            <button type="submit" class="btn btn-success btn-sm">Sinkron HFIS</button>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" style="font-size:12px;">
                                <thead class="table-light"><tr><th>Poli</th><th>Dokter</th><th>Hari</th><th>Jadwal</th><th>Kuota</th></tr></thead>
                                <tbody id="hfisJadwalBody"><tr><td colspan="5" class="text-center text-muted">Belum ada jadwal HFIS.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="small fw-bold">Antrean Online BPJS (Antrol Task ID 1–7)</h6>
                        <form id="formAntrolPush" class="d-flex flex-wrap gap-2 mb-2">
                            <input type="text" id="antrolKodeBooking" class="form-control form-control-sm" style="max-width:150px;" placeholder="Kode Booking" required>
                            <input type="text" id="antrolNoRawat" class="form-control form-control-sm" style="max-width:140px;" placeholder="No. Rawat">
                            <select id="antrolTaskId" class="form-select form-select-sm" style="max-width:210px;">
                                <option value="1">Task 1 - Mulai Tunggu Admisi</option>
                                <option value="2">Task 2 - Selesai Admisi</option>
                                <option value="3">Task 3 - Mulai Tunggu Poli</option>
                                <option value="4">Task 4 - Dilayani Poli</option>
                                <option value="5">Task 5 - Selesai Poli</option>
                                <option value="6">Task 6 - Mulai Tunggu Farmasi</option>
                                <option value="7">Task 7 - Selesai Obat</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Kirim Task Antrol</button>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" style="font-size:12px;">
                                <thead class="table-light"><tr><th>Kode Booking</th><th>No. Rawat</th><th>Task ID</th><th>Status</th><th>Respon</th></tr></thead>
                                <tbody id="antrolTaskBody"><tr><td colspan="5" class="text-center text-muted">Belum ada log antrol.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel 5: SatuSehat FHIR -->
    <div class="col-12" id="satuSehatPanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-hospital me-1"></i> SatuSehat Kemenkes (Mapping IHS ID &amp; Antrean FHIR Observation / MedicationRequest / ServiceRequest)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="small fw-bold">Mapping IHS ID (Location / Practitioner / Patient / Organization)</h6>
                        <form id="formSsMapping" class="d-flex flex-wrap gap-2 mb-2">
                            <select id="ssEntityType" class="form-select form-select-sm" style="max-width:155px;">
                                <option value="location">Location (Poli)</option>
                                <option value="practitioner">Practitioner (Nakes)</option>
                                <option value="patient">Patient (Pasien)</option>
                                <option value="organization">Organization (Unit)</option>
                            </select>
                            <input type="text" id="ssLocalCode" class="form-control form-control-sm" style="max-width:110px;" placeholder="Kode Lokal" required>
                            <input type="text" id="ssLocalName" class="form-control form-control-sm" style="max-width:130px;" placeholder="Nama Lokal">
                            <input type="text" id="ssIhsId" class="form-control form-control-sm" style="max-width:150px;" placeholder="IHS ID SatuSehat" required>
                            <button type="submit" class="btn btn-success btn-sm">Simpan Mapping IHS</button>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" style="font-size:12px;">
                                <thead class="table-light"><tr><th>Tipe</th><th>Kode Lokal</th><th>Nama</th><th>IHS ID</th></tr></thead>
                                <tbody id="ssMappingBody"><tr><td colspan="4" class="text-center text-muted">Belum ada mapping IHS ID.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="small fw-bold">Kirim Antrean FHIR Resource (Observation / MedicationRequest / ServiceRequest)</h6>
                        <form id="formSsFhir" class="d-flex flex-wrap gap-2 mb-2">
                            <select id="ssFhirType" class="form-select form-select-sm" style="max-width:210px;">
                                <option value="Observation">Observation (TTV / Lab)</option>
                                <option value="MedicationRequest">MedicationRequest (E-Resep)</option>
                                <option value="ServiceRequest">ServiceRequest (Order Lab/Rad)</option>
                                <option value="Encounter">Encounter</option>
                                <option value="Condition">Condition</option>
                            </select>
                            <input type="text" id="ssFhirNoRawat" class="form-control form-control-sm" style="max-width:140px;" placeholder="No. Rawat" required>
                            <input type="text" id="ssFhirNote" class="form-control form-control-sm" style="max-width:180px;" placeholder="Kode LOINC / KFA">
                            <button type="submit" class="btn btn-primary btn-sm">Antrekan FHIR</button>
                        </form>
                        <div id="ssFhirFeedback" class="small text-muted"></div>
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
    const api = window.MedarsApp && window.MedarsApp.apiJson ? window.MedarsApp.apiJson : null;
    if (!api) return;

    document.getElementById('btnVclaimCariPeserta')?.addEventListener('click', async () => {
        const q = vclaimCariPeserta.value.trim();
        const res = await api('/api/v1/bridging/vclaim/peserta?no_kartu=' + encodeURIComponent(q));
        if (res.status === 'success' && res.data) {
            vclaimPesertaResult.innerHTML = '<span class="badge bg-success">Aktif</span> <strong>' + esc(res.data.nm_pasien) + '</strong> | No. RM: ' + esc(res.data.no_rkm_medis) + ' | Kartu: ' + esc(res.data.no_peserta || '-');
            sepNoKartu.value = res.data.no_peserta || q;
            sepNama.value = res.data.nm_pasien || '';
        } else {
            vclaimPesertaResult.innerHTML = '<span class="text-danger">' + esc(res.message || 'Peserta tidak ditemukan') + '</span>';
        }
    });

    async function loadSep() {
        const res = await api('/api/v1/bridging/vclaim/sep?q=' + encodeURIComponent(sepFilterQ.value.trim()));
        const rows = res.data || [];
        vclaimSepBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.no_sep) + '</td><td>' + esc(r.tglsep) + '</td><td>' + esc(r.no_rawat) + '</td><td>' + esc(r.nama_pasien) + ' (' + esc(r.no_kartu) + ')</td><td>' + esc(r.nmpolitujuan || r.kdpolitujuan) + ' / ' + esc(r.diagawal) + '</td><td><a class="btn btn-xs btn-info" target="_blank" href="/bridging/vclaim/sep/cetak?no_sep=' + encodeURIComponent(r.no_sep) + '">Cetak</a></td></tr>').join('') : '<tr><td colspan="6" class="text-center text-muted">Belum ada data SEP.</td></tr>';
    }
    async function loadRujukan() {
        const res = await api('/api/v1/bridging/vclaim/rujukan');
        const rows = res.data || [];
        vclaimRujukanBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.no_rujukan) + '</td><td>' + esc(r.tgl_rujukan) + '</td><td>' + esc(r.no_sep) + '</td><td>' + esc(r.nm_ppk_dirujuk || r.ppk_dirujuk) + '</td><td>' + esc(r.diag_rujukan) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada rujukan.</td></tr>';
    }
    async function loadKontrol() {
        const res = await api('/api/v1/bridging/vclaim/surat-kontrol');
        const rows = res.data || [];
        vclaimKontrolBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.no_surat) + '</td><td>' + esc(r.tgl_rencana) + '</td><td>' + esc(r.no_sep) + '</td><td>' + esc(r.nm_dokter_bpjs) + '</td><td>' + esc(r.nm_poli_bpjs) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada surat kontrol.</td></tr>';
    }
    async function loadHfis() {
        const res = await api('/api/v1/bridging/hfis/jadwal');
        const rows = res.data || [];
        hfisJadwalBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.nm_poli_bpjs || r.kd_poli_bpjs) + '</td><td>' + esc(r.nm_dokter_bpjs || r.kd_dokter_bpjs) + '</td><td>' + esc(r.nama_hari) + '</td><td>' + esc(r.jadwal) + '</td><td>' + esc(r.kapasitas_pasien) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada jadwal HFIS.</td></tr>';
    }
    async function loadAntrol() {
        const res = await api('/api/v1/bridging/antrol/tasks');
        const rows = res.data || [];
        antrolTaskBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.kodebooking) + '</td><td>' + esc(r.no_rawat) + '</td><td>Task ' + esc(r.task_id) + '</td><td>' + esc(r.status_sync) + '</td><td>' + esc(r.response_code) + ' ' + esc(r.response_msg) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada log antrol.</td></tr>';
    }
    async function loadMappings() {
        const res = await api('/api/v1/bridging/satusehat/mappings');
        const rows = res.data || [];
        ssMappingBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.entity_type) + '</td><td>' + esc(r.local_code) + '</td><td>' + esc(r.local_name) + '</td><td>' + esc(r.ihs_id) + '</td></tr>').join('') : '<tr><td colspan="4" class="text-center text-muted">Belum ada mapping IHS ID.</td></tr>';
    }

    formVclaimSep?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/vclaim/sep-save', { method: 'POST', body: JSON.stringify({ no_rawat: sepNoRawat.value, no_kartu: sepNoKartu.value, nama_pasien: sepNama.value, kdpolitujuan: sepKdPoli.value, nmpolitujuan: sepNmPoli.value, diagawal: sepDiag.value, nmdiagnosaawal: sepNmDiag.value, catatan: sepCatatan.value }) });
        if (res.status === 'success') loadSep();
    });
    formVclaimRujukan?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/vclaim/rujukan-save', { method: 'POST', body: JSON.stringify({ no_sep: rujNoSep.value, ppk_dirujuk: rujPpk.value, nm_ppk_dirujuk: rujNmPpk.value, diag_rujukan: rujDiag.value, catatan: rujCatatan.value }) });
        if (res.status === 'success') loadRujukan();
    });
    formVclaimKontrol?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/vclaim/surat-kontrol-save', { method: 'POST', body: JSON.stringify({ no_sep: skNoSep.value, tgl_rencana: skTglRencana.value, nm_dokter_bpjs: skNmDokter.value, nm_poli_bpjs: skNmPoli.value }) });
        if (res.status === 'success') loadKontrol();
    });
    formHfisSync?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/hfis/jadwal-sync', { method: 'POST', body: JSON.stringify({ kd_poli_bpjs: hfisKdPoli.value, nm_poli_bpjs: hfisNmPoli.value, kd_dokter_bpjs: hfisKdDokter.value, nm_dokter_bpjs: hfisNmDokter.value, nama_hari: hfisNamaHari.value, jadwal: hfisJadwal.value }) });
        if (res.status === 'success') loadHfis();
    });
    formAntrolPush?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/antrol/task-push', { method: 'POST', body: JSON.stringify({ kodebooking: antrolKodeBooking.value, no_rawat: antrolNoRawat.value, task_id: antrolTaskId.value }) });
        if (res.status === 'success') loadAntrol();
    });
    formSsMapping?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/satusehat/mappings-save', { method: 'POST', body: JSON.stringify({ entity_type: ssEntityType.value, local_code: ssLocalCode.value, local_name: ssLocalName.value, ihs_id: ssIhsId.value }) });
        if (res.status === 'success') loadMappings();
    });
    formSsFhir?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/bridging/satusehat/fhir-enqueue', { method: 'POST', body: JSON.stringify({ resource_type: ssFhirType.value, no_rawat: ssFhirNoRawat.value, note: ssFhirNote.value }) });
        if (res.status === 'success' && res.data) {
            ssFhirFeedback.innerHTML = '<span class="text-success">Berhasil masuk antrean FHIR: ' + esc(res.data.resource_type) + ' (' + esc(res.data.no_rawat) + ')</span>';
        }
    });

    btnReloadSep?.addEventListener('click', loadSep);
    btnReloadRujukan?.addEventListener('click', loadRujukan);
    btnReloadKontrol?.addEventListener('click', loadKontrol);
    loadSep(); loadRujukan(); loadKontrol(); loadHfis(); loadAntrol(); loadMappings();
});
</script>
