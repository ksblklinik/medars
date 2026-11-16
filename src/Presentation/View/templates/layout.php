<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $this->e($judul ?? 'Dashboard') ?> - <?= $this->e($klinik_nama ?? 'SIMRS ksm-next') ?></title>
    
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/medars-app.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Topbar Header Identik ksmlite -->
    <header id="navbar-wrapper" class="bg-medical-teal text-white shadow-sm sticky-top">
        <div class="container-fluid px-3 py-2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-light btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarNav" aria-controls="sidebarNav">
                    <i class="fa fa-bars"></i>
                </button>
                <div class="brand-logo d-flex align-items-center gap-2">
                    <i class="fa fa-hospital-user fa-lg"></i>
                    <span class="fw-bold fs-6 tracking-wide"><?= $this->e($klinik_nama ?? 'SIMRS ksm-next') ?></span>
                </div>
            </div>

            <!-- Jam Real-Time & SSE Online Status Indicator -->
            <div class="d-none d-md-flex align-items-center gap-3">
                <div class="live-clock badge bg-teal-dark font-monospace px-3 py-2" id="liveClock">
                    <i class="fa fa-clock me-1"></i> <span id="clockText">--:--:-- WIB</span>
                </div>
                <div class="sse-status badge bg-success-subtle text-success border border-success px-2 py-1" id="sseBadge">
                    <i class="fa fa-circle-dot text-success animate-pulse me-1"></i> <span id="sseStatusText">SSE Online</span>
                </div>
            </div>

            <!-- User Profile & Hotkey Hint -->
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa fa-user-doctor"></i>
                        <span class="d-none d-sm-inline"><?= $this->e($user_name ?? 'Petugas Medis') ?></span>
                        <span class="badge bg-warning text-dark"><?= $this->e($user_role ?? 'Medis') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><h6 class="dropdown-header">Hotkeys Cepat</h6></li>
                        <li><span class="dropdown-item-text small text-muted"><kbd>F2</kbd> Simpan / Bayar</span></li>
                        <li><span class="dropdown-item-text small text-muted"><kbd>F4</kbd> Cari Pasien / E-Resep</span></li>
                        <li><span class="dropdown-item-text small text-muted"><kbd>F8</kbd> Pending Transaksi</span></li>
                        <li><span class="dropdown-item-text small text-muted"><kbd>F9</kbd> Batal / Reset</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/logout"><i class="fa fa-sign-out-alt me-2"></i>Keluar</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Menu Ikon Cepat Atas (.navbar li.menu-icon) Identik ksmlite -->
        <nav class="top-icon-menu bg-white border-bottom px-2 py-1 overflow-x-auto">
            <ul class="nav nav-pills flex-nowrap gap-1 align-items-center">
                <?php if (!empty($navigations) && is_array($navigations)): ?>
                    <?php foreach ($navigations as $nav): ?>
                        <li class="nav-item menu-icon">
                            <a class="nav-link py-1 px-2 d-flex align-items-center gap-2 text-dark" href="<?= $this->e($nav['route'] ?? '#') ?>">
                                <i class="fa <?= $this->e($nav['icon'] ?? 'fa-cube') ?> text-medical-teal"></i>
                                <span class="nav-text small fw-medium"><?= $this->e($nav['title'] ?? 'Modul') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="nav-item menu-icon">
                        <a class="nav-link py-1 px-2 d-flex align-items-center gap-2 text-dark active" href="/dashboard">
                            <i class="fa fa-gauge text-medical-teal"></i>
                            <span class="nav-text small fw-medium">Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main class="container-fluid px-3 py-3" id="content-wrapper">
        <!-- Breadcrumb & Flash Notification Area -->
        <div id="alertContainer"></div>

        <!-- Area Kerja Utama dengan Dynamic Viewport Slot (Anti Tumpuk-Tumpuk Hidden-Show) -->
        <section id="manage" data-module="<?= $this->e($current_module ?? 'core') ?>" class="module-container">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
                    <h5 class="card-title m-0 text-medical-teal fw-bold fs-6">
                        <i class="fa <?= $this->e($module_icon ?? 'fa-cube') ?> me-2"></i><?= $this->e($judul ?? 'Workspace') ?>
                    </h5>
                    <div class="card-actions d-flex gap-2" id="moduleActions">
                        <!-- Action buttons injected per active view -->
                    </div>
                </div>
                <div class="card-body p-3">
                    <!-- Dynamic Viewport Slot: Hanya satu tampilan yang aktif dan di-mount di sini -->
                    <div id="moduleViewportSlot">
                        <?= $content ?? '' ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Single-Host Dynamic Modal Shell (#appModalHost) -->
    <!-- Tidak ada puluhan modal tersembunyi! Cukup 1 host universal ini. -->
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

    <!-- Bootstrap 5 Bundle & Core App Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/medars-app.js"></script>
</body>
</html>
