<div class="container-fluid px-0" id="kepegawaianWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="sdmSearchInput" class="form-control" placeholder="Cari NIK / NIP / Nama...">
            </div>
            <button type="button" id="btnSdmSearch" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <button type="button" id="btnPrintSdm" class="btn btn-sm btn-outline-teal"><i class="fa fa-print me-1"></i>Cetak Daftar</button>
    </div>

    <div id="sdmAlertContainer" class="mt-2"></div>

    <ul class="nav nav-tabs mt-2 mb-3" id="sdmTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPegawai" data-entity="pegawai" type="button">Pegawai</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPetugas" data-entity="petugas" type="button">Petugas Medis</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabPegawai">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Pegawai</div>
                        <div class="card-body">
                            <form id="formPegawai" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">NIK</label><input type="text" id="pegNik" class="form-control form-control-sm" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama</label><input type="text" id="pegNama" class="form-control form-control-sm" required></div>
                                <div class="col-4"><label class="form-label small mb-1">JK</label><select id="pegJk" class="form-select form-select-sm"><option value="Pria">Pria</option><option value="Wanita">Wanita</option></select></div>
                                <div class="col-8"><label class="form-label small mb-1">Jabatan</label><input type="text" id="pegJbtn" class="form-control form-control-sm"></div>
                                <div class="col-7"><label class="form-label small mb-1">Departemen</label><input type="text" id="pegDept" class="form-control form-control-sm"></div>
                                <div class="col-5"><label class="form-label small mb-1">Status</label><select id="pegStatus" class="form-select form-select-sm"><option value="AKTIF">AKTIF</option><option value="NONAKTIF">NONAKTIF</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th>Status</th><th class="text-center">Aksi</th></tr></thead><tbody id="tbodyPegawai"></tbody></table></div></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabPetugas">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Petugas</div>
                        <div class="card-body">
                            <form id="formPetugas" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">NIP</label><input type="text" id="ptgNip" class="form-control form-control-sm" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama</label><input type="text" id="ptgNama" class="form-control form-control-sm" required></div>
                                <div class="col-4"><label class="form-label small mb-1">JK</label><select id="ptgJk" class="form-select form-select-sm"><option value="L">L</option><option value="P">P</option></select></div>
                                <div class="col-8"><label class="form-label small mb-1">Jabatan</label><input type="text" id="ptgJbtn" class="form-control form-control-sm"></div>
                                <div class="col-7"><label class="form-label small mb-1">No. Telp</label><input type="text" id="ptgTelp" class="form-control form-control-sm"></div>
                                <div class="col-5"><label class="form-label small mb-1">Status</label><select id="ptgStatus" class="form-select form-select-sm"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>NIP</th><th>Nama</th><th>Jabatan</th><th>No. Telp</th><th>Status</th><th class="text-center">Aksi</th></tr></thead><tbody id="tbodyPetugas"></tbody></table></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/kepegawaian.js?v=20261117b"></script>
