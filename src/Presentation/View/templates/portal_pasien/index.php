<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) ($judul ?? 'Portal Pasien Mandiri'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>body{background:#f4f7f6}.bg-teal{background:#00897b;color:#fff}.btn-teal{background:#00897b;color:#fff;border-color:#00897b}</style>
</head>
<body>
<nav class="navbar bg-teal px-3 py-2 shadow-sm mb-4">
    <span class="navbar-brand text-white fw-bold m-0"><i class="fa fa-hospital-user me-2"></i>Portal Pasien Mandiri</span>
    <a href="/auth/login" class="btn btn-sm btn-outline-light">Login Staf</a>
</nav>
<div class="container pb-5">
    <div id="portalAlert"></div>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-bold" style="color:#00897b;">Verifikasi Identitas Pasien</div>
        <div class="card-body">
            <form id="formPortalLookup" class="row g-2 align-items-end">
                <div class="col-md-5"><label class="form-label small mb-1">No. Rekam Medis</label><input type="text" id="portalRm" class="form-control form-control-sm" placeholder="Contoh: 000001" required></div>
                <div class="col-md-5"><label class="form-label small mb-1">Tanggal Lahir</label><input type="date" id="portalTglLahir" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><button type="submit" class="btn btn-sm btn-teal w-100">Masuk Portal</button></div>
            </form>
        </div>
    </div>

    <div id="portalContent" class="d-none">
        <div class="alert alert-light border shadow-sm mb-3" id="portalPatientBanner"></div>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-teal fw-semibold">Booking Antrean Online</div>
                    <div class="card-body">
                        <form id="formPortalBooking" class="row g-2">
                            <div class="col-12"><label class="form-label small mb-1">Tanggal Periksa</label><input type="date" id="bookTgl" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
                            <div class="col-12"><label class="form-label small mb-1">Poliklinik</label><select id="bookPoli" class="form-select form-select-sm" required></select></div>
                            <div class="col-12"><label class="form-label small mb-1">Dokter</label><select id="bookDokter" class="form-select form-select-sm" required></select></div>
                            <div class="col-12 mt-2"><button type="submit" class="btn btn-sm btn-teal w-100">Buat Booking Antrean</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white fw-bold" style="color:#00897b;">Riwayat Kunjungan &amp; Booking</div>
                    <div class="card-body p-0 table-responsive"><table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Tanggal</th><th>Poli / Dokter</th><th>Status</th></tr></thead><tbody id="tbodyPortalVisits"></tbody></table></div>
                </div>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold" style="color:#00897b;">Hasil Laboratorium</div>
                    <div class="card-body p-0 table-responsive"><table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Tanggal</th><th>Parameter</th><th>Hasil</th><th>Rujukan</th></tr></thead><tbody id="tbodyPortalLabs"></tbody></table></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/portal-pasien.js?v=20261117b"></script>
</body>
</html>
