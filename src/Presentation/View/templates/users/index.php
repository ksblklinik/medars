<div class="container-fluid py-2">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title m-0 text-medical-teal fw-bold">
                <i class="fa fa-users-gear me-2"></i>Manajemen Pengguna & Hak Akses
            </h5>
            <button type="button" id="btnAddUser" class="btn btn-sm btn-primary" style="background-color: #00897b; border-color: #00897b;" data-bs-toggle="modal" data-bs-target="#modalUser">
                <i class="fa fa-user-plus me-1"></i> Tambah Pengguna
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 50px;">ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Modul Akses</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 100px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="ps-3"><?= $this->e((string) $u->getId()) ?></td>
                                <td class="fw-semibold"><?= $this->e($u->getUsername()) ?></td>
                                <td><?= $this->e($u->getFullname()) ?></td>
                                <td><?= $this->e($u->getEmail()) ?></td>
                                <td><span class="badge bg-info text-dark"><?= $this->e($u->getRole()) ?></span></td>
                                <td><small class="text-muted"><?= $this->e(implode(', ', $u->getAccess())) ?></small></td>
                                <td>
                                    <?php if ($u->isActive()): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Non-Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $userPayload = json_encode([
                                            'id' => $u->getId(),
                                            'username' => $u->getUsername(),
                                            'fullname' => $u->getFullname(),
                                            'email' => $u->getEmail(),
                                            'role' => $u->getRole(),
                                            'access' => implode(', ', $u->getAccess()),
                                            'cap' => implode(', ', $u->getCap()),
                                            'status' => $u->getStatus(),
                                        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                    ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-user" data-user="<?= $this->e((string) $userPayload) ?>" title="Edit Pengguna">
                                        <i class="fa fa-pen-to-square"></i>
                                    </button>
                                    <?php if ($u->getId() !== 1): ?>
                                        <form action="/users/delete" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">
                                            <?= $csrf_input ?? '' ?>
                                            <input type="hidden" name="id" value="<?= $this->e((string) $u->getId()) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Pengguna">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Superadmin dilindungi"><i class="fa fa-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengguna -->
<div class="modal fade" id="modalUser" tabindex="-1" aria-labelledby="modalUserLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/users/save" method="POST">
                <?= $csrf_input ?? '' ?>
                <div class="modal-header bg-medical-teal text-white">
                    <h5 class="modal-title" id="modalUserLabel"><i class="fa fa-user-plus me-2"></i>Form Pengguna</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="userId" value="">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" class="form-control" name="username" id="userUsername" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                        <input type="text" class="form-control" name="fullname" id="userFullname" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" class="form-control" name="email" id="userEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi (Kosongkan bila tidak diubah)</label>
                        <input type="password" class="form-control" name="password" id="userPassword">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role</label>
                        <select class="form-select" name="role" id="userRole">
                            <option value="admin">Administrator</option>
                            <option value="dokter">Dokter</option>
                            <option value="perawat">Perawat / Bidan</option>
                            <option value="kasir">Kasir</option>
                            <option value="farmasi">Apoteker / Farmasi</option>
                            <option value="staff" selected>Staff / Loket</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Akses Modul (Pisahkan dengan koma atau 'all')</label>
                        <input type="text" class="form-control" name="access" id="userAccess" value="all">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Hak Poli / Cap (Pisahkan dengan koma atau 'all')</label>
                        <input type="text" class="form-control" name="cap" id="userCap" value="all">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="userStatus">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" style="background-color: #00897b; border-color: #00897b;">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modalEl = document.getElementById('modalUser');
    if (!modalEl) return;

    const btnAdd = document.getElementById('btnAddUser');
    if (btnAdd) {
        btnAdd.addEventListener('click', function () {
            document.getElementById('userId').value = '';
            document.getElementById('userUsername').value = '';
            document.getElementById('userFullname').value = '';
            document.getElementById('userEmail').value = '';
            document.getElementById('userPassword').value = '';
            document.getElementById('userRole').value = 'staff';
            document.getElementById('userAccess').value = 'all';
            document.getElementById('userCap').value = 'all';
            document.getElementById('userStatus').value = '1';
        });
    }

    document.querySelectorAll('.btn-edit-user').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const raw = this.getAttribute('data-user');
            if (!raw) return;
            try {
                const u = JSON.parse(raw);
                document.getElementById('userId').value = u.id || '';
                document.getElementById('userUsername').value = u.username || '';
                document.getElementById('userFullname').value = u.fullname || '';
                document.getElementById('userEmail').value = u.email || '';
                document.getElementById('userPassword').value = '';
                document.getElementById('userRole').value = u.role || 'staff';
                document.getElementById('userAccess').value = u.access || '';
                document.getElementById('userCap').value = u.cap || '';
                document.getElementById('userStatus').value = u.status || '1';

                if (window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            } catch (e) {
                console.error('Gagal mem-parsing data pengguna.', e);
            }
        });
    });
})();
</script>
