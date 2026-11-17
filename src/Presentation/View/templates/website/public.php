<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) ($judul ?? 'SIMRS Medars'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; margin: 0; }
        .hero { background: linear-gradient(135deg, #00897b 0%, #00695c 100%); color: #fff; padding: 48px 20px; text-align: center; }
        .container-pub { max-width: 960px; margin: 28px auto; padding: 0 16px; }
        .card-pub { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <header class="hero">
        <h1><?= htmlspecialchars((string) ($klinik_nama ?? 'SIMRS Medars'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Layanan Klinik &amp; Rumah Sakit Terintegrasi BPJS Kesehatan &amp; SatuSehat</p>
        <div style="margin-top:16px;">
            <a href="/portal-pasien" class="btn btn-light btn-sm">Portal Pasien &amp; Booking</a>
            <a href="/auth/login" class="btn btn-outline-light btn-sm">Login Staf SIMRS</a>
        </div>
    </header>
    <main class="container-pub" id="websitePublicContent">
        <?php if (empty($pages)): ?>
            <div class="card-pub">
                <h4>Selamat Datang di <?= htmlspecialchars((string) ($klinik_nama ?? 'SIMRS Medars'), ENT_QUOTES, 'UTF-8') ?></h4>
                <p>Kami melayani Poli Umum, Poli Spesialis, IGD 24 Jam, Farmasi, Laboratorium, Radiologi, serta Layanan Homecare.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pages as $p): ?>
                <article class="card-pub">
                    <h4><?= htmlspecialchars((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h4>
                    <div><?= htmlspecialchars(strip_tags((string) ($p['content_html'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
