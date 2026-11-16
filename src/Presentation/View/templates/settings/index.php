<div class="container-fluid py-2">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="card-title m-0 text-medical-teal fw-bold">
                <i class="fa fa-sliders me-2"></i>Pengaturan Sistem SIMRS
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="/settings/save" method="POST">
                <?= $csrf_input ?? '' ?>

                <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab">
                            <i class="fa fa-hospital me-1"></i> Profil Instansi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pendaftaran-tab" data-bs-toggle="tab" data-bs-target="#pendaftaran-pane" type="button" role="tab">
                            <i class="fa fa-user-plus me-1"></i> Pendaftaran & Antrean
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="farmasi-tab" data-bs-toggle="tab" data-bs-target="#farmasi-pane" type="button" role="tab">
                            <i class="fa fa-pills me-1"></i> Farmasi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bpjs-tab" data-bs-toggle="tab" data-bs-target="#bpjs-pane" type="button" role="tab">
                            <i class="fa fa-heart-pulse me-1"></i> Integrasi BPJS
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabContent">
                    <!-- Tab Profil Instansi -->
                    <div class="tab-pane fade show active" id="general-pane" role="tabpanel">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Nama Instansi / Rumah Sakit</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="settings[general][nama_instansi]" value="<?= $this->e($general['nama_instansi'] ?? 'SIMRS Medars Hospital') ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Alamat Lengkap</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" name="settings[general][alamat]" rows="3"><?= $this->e($general['alamat'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Nomor Telepon</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="settings[general][no_telp]" value="<?= $this->e($general['no_telp'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Email Resmi</label>
                            <div class="col-sm-8">
                                <input type="email" class="form-control" name="settings[general][email]" value="<?= $this->e($general['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Tab Pendaftaran -->
                    <div class="tab-pane fade" id="pendaftaran-pane" role="tabpanel">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Maksimal Kuota Pasien per Poli</label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="settings[pendaftaran][max_kuota]" value="<?= $this->e($pendaftaran['max_kuota'] ?? '40') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Format Penomoran Rekam Medis</label>
                            <div class="col-sm-4">
                                <select class="form-select" name="settings[pendaftaran][format_rm]">
                                    <option value="6-digit" <?= ($pendaftaran['format_rm'] ?? '') === '6-digit' ? 'selected' : '' ?>>6 Digit (000001)</option>
                                    <option value="8-digit" <?= ($pendaftaran['format_rm'] ?? '') === '8-digit' ? 'selected' : '' ?>>8 Digit (00000001)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Farmasi -->
                    <div class="tab-pane fade" id="farmasi-pane" role="tabpanel">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Offset Waktu Racikan Farmasi (Detik)</label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="settings[farmasi][racikan_offset_detik]" value="<?= $this->e($farmasi['racikan_offset_detik'] ?? '1') ?>">
                                <div class="form-text">Mencegah tabrakan nomor resep racikan unik pada timestamp detik yang sama.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab BPJS -->
                    <div class="tab-pane fade" id="bpjs-pane" role="tabpanel">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Cons ID BPJS</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="settings[bpjs][cons_id]" value="<?= $this->e($bpjs['cons_id'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">User Key Antrean JKN</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="settings[bpjs][user_key]" value="<?= $this->e($bpjs['user_key'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4 pt-3 border-top">
                    <div class="col-sm-8 offset-sm-3">
                        <button type="submit" class="btn btn-primary" style="background-color: #00897b; border-color: #00897b;">
                            <i class="fa fa-floppy-disk me-1"></i> Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
