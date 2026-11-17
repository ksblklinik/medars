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
                            <i class="fa fa-user-plus me-1"></i> Pendaftaran
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="farmasi-tab" data-bs-toggle="tab" data-bs-target="#farmasi-pane" type="button" role="tab">
                            <i class="fa fa-pills me-1"></i> Farmasi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bpjs-tab" data-bs-toggle="tab" data-bs-target="#bpjs-pane" type="button" role="tab">
                            <i class="fa fa-heart-pulse me-1"></i> Bridging BPJS &amp; SatuSehat
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="wa-tab" data-bs-toggle="tab" data-bs-target="#wa-pane" type="button" role="tab">
                            <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp Gateway
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tarif-tab" data-bs-toggle="tab" data-bs-target="#tarif-pane" type="button" role="tab">
                            <i class="fa fa-tags me-1"></i> Tarif &amp; Billing
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabContent">
                    <!-- Tab Profil Instansi -->
                    <div class="tab-pane fade show active" id="general-pane" role="tabpanel">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Nama Instansi / Klinik / RS</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="settings[general][nama_instansi]" value="<?= $this->e($general['nama_instansi'] ?? 'SIMRS Medars Hospital') ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Kode Fasyankes / RS</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="settings[general][kode_rs]" value="<?= $this->e($general['kode_rs'] ?? '') ?>" placeholder="Contoh: 3201001">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Kota / Kabupaten</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="settings[general][kota]" value="<?= $this->e($general['kota'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Alamat Lengkap</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" name="settings[general][alamat]" rows="2"><?= $this->e($general['alamat'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Nomor Telepon</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="settings[general][no_telp]" value="<?= $this->e($general['no_telp'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Email Resmi</label>
                            <div class="col-sm-6">
                                <input type="email" class="form-control" name="settings[general][email]" value="<?= $this->e($general['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Path / URL Logo Kop Cetak</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="settings[general][logo_url]" value="<?= $this->e($general['logo_url'] ?? '/assets/img/logo.png') ?>">
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

                    <!-- Tab Bridging BPJS & SatuSehat -->
                    <div class="tab-pane fade" id="bpjs-pane" role="tabpanel">
                        <h6 class="fw-bold text-secondary mb-3">Kredensial BPJS Kesehatan (VClaim / Antrol / PCare)</h6>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">BPJS Cons ID</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="settings[bridging][bpjs_consid]" value="<?= $this->e($bridging['bpjs_consid'] ?? $bpjs['cons_id'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">BPJS Secret Key</label>
                            <div class="col-sm-6">
                                <input type="password" class="form-control" name="settings[bridging][bpjs_secretkey]" value="<?= $this->e($bridging['bpjs_secretkey'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">BPJS User Key</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="settings[bridging][bpjs_user_key]" value="<?= $this->e($bridging['bpjs_user_key'] ?? $bpjs['user_key'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-4">
                            <label class="col-sm-3 col-form-label fw-semibold">Base URL BPJS</label>
                            <div class="col-sm-8">
                                <input type="url" class="form-control" name="settings[bridging][bpjs_url]" value="<?= $this->e($bridging['bpjs_url'] ?? 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest') ?>">
                            </div>
                        </div>

                        <h6 class="fw-bold text-secondary mb-3 pt-2 border-top">Kredensial SatuSehat Kemenkes (FHIR R4)</h6>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Organization ID</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="settings[bridging][satusehat_org_id]" value="<?= $this->e($bridging['satusehat_org_id'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Client ID</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="settings[bridging][satusehat_client_id]" value="<?= $this->e($bridging['satusehat_client_id'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Client Secret</label>
                            <div class="col-sm-6">
                                <input type="password" class="form-control" name="settings[bridging][satusehat_client_secret]" value="<?= $this->e($bridging['satusehat_client_secret'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Auth URL</label>
                            <div class="col-sm-8">
                                <input type="url" class="form-control" name="settings[bridging][satusehat_auth_url]" value="<?= $this->e($bridging['satusehat_auth_url'] ?? 'https://api-satusehat.kemkes.go.id/oauth2/v1') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">FHIR Base URL</label>
                            <div class="col-sm-8">
                                <input type="url" class="form-control" name="settings[bridging][satusehat_url]" value="<?= $this->e($bridging['satusehat_url'] ?? 'https://api-satusehat.kemkes.go.id/fhir-r4/v1') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Tab WhatsApp Gateway -->
                    <div class="tab-pane fade" id="wa-pane" role="tabpanel">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">API URL Gateway</label>
                            <div class="col-sm-8">
                                <input type="url" class="form-control" name="settings[wagateway][api_url]" value="<?= $this->e($wagateway['api_url'] ?? '') ?>" placeholder="http://127.0.0.1:3000">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">API Token</label>
                            <div class="col-sm-6">
                                <input type="password" class="form-control" name="settings[wagateway][api_token]" value="<?= $this->e($wagateway['api_token'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Webhook Secret</label>
                            <div class="col-sm-6">
                                <input type="password" class="form-control" name="settings[wagateway][webhook_secret]" value="<?= $this->e($wagateway['webhook_secret'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Nomor Pengirim Default</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="settings[wagateway][sender_number]" value="<?= $this->e($wagateway['sender_number'] ?? '') ?>" placeholder="62812...">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Jeda Anti-Ban (ms)</label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="settings[wagateway][anti_ban_delay_ms]" value="<?= $this->e($wagateway['anti_ban_delay_ms'] ?? '3500') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Tab Tarif & Billing -->
                    <div class="tab-pane fade" id="tarif-pane" role="tabpanel">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">PPN Retail Apotek Umum (%)</label>
                            <div class="col-sm-3">
                                <input type="number" step="0.1" class="form-control" name="settings[tarif][ppn_persen]" value="<?= $this->e($tarif['ppn_persen'] ?? '11') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Biaya Administrasi Ralan (Rp)</label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="settings[tarif][biaya_admin_ralan]" value="<?= $this->e($tarif['biaya_admin_ralan'] ?? '0') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Biaya Administrasi Ranap (Rp)</label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="settings[tarif][biaya_admin_ranap]" value="<?= $this->e($tarif['biaya_admin_ranap'] ?? '0') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">Auto-Posting Jurnal Kasir</label>
                            <div class="col-sm-4">
                                <select class="form-select" name="settings[tarif][auto_jurnal_kasir]">
                                    <option value="1" <?= ($tarif['auto_jurnal_kasir'] ?? '1') === '1' ? 'selected' : '' ?>>Aktif (Otomatis ke mlite_jurnal)</option>
                                    <option value="0" <?= ($tarif['auto_jurnal_kasir'] ?? '1') === '0' ? 'selected' : '' ?>>Manual</option>
                                </select>
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
