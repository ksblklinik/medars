<div class="container-fluid px-0" id="keuanganWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white text-muted">Dari</span>
                <input type="date" id="keuFilterFrom" class="form-control" value="<?= date('Y-m-d') ?>">
                <span class="input-group-text bg-white text-muted">s/d</span>
                <input type="date" id="keuFilterTo" class="form-control" value="<?= date('Y-m-d') ?>">
                <button type="button" id="btnKeuFilter" class="btn btn-outline-teal">Tampilkan</button>
            </div>
        </div>
        <div class="small text-muted">Akuntansi Double-Entry (`mlite_jurnal`)</div>
    </div>

    <div id="keuAlertContainer" class="mt-2"></div>

    <!-- Ringkasan Laba Rugi -->
    <div class="row g-2 mt-1 mb-3 text-center">
        <div class="col-md-4"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Total Pendapatan (4xxx)</small><h5 class="m-0 fw-bold text-success" id="keuPendapatan">Rp 0</h5></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Total Beban (5xxx-6xxx)</small><h5 class="m-0 fw-bold text-danger" id="keuBeban">Rp 0</h5></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-2"><small class="text-muted">Laba / Rugi Bersih</small><h5 class="m-0 fw-bold text-medical-teal" id="keuLaba">Rp 0</h5></div></div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabJurnal" type="button">Jurnal Umum &amp; Kas</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBukuBesar" type="button">Buku Besar &amp; Neraca Saldo</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCoa" type="button">Master Akun (COA)</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabJurnal">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Posting Jurnal / Pengeluaran Kas</div>
                        <div class="card-body">
                            <form id="formPostJurnal" class="row g-2">
                                <div class="col-12"><label class="form-label small mb-1">No. Bukti</label><input type="text" id="jrnBukti" class="form-control form-control-sm" placeholder="BKK-001" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Akun Debet</label><input type="text" id="jrnDebet" class="form-control form-control-sm" placeholder="5101.01" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Akun Kredit</label><input type="text" id="jrnKredit" class="form-control form-control-sm" placeholder="1101.01" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Nominal (Rp)</label><input type="number" id="jrnNominal" class="form-control form-control-sm" min="1" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Keterangan</label><input type="text" id="jrnKet" class="form-control form-control-sm" required></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Posting Jurnal</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>No. Jurnal</th><th>Tanggal</th><th>Akun</th><th>Keterangan</th><th class="text-end">Debet</th><th class="text-end">Kredit</th></tr></thead><tbody id="tbodyJurnal"></tbody></table></div></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabBukuBesar">
            <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>Kode Akun</th><th>Nama Rekening</th><th>Tipe</th><th class="text-end">Total Debet</th><th class="text-end">Total Kredit</th></tr></thead><tbody id="tbodyBukuBesar"></tbody></table></div></div>
        </div>

        <div class="tab-pane fade" id="tabCoa">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Tambah / Update COA</div>
                        <div class="card-body">
                            <form id="formSaveCoa" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">Kode Rek</label><input type="text" id="coaKd" class="form-control form-control-sm" placeholder="5101.01" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama Rekening</label><input type="text" id="coaNm" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Tipe</label><select id="coaTipe" class="form-select form-select-sm"><option>Aset</option><option>Kewajiban</option><option>Modal</option><option>Pendapatan</option><option>Beban</option></select></div>
                                <div class="col-6"><label class="form-label small mb-1">Normal</label><select id="coaBal" class="form-select form-select-sm"><option value="D">Debet (D)</option><option value="K">Kredit (K)</option></select></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Simpan Akun</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>Kode</th><th>Nama Rekening</th><th>Tipe</th><th>Normal</th></tr></thead><tbody id="tbodyCoa"></tbody></table></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/keuangan.js?v=20261117b"></script>
