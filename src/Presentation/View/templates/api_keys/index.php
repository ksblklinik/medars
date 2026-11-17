<?php
$csrfToken = (string) ($csrf_token ?? '');
?>
<div class="container-fluid py-3" id="apiKeysApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-teal text-white fw-semibold" style="background-color:#00897b;">
                    <i class="fa-solid fa-key me-1"></i> Buat API Key Baru
                </div>
                <div class="card-body">
                    <form id="apiKeyCreateForm">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="akLabel">Label Integrasi *</label>
                            <input type="text" id="akLabel" class="form-control form-control-sm" placeholder="Contoh: Mobile JKN / Lab LIS" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="akUsername">Service Username</label>
                            <input type="text" id="akUsername" class="form-control form-control-sm" placeholder="jkn_service">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="akMethods">Allowed Methods / Scopes</label>
                            <input type="text" id="akMethods" class="form-control form-control-sm" value="GET,POST">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold" for="akIpRange">IP Range / Whitelist</label>
                            <input type="text" id="akIpRange" class="form-control form-control-sm" value="*">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="akExpTime">Kedaluwarsa (Opsional)</label>
                            <input type="datetime-local" id="akExpTime" class="form-control form-control-sm">
                        </div>
                        <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">
                            <i class="fa-solid fa-plus me-1"></i> Generate API Key
                        </button>
                    </form>
                    <div id="apiKeyOneTimeAlert" class="alert alert-warning mt-3 d-none small mb-0">
                        <div class="fw-bold mb-1">Salin API Key Sekarang (hanya tampil 1x):</div>
                        <code id="apiKeyRawValue" class="d-block p-2 bg-light text-dark rounded user-select-all"></code>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="fa-solid fa-shield-halved me-1 text-teal"></i> Daftar API Key Terdaftar</span>
                    <div class="input-group input-group-sm" style="max-width:260px;">
                        <input type="text" id="akSearchInput" class="form-control" placeholder="Cari label / username...">
                        <button type="button" id="akSearchBtn" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Label</th>
                                    <th>Username</th>
                                    <th>Prefix Key</th>
                                    <th>Methods</th>
                                    <th>IP Whitelist</th>
                                    <th>Exp</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="apiKeysTableBody" class="small">
                                <tr><td colspan="8" class="text-center text-muted py-3">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/api-keys.js?v=<?= time() ?>"></script>
