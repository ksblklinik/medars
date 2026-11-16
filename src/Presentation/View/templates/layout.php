<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $this->e($csrf_token ?? '') ?>">
    <title><?= $this->e($judul ?? 'Dashboard') ?> - <?= $this->e($klinik_nama ?? 'SIMRS ksm-next') ?></title>

    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/medars-app.css?v=20261116b" rel="stylesheet">
</head>
<body>
    <?php
        $navList = is_array($navigations ?? null) ? $navigations : [];
        $groupedNav = [];
        foreach ($navList as $item) {
            $cat = (string) ($item['category'] ?? 'Modul');
            if ($cat !== 'Utama') {
                $groupedNav[$cat][] = $item;
            }
        }
        $activeMod = (string) ($current_module ?? 'dashboard');
    ?>

    <!-- Topbar Header: Mobile App Bar (<768px) + Desktop 2-Bar ksmlite (>=768px) -->
    <header id="navbar-wrapper" class="sticky-top">
        <!-- Bar 1: .menu-top -->
        <nav class="menu-top px-2 px-md-3 py-1">
            <div class="container-fluid px-0 d-flex justify-content-between align-items-center flex-nowrap gap-2">
                <div class="d-flex align-items-center gap-1 min-w-0 flex-grow-1">
                    <a href="/" class="nav-link-top fw-bold text-truncate">
                        <i class="fa fa-hospital-user flex-shrink-0"></i>
                        <span class="d-none d-md-inline"><?= $this->e($klinik_nama ?? 'SIMRS Medars') ?></span>
                        <span class="d-md-none text-truncate"><?= $this->e($judul ?? ($klinik_nama ?? 'SIMRS Medars')) ?></span>
                    </a>

                    <button type="button" class="btn btn-sm btn-outline-light py-1 px-2 d-none d-md-inline-flex align-items-center flex-shrink-0" data-bs-toggle="modal" data-bs-target="#menuModal" title="Menu Utama (Grid)">
                        <i class="fa fa-th-large me-1"></i><span>Menu</span>
                    </button>

                    <div class="d-none d-md-flex align-items-center gap-1">
                        <?php foreach ($groupedNav as $catName => $catItems): ?>
                            <div class="dropdown">
                                <a href="#" class="nav-link-top dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?= $this->e($catName) ?>
                                </a>
                                <ul class="dropdown-menu shadow-sm">
                                    <?php foreach ($catItems as $sub): ?>
                                        <li>
                                            <a class="dropdown-item small d-flex align-items-center gap-2 <?= (($sub['module_key'] ?? '') === $activeMod) ? 'active' : '' ?>" href="<?= $this->e($sub['route'] ?? '#') ?>">
                                                <i class="fa <?= $this->e($sub['icon'] ?? 'fa-cube') ?> fa-fw"></i>
                                                <span><?= $this->e($sub['title'] ?? '') ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Kanan: Quick Pills, Jam Server, SSE & User/Logout -->
                <div class="d-flex align-items-center gap-1 gap-md-2 flex-shrink-0">
                    <a href="/display-antrean" target="_blank" class="btn btn-warning pill-quick d-none d-lg-inline-flex align-items-center gap-1" title="Buka Display TV Antrean">
                        <i class="fa fa-tv"></i><span>Display TV</span>
                    </a>
                    <a href="/kiosk" target="_blank" class="btn btn-info text-dark pill-quick d-none d-lg-inline-flex align-items-center gap-1" title="Buka Kiosk Mandiri">
                        <i class="fa fa-tablet-screen-button"></i><span>Kiosk</span>
                    </a>
                    <a href="/pendaftaran" class="btn btn-success pill-quick d-none d-md-inline-flex align-items-center gap-1" title="Pendaftaran & Booking">
                        <i class="fa fa-calendar-check"></i><span>Booking</span>
                    </a>

                    <div class="live-clock badge bg-teal-dark font-monospace px-2 py-1 d-none d-xl-inline-block" id="liveClock">
                        <i class="fa fa-clock me-1"></i><span id="clockText">--:--:-- WIB</span>
                    </div>

                    <div class="sse-status badge bg-light text-success border border-success px-2 py-1 d-none d-xl-inline-block" id="sseBadge" title="Status Real-Time SSE">
                        <i class="fa fa-circle-dot text-success animate-pulse me-1"></i><span id="sseStatusText">Online</span>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle py-1 px-2 d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-user-doctor"></i>
                            <span class="d-none d-sm-inline small"><?= $this->e($user_name ?? 'Petugas') ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><h6 class="dropdown-header">Role: <?= $this->e($user_role ?? 'Medis') ?></h6></li>
                            <li><span class="dropdown-item-text small text-muted"><kbd>F2</kbd> Simpan / Bayar</span></li>
                            <li><span class="dropdown-item-text small text-muted"><kbd>F4</kbd> Cari Pasien / E-Resep</span></li>
                            <li><span class="dropdown-item-text small text-muted"><kbd>F8</kbd> Pending Transaksi</span></li>
                            <li><span class="dropdown-item-text small text-muted"><kbd>F9</kbd> Batal / Reset</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/logout"><i class="fa fa-power-off me-2"></i>Keluar</a></li>
                        </ul>
                    </div>

                    <a href="/logout" class="text-decoration-none d-none d-sm-flex flex-column align-items-center px-1" style="color: #ff6b6b;" title="Keluar (Logout)">
                        <i class="fa fa-power-off"></i>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Bar 2: Menu Ikon Cepat Atas (Desktop & Tablet >=768px saja; di Mobile diganti Bottom Nav) -->
        <nav class="top-icon-menu px-2 py-1 overflow-x-auto d-none d-md-block">
            <ul class="nav nav-pills flex-nowrap gap-1 align-items-center">
                <?php if ($navList !== []): ?>
                    <?php foreach ($navList as $nav): ?>
                        <?php $isActive = (($nav['module_key'] ?? '') === $activeMod); ?>
                        <li class="nav-item menu-icon <?= $isActive ? 'active' : '' ?>">
                            <a class="nav-link py-1 px-2 d-flex align-items-center gap-1 <?= $isActive ? 'active' : '' ?>" href="<?= $this->e($nav['route'] ?? '#') ?>">
                                <i class="fa <?= $this->e($nav['icon'] ?? 'fa-cube') ?> text-medical-teal"></i>
                                <span class="nav-text"><?= $this->e($nav['title'] ?? 'Modul') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="nav-item menu-icon active">
                        <a class="nav-link py-1 px-2 d-flex align-items-center gap-1 active" href="/dashboard">
                            <i class="fa fa-gauge text-medical-teal"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main class="container-fluid px-2 px-md-3 py-2 py-md-3" id="content-wrapper">
        <div id="alertContainer"></div>

        <!-- Area Kerja Utama dengan Dynamic Viewport Slot -->
        <section id="manage" data-module="<?= $this->e($current_module ?? 'core') ?>" class="module-container">
            <div class="card panel-ksmlite">
                <div class="card-header d-none d-md-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0 text-medical-teal fw-bold fs-6">
                        <i class="fa <?= $this->e($module_icon ?? 'fa-cube') ?> me-2"></i><?= $this->e($judul ?? 'Workspace') ?>
                    </h5>
                    <div class="card-actions d-flex gap-2" id="moduleActions"></div>
                </div>
                <div class="card-body p-0 p-md-3">
                    <div id="moduleViewportSlot">
                        <?= $content ?? '' ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Mobile App Bottom Navigation Bar (< 768px) -->
    <nav class="mobile-bottom-nav d-md-none" aria-label="Navigasi Mobile">
        <a href="/" class="mobile-nav-item <?= $activeMod === 'dashboard' ? 'active' : '' ?>">
            <i class="fa fa-gauge"></i>
            <span>Beranda</span>
        </a>
        <a href="/pendaftaran" class="mobile-nav-item <?= $activeMod === 'pendaftaran' ? 'active' : '' ?>">
            <i class="fa fa-user-plus"></i>
            <span>Daftar</span>
        </a>
        <a href="/dokter-ralan" class="mobile-nav-item <?= $activeMod === 'dokter_ralan' ? 'active' : '' ?>">
            <i class="fa fa-stethoscope"></i>
            <span>Dokter</span>
        </a>
        <a href="/kasir" class="mobile-nav-item <?= $activeMod === 'kasir' ? 'active' : '' ?>">
            <i class="fa fa-cash-register"></i>
            <span>Kasir</span>
        </a>
        <button type="button" class="mobile-nav-item border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#menuModal">
            <i class="fa fa-th-large"></i>
            <span>Menu</span>
        </button>
    </nav>

    <!-- Menu Grid Modal (#menuModal) Identik ksmlite -->
    <div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow">
                <div class="modal-header bg-medical-teal text-white py-2">
                    <h6 class="modal-title m-0 fw-semibold"><i class="fa fa-th-large me-2"></i>Menu Modul SIMRS</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light p-3">
                    <?php foreach ($groupedNav as $catName => $catItems): ?>
                        <div class="mb-3">
                            <div class="small fw-bold text-uppercase text-muted mb-2"><?= $this->e($catName) ?></div>
                            <div class="row g-2">
                                <?php foreach ($catItems as $item): ?>
                                    <div class="col-6 col-sm-4 col-md-3">
                                        <a href="<?= $this->e($item['route'] ?? '#') ?>" class="menu-grid-tile">
                                            <i class="fa <?= $this->e($item['icon'] ?? 'fa-cube') ?>"></i>
                                            <span class="small fw-semibold text-center"><?= $this->e($item['title'] ?? '') ?></span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Single-Host Dynamic Modal Shell (#appModalHost) -->
    <div class="modal fade" id="appModalHost" tabindex="-1" aria-labelledby="appModalHostTitle" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg" id="appModalDialog">
            <div class="modal-content shadow" id="appModalContent">
                <div class="modal-header bg-medical-teal text-white py-2">
                    <h6 class="modal-title m-0 fw-semibold" id="appModalHostTitle">Memuat Dialog...</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center" id="appModalBody">
                    <div class="spinner-border text-medical-teal" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/medars-app.js?v=20261116b"></script>
</body>
</html>
