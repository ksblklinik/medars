<div class="container-fluid px-0" id="dokterRanapWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="ranapDocSearch" class="form-control" placeholder="Cari Pasien / Kamar Ranap...">
            </div>
            <button type="button" id="btnRanapDocRefresh" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <div class="small text-muted" id="activeRanapDocBanner">Pilih pasien rawat inap di sebelah kiri</div>
    </div>

    <div id="ranapDocAlertContainer" class="mt-2"></div>

    <div class="row g-3 mt-1">
        <!-- Kolom Kiri: Pasien Rawat Inap Aktif -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-bed-pulse me-1"></i> Pasien Rawat Inap Aktif
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 560px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Kamar</th><th>Pasien</th><th>No. Rawat</th></tr></thead>
                        <tbody id="tbodyRanapDocPatients"><tr><td colspan="3" class="text-center text-muted py-3">Memuat pasien ranap...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: CPPT / SOAP Visite, E-Resep Ranap & Resume Pulang -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2">
                    <ul class="nav nav-tabs card-header-tabs small fw-semibold" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabRanapCppt" type="button">CPPT &amp; Visite</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRanapEresep" type="button">E-Resep Ranap</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRanapResume" type="button">Resume Pulang</button></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabRanapCppt">
                            <form id="formCpptRanap" class="row g-2 mb-3">
                                <input type="hidden" id="cpptNoRawat">
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">TD</label><input type="text" id="cpptTensi" class="form-control form-control-sm" placeholder="120/80"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">Nadi</label><input type="text" id="cpptNadi" class="form-control form-control-sm" placeholder="80"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">Suhu</label><input type="text" id="cpptSuhu" class="form-control form-control-sm" placeholder="36.5"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">RR</label><input type="text" id="cpptRr" class="form-control form-control-sm" placeholder="20"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">SpO2</label><input type="text" id="cpptSpo2" class="form-control form-control-sm" placeholder="98%"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">GCS</label><input type="text" id="cpptGcs" class="form-control form-control-sm" value="15"></div>
                                <div class="col-md-6"><label class="form-label small mb-1">Subjective (S - Keluhan)</label><textarea id="cpptS" class="form-control form-control-sm" rows="2" required></textarea></div>
                                <div class="col-md-6"><label class="form-label small mb-1">Objective (O - Pemeriksaan)</label><textarea id="cpptO" class="form-control form-control-sm" rows="2"></textarea></div>
                                <div class="col-md-6"><label class="form-label small mb-1">Assessment (A - Penilaian)</label><textarea id="cpptA" class="form-control form-control-sm" rows="2"></textarea></div>
                                <div class="col-md-6"><label class="form-label small mb-1">Plan / Instruksi (P)</label><textarea id="cpptP" class="form-control form-control-sm" rows="2"></textarea></div>
                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn btn-sm btn-teal text-white">Simpan CPPT Visite</button>
                                </div>
                            </form>

                            <h6 class="small fw-bold text-medical-teal mb-2">Riwayat CPPT Pasien</h6>
                            <div id="cpptHistoryList" class="list-group list-group-flush small border rounded" style="max-height: 240px; overflow-y: auto;">
                                <div class="list-group-item text-muted">Pilih pasien untuk melihat riwayat CPPT.</div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tabRanapEresep">
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" id="ranapObatSearch" class="form-control" placeholder="Cari obat (databarang)...">
                                <button type="button" id="btnRanapSearchObat" class="btn btn-outline-teal">Cari Obat</button>
                            </div>
                            <div id="ranapObatResults" class="list-group list-group-flush small border rounded mb-2 d-none" style="max-height:140px; overflow-y:auto;"></div>
                            <div id="ranapResepDraft" class="border rounded p-2 mb-2 small bg-light">Belum ada item obat dipilih.</div>
                            <button type="button" id="btnRanapKirimResep" class="btn btn-sm btn-primary">Kirim E-Resep Ranap ke Farmasi</button>
                        </div>

                        <div class="tab-pane fade" id="tabRanapResume">
                            <form id="formRanapResume" class="row g-2 small">
                                <div class="col-md-6"><label class="form-label mb-1">Keluhan Utama</label><textarea id="ranapResKeluhan" class="form-control form-control-sm" rows="2"></textarea></div>
                                <div class="col-md-6"><label class="form-label mb-1">Jalannya Penyakit</label><textarea id="ranapResJalan" class="form-control form-control-sm" rows="2"></textarea></div>
                                <div class="col-md-4"><label class="form-label mb-1">Kode ICD-10</label><input type="text" id="ranapResKdDiag" class="form-control form-control-sm" placeholder="A91"></div>
                                <div class="col-md-4"><label class="form-label mb-1">Diagnosa Utama</label><input type="text" id="ranapResDiag" class="form-control form-control-sm"></div>
                                <div class="col-md-4"><label class="form-label mb-1">Kondisi Pulang</label><select id="ranapResKondisi" class="form-select form-select-sm"><option value="Hidup">Hidup / Membaik</option><option value="Rujuk">Rujuk</option><option value="Pulang Paksa">Pulang Paksa</option><option value="Meninggal">Meninggal</option></select></div>
                                <div class="col-12"><label class="form-label mb-1">Obat Pulang &amp; Edukasi</label><textarea id="ranapResObat" class="form-control form-control-sm" rows="2"></textarea></div>
                                <div class="col-12 d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-sm btn-teal text-white">Simpan Resume Pulang</button>
                                    <button type="button" id="btnRanapPrintResume" class="btn btn-sm btn-outline-primary">Cetak Resume</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <hr class="my-3">
                    <div class="border rounded p-2 bg-light" id="ranapQuickActionPanel">
                        <div class="small fw-bold text-medical-teal mb-2">Aksi Cepat Ranap: Tindakan &amp; Order Lab / Radiologi</div>
                        <form id="formRanapQuickAction" class="row g-2">
                            <div class="col-md-4"><input type="text" id="ranapActNoRawat" class="form-control form-control-sm" placeholder="No. Rawat" required></div>
                            <div class="col-md-3">
                                <select id="ranapActType" class="form-select form-select-sm">
                                    <option value="tindakan">Tindakan Ranap</option>
                                    <option value="lab">Order Lab Ranap</option>
                                    <option value="radiologi">Order Radiologi Ranap</option>
                                </select>
                            </div>
                            <div class="col-md-3"><input type="text" id="ranapActKode" class="form-control form-control-sm" placeholder="Kode Prw" required></div>
                            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-medical-teal w-100">Kirim</button></div>
                        </form>
                        <div id="ranapActFeedback" class="small mt-1"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/dokter-ranap.js?v=20261117b"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const api = window.MedarsApp && window.MedarsApp.apiJson ? window.MedarsApp.apiJson : null;
    if (!api) return;
    document.getElementById('formRanapQuickAction')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const type = ranapActType.value;
        const nr = ranapActNoRawat.value.trim();
        const kd = ranapActKode.value.trim();
        let res;
        if (type === 'tindakan') {
            res = await api('/api/v1/dokter-ranap/tindakan', { method: 'POST', body: JSON.stringify({ no_rawat: nr, kd_jenis_prw: kd, claim_type: 'Tunai' }) });
        } else {
            const ep = type === 'lab' ? '/api/v1/dokter-ranap/lab-order' : '/api/v1/dokter-ranap/radiologi-order';
            res = await api(ep, { method: 'POST', body: JSON.stringify({ no_rawat: nr, items: [{ kd_jenis_prw: kd, nm_perawatan: kd, harga: 50000, claim_type: 'Tunai' }] }) });
        }
        ranapActFeedback.textContent = res.status === 'success' ? 'Berhasil disimpan ke billing & antrean penunjang.' : (res.message || 'Gagal');
    });
});
</script>
