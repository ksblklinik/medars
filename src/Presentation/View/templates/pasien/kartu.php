<?php
    $p = is_array($pasien ?? null) ? $pasien : [];
    $rm = (string) ($p['no_rkm_medis'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Pasien - <?= htmlspecialchars($rm, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 24px; }
        .kartu { width: 340px; border: 2px solid #00897b; border-radius: 10px; background: #ffffff; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.12); margin: 0 auto; }
        .kartu-header { background: #00897b; color: #ffffff; padding: 10px 14px; font-weight: bold; font-size: 14px; }
        .kartu-body { padding: 14px; font-size: 12.5px; line-height: 1.5; }
        .rm-box { font-size: 20px; font-weight: bold; letter-spacing: 2px; color: #00695c; border: 1px dashed #00897b; padding: 6px; text-align: center; margin-bottom: 10px; background: #e0f2f1; }
        .print-actions { text-align: center; margin-top: 16px; }
        @media print { .print-actions { display: none; } body { background: #ffffff; padding: 0; } }
    </style>
</head>
<body>
    <div class="kartu">
        <div class="kartu-header"><?= htmlspecialchars((string) ($klinik_nama ?? 'SIMRS Medars'), ENT_QUOTES, 'UTF-8') ?> — KARTU BEROBAT</div>
        <div class="kartu-body">
            <div class="rm-box"><?= htmlspecialchars($rm, ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>Nama:</strong> <?= htmlspecialchars((string) ($p['nm_pasien'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>JK / Tgl Lahir:</strong> <?= htmlspecialchars((string) ($p['jk'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) ($p['tgl_lahir'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>Alamat:</strong> <?= htmlspecialchars((string) ($p['alamat'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>No. Telp:</strong> <?= htmlspecialchars((string) ($p['no_tlp'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    <div class="print-actions">
        <button onclick="window.print()">Cetak Kartu</button>
    </div>
</body>
</html>
