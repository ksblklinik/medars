<?php
    $stats = is_array($dashboard_stats ?? null) ? $dashboard_stats : [
        'visits_today' => 0,
        'soap_done' => 0,
        'resep_pending' => 0,
        'kasir_pending' => 0,
        'lab_pending' => 0,
    ];
    $navList = is_array($navigations ?? null) ? $navigations : [];
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100 border-start border-4 border-primary">
            <div class="card-body py-2 px-3">
                <div class="text-muted small fw-semibold">Kunjungan Hari Ini</div>
                <div class="fs-3 fw-bold text-medical-teal"><?= (int) $stats['visits_today'] ?></div>
                <div class="small text-muted">Terdaftar di loket</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100 border-start border-4 border-success">
            <div class="card-body py-2 px-3">
                <div class="text-muted small fw-semibold">Sudah Diperiksa (SOAP)</div>
                <div class="fs-3 fw-bold text-success"><?= (int) $stats['soap_done'] ?></div>
                <div class="small text-muted">Rekam medis terisi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100 border-start border-4 border-warning">
            <div class="card-body py-2 px-3">
                <div class="text-muted small fw-semibold">E-Resep Menunggu</div>
                <div class="fs-3 fw-bold text-warning"><?= (int) $stats['resep_pending'] ?></div>
                <div class="small text-muted">Antrean farmasi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100 border-start border-4 border-danger">
            <div class="card-body py-2 px-3">
                <div class="text-muted small fw-semibold">Tagihan Kasir Pending</div>
                <div class="fs-3 fw-bold text-danger"><?= (int) $stats['kasir_pending'] ?></div>
                <div class="small text-muted">Menunggu pelunasan</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold small text-medical-teal py-2">
                <i class="fa fa-th-large me-1"></i> Akses Cepat Modul Pelayanan &amp; Manajemen
            </div>
            <div class="card-body bg-light">
                <div class="row g-2">
                    <?php foreach ($navList as $nav): ?>
                        <?php if (($nav['module_key'] ?? '') === 'dashboard') { continue; } ?>
                        <div class="col-6 col-sm-4 col-md-3">
                            <a href="<?= $this->e($nav['route'] ?? '#') ?>" class="menu-grid-tile">
                                <i class="fa <?= $this->e($nav['icon'] ?? 'fa-cube') ?>"></i>
                                <span class="small fw-semibold text-center"><?= $this->e($nav['title'] ?? '') ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold small text-medical-teal py-2">
                <i class="fa fa-tv me-1"></i> Layar Publik &amp; Anjungan
            </div>
            <div class="card-body d-grid gap-2">
                <a href="/display-antrean" target="_blank" class="btn btn-sm btn-outline-primary text-start">
                    <i class="fa fa-tv me-2"></i> Buka Display TV Antrean Poli
                </a>
                <a href="/kiosk" target="_blank" class="btn btn-sm btn-outline-success text-start">
                    <i class="fa fa-tablet-screen-button me-2"></i> Buka Kiosk Pendaftaran Mandiri
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold small text-medical-teal py-2">
                <i class="fa fa-keyboard me-1"></i> Pintasan Keyboard Operasional
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1"><span>Simpan / Bayar</span><kbd>F2</kbd></div>
                <div class="d-flex justify-content-between mb-1"><span>Cari Pasien / E-Resep</span><kbd>F4</kbd></div>
                <div class="d-flex justify-content-between mb-1"><span>Pending Transaksi</span><kbd>F8</kbd></div>
                <div class="d-flex justify-content-between"><span>Reset Form / Tutup Modal</span><span><kbd>F9</kbd> / <kbd>Esc</kbd></span></div>
            </div>
        </div>
    </div>
</div>
