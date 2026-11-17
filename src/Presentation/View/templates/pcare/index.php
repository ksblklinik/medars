<div class="row g-3" id="pcarePage">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-hospital-user me-1"></i> Bridging FKTP PCare BPJS (Pendaftaran, Kunjungan, Obat, Lab/Tindakan &amp; Rujukan)</h6>
                <span class="badge bg-light text-dark border">FKTP PCare</span>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pcTabPendaftaran" type="button">1. Pendaftaran FKTP</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pcTabKunjungan" type="button">2. Kunjungan</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pcTabObat" type="button">3. Obat DPHO</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pcTabTindakan" type="button">4. Lab &amp; Tindakan</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pcTabRujukan" type="button">5. Rujukan FKTP</button></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pcTabPendaftaran">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formPcPendaftaran" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. Rawat</label><input type="text" id="pcPdfRawat" class="form-control form-control-sm" placeholder="2026/11/17/000001"></div>
                                    <div class="col-12"><label class="form-label small mb-0">No. Kartu BPJS *</label><input type="text" id="pcPdfKartu" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Peserta</label><input type="text" id="pcPdfNama" class="form-control form-control-sm"></div>
                                    <div class="col-4"><label class="form-label small mb-0">Kd Poli</label><input type="text" id="pcPdfKdPoli" class="form-control form-control-sm" value="001"></div>
                                    <div class="col-8"><label class="form-label small mb-0">Nama Poli</label><input type="text" id="pcPdfNmPoli" class="form-control form-control-sm" value="Poli Umum"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Keluhan</label><input type="text" id="pcPdfKeluhan" class="form-control form-control-sm"></div>
                                    <div class="col-6"><label class="form-label small mb-0">Sistole</label><input type="number" id="pcPdfSis" class="form-control form-control-sm" value="120"></div>
                                    <div class="col-6"><label class="form-label small mb-0">Diastole</label><input type="number" id="pcPdfDia" class="form-control form-control-sm" value="80"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Kirim Pendaftaran PCare</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcPdf" class="btn btn-outline-secondary btn-sm mb-2">Muat Pendaftaran</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. Urut</th><th>Tgl</th><th>No. Kartu</th><th>Peserta</th><th>Poli</th><th>Status</th></tr></thead>
                                        <tbody id="pcPdfBody"><tr><td colspan="6" class="text-center text-muted">Belum ada pendaftaran PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pcTabKunjungan">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formPcKunjungan" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. Rawat</label><input type="text" id="pcKnjRawat" class="form-control form-control-sm"></div>
                                    <div class="col-12"><label class="form-label small mb-0">No. Kartu BPJS *</label><input type="text" id="pcKnjKartu" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Peserta</label><input type="text" id="pcKnjNama" class="form-control form-control-sm"></div>
                                    <div class="col-4"><label class="form-label small mb-0">ICD-10 *</label><input type="text" id="pcKnjDiag" class="form-control form-control-sm" placeholder="J00" required></div>
                                    <div class="col-8"><label class="form-label small mb-0">Nama Diagnosa</label><input type="text" id="pcKnjNmDiag" class="form-control form-control-sm"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Status Pulang</label><input type="text" id="pcKnjPulang" class="form-control form-control-sm" value="Berobat Jalan"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Dokter</label><input type="text" id="pcKnjDokter" class="form-control form-control-sm"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Simpan Kunjungan PCare</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcKnj" class="btn btn-outline-secondary btn-sm mb-2">Muat Kunjungan</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. Kunjungan</th><th>Tgl</th><th>Peserta</th><th>Diagnosa</th><th>Status Pulang</th></tr></thead>
                                        <tbody id="pcKnjBody"><tr><td colspan="5" class="text-center text-muted">Belum ada kunjungan PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pcTabObat">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formPcObat" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. Kunjungan PCare *</label><input type="text" id="pcObtKunjungan" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Kode Obat DPHO</label><input type="text" id="pcObtKode" class="form-control form-control-sm"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Obat *</label><input type="text" id="pcObtNama" class="form-control form-control-sm" required></div>
                                    <div class="col-4"><label class="form-label small mb-0">Signa 1</label><input type="number" id="pcObtS1" class="form-control form-control-sm" value="3"></div>
                                    <div class="col-4"><label class="form-label small mb-0">Signa 2</label><input type="number" id="pcObtS2" class="form-control form-control-sm" value="1"></div>
                                    <div class="col-4"><label class="form-label small mb-0">Jumlah</label><input type="number" id="pcObtJml" class="form-control form-control-sm" value="10"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Tambah Obat PCare</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcObt" class="btn btn-outline-secondary btn-sm mb-2">Muat Obat</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. Kunjungan</th><th>Kd SK</th><th>Nama Obat</th><th>Signa</th><th>Jumlah</th></tr></thead>
                                        <tbody id="pcObtBody"><tr><td colspan="5" class="text-center text-muted">Belum ada obat PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pcTabTindakan">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formPcTindakan" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. Kunjungan PCare *</label><input type="text" id="pcTdkKunjungan" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Kode Tindakan / Lab</label><input type="text" id="pcTdkKode" class="form-control form-control-sm" placeholder="LAB-01"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Tindakan / Lab *</label><input type="text" id="pcTdkNama" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Biaya</label><input type="number" id="pcTdkBiaya" class="form-control form-control-sm" value="0"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Keterangan</label><input type="text" id="pcTdkKet" class="form-control form-control-sm"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Tambah Lab / Tindakan</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcTdk" class="btn btn-outline-secondary btn-sm mb-2">Muat Tindakan / Lab</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. Kunjungan</th><th>Kd SK</th><th>Nama Tindakan / Lab</th><th>Biaya</th><th>Keterangan</th></tr></thead>
                                        <tbody id="pcTdkBody"><tr><td colspan="5" class="text-center text-muted">Belum ada tindakan/lab PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pcTabRujukan">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <form id="formPcRujukan" class="row g-2">
                                    <div class="col-12"><label class="form-label small mb-0">No. Kunjungan PCare *</label><input type="text" id="pcRujKunjungan" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Kode Faskes Rujukan</label><input type="text" id="pcRujKdPpk" class="form-control form-control-sm" placeholder="0112R002"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Nama Faskes Rujukan *</label><input type="text" id="pcRujNmPpk" class="form-control form-control-sm" required></div>
                                    <div class="col-12"><label class="form-label small mb-0">Sub Spesialis</label><input type="text" id="pcRujSub" class="form-control form-control-sm" placeholder="Penyakit Dalam"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Tgl Est. Rujuk</label><input type="date" id="pcRujTgl" class="form-control form-control-sm"></div>
                                    <div class="col-12"><label class="form-label small mb-0">Catatan</label><input type="text" id="pcRujCat" class="form-control form-control-sm"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm">Buat Rujukan FKTP</button></div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcRuj" class="btn btn-outline-secondary btn-sm mb-2">Muat Rujukan</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" style="font-size:12px;">
                                        <thead class="table-light"><tr><th>No. Kunjungan</th><th>Tgl Rujuk</th><th>Faskes Tujuan</th><th>Sub Spesialis</th><th>Aksi</th></tr></thead>
                                        <tbody id="pcRujBody"><tr><td colspan="5" class="text-center text-muted">Belum ada rujukan FKTP.</td></tr></tbody>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const api = window.MedarsApp && window.MedarsApp.apiJson ? window.MedarsApp.apiJson : null;
    if (!api) return;

    async function loadPdf() {
        const res = await api('/api/v1/pcare/pendaftaran');
        const rows = res.data || [];
        pcPdfBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noUrut) + '</td><td>' + esc(r.tglDaftar) + '</td><td>' + esc(r.noKartu) + '</td><td>' + esc(r.nmPasien) + '</td><td>' + esc(r.nmPoli) + '</td><td>' + esc(r.status) + '</td></tr>').join('') : '<tr><td colspan="6" class="text-center text-muted">Belum ada pendaftaran PCare.</td></tr>';
    }
    async function loadKnj() {
        const res = await api('/api/v1/pcare/kunjungan');
        const rows = res.data || [];
        pcKnjBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.tglDaftar) + '</td><td>' + esc(r.nmPasien) + ' (' + esc(r.noKartu) + ')</td><td>' + esc(r.kdDiagnosa1) + '</td><td>' + esc(r.nmStatusPulang) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada kunjungan PCare.</td></tr>';
    }
    async function loadObt() {
        const res = await api('/api/v1/pcare/obat');
        const rows = res.data || [];
        pcObtBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.kdObatSK) + '</td><td>' + esc(r.nmObat) + '</td><td>' + esc(r.signa1) + 'x' + esc(r.signa2) + '</td><td>' + esc(r.jmlObat) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada obat PCare.</td></tr>';
    }
    async function loadTdk() {
        const res = await api('/api/v1/pcare/tindakan');
        const rows = res.data || [];
        pcTdkBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.kdTindakanSK) + '</td><td>' + esc(r.nmTindakan) + '</td><td>' + esc(r.biaya) + '</td><td>' + esc(r.keterangan) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada tindakan/lab PCare.</td></tr>';
    }
    async function loadRuj() {
        const res = await api('/api/v1/pcare/rujukan');
        const rows = res.data || [];
        pcRujBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.tglEstRujuk) + '</td><td>' + esc(r.nmppk) + '</td><td>' + esc(r.nmSubSpesialis) + '</td><td><a class="btn btn-xs btn-info" target="_blank" href="/pcare/rujukan/cetak?no_kunjungan=' + encodeURIComponent(r.noKunjungan) + '">Cetak</a></td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada rujukan FKTP.</td></tr>';
    }

    formPcPendaftaran?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/pendaftaran-save', { method: 'POST', body: JSON.stringify({ no_rawat: pcPdfRawat.value, noKartu: pcPdfKartu.value, nmPasien: pcPdfNama.value, kdPoli: pcPdfKdPoli.value, nmPoli: pcPdfNmPoli.value, keluhan: pcPdfKeluhan.value, sistole: pcPdfSis.value, diastole: pcPdfDia.value }) });
        if (res.status === 'success') loadPdf();
    });
    formPcKunjungan?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/kunjungan-save', { method: 'POST', body: JSON.stringify({ no_rawat: pcKnjRawat.value, noKartu: pcKnjKartu.value, nmPasien: pcKnjNama.value, kdDiagnosa1: pcKnjDiag.value, nmDiagnosa1: pcKnjNmDiag.value, nmStatusPulang: pcKnjPulang.value, nmDokter: pcKnjDokter.value }) });
        if (res.status === 'success') loadKnj();
    });
    formPcObat?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/obat-save', { method: 'POST', body: JSON.stringify({ noKunjungan: pcObtKunjungan.value, kdObat: pcObtKode.value, nmObat: pcObtNama.value, signa1: pcObtS1.value, signa2: pcObtS2.value, jmlObat: pcObtJml.value }) });
        if (res.status === 'success') loadObt();
    });
    formPcTindakan?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/tindakan-save', { method: 'POST', body: JSON.stringify({ noKunjungan: pcTdkKunjungan.value, kdTindakan: pcTdkKode.value, nmTindakan: pcTdkNama.value, biaya: pcTdkBiaya.value, keterangan: pcTdkKet.value }) });
        if (res.status === 'success') loadTdk();
    });
    formPcRujukan?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/rujukan-save', { method: 'POST', body: JSON.stringify({ noKunjungan: pcRujKunjungan.value, kdppk: pcRujKdPpk.value, nmppk: pcRujNmPpk.value, nmSubSpesialis: pcRujSub.value, tglEstRujuk: pcRujTgl.value, catatan: pcRujCat.value }) });
        if (res.status === 'success') loadRuj();
    });

    btnReloadPcPdf?.addEventListener('click', loadPdf);
    btnReloadPcKnj?.addEventListener('click', loadKnj);
    btnReloadPcObt?.addEventListener('click', loadObt);
    btnReloadPcTdk?.addEventListener('click', loadTdk);
    btnReloadPcRuj?.addEventListener('click', loadRuj);
    loadPdf(); loadKnj(); loadObt(); loadTdk(); loadRuj();
});
</script>
