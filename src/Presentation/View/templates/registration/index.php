<div class="container-fluid px-0">
    <!-- Toolbar Paritas ksmlite (Toggle Form, Cari Pasien Antrean, Filter Status, Periode) -->
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-medical-teal" id="bukaform" data-ksmlite-toggle-form="formRegistration">
                <i class="fa fa-minus-circle me-1"></i> Tutup Form
            </button>
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" class="form-control" placeholder="Cari RM / Nama / No. Rawat..." data-ksmlite-search="tbodyRegistrations">
            </div>
            <select class="form-select form-select-sm toolbar-filter" data-ksmlite-status-filter="tbodyRegistrations">
                <option value="">Semua Status</option>
                <option value="belum">Belum Diperiksa</option>
                <option value="sudah">Sudah Diperiksa</option>
                <option value="batal">Batal</option>
            </select>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white text-muted">Dari</span>
                <input type="date" id="regFilterFrom" class="form-control" value="<?= date('Y-m-d') ?>">
                <span class="input-group-text bg-white text-muted">s/d</span>
                <input type="date" id="regFilterTo" class="form-control" value="<?= date('Y-m-d') ?>">
                <select id="regFilterStts" class="form-select" style="max-width: 130px;">
                    <option value="">Semua Status</option>
                    <option value="Belum">Belum</option>
                    <option value="Sudah">Sudah</option>
                    <option value="Batal">Batal</option>
                </select>
                <button type="button" id="btnFilterRegistrations" class="btn btn-outline-teal">Filter</button>
            </div>
            <a href="/display-antrean" target="_blank" class="btn btn-sm btn-outline-teal"><i class="fa fa-tv me-1"></i>Display TV</a>
        </div>
    </div>

    <div class="row g-3">
        <!-- Panel Kiri: Form Registrasi Pasien (Identik ksmlite #form) -->
        <div class="col-lg-6 col-12">
            <div class="card shadow-sm border-0" id="formRegistration">
                <div class="card-header bg-medical-teal text-white d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold"><i class="fa fa-user-plus me-2"></i>Pendaftaran Rawat Jalan</span>
                    <span class="badge bg-white text-dark small"><kbd>F2</kbd> Daftarkan</span>
                </div>
                <div class="card-body p-3">
                    <div id="alertRegistration" class="alert d-none mb-3" role="alert"></div>

                    <form id="regForm" autocomplete="off">
                        <div class="row g-2 mb-2">
                            <div class="col-sm-7 col-12">
                                <label for="regTglRegistrasi" class="form-label small mb-1 fw-bold text-muted">Tanggal Kunjungan</label>
                                <input type="date" class="form-control form-control-sm" id="regTglRegistrasi" name="tgl_registrasi" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-sm-5 col-12">
                                <label for="regJamReg" class="form-label small mb-1 fw-bold text-muted">Jam Kunjungan</label>
                                <input type="time" class="form-control form-control-sm" id="regJamReg" name="jam_reg" value="<?= date('H:i:s') ?>" step="1">
                            </div>
                        </div>

                        <!-- Pencarian Pasien (F4) -->
                        <div class="mb-2">
                            <label for="regNoRkmMedis" class="form-label small mb-1 fw-bold text-muted">Pasien (No. RM / Nama)</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="regNoRkmMedis" name="no_rkm_medis" placeholder="Ketik No. RM atau tekan F4" required>
                                <button class="btn btn-outline-teal btn-cari-pasien" type="button" id="btnCariPasien" data-hotkey="F4" title="Cari Pasien (F4)">
                                    <i class="fa fa-search me-1"></i><kbd class="bg-dark text-white">F4</kbd>
                                </button>
                            </div>
                        </div>

                        <!-- Pilihan Poliklinik & Dokter -->
                        <div class="row g-2 mb-2">
                            <div class="col-sm-6 col-12">
                                <label for="regKdPoli" class="form-label small mb-1 fw-bold text-muted">Poliklinik Tujuan</label>
                                <select class="form-select form-select-sm" id="regKdPoli" name="kd_poli" required>
                                    <option value="">-- Pilih Poliklinik --</option>
                                    <?php if (!empty($poliklinik)): ?>
                                        <?php foreach ($poliklinik as $poli): ?>
                                            <option value="<?= htmlspecialchars($poli->kdPoli) ?>" data-allow-bpjs="<?= $poli->allowBpjs ? '1' : '0' ?>" data-tarif-baru="<?= $poli->registrasiBaru ?>" data-tarif-lama="<?= $poli->registrasiLama ?>">
                                                <?= htmlspecialchars($poli->nmPoli) ?> <?= !$poli->allowBpjs ? '(Non-BPJS)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 col-12">
                                <label for="regKdDokter" class="form-label small mb-1 fw-bold text-muted">Dokter Pemeriksa</label>
                                <select class="form-select form-select-sm" id="regKdDokter" name="kd_dokter" required>
                                    <option value="">-- Pilih Dokter --</option>
                                    <?php if (!empty($dokter)): ?>
                                        <?php foreach ($dokter as $doc): ?>
                                            <option value="<?= htmlspecialchars($doc['kd_dokter']) ?>">
                                                <?= htmlspecialchars($doc['nm_dokter']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Penjamin Pembayaran -->
                        <div class="row g-2 mb-2">
                            <div class="col-sm-6 col-12">
                                <label for="regKdPj" class="form-label small mb-1 fw-bold text-muted">Penjamin Bayar</label>
                                <select class="form-select form-select-sm" id="regKdPj" name="kd_pj" required>
                                    <?php if (!empty($penjab)): ?>
                                        <?php foreach ($penjab as $pj): ?>
                                            <option value="<?= htmlspecialchars($pj['kd_pj']) ?>" <?= $pj['kd_pj'] === 'A09' ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pj['png_jawab']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="A09">Umum / Mandiri</option>
                                        <option value="BPJ">BPJS Kesehatan</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 col-12">
                                <label class="form-label small mb-1 fw-bold text-muted">Tarif Registrasi</label>
                                <div class="form-control form-control-sm bg-light fw-bold text-end" id="displayBiayaReg">Rp 0</div>
                            </div>
                        </div>

                        <!-- Detail Pasien Terpilih -->
                        <div class="card bg-light border-0 p-2 mb-3">
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <span class="text-muted d-block">Nama Lengkap:</span>
                                    <span class="fw-bold" id="infoNmPasien">-</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">NIK / No. BPJS:</span>
                                    <span class="fw-bold" id="infoNikPeserta">-</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">Usia / Jenis Kelamin:</span>
                                    <span id="infoUmurJk">-</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">Alamat:</span>
                                    <span id="infoAlamat" class="text-truncate d-block">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center pt-2 border-top">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-teal text-white btn-sm px-3" id="btnDaftarInSite">
                                    <i class="fa fa-save me-1"></i> <kbd class="bg-dark text-white">F2</kbd> Daftarkan
                                </button>
                                <button type="button" class="btn btn-success btn-sm px-3" id="btnCheckInMobileJkn">
                                    <i class="fa fa-check-to-slot me-1"></i> Check-in Mobile JKN
                                </button>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetForm" data-hotkey="F9">
                                <i class="fa fa-eraser me-1"></i> <kbd class="bg-light text-dark">F9</kbd> Kosongkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Panel Kanan: Tabel Kunjungan Hari Ini (Identik ksmlite #display) -->
        <div class="col-lg-6 col-12">
            <div class="card shadow-sm border-0" id="displayRegistration">
                <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-medical-teal"><i class="fa fa-list-ol me-2"></i>Daftar Kunjungan Rawat Jalan Hari Ini</span>
                    <button class="btn btn-sm btn-outline-teal" id="btnRefreshList" title="Muat Ulang">
                        <i class="fa fa-rotate"></i>
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive" style="max-height: 580px; overflow-y: auto;">
                        <table class="table table-hover table-sm align-middle small mb-0" id="tableTodayRegistrations">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="text-center" style="width: 10%">Antrean</th>
                                    <th style="width: 20%">No. Rawat</th>
                                    <th style="width: 30%">Pasien</th>
                                    <th style="width: 25%">Poli / Dokter</th>
                                    <th class="text-center" style="width: 15%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyRegistrations">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Memuat data kunjungan hari ini...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/registration.js?v=20261117b"></script>

