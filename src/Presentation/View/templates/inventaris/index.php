<div class="container-fluid px-0" id="inventarisWorkspace">
    <div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm toolbar-search">
                <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                <input type="search" id="invSearchInput" class="form-control" placeholder="Cari Kode / Nama Aset / Lokasi...">
            </div>
            <button type="button" id="btnInvSearch" class="btn btn-sm btn-outline-teal">Cari</button>
        </div>
        <div class="small text-muted">Inventaris Barang Medis &amp; Non-Medis</div>
    </div>

    <div id="invAlert" class="mt-2"></div>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-medical-teal text-white py-2 fw-semibold">Form Aset Inventaris</div>
                <div class="card-body">
                    <form id="formInventaris" class="row g-2">
                        <div class="col-5"><label class="form-label small mb-1">Kode Aset</label><input type="text" id="invKode" class="form-control form-control-sm" required></div>
                        <div class="col-7"><label class="form-label small mb-1">Nama Aset</label><input type="text" id="invNama" class="form-control form-control-sm" required></div>
                        <div class="col-6"><label class="form-label small mb-1">Kategori</label><select id="invKategori" class="form-select form-select-sm"><option>Alat Medis</option><option>Elektronik / IT</option><option>Furnitur</option><option>Kendaraan</option></select></div>
                        <div class="col-6"><label class="form-label small mb-1">Lokasi</label><input type="text" id="invLokasi" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small mb-1">Tgl Perolehan</label><input type="date" id="invTgl" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-6"><label class="form-label small mb-1">Nilai (Rp)</label><input type="number" id="invNilai" class="form-control form-control-sm" value="0" min="0"></div>
                        <div class="col-12"><label class="form-label small mb-1">Kondisi</label><select id="invKondisi" class="form-select form-select-sm"><option>Baik</option><option>Rusak Ringan</option><option>Rusak Berat</option></select></div>
                        <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal text-white w-100">Simpan Aset</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light"><tr><th>Kode</th><th>Nama Aset</th><th>Kategori</th><th>Lokasi</th><th class="text-end">Nilai</th><th>Kondisi</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody id="tbodyInventaris"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/inventaris.js?v=20261117b"></script>
