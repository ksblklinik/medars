<!-- Toolbar Paritas ksmlite (Toggle Form, Cari RM/Nama/NIK/BPJS) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-medical-teal" id="btnTogglePasienForm" data-ksmlite-toggle-form="formPasienCard">
            <i class="fa fa-minus-circle me-1"></i> Tutup Form
        </button>
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" id="pasienSearchInput" placeholder="Cari RM / Nama / NIK / BPJS / HP...">
        </div>
        <button type="button" class="btn btn-sm btn-outline-teal" id="btnSearchPasien">Cari</button>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border" id="pasienTotalBadge">Total: 0 Pasien</span>
    </div>
</div>

<div class="row g-3" id="pasienWorkspace">
    <!-- Panel Form Master Pasien (#form) -->
    <div class="col-lg-5 col-12">
        <div class="card shadow-sm border-0" id="formPasienCard">
            <div class="card-header bg-medical-teal text-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="fa fa-address-card me-1"></i> Form Master Pasien</span>
                <button type="button" class="btn btn-xs btn-light text-dark" id="btnPasienMaxId" title="Generate No. RM Berikutnya">
                    <i class="fa fa-wand-magic-sparkles me-1"></i>Auto RM
                </button>
            </div>
            <div class="card-body p-3">
                <form id="formMasterPasien" autocomplete="off">
                    <div class="row g-2 mb-2">
                        <div class="col-sm-5">
                            <label class="form-label small fw-semibold mb-1">No. Rekam Medis</label>
                            <input type="text" class="form-control form-control-sm" id="pasNoRm" placeholder="(Otomatis jika kosong)">
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label small fw-semibold mb-1">NIK / No. KTP</label>
                            <input type="text" class="form-control form-control-sm" id="pasNik" maxlength="20">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-8">
                            <label class="form-label small fw-semibold mb-1">Nama Lengkap *</label>
                            <input type="text" class="form-control form-control-sm" id="pasNama" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small fw-semibold mb-1">JK *</label>
                            <select class="form-select form-select-sm" id="pasJk" required>
                                <option value="L">Laki-laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Tempat Lahir</label>
                            <input type="text" class="form-control form-control-sm" id="pasTmpLahir">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Tanggal Lahir *</label>
                            <input type="date" class="form-control form-control-sm" id="pasTglLahir" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-4">
                            <label class="form-label small mb-1">Gol. Darah</label>
                            <select class="form-select form-select-sm" id="pasGolDarah">
                                <option value="-">-</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="AB">AB</option>
                                <option value="O">O</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small mb-1">Agama</label>
                            <select class="form-select form-select-sm" id="pasAgama">
                                <option value="ISLAM">ISLAM</option>
                                <option value="KRISTEN">KRISTEN</option>
                                <option value="KATOLIK">KATOLIK</option>
                                <option value="HINDU">HINDU</option>
                                <option value="BUDHA">BUDHA</option>
                                <option value="KONGHUCU">KONGHUCU</option>
                                <option value="-">-</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small mb-1">Stts Nikah</label>
                            <select class="form-select form-select-sm" id="pasSttsNikah">
                                <option value="BELUM MENIKAH">BELUM MENIKAH</option>
                                <option value="MENIKAH">MENIKAH</option>
                                <option value="JANDA">JANDA</option>
                                <option value="DUDA">DUDA</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">No. HP / WhatsApp</label>
                            <input type="text" class="form-control form-control-sm" id="pasTelp" placeholder="08...">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold mb-1">Nama Ibu Kandung</label>
                            <input type="text" class="form-control form-control-sm" id="pasIbu" value="-">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-5">
                            <label class="form-label small fw-semibold mb-1">Penjamin (Cara Bayar)</label>
                            <select class="form-select form-select-sm" id="pasKdPj">
                                <option value="UMU">Umum / Tunai (UMU)</option>
                                <option value="BPJ">BPJS Kesehatan (BPJ)</option>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label small fw-semibold mb-1">No. Kartu BPJS / Peserta</label>
                            <input type="text" class="form-control form-control-sm" id="pasNoPeserta">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Alamat Lengkap</label>
                        <textarea class="form-control form-control-sm" id="pasAlamat" rows="2"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-5">
                            <label class="form-label small mb-1">Hubungan Keluarga</label>
                            <select class="form-select form-select-sm" id="pasKeluarga">
                                <option value="DIRI SENDIRI">DIRI SENDIRI</option>
                                <option value="AYAH">AYAH</option>
                                <option value="IBU">IBU</option>
                                <option value="ISTRI">ISTRI</option>
                                <option value="SUAMI">SUAMI</option>
                                <option value="SAUDARA">SAUDARA</option>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label small mb-1">Nama Penanggung Jawab</label>
                            <input type="text" class="form-control form-control-sm" id="pasNamaKeluarga" value="-">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-sm btn-medical-teal flex-grow-1" id="btnSavePasien">
                            <i class="fa fa-save me-1"></i> Simpan Pasien
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetPasien">
                            <i class="fa fa-eraser me-1"></i> Reset
                        </button>
                    </div>
                </form>
                <div id="pasienFeedback" class="mt-2"></div>
            </div>
        </div>
    </div>

    <!-- Panel Daftar Pasien (#display) -->
    <div class="col-lg-7 col-12">
        <div class="card shadow-sm border-0" id="displayPasienCard">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-users me-1"></i> Data Induk Pasien</h6>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnRefreshPasien"><i class="fa fa-rotate"></i></button>
            </div>
            <div class="card-body p-0" style="max-height: 560px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2">No. RM</th>
                                <th>Nama Pasien</th>
                                <th>JK / Tgl Lahir</th>
                                <th>No. Telp / BPJS</th>
                                <th class="text-end pe-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="pasienTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-4 small">Memuat data pasien...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnPasienPrev">Sebelumnya</button>
                <span class="small text-muted" id="pasienPageInfo">Halaman 1</span>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnPasienNext">Berikutnya</button>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/pasien.js?v=20261117a" defer></script>
