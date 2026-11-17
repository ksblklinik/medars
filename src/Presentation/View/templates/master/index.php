<div class="container-fluid px-0" id="masterWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="masterSearchInput" class="form-control" placeholder="Cari kode / nama...">
            </div>
            <button type="button" id="btnMasterSearch" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <div class="small text-muted">Master Data Klinik &amp; Tarif Layanan</div>
    </div>

    <div id="masterAlertContainer" class="mt-2"></div>

    <ul class="nav nav-tabs mt-2 mb-3" id="masterTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPoli" data-entity="poliklinik" type="button">Poliklinik</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDokter" data-entity="dokter" type="button">Dokter</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPenjab" data-entity="penjab" type="button">Penjamin (Cara Bayar)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTarif" data-entity="jns_perawatan" type="button">Tarif Tindakan</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabJadwal" data-entity="jadwal" type="button">Jadwal Dokter</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBangsal" data-entity="bangsal" type="button">Bangsal</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKamar" data-entity="kamar" type="button">Kamar Inap</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPenyakit" data-entity="penyakit" type="button">Penyakit (ICD-10)</button></li>
    </ul>

    <div class="tab-content">
        <!-- Tab 1: Poliklinik -->
        <div class="tab-pane fade show active" id="tabPoli">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Poliklinik</div>
                        <div class="card-body">
                            <form id="formPoli" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">Kode Poli</label><input type="text" id="poliKd" class="form-control form-control-sm" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama Poliklinik</label><input type="text" id="poliNm" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Tarif Reg Baru</label><input type="number" id="poliReg" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">Tarif Reg Lama</label><input type="number" id="poliRegLama" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">Status</label><select id="poliStatus" class="form-select form-select-sm"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                                <div class="col-6"><label class="form-label small mb-1">Terima BPJS</label><select id="poliBpjs" class="form-select form-select-sm"><option value="1">Ya</option><option value="0">Tidak</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Poliklinik</th><th class="text-end">Reg Baru</th><th class="text-end">Reg Lama</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyPoli"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Dokter -->
        <div class="tab-pane fade" id="tabDokter">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Dokter</div>
                        <div class="card-body">
                            <form id="formDokter" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">Kode Dokter</label><input type="text" id="dokterKd" class="form-control form-control-sm" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama Dokter</label><input type="text" id="dokterNm" class="form-control form-control-sm" required></div>
                                <div class="col-4"><label class="form-label small mb-1">JK</label><select id="dokterJk" class="form-select form-select-sm"><option value="L">L</option><option value="P">P</option></select></div>
                                <div class="col-8"><label class="form-label small mb-1">No. SIP</label><input type="text" id="dokterSip" class="form-control form-control-sm"></div>
                                <div class="col-6"><label class="form-label small mb-1">No. Telp</label><input type="text" id="dokterTelp" class="form-control form-control-sm"></div>
                                <div class="col-6"><label class="form-label small mb-1">Status</label><select id="dokterStatus" class="form-select form-select-sm"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Dokter</th><th>JK</th><th>SIP</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyDokter"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Penjamin -->
        <div class="tab-pane fade" id="tabPenjab">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Penjamin</div>
                        <div class="card-body">
                            <form id="formPenjab" class="row g-2">
                                <div class="col-4"><label class="form-label small mb-1">Kode PJ</label><input type="text" id="penjabKd" class="form-control form-control-sm" required></div>
                                <div class="col-8"><label class="form-label small mb-1">Nama Penjamin</label><input type="text" id="penjabNm" class="form-control form-control-sm" required></div>
                                <div class="col-8"><label class="form-label small mb-1">Perusahaan</label><input type="text" id="penjabPerusahaan" class="form-control form-control-sm"></div>
                                <div class="col-4"><label class="form-label small mb-1">Status</label><select id="penjabStatus" class="form-select form-select-sm"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Penjamin</th><th>Perusahaan</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyPenjab"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: Tarif Tindakan -->
        <div class="tab-pane fade" id="tabTarif">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Tarif Tindakan</div>
                        <div class="card-body">
                            <form id="formTarif" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">Kode Tarif</label><input type="text" id="tarifKd" class="form-control form-control-sm" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama Tindakan</label><input type="text" id="tarifNm" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Material / BHP</label><input type="number" id="tarifMaterial" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">Jasa Dokter</label><input type="number" id="tarifDr" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">Total Tarif</label><input type="number" id="tarifTotal" class="form-control form-control-sm" value="0" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-1">Status</label><select id="tarifStatus" class="form-select form-select-sm"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Tindakan</th><th class="text-end">Total (Rp)</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyTarif"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 5: Jadwal Dokter -->
        <div class="tab-pane fade" id="tabJadwal">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Jadwal Dokter</div>
                        <div class="card-body">
                            <form id="formJadwal" class="row g-2">
                                <div class="col-6"><label class="form-label small mb-1">Kode Dokter</label><input type="text" id="jadwalDokter" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Kode Poli</label><input type="text" id="jadwalPoli" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Hari</label><select id="jadwalHari" class="form-select form-select-sm"><option>SENIN</option><option>SELASA</option><option>RABU</option><option>KAMIS</option><option>JUMAT</option><option>SABTU</option><option>MINGGU</option></select></div>
                                <div class="col-6"><label class="form-label small mb-1">Kuota</label><input type="number" id="jadwalKuota" class="form-control form-control-sm" value="20" min="1"></div>
                                <div class="col-6"><label class="form-label small mb-1">Jam Mulai</label><input type="text" id="jadwalMulai" class="form-control form-control-sm" value="08:00:00" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Jam Selesai</label><input type="text" id="jadwalSelesai" class="form-control form-control-sm" value="12:00:00" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Prefik</label><input type="text" id="jadwalPrefik" class="form-control form-control-sm" placeholder="A"></div>
                                <div class="col-6"><label class="form-label small mb-1">Ruangan</label><input type="text" id="jadwalRoom" class="form-control form-control-sm" placeholder="Poli 1"></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Dokter</th><th>Poli</th><th>Hari</th><th>Jam</th><th>Kuota</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyJadwal"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 6: Bangsal -->
        <div class="tab-pane fade" id="tabBangsal">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Bangsal</div>
                        <div class="card-body">
                            <form id="formBangsal" class="row g-2">
                                <div class="col-5"><label class="form-label small mb-1">Kode Bangsal</label><input type="text" id="bangsalKd" class="form-control form-control-sm" required></div>
                                <div class="col-7"><label class="form-label small mb-1">Nama Bangsal</label><input type="text" id="bangsalNm" class="form-control form-control-sm" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Status</label><select id="bangsalStatus" class="form-select form-select-sm"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Bangsal</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyBangsal"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 7: Kamar Inap -->
        <div class="tab-pane fade" id="tabKamar">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Kamar Inap</div>
                        <div class="card-body">
                            <form id="formKamar" class="row g-2">
                                <div class="col-6"><label class="form-label small mb-1">Kode Kamar</label><input type="text" id="kamarKd" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Kode Bangsal</label><input type="text" id="kamarBangsal" class="form-control form-control-sm" required></div>
                                <div class="col-6"><label class="form-label small mb-1">Kelas</label><select id="kamarKelas" class="form-select form-select-sm"><option>Kelas 3</option><option>Kelas 2</option><option>Kelas 1</option><option>VIP</option></select></div>
                                <div class="col-6"><label class="form-label small mb-1">Tarif/Hari (Rp)</label><input type="number" id="kamarTarif" class="form-control form-control-sm" value="150000" min="0"></div>
                                <div class="col-12"><label class="form-label small mb-1">Status</label><select id="kamarStatus" class="form-select form-select-sm"><option value="KOSONG">KOSONG</option><option value="ISI">ISI</option></select></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode Kamar</th><th>Bangsal</th><th>Kelas</th><th class="text-end">Tarif</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyKamar"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 8: Penyakit ICD-10 -->
        <div class="tab-pane fade" id="tabPenyakit">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Penyakit (ICD-10)</div>
                        <div class="card-body">
                            <form id="formPenyakit" class="row g-2">
                                <div class="col-4"><label class="form-label small mb-1">ICD-10</label><input type="text" id="penyakitKd" class="form-control form-control-sm" placeholder="J06.9" required></div>
                                <div class="col-8"><label class="form-label small mb-1">Nama Penyakit</label><input type="text" id="penyakitNm" class="form-control form-control-sm" required></div>
                                <div class="col-12"><label class="form-label small mb-1">Nama Indonesia</label><input type="text" id="penyakitIdn" class="form-control form-control-sm"></div>
                                <div class="col-6"><label class="form-label small mb-1">Kategori</label><select id="penyakitStatus" class="form-select form-select-sm"><option value="Tidak Menular">Tidak Menular</option><option value="Menular">Menular</option></select></div>
                                <div class="col-6"><label class="form-label small mb-1">Level SKDI</label><input type="text" id="penyakitSkdi" class="form-control form-control-sm" value="4A"></div>
                                <div class="col-12 d-flex gap-2 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white">Simpan</button><button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Penyakit</th><th>Indonesia</th><th>Status</th><th>SKDI</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody id="tbodyPenyakit"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/master.js?v=20261117c"></script>
