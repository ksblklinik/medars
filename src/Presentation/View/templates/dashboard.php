<?php
    $stats = is_array($dashboard_stats ?? null) ? $dashboard_stats : [
        'visits_today' => 0,
        'soap_done' => 0,
        'resep_pending' => 0,
        'kasir_pending' => 0,
        'lab_pending' => 0,
    ];
    $navList = is_array($navigations ?? null) ? $navigations : [];
    $trends = is_array($dashboard_trends ?? null) ? $dashboard_trends : [];
    $announcements = is_array($dashboard_announcements ?? null) ? $dashboard_announcements : [];
    $maxVisits = max(1, ...array_map(static fn (array $t): int => (int) ($t['kunjungan'] ?? 0), $trends ?: [['kunjungan' => 0]]));
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

        <div class="card border-0 shadow-sm mb-3">
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

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold small text-medical-teal py-2">
                <i class="fa fa-chart-column me-1"></i> Tren Kunjungan &amp; Pendapatan (7 Hari)
            </div>
            <div class="card-body small">
                <?php if ($trends === []): ?>
                    <div class="text-muted">Belum ada data tren 7 hari.</div>
                <?php else: ?>
                    <?php foreach ($trends as $t): ?>
                        <?php
                            $visits = (int) ($t['kunjungan'] ?? 0);
                            $rev = (float) ($t['pendapatan'] ?? 0);
                            $pct = (int) round(($visits / $maxVisits) * 100);
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><?= $this->e(substr((string) ($t['tanggal'] ?? ''), 5)) ?></span>
                                <span class="fw-semibold"><?= $visits ?> psn &middot; Rp <?= number_format($rev, 0, ',', '.') ?></span>
                            </div>
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar" role="progressbar" style="width:<?= $pct ?>%; background-color:#00897b;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm" id="dashboardAnnouncementsCard" data-csrf="<?= $this->e($csrf_token ?? '') ?>">
            <div class="card-header bg-white fw-bold small text-medical-teal py-2 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-bullhorn me-1"></i> Pengumuman Internal</span>
            </div>
            <div class="card-body small">
                <form id="dashAnnouncementForm" class="mb-3">
                    <input type="text" id="dashAnnTitle" class="form-control form-control-sm mb-1" placeholder="Judul pengumuman..." required>
                    <textarea id="dashAnnBody" class="form-control form-control-sm mb-1" rows="2" placeholder="Isi pengumuman staf..." required></textarea>
                    <div class="d-flex gap-1">
                        <select id="dashAnnPriority" class="form-select form-select-sm" style="max-width:110px;">
                            <option value="Info">Info</option>
                            <option value="Penting">Penting</option>
                        </select>
                        <button type="submit" class="btn btn-sm text-white flex-grow-1" style="background-color:#00897b;">Terbitkan</button>
                    </div>
                </form>
                <div id="dashAnnList">
                    <?php if ($announcements === []): ?>
                        <div class="text-muted">Belum ada pengumuman aktif.</div>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="border rounded p-2 mb-2 bg-light">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="fw-bold">
                                        <span class="badge <?= ($ann['prioritas'] ?? '') === 'Penting' ? 'bg-danger' : 'bg-info text-dark' ?> me-1"><?= $this->e($ann['prioritas'] ?? 'Info') ?></span>
                                        <?= $this->e($ann['judul'] ?? '') ?>
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm text-danger p-0 js-del-ann" data-id="<?= (int) ($ann['id'] ?? 0) ?>" title="Hapus">&times;</button>
                                </div>
                                <div class="mt-1"><?= $this->e($ann['isi'] ?? '') ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= $this->e($ann['penulis'] ?? 'Admin') ?> &middot; <?= $this->e($ann['created_at'] ?? '') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
        (() => {
            const card = document.getElementById('dashboardAnnouncementsCard');
            const form = document.getElementById('dashAnnouncementForm');
            if (!card || !form) return;
            const csrf = card.dataset.csrf || '';
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const res = await fetch('/api/v1/dashboard/announcements/create', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({
                        judul: document.getElementById('dashAnnTitle').value.trim(),
                        isi: document.getElementById('dashAnnBody').value.trim(),
                        prioritas: document.getElementById('dashAnnPriority').value
                    })
                });
                if (res.ok) window.location.reload();
            });
            card.addEventListener('click', async (e) => {
                const btn = e.target.closest('.js-del-ann');
                if (!btn) return;
                const res = await fetch('/api/v1/dashboard/announcements/delete', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ id: Number(btn.dataset.id) })
                });
                if (res.ok) window.location.reload();
            });
        })();
        </script>
    </div>
</div>
