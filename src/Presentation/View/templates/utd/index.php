<?php
$csrfToken = (string) ($csrf_token ?? '');
$today = date('Y-m-d');
?>
<div class="container-fluid py-3" id="utdApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUtdPermintaan" type="button">Permintaan &amp; Crossmatch</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUtdStok" type="button">Stok Kantong Darah</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUtdPendonor" type="button">Data Pendonor</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUtdKomponen" type="button">Komponen Darah</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabUtdPermintaan">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header text-white fw-semibold" style="background-color:#00897b;">Permintaan Darah Pasien</div>
                        <div class="card-body">
                            <form id="utdReqForm">
                                <div class="mb-2"><label class="form-label small">No. Rawat *</label><input type="text" id="utdReqRawat" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Golongan Darah</label><select id="utdReqGol" class="form-select form-select-sm"><option value="A">A</option><option value="B">B</option><option value="AB">AB</option><option value="O">O</option></select></div>
                                <div class="mb-3"><label class="form-label small">No. Kantong (Opsional)</label><input type="text" id="utdReqKantong" class="form-control form-control-sm"></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Ajukan Permintaan</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Antrean Crossmatch &amp; Penyerahan</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Tanggal</th><th>No. Rawat</th><th>Gol</th><th>No. Kantong</th><th>Crossmatch</th><th>Status</th><th></th></tr></thead>
                                <tbody id="utdReqBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabUtdStok">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Tambah Kantong Darah</div>
                        <div class="card-body">
                            <form id="utdStokForm">
                                <div class="mb-2"><label class="form-label small">No. Kantong *</label><input type="text" id="utdStKantong" class="form-control form-control-sm" required></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label small">Komponen</label><input type="text" id="utdStKomp" class="form-control form-control-sm" value="PRC"></div>
                                    <div class="col-6"><label class="form-label small">Golongan</label><select id="utdStGol" class="form-select form-select-sm"><option value="A">A</option><option value="B">B</option><option value="AB">AB</option><option value="O">O</option></select></div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label class="form-label small">Tgl Aftap</label><input type="date" id="utdStAftap" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"></div>
                                    <div class="col-6"><label class="form-label small">Kadaluarsa</label><input type="date" id="utdStExp" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"></div>
                                </div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Kantong</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Stok Kantong Darah</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>No. Kantong</th><th>Komponen</th><th>Gol/Rh</th><th>Kadaluarsa</th><th>Status</th></tr></thead>
                                <tbody id="utdStokBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabUtdPendonor">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Registrasi Pendonor</div>
                        <div class="card-body">
                            <form id="utdPendonorForm">
                                <div class="mb-2"><label class="form-label small">No. Pendonor *</label><input type="text" id="utdPnNo" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Nama *</label><input type="text" id="utdPnNama" class="form-control form-control-sm" required></div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label class="form-label small">Golongan</label><select id="utdPnGol" class="form-select form-select-sm"><option value="A">A</option><option value="B">B</option><option value="AB">AB</option><option value="O">O</option></select></div>
                                    <div class="col-6"><label class="form-label small">Telp</label><input type="text" id="utdPnTelp" class="form-control form-control-sm"></div>
                                </div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Pendonor</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Daftar Pendonor</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>No. Pendonor</th><th>Nama</th><th>Gol/Rh</th><th>Telp</th></tr></thead>
                                <tbody id="utdPendonorBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabUtdKomponen">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Master Komponen Darah</div>
                        <div class="card-body">
                            <form id="utdKompForm">
                                <div class="mb-2"><label class="form-label small">Kode *</label><input type="text" id="utdKmKode" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Nama Komponen *</label><input type="text" id="utdKmNama" class="form-control form-control-sm" required></div>
                                <div class="mb-3"><label class="form-label small">Tarif (Rp)</label><input type="number" id="utdKmTotal" class="form-control form-control-sm" value="360000"></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Komponen</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Daftar Komponen Darah</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama</th><th>Masa Simpan (Hari)</th><th class="text-end">Tarif</th></tr></thead>
                                <tbody id="utdKompBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/utd.js?v=<?= time() ?>"></script>
