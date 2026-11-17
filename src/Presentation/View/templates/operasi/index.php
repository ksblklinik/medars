<?php
$csrfToken = (string) ($csrf_token ?? '');
$today = date('Y-m-d');
?>
<div class="container-fluid py-3" id="operasiApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabBookingOp" type="button">Jadwal &amp; Booking Operasi</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLaporanOp" type="button">Laporan Operasi &amp; Tim Bedah</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPaketOp" type="button">Paket Tindakan Operasi</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabBookingOp">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header text-white fw-semibold" style="background-color:#00897b;">Jadwalkan Operasi</div>
                        <div class="card-body">
                            <form id="opBookingForm">
                                <div class="mb-2"><label class="form-label small">No. Rawat *</label><input type="text" id="opBkRawat" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Paket Operasi *</label><select id="opBkPaket" class="form-select form-select-sm"></select></div>
                                <div class="mb-2"><label class="form-label small">Tanggal</label><input type="date" id="opBkTgl" class="form-control form-control-sm" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label small">Mulai</label><input type="time" id="opBkMulai" class="form-control form-control-sm" value="08:00"></div>
                                    <div class="col-6"><label class="form-label small">Selesai</label><input type="time" id="opBkSelesai" class="form-control form-control-sm" value="09:00"></div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label class="form-label small">Dokter Operator</label><input type="text" id="opBkDokter" class="form-control form-control-sm" placeholder="DR01"></div>
                                    <div class="col-6"><label class="form-label small">Ruang OK</label><input type="text" id="opBkRuang" class="form-control form-control-sm" value="OK-1"></div>
                                </div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Jadwal</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Daftar Jadwal Operasi</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Tanggal</th><th>Jam</th><th>No. Rawat</th><th>Paket</th><th>Ruang</th><th>Status</th><th></th></tr></thead>
                                <tbody id="opBookingBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabLaporanOp">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Input Laporan Operasi &amp; Tim Bedah</div>
                        <div class="card-body">
                            <form id="opLaporanForm">
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label small">No. Rawat *</label><input type="text" id="opLpRawat" class="form-control form-control-sm" required></div>
                                    <div class="col-6"><label class="form-label small">Paket Operasi</label><select id="opLpPaket" class="form-select form-select-sm"></select></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label small">Diagnosa Pre-Op</label><input type="text" id="opLpPre" class="form-control form-control-sm"></div>
                                    <div class="col-6"><label class="form-label small">Diagnosa Post-Op</label><input type="text" id="opLpPost" class="form-control form-control-sm"></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label small">Operator</label><input type="text" id="opLpOp1" class="form-control form-control-sm"></div>
                                    <div class="col-4"><label class="form-label small">Asisten</label><input type="text" id="opLpAs1" class="form-control form-control-sm"></div>
                                    <div class="col-4"><label class="form-label small">Anestesi</label><input type="text" id="opLpAnestesi" class="form-control form-control-sm"></div>
                                </div>
                                <div class="mb-3"><label class="form-label small">Uraian Prosedur / Laporan *</label><textarea id="opLpIsi" class="form-control form-control-sm" rows="3" required></textarea></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Laporan &amp; Posting Billing</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Riwayat Laporan Operasi</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Tanggal</th><th>No. Rawat</th><th>Pre/Post-Op</th><th>Operator</th><th>Laporan</th></tr></thead>
                                <tbody id="opLaporanBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabPaketOp">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Tambah / Edit Paket Operasi</div>
                        <div class="card-body">
                            <form id="opPaketForm">
                                <div class="mb-2"><label class="form-label small">Kode Paket *</label><input type="text" id="opPkKode" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Nama Tindakan *</label><input type="text" id="opPkNama" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Kategori</label><input type="text" id="opPkKat" class="form-control form-control-sm" value="Bedah Minor"></div>
                                <div class="mb-3"><label class="form-label small">Tarif Total (Rp)</label><input type="number" id="opPkTarif" class="form-control form-control-sm" value="0"></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Paket</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Daftar Paket Operasi</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Kode</th><th>Nama Tindakan</th><th>Kategori</th><th class="text-end">Tarif</th></tr></thead>
                                <tbody id="opPaketBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/operasi.js?v=<?= time() ?>"></script>
