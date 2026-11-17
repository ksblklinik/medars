<div class="row" id="pcarePage">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading" style="background:#00897b;color:#fff;font-weight:bold;">
                Bridging FKTP PCare BPJS (Pendaftaran, Kunjungan, Obat, Lab/Tindakan &amp; Rujukan FKTP)
            </div>
            <div class="panel-body">
                <ul class="nav nav-tabs" style="margin-bottom:14px;">
                    <li class="active"><a href="#pcTabPendaftaran" data-toggle="tab">1. Pendaftaran FKTP</a></li>
                    <li><a href="#pcTabKunjungan" data-toggle="tab">2. Kunjungan</a></li>
                    <li><a href="#pcTabObat" data-toggle="tab">3. Obat DPHO</a></li>
                    <li><a href="#pcTabTindakan" data-toggle="tab">4. Lab &amp; Tindakan</a></li>
                    <li><a href="#pcTabRujukan" data-toggle="tab">5. Rujukan FKTP</a></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane active" id="pcTabPendaftaran">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formPcPendaftaran">
                                    <div class="form-group"><label>No. Rawat</label><input type="text" id="pcPdfRawat" class="form-control input-sm" placeholder="2026/11/17/000001"></div>
                                    <div class="form-group"><label>No. Kartu BPJS *</label><input type="text" id="pcPdfKartu" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Nama Peserta</label><input type="text" id="pcPdfNama" class="form-control input-sm"></div>
                                    <div class="row">
                                        <div class="col-xs-4 form-group"><label>Kd Poli</label><input type="text" id="pcPdfKdPoli" class="form-control input-sm" value="001"></div>
                                        <div class="col-xs-8 form-group"><label>Nama Poli</label><input type="text" id="pcPdfNmPoli" class="form-control input-sm" value="Poli Umum"></div>
                                    </div>
                                    <div class="form-group"><label>Keluhan</label><input type="text" id="pcPdfKeluhan" class="form-control input-sm"></div>
                                    <div class="row">
                                        <div class="col-xs-6 form-group"><label>Sistole</label><input type="number" id="pcPdfSis" class="form-control input-sm" value="120"></div>
                                        <div class="col-xs-6 form-group"><label>Diastole</label><input type="number" id="pcPdfDia" class="form-control input-sm" value="80"></div>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm">Kirim Pendaftaran PCare</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcPdf" class="btn btn-default btn-sm" style="margin-bottom:8px;">Muat Pendaftaran</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. Urut</th><th>Tgl</th><th>No. Kartu</th><th>Peserta</th><th>Poli</th><th>Status</th></tr></thead>
                                        <tbody id="pcPdfBody"><tr><td colspan="6" class="text-center text-muted">Belum ada pendaftaran PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="pcTabKunjungan">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formPcKunjungan">
                                    <div class="form-group"><label>No. Rawat</label><input type="text" id="pcKnjRawat" class="form-control input-sm"></div>
                                    <div class="form-group"><label>No. Kartu BPJS *</label><input type="text" id="pcKnjKartu" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Nama Peserta</label><input type="text" id="pcKnjNama" class="form-control input-sm"></div>
                                    <div class="row">
                                        <div class="col-xs-4 form-group"><label>ICD-10 *</label><input type="text" id="pcKnjDiag" class="form-control input-sm" placeholder="J00" required></div>
                                        <div class="col-xs-8 form-group"><label>Nama Diagnosa</label><input type="text" id="pcKnjNmDiag" class="form-control input-sm"></div>
                                    </div>
                                    <div class="form-group"><label>Status Pulang</label><input type="text" id="pcKnjPulang" class="form-control input-sm" value="Berobat Jalan"></div>
                                    <div class="form-group"><label>Nama Dokter</label><input type="text" id="pcKnjDokter" class="form-control input-sm"></div>
                                    <button type="submit" class="btn btn-success btn-sm">Simpan Kunjungan PCare</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcKnj" class="btn btn-default btn-sm" style="margin-bottom:8px;">Muat Kunjungan</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. Kunjungan</th><th>Tgl</th><th>Peserta</th><th>Diagnosa</th><th>Status Pulang</th></tr></thead>
                                        <tbody id="pcKnjBody"><tr><td colspan="5" class="text-center text-muted">Belum ada kunjungan PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="pcTabObat">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formPcObat">
                                    <div class="form-group"><label>No. Kunjungan PCare *</label><input type="text" id="pcObtKunjungan" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Kode Obat DPHO</label><input type="text" id="pcObtKode" class="form-control input-sm"></div>
                                    <div class="form-group"><label>Nama Obat *</label><input type="text" id="pcObtNama" class="form-control input-sm" required></div>
                                    <div class="row">
                                        <div class="col-xs-4 form-group"><label>Signa 1</label><input type="number" id="pcObtS1" class="form-control input-sm" value="3"></div>
                                        <div class="col-xs-4 form-group"><label>Signa 2</label><input type="number" id="pcObtS2" class="form-control input-sm" value="1"></div>
                                        <div class="col-xs-4 form-group"><label>Jumlah</label><input type="number" id="pcObtJml" class="form-control input-sm" value="10"></div>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm">Tambah Obat PCare</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcObt" class="btn btn-default btn-sm" style="margin-bottom:8px;">Muat Obat</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. Kunjungan</th><th>Kd SK</th><th>Nama Obat</th><th>Signa</th><th>Jumlah</th></tr></thead>
                                        <tbody id="pcObtBody"><tr><td colspan="5" class="text-center text-muted">Belum ada obat PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="pcTabTindakan">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formPcTindakan">
                                    <div class="form-group"><label>No. Kunjungan PCare *</label><input type="text" id="pcTdkKunjungan" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Kode Tindakan / Lab</label><input type="text" id="pcTdkKode" class="form-control input-sm" placeholder="LAB-01"></div>
                                    <div class="form-group"><label>Nama Tindakan / Lab *</label><input type="text" id="pcTdkNama" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Biaya</label><input type="number" id="pcTdkBiaya" class="form-control input-sm" value="0"></div>
                                    <div class="form-group"><label>Keterangan</label><input type="text" id="pcTdkKet" class="form-control input-sm"></div>
                                    <button type="submit" class="btn btn-success btn-sm">Tambah Lab / Tindakan</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcTdk" class="btn btn-default btn-sm" style="margin-bottom:8px;">Muat Tindakan / Lab</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. Kunjungan</th><th>Kd SK</th><th>Nama Tindakan / Lab</th><th>Biaya</th><th>Keterangan</th></tr></thead>
                                        <tbody id="pcTdkBody"><tr><td colspan="5" class="text-center text-muted">Belum ada tindakan/lab PCare.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="pcTabRujukan">
                        <div class="row">
                            <div class="col-md-4">
                                <form id="formPcRujukan">
                                    <div class="form-group"><label>No. Kunjungan PCare *</label><input type="text" id="pcRujKunjungan" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Kode Faskes Rujukan</label><input type="text" id="pcRujKdPpk" class="form-control input-sm" placeholder="0112R002"></div>
                                    <div class="form-group"><label>Nama Faskes Rujukan *</label><input type="text" id="pcRujNmPpk" class="form-control input-sm" required></div>
                                    <div class="form-group"><label>Sub Spesialis</label><input type="text" id="pcRujSub" class="form-control input-sm" placeholder="Penyakit Dalam"></div>
                                    <div class="form-group"><label>Tgl Est. Rujuk</label><input type="date" id="pcRujTgl" class="form-control input-sm"></div>
                                    <div class="form-group"><label>Catatan</label><input type="text" id="pcRujCat" class="form-control input-sm"></div>
                                    <button type="submit" class="btn btn-success btn-sm">Buat Rujukan FKTP</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <button type="button" id="btnReloadPcRuj" class="btn btn-default btn-sm" style="margin-bottom:8px;">Muat Rujukan</button>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-condensed" style="font-size:12px;">
                                        <thead><tr><th>No. Kunjungan</th><th>Tgl Rujuk</th><th>Faskes Tujuan</th><th>Sub Spesialis</th><th>Aksi</th></tr></thead>
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
    const api = window.MedarsApp && window.MedarsApp.apiFetch ? window.MedarsApp.apiFetch : null;
    if (!api) return;

    async function loadPdf() {
        const res = await api('/api/v1/pcare/pendaftaran');
        const rows = res.data || [];
        document.getElementById('pcPdfBody').innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noUrut) + '</td><td>' + esc(r.tglDaftar) + '</td><td>' + esc(r.noKartu) + '</td><td>' + esc(r.nmPasien) + '</td><td>' + esc(r.nmPoli) + '</td><td>' + esc(r.status) + '</td></tr>').join('') : '<tr><td colspan="6" class="text-center text-muted">Belum ada pendaftaran PCare.</td></tr>';
    }
    async function loadKnj() {
        const res = await api('/api/v1/pcare/kunjungan');
        const rows = res.data || [];
        document.getElementById('pcKnjBody').innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.tglDaftar) + '</td><td>' + esc(r.nmPasien) + ' (' + esc(r.noKartu) + ')</td><td>' + esc(r.kdDiagnosa1) + '</td><td>' + esc(r.nmStatusPulang) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada kunjungan PCare.</td></tr>';
    }
    async function loadObt() {
        const res = await api('/api/v1/pcare/obat');
        const rows = res.data || [];
        document.getElementById('pcObtBody').innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.kdObatSK) + '</td><td>' + esc(r.nmObat) + '</td><td>' + esc(r.signa1) + 'x' + esc(r.signa2) + '</td><td>' + esc(r.jmlObat) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada obat PCare.</td></tr>';
    }
    async function loadTdk() {
        const res = await api('/api/v1/pcare/tindakan');
        const rows = res.data || [];
        document.getElementById('pcTdkBody').innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.kdTindakanSK) + '</td><td>' + esc(r.nmTindakan) + '</td><td>' + esc(r.biaya) + '</td><td>' + esc(r.keterangan) + '</td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada tindakan/lab PCare.</td></tr>';
    }
    async function loadRuj() {
        const res = await api('/api/v1/pcare/rujukan');
        const rows = res.data || [];
        document.getElementById('pcRujBody').innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.noKunjungan) + '</td><td>' + esc(r.tglEstRujuk) + '</td><td>' + esc(r.nmppk) + '</td><td>' + esc(r.nmSubSpesialis) + '</td><td><a class="btn btn-xs btn-info" target="_blank" href="/pcare/rujukan/cetak?no_kunjungan=' + encodeURIComponent(r.noKunjungan) + '">Cetak</a></td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted">Belum ada rujukan FKTP.</td></tr>';
    }

    document.getElementById('formPcPendaftaran')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/pendaftaran-save', { method: 'POST', body: JSON.stringify({ no_rawat: pcPdfRawat.value, noKartu: pcPdfKartu.value, nmPasien: pcPdfNama.value, kdPoli: pcPdfKdPoli.value, nmPoli: pcPdfNmPoli.value, keluhan: pcPdfKeluhan.value, sistole: pcPdfSis.value, diastole: pcPdfDia.value }) });
        if (res.status === 'success') loadPdf();
    });
    document.getElementById('formPcKunjungan')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/kunjungan-save', { method: 'POST', body: JSON.stringify({ no_rawat: pcKnjRawat.value, noKartu: pcKnjKartu.value, nmPasien: pcKnjNama.value, kdDiagnosa1: pcKnjDiag.value, nmDiagnosa1: pcKnjNmDiag.value, nmStatusPulang: pcKnjPulang.value, nmDokter: pcKnjDokter.value }) });
        if (res.status === 'success') loadKnj();
    });
    document.getElementById('formPcObat')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/obat-save', { method: 'POST', body: JSON.stringify({ noKunjungan: pcObtKunjungan.value, kdObat: pcObtKode.value, nmObat: pcObtNama.value, signa1: pcObtS1.value, signa2: pcObtS2.value, jmlObat: pcObtJml.value }) });
        if (res.status === 'success') loadObt();
    });
    document.getElementById('formPcTindakan')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/tindakan-save', { method: 'POST', body: JSON.stringify({ noKunjungan: pcTdkKunjungan.value, kdTindakan: pcTdkKode.value, nmTindakan: pcTdkNama.value, biaya: pcTdkBiaya.value, keterangan: pcTdkKet.value }) });
        if (res.status === 'success') loadTdk();
    });
    document.getElementById('formPcRujukan')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/pcare/rujukan-save', { method: 'POST', body: JSON.stringify({ noKunjungan: pcRujKunjungan.value, kdppk: pcRujKdPpk.value, nmppk: pcRujNmPpk.value, nmSubSpesialis: pcRujSub.value, tglEstRujuk: pcRujTgl.value, catatan: pcRujCat.value }) });
        if (res.status === 'success') loadRuj();
    });

    document.getElementById('btnReloadPcPdf')?.addEventListener('click', loadPdf);
    document.getElementById('btnReloadPcKnj')?.addEventListener('click', loadKnj);
    document.getElementById('btnReloadPcObt')?.addEventListener('click', loadObt);
    document.getElementById('btnReloadPcTdk')?.addEventListener('click', loadTdk);
    document.getElementById('btnReloadPcRuj')?.addEventListener('click', loadRuj);
    loadPdf(); loadKnj(); loadObt(); loadTdk(); loadRuj();
});
</script>
