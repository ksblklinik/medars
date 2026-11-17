<div class="container-fluid px-0" id="suratWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="suratSearchInput" class="form-control" placeholder="Cari No. Surat / Pasien...">
            </div>
            <button type="button" id="btnSuratSearch" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <div class="small text-muted">Surat Keterangan Medis &amp; Cetak</div>
    </div>

    <div id="suratAlertContainer" class="mt-2"></div>

    <ul class="nav nav-tabs mt-2 mb-3" id="suratTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSuratSakit" data-type="sakit" type="button">Surat Sakit</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSuratHamil" data-type="hamil" type="button">Surat Hamil</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSuratNarkoba" data-type="narkoba" type="button">Surat Bebas Narkoba</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRujukanInternal" data-type="rujukan" type="button">Rujukan / SEP Internal</button></li>
    </ul>

    <div class="tab-content">
        <!-- Tab 1: Surat Sakit -->
        <div class="tab-pane fade show active" id="tabSuratSakit">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Terbitkan Surat Sakit</div>
                        <div class="card-body">
                            <form id="formSuratSakit" class="row g-2">
                                <div class="col-12"><label class="form-label small mb-1">No. Rawat</label><input type="text" id="sakitNoRawat" class="form-control form-control-sm" placeholder="2026/11/17/000001" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Tgl Mulai</label><input type="date" id="sakitAwal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Tgl Selesai</label><input type="date" id="sakitAkhir" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Terbitkan Surat Sakit</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>No. Surat</th><th>No. Rawat</th><th>Pasien</th><th>Periode</th><th>Lama</th><th class="text-center">Aksi</th></tr></thead><tbody id="tbodySuratSakit"></tbody></table></div></div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Surat Hamil -->
        <div class="tab-pane fade" id="tabSuratHamil">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Terbitkan Surat Hamil</div>
                        <div class="card-body">
                            <form id="formSuratHamil" class="row g-2">
                                <div class="col-12"><label class="form-label small mb-1">No. Rawat</label><input type="text" id="hamilNoRawat" class="form-control form-control-sm" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Tgl Periksa</label><input type="date" id="hamilTgl" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Hasil Pemeriksaan</label><input type="text" id="hamilHasil" class="form-control form-control-sm" placeholder="Positif Hamil / Usia Kehamilan..." required></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Terbitkan Surat Hamil</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>No. Surat</th><th>No. Rawat</th><th>Pasien</th><th>Tgl Periksa</th><th>Hasil</th><th class="text-center">Aksi</th></tr></thead><tbody id="tbodySuratHamil"></tbody></table></div></div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Surat Bebas Narkoba -->
        <div class="tab-pane fade" id="tabSuratNarkoba">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Terbitkan Surat Bebas Narkoba</div>
                        <div class="card-body">
                            <form id="formSuratNarkoba" class="row g-2">
                                <div class="col-12"><label class="form-label small mb-1">No. Rawat</label><input type="text" id="nrkNoRawat" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Tanggal</label><input type="date" id="nrkTgl" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Kategori</label><input type="text" id="nrkKategori" class="form-control form-control-sm" value="Umum" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Keperluan</label><input type="text" id="nrkKeperluan" class="form-control form-control-sm" placeholder="Melamar Pekerjaan / Pendidikan" required></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Terbitkan Surat Narkoba</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>No. Surat</th><th>No. Rawat</th><th>Pasien</th><th>Tanggal</th><th>Keperluan</th><th class="text-center">Aksi</th></tr></thead><tbody id="tbodySuratNarkoba"></tbody></table></div></div>
                </div>
            </div>
        </div>

        <!-- Tab 4: Rujukan / SEP Internal -->
        <div class="tab-pane fade" id="tabRujukanInternal">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Buat Rujukan / SEP Internal</div>
                        <div class="card-body">
                            <form id="formRujukanInternal" class="row g-2">
                                <div class="col-12"><label class="form-label small mb-1">No. Rawat</label><input type="text" id="rujNoRawat" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Poli Tujuan</label><input type="text" id="rujPoli" class="form-control form-control-sm" placeholder="U02" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Dokter Tujuan</label><input type="text" id="rujDokter" class="form-control form-control-sm" placeholder="DR02" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Catatan Konsul</label><textarea id="rujCatatan" class="form-control form-control-sm" rows="2"></textarea></div>
                                <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Simpan Rujukan Internal</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0"><div class="card-body p-0 table-responsive"><table class="table table-sm table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>No. Rawat</th><th>Pasien</th><th>Poli Tujuan</th><th>Dokter Tujuan</th><th>Catatan</th></tr></thead><tbody id="tbodyRujukanInternal"></tbody></table></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/surat.js?v=20261117c"></script>
