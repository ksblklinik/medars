<div class="container-fluid px-0" id="ranapWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="ranapSearchInput" class="form-control" placeholder="Cari No. Rawat / Pasien / Kamar...">
            </div>
            <button type="button" id="btnRanapSearch" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <div class="small text-muted">Rawat Inap &amp; Manajemen Bed / Kamar</div>
    </div>

    <div id="ranapAlertContainer" class="mt-2"></div>

    <ul class="nav nav-tabs mt-2 mb-3" id="ranapTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabInpatients" type="button">Pasien Rawat Inap</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKamar" type="button">Master Kamar &amp; Bangsal</button></li>
    </ul>

    <div class="tab-content">
        <!-- Tab 1: Pasien Rawat Inap -->
        <div class="tab-pane fade show active" id="tabInpatients">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Check-In Kamar Inap</div>
                        <div class="card-body">
                            <form id="formRanapCheckIn" class="row g-2">
                                <div class="col-12"><label class="form-label small mb-1">No. Rawat</label><input type="text" id="ranapNoRawat" class="form-control form-control-sm" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Pilih Kamar Kosong</label><select id="ranapKdKamar" class="form-select form-select-sm"></select></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Masuk Kamar (Check-In)</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>No. Rawat</th><th>Pasien</th><th>Kamar</th><th>Masuk</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyInpatients"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Master Kamar -->
        <div class="tab-pane fade" id="tabKamar">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Tambah / Update Kamar</div>
                        <div class="card-body">
                            <form id="formSaveKamar" class="row g-2">
                                <div class="col-6"><label class="form-label small mb-1">Kode Kamar</label><input type="text" id="kmrKode" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Kode Bangsal</label><input type="text" id="kmrBangsal" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Nama Bangsal</label><input type="text" id="kmrNmBangsal" class="form-control form-control-sm"></div>
                                <div class="col-6"><label class="form-label small mb-1">Kelas</label><input type="text" id="kmrKelas" class="form-control form-control-sm" value="Kelas 3"></div>
                                <div class="col-6"><label class="form-label small mb-1">Tarif / Hari (Rp)</label><input type="number" id="kmrTarif" class="form-control form-control-sm" value="150000" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">Status</label><select id="kmrStatus" class="form-select form-select-sm"><option value="KOSONG">KOSONG</option><option value="ISI">ISI</option></select></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Simpan Kamar</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode Kamar</th><th>Bangsal</th><th>Kelas</th><th class="text-end">Tarif / Hari</th><th>Status</th></tr></thead>
                                <tbody id="tbodyKamar"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/rawat-inap.js?v=20261117b"></script>
