<?php
$csrfToken = (string) ($csrf_token ?? '');
$today = date('Y-m-d');
?>
<div class="container-fluid py-3" id="veronisaApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabVrClaims" type="button">Verifikasi Klaim &amp; Non-Kapitasi</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVrMapping" type="button">Mapping Obat DPHO</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabVrClaims">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header text-white fw-semibold" style="background-color:#00897b;">Ajukan Verifikasi Klaim</div>
                        <div class="card-body">
                            <form id="vrClaimForm">
                                <div class="mb-2"><label class="form-label small">No. Rawat *</label><input type="text" id="vrRawat" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">No. RM</label><input type="text" id="vrRm" class="form-control form-control-sm"></div>
                                <div class="mb-2"><label class="form-label small">No. SEP</label><input type="text" id="vrSep" class="form-control form-control-sm"></div>
                                <div class="mb-3"><label class="form-label small">Catatan</label><textarea id="vrCatatan" class="form-control form-control-sm" rows="2"></textarea></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Pengajuan</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <span class="fw-semibold">Daftar Klaim Veronisa</span>
                            <div class="d-flex gap-2">
                                <input type="date" id="vrFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="date" id="vrTo" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="text" id="vrSearch" class="form-control form-control-sm" placeholder="Cari SEP / No. Rawat...">
                                <button type="button" id="vrFilterBtn" class="btn btn-sm btn-outline-secondary">Filter</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Tanggal</th><th>No. Rawat</th><th>No. SEP</th><th>Status</th><th>Catatan</th><th class="text-end">Aksi</th></tr></thead>
                                <tbody id="vrClaimsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabVrMapping">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Mapping Kode Barang &harr; DPHO</div>
                        <div class="card-body">
                            <form id="vrMapForm">
                                <div class="mb-2"><label class="form-label small">Kode Barang RS *</label><input type="text" id="vrMapBrng" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Kode DPHO BPJS *</label><input type="text" id="vrMapDpho" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Nama Obat DPHO</label><input type="text" id="vrMapNama" class="form-control form-control-sm"></div>
                                <div class="mb-3"><label class="form-label small">Harga Plafon DPHO</label><input type="number" id="vrMapHarga" class="form-control form-control-sm" value="0"></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Mapping</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Daftar Mapping Obat DPHO</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Kode RS</th><th>Kode DPHO</th><th>Nama DPHO</th><th class="text-end">Harga</th></tr></thead>
                                <tbody id="vrMapBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/veronisa.js?v=<?= time() ?>"></script>
