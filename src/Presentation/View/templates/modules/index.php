<?php
$csrfToken = (string) ($csrf_token ?? '');
?>
<div class="container-fluid py-3" id="moduleManagerApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold"><i class="fa-solid fa-cubes me-1 text-teal"></i> Manajer Modul SIMRS</span>
                <div class="btn-group btn-group-sm" role="group" id="modFilterGroup">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">Semua</button>
                    <button type="button" class="btn btn-outline-success" data-filter="active">Aktif</button>
                    <button type="button" class="btn btn-outline-danger" data-filter="inactive">Nonaktif</button>
                </div>
            </div>
            <div class="input-group input-group-sm" style="max-width:280px;">
                <input type="text" id="modSearchInput" class="form-control" placeholder="Cari modul atau kategori...">
                <button type="button" id="modSearchBtn" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th style="width:85px;">Urutan</th>
                            <th>Modul</th>
                            <th>Direktori</th>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="modulesTableBody" class="small">
                        <tr><td colspan="7" class="text-center text-muted py-3">Memuat daftar modul...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/module-manager.js?v=<?= time() ?>"></script>
