<?php
$e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$typeVal = (string) ($type ?? 'sakit');
$noSuratVal = (string) ($no_surat ?? '');
$res = is_array($result ?? null) ? $result : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Keaslian Dokumen Medis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:680px;">
    <div class="card shadow-sm border-0">
        <div class="card-header text-white py-3" style="background-color:#00897b;">
            <h5 class="mb-0"><i class="fa-solid fa-certificate me-2"></i>Verifikasi Keaslian Surat Medis</h5>
            <small class="opacity-75">Validasi Tanda Tangan &amp; QR Code Resmi Klinik</small>
        </div>
        <div class="card-body p-4">
            <?php if ($res !== null): ?>
                <?php if ($res['valid']): ?>
                    <?php $d = $res['data'] ?? []; ?>
                    <div class="alert alert-success border-0 shadow-sm">
                        <div class="fw-bold fs-6"><i class="fa-solid fa-circle-check me-1"></i> <?= $e($res['message']) ?></div>
                        <div class="small mt-1"><?= $e($res['title']) ?> tercatat resmi di sistem.</div>
                    </div>
                    <table class="table table-sm table-bordered small mb-4">
                        <tr><th style="width:38%;">Nomor Surat</th><td class="fw-semibold"><?= $e($d['no_surat'] ?? '-') ?></td></tr>
                        <tr><th>No. Rawat</th><td><?= $e($d['no_rawat'] ?? '-') ?></td></tr>
                        <tr><th>Nama Pasien</th><td><?= $e($d['nm_pasien'] ?? '-') ?></td></tr>
                        <tr><th>Dokter Pemeriksa</th><td><?= $e($d['nm_dokter'] ?? '-') ?></td></tr>
                        <tr><th>Tanggal Terbit</th><td><?= $e($d['tanggal_mulai'] ?? $d['tanggal_periksa'] ?? $d['created_at'] ?? '-') ?></td></tr>
                    </table>
                <?php else: ?>
                    <div class="alert alert-danger border-0 shadow-sm">
                        <div class="fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Dokumen Tidak Valid / Tidak Ditemukan</div>
                        <div class="small mt-1"><?= $e($res['message']) ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="get" action="/verifikasi" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Jenis Surat</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="sakit" <?= $typeVal === 'sakit' ? 'selected' : '' ?>>Surat Sakit</option>
                        <option value="hamil" <?= $typeVal === 'hamil' ? 'selected' : '' ?>>Surat Hamil</option>
                        <option value="narkoba" <?= $typeVal === 'narkoba' ? 'selected' : '' ?>>Bebas Narkoba</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Nomor Surat</label>
                    <input type="text" name="no_surat" class="form-control form-control-sm" value="<?= $e($noSuratVal) ?>" placeholder="Contoh: SKS-2026-001" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Cek Surat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
