<?php $u = $current_user; ?>
<div class="container-fluid px-0" id="profilWorkspace">
    <div id="profilAlertContainer"></div>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-user-pen me-1"></i> Data Profil Pengguna
                </div>
                <div class="card-body">
                    <form id="formUpdateProfil" class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">Username</label>
                            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($u->getUsername(), ENT_QUOTES, 'UTF-8') ?>" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Peran (Role)</label>
                            <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($u->getRole(), ENT_QUOTES, 'UTF-8') ?>" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Nama Lengkap</label>
                            <input type="text" id="profilFullname" class="form-control form-control-sm" value="<?= htmlspecialchars($u->getFullname(), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Email</label>
                            <input type="email" id="profilEmail" class="form-control form-control-sm" value="<?= htmlspecialchars($u->getEmail(), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-sm btn-teal text-white">Simpan Profil</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">
                    <i class="fa fa-key me-1"></i> Ganti Password
                </div>
                <div class="card-body">
                    <form id="formChangePassword" class="row g-2">
                        <div class="col-12">
                            <label class="form-label small mb-1">Password Saat Ini</label>
                            <input type="password" id="pwdCurrent" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Password Baru (min. 8 karakter)</label>
                            <input type="password" id="pwdNew" class="form-control form-control-sm" minlength="8" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Konfirmasi Password Baru</label>
                            <input type="password" id="pwdConfirm" class="form-control form-control-sm" minlength="8" required>
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-sm btn-outline-teal">Perbarui Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/profil.js?v=20261117b"></script>
