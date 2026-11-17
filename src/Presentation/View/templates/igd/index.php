<div class="container-fluid px-0" id="igdWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white text-muted">Dari</span>
                <input type="date" id="igdFilterFrom" class="form-control" value="<?= date('Y-m-d') ?>">
                <span class="input-group-text bg-white text-muted">s/d</span>
                <input type="date" id="igdFilterTo" class="form-control" value="<?= date('Y-m-d') ?>">
                <button type="button" id="btnIgdRefresh" class="btn btn-outline-teal">Filter</button>
            </div>
        </div>
        <div class="small text-muted">IGD &amp; Triase ATS/ESI (Prioritas Merah &rarr; Kuning &rarr; Hijau)</div>
    </div>

    <div id="igdAlertContainer" class="mt-2"></div>

    <div class="row g-3 mt-1">
        <!-- Daftar Pasien IGD -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">
                    <i class="fa fa-truck-medical me-1"></i> Antrean Pasien IGD
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 540px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Zona</th><th>No. Rawat</th><th>Pasien</th><th>Keluhan / SpO2</th><th>Disposisi</th></tr></thead>
                        <tbody id="tbodyIgdVisits"><tr><td colspan="5" class="text-center text-muted py-3">Memuat antrean IGD...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Form Triase & Disposisi IGD -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white border-bottom py-2 fw-semibold text-medical-teal">
                    <i class="fa fa-heart-pulse me-1"></i> Form Triase &amp; Disposisi IGD
                </div>
                <div class="card-body">
                    <form id="formIgdTriase" class="row g-2">
                        <div class="col-md-6"><label class="form-label small mb-1">No. Rawat</label><input type="text" id="igdNoRawat" class="form-control form-control-sm" required></div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Zona Triase</label>
                            <select id="igdZona" class="form-select form-select-sm">
                                <option value="Merah">Merah (Resusitasi)</option>
                                <option value="Kuning">Kuning (Urgent)</option>
                                <option value="Hijau" selected>Hijau (Non-Urgent)</option>
                                <option value="Hitam">Hitam (DOA)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Cara Masuk</label>
                            <select id="igdCaraMasuk" class="form-select form-select-sm">
                                <option>Jalan</option>
                                <option>Brankar</option>
                                <option>Kursi Roda</option>
                                <option>Ambulans</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label small mb-1">Keluhan Utama / Kondisi Datang</label><textarea id="igdKeluhan" class="form-control form-control-sm" rows="2" required></textarea></div>
                        <div class="col-4 col-md-2"><label class="form-label small mb-1">TD</label><input type="text" id="igdTensi" class="form-control form-control-sm" placeholder="120/80"></div>
                        <div class="col-4 col-md-2"><label class="form-label small mb-1">Nadi</label><input type="text" id="igdNadi" class="form-control form-control-sm" placeholder="88"></div>
                        <div class="col-4 col-md-2"><label class="form-label small mb-1">Suhu</label><input type="text" id="igdSuhu" class="form-control form-control-sm" placeholder="36.8"></div>
                        <div class="col-4 col-md-2"><label class="form-label small mb-1">RR</label><input type="text" id="igdRr" class="form-control form-control-sm" placeholder="20"></div>
                        <div class="col-4 col-md-2"><label class="form-label small mb-1">SpO2 (%)</label><input type="text" id="igdSpo2" class="form-control form-control-sm" placeholder="98"></div>
                        <div class="col-4 col-md-2"><label class="form-label small mb-1">GCS</label><input type="text" id="igdGcs" class="form-control form-control-sm" value="15"></div>
                        <div class="col-12 d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-sm btn-teal text-white">Simpan Triase</button>
                        </div>
                    </form>

                    <hr class="my-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold mb-1">Disposisi Keluar IGD</label>
                            <select id="igdDisposisi" class="form-select form-select-sm">
                                <option value="Observasi">Observasi IGD</option>
                                <option value="Pulang">Pulang / Rawat Jalan</option>
                                <option value="Rawat Inap">Masuk Rawat Inap (Ranap)</option>
                                <option value="Rujuk">Rujuk RS Lain</option>
                                <option value="Meninggal">Meninggal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="button" id="btnIgdDisposisi" class="btn btn-sm btn-outline-teal w-100">Update Disposisi &amp; Status</button>
                        </div>
                    </div>

                    <hr class="my-3">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a href="/dokter-ralan" class="btn btn-sm btn-outline-primary"><i class="fa fa-stethoscope me-1"></i>SOAP / Resep / Penunjang Dokter IGD</a>
                        <a href="/kasir" class="btn btn-sm btn-outline-success"><i class="fa fa-cash-register me-1"></i>Kasir &amp; Billing IGD</a>
                    </div>

                    <div class="border rounded p-2 bg-light" id="igdClinicalActionsPanel">
                        <div class="small fw-bold text-medical-teal mb-2">Aksi Cepat IGD: Tindakan &amp; Order Lab / Radiologi Cito</div>
                        <form id="formIgdQuickAction" class="row g-2">
                            <div class="col-md-4"><input type="text" id="igdActNoRawat" class="form-control form-control-sm" placeholder="No. Rawat" required></div>
                            <div class="col-md-3">
                                <select id="igdActType" class="form-select form-select-sm">
                                    <option value="tindakan">Tindakan IGD</option>
                                    <option value="lab">Order Lab Cito</option>
                                    <option value="radiologi">Order Radiologi Cito</option>
                                </select>
                            </div>
                            <div class="col-md-3"><input type="text" id="igdActKode" class="form-control form-control-sm" placeholder="Kode Prw (TND01/LAB01)" required></div>
                            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-medical-teal w-100">Kirim</button></div>
                        </form>
                        <div id="igdActFeedback" class="small mt-1"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/igd.js?v=20261117b"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const api = window.MedarsApp && window.MedarsApp.apiJson ? window.MedarsApp.apiJson : null;
    if (!api) return;
    document.getElementById('formIgdQuickAction')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const type = igdActType.value;
        const nr = igdActNoRawat.value.trim();
        const kd = igdActKode.value.trim();
        let res;
        if (type === 'tindakan') {
            res = await api('/api/v1/igd/tindakan', { method: 'POST', body: JSON.stringify({ no_rawat: nr, kd_jenis_prw: kd, claim_type: 'Tunai' }) });
        } else {
            const ep = type === 'lab' ? '/api/v1/igd/lab-order' : '/api/v1/igd/radiologi-order';
            res = await api(ep, { method: 'POST', body: JSON.stringify({ no_rawat: nr, items: [{ kd_jenis_prw: kd, nm_perawatan: kd, harga: 50000, claim_type: 'Tunai' }] }) });
        }
        igdActFeedback.textContent = res.status === 'success' ? 'Berhasil disimpan ke billing & antrean penunjang.' : (res.message || 'Gagal');
    });
});
</script>
