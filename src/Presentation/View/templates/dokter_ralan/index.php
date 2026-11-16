<!-- Toolbar Paritas ksmlite (Cari Antrean, Filter Status, Periode Rawat) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: 240px;">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari Pasien / No. Rawat..." data-ksmlite-search="poliQueueBody">
        </div>
        <select class="form-select form-select-sm" style="width: 160px;" data-ksmlite-status-filter="poliQueueBody">
            <option value="">Semua Status</option>
            <option value="belum">Belum Diperiksa</option>
            <option value="sudah">Selesai Diperiksa</option>
        </select>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border"><i class="fa fa-calendar me-1 text-medical-teal"></i>Periode Rawat: <?= date('d-m-Y') ?></span>
    </div>
</div>

<div class="row g-3" id="dokterRalanWorkspace" data-dokter="<?= $this->e($kd_dokter ?? '') ?>">
    <!-- Kolom Kiri: Daftar Antrean Poliklinik & Smart Peek Riwayat Medis -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-list-ol me-1"></i> Antrean Poli Hari Ini</h6>
                <button type="button" id="btnRefreshPoliQueue" class="btn btn-xs btn-outline-secondary" title="Muat Ulang Antrean">
                    <i class="fa fa-rotate"></i>
                </button>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0" id="poliQueueTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">No.</th>
                            <th>Pasien</th>
                            <th>Poli</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="poliQueueBody">
                        <tr><td colspan="4" class="text-center text-muted py-3 small">Memuat antrean poli...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel Samping Smart Peek Riwayat Medis -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-clock-rotate-left me-1"></i> Smart Peek Riwayat Medis</h6>
                <span class="badge bg-secondary-subtle text-secondary small" id="smartPeekCount">0 Kunjungan</span>
            </div>
            <div class="card-body p-2" id="smartPeekPanel" style="max-height: 420px; overflow-y: auto;">
                <div class="text-center text-muted small py-4">Pilih pasien dari antrean untuk melihat riwayat klinis (Next + Legacy Read-Only).</div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Workspace RME & SOAP Dinamis -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-medical-teal text-white py-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="m-0 fw-bold"><i class="fa fa-notes-medical me-1"></i> Form Pemeriksaan & SOAP Dinamis</h6>
                    <small id="activePatientBanner" class="text-white-50">Belum ada pasien dipilih</small>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="btnCallPatient" class="btn btn-sm btn-warning text-dark fw-semibold" disabled>
                        <i class="fa fa-bullhorn me-1"></i> Panggil TV
                    </button>
                    <button type="button" id="btnSaveSoapMain" class="btn btn-sm btn-light fw-bold text-medical-teal" disabled>
                        <i class="fa fa-save me-1"></i> Simpan SOAP (F2)
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="rmeAlertContainer"></div>

                <form id="soapForm">
                    <input type="hidden" id="soapNoRawat" name="no_rawat" value="">
                    <input type="hidden" id="soapNoRkmMedis" value="">
                    <input type="hidden" id="soapTglPerawatan" name="tgl_perawatan" value="<?= date('Y-m-d') ?>">
                    <input type="hidden" id="soapJamRawat" name="jam_rawat" value="<?= date('H:i:s') ?>">
                    <input type="hidden" id="soapMetaKey" name="meta_key" value="">
                    <input type="hidden" id="soapIsMetaDirty" name="is_meta_dirty" value="0">

                    <!-- Nav Tabs RME -->
                    <ul class="nav nav-tabs mb-3" id="rmeTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSoap" type="button">SOAP & TTV</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabIcd10" type="button">Diagnosa ICD-10</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPrbSurat" type="button">PRB 30 Hari & Surat Medis</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPoliSpesifik" type="button">Poli Spesifik & Odontogram</button></li>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab 1: SOAP & TTV -->
                        <div class="tab-pane fade show active" id="tabSoap">
                            <div class="row g-2 mb-3 bg-light p-2 rounded border">
                                <div class="col-6 col-md-2"><label class="form-label small mb-0">Suhu (&deg;C)</label><input type="text" class="form-control form-control-sm" name="suhu_tubuh" placeholder="36.5"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-0">Tensi</label><input type="text" class="form-control form-control-sm" name="tensi" placeholder="120/80"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-0">Nadi</label><input type="text" class="form-control form-control-sm" name="nadi" placeholder="80"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-0">Respirasi</label><input type="text" class="form-control form-control-sm" name="respirasi" placeholder="20"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-0">SpO2 (%)</label><input type="text" class="form-control form-control-sm" name="spo2" placeholder="98"></div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-0">Kesadaran</label>
                                    <select class="form-select form-select-sm" name="kesadaran">
                                        <option value="Compos Mentis">Compos Mentis</option>
                                        <option value="Somnolence">Somnolence</option>
                                        <option value="Sopor">Sopor</option>
                                        <option value="Coma">Coma</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">S - Subjektif (Keluhan)</label>
                                    <textarea class="form-control form-control-sm" name="keluhan" id="soapKeluhan" rows="3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">O - Objektif (Pemeriksaan Fisik)</label>
                                    <textarea class="form-control form-control-sm" name="pemeriksaan" id="soapPemeriksaan" rows="3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">A - Asesmen (Penilaian)</label>
                                    <textarea class="form-control form-control-sm" name="penilaian" id="soapPenilaian" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">P - Planning (Rencana Tindak Lanjut)</label>
                                    <textarea class="form-control form-control-sm" name="rtl" id="soapRtl" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Instruksi Medis</label>
                                    <input type="text" class="form-control form-control-sm" name="instruksi" id="soapInstruksi">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Alergi</label>
                                    <input type="text" class="form-control form-control-sm" name="alergi" id="soapAlergi" placeholder="-">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Grounding ICD-10 -->
                        <div class="tab-pane fade" id="tabIcd10">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Cari Kode / Nama Diagnosa ICD-10 SatuSehat</label>
                                <input type="text" class="form-control form-control-sm" id="icdSearchInput" placeholder="Ketik minimal 2 huruf (mis. ISPA, Demam, J06)..." autocomplete="off">
                                <div class="form-text">Semua kode ICD-10 terbuka tanpa pemblokiran level SKDI.</div>
                            </div>
                            <div id="icdSearchResults" class="list-group small"></div>
                        </div>

                        <!-- Tab 3: PRB 30 Hari & Surat Medis -->
                        <div class="tab-pane fade" id="tabPrbSurat">
                            <div class="row g-3">
                                <div class="col-md-6 border-end">
                                    <h6 class="fw-bold text-medical-teal small mb-2">Resep PRB 30 Hari (Tanpa Potong Stok)</h6>
                                    <div class="mb-2">
                                        <input type="text" class="form-control form-control-sm" id="prbDiagnosa" placeholder="Diagnosa Kronis (mis. Hipertensi)">
                                    </div>
                                    <div class="row g-1 mb-2">
                                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="prbObat" placeholder="Nama Obat"></div>
                                        <div class="col-2"><input type="number" class="form-control form-control-sm" id="prbJumlah" value="30"></div>
                                        <div class="col-4"><input type="text" class="form-control form-control-sm" id="prbAturan" placeholder="1x1 pagi"></div>
                                    </div>
                                    <button type="button" id="btnSavePrb" class="btn btn-sm btn-outline-primary">Simpan PRB & Sinkron ke Planning</button>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-medical-teal small mb-2">Terbitkan Surat Keterangan Sakit</h6>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6"><label class="small">Dari Tanggal</label><input type="date" class="form-control form-control-sm" id="suratTglAwal" value="<?= date('Y-m-d') ?>"></div>
                                        <div class="col-6"><label class="small">Sampai Tanggal</label><input type="date" class="form-control form-control-sm" id="suratTglAkhir" value="<?= date('Y-m-d') ?>"></div>
                                    </div>
                                    <button type="button" id="btnCreateSuratSakit" class="btn btn-sm btn-outline-success">Terbitkan Surat Sakit</button>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: Poli Spesifik (KIA) & Odontogram -->
                        <div class="tab-pane fade" id="tabPoliSpesifik">
                            <div class="row g-3">
                                <div class="col-md-6 border-end">
                                    <h6 class="fw-bold text-medical-teal small mb-2">Metadata Kebidanan / KIA (Smart Peek Protected)</h6>
                                    <div class="row g-2">
                                        <div class="col-4"><label class="small">Gravida</label><input type="number" class="form-control form-control-sm kia-meta-field" id="kiaG"></div>
                                        <div class="col-4"><label class="small">Para</label><input type="number" class="form-control form-control-sm kia-meta-field" id="kiaP"></div>
                                        <div class="col-4"><label class="small">Abortus</label><input type="number" class="form-control form-control-sm kia-meta-field" id="kiaA"></div>
                                        <div class="col-6"><label class="small">DJJ (x/mnt)</label><input type="text" class="form-control form-control-sm kia-meta-field" id="kiaDjj"></div>
                                        <div class="col-6"><label class="small">TFU (cm)</label><input type="text" class="form-control form-control-sm kia-meta-field" id="kiaTfu"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-medical-teal small mb-2">Odontogram Poli Gigi (FDI + UNE)</h6>
                                    <div class="row g-2 mb-2">
                                        <div class="col-4"><input type="text" class="form-control form-control-sm" id="odoGigi" placeholder="No. Gigi (11-48)"></div>
                                        <div class="col-8">
                                            <select class="form-select form-select-sm" id="odoKondisi">
                                                <option value="CAR">CAR - Karies</option>
                                                <option value="UNE">UNE - Unerupted (Belum Erupsi)</option>
                                                <option value="MIS">MIS - Missing</option>
                                                <option value="AMF">AMF - Tambalan Amalgam</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" id="btnSaveOdo" class="btn btn-sm btn-outline-primary mb-2">Simpan Odontogram</button>
                                    <div id="odontogramHistoryList" class="list-group list-group-flush small border rounded" style="max-height: 150px; overflow-y: auto;">
                                        <div class="list-group-item text-muted">Belum ada catatan odontogram.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/modules/dokter-ralan.js"></script>
