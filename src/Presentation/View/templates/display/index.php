<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Display Antrean Poliklinik - <?= $this->e($klinik_nama ?? 'SIMRS Medars') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-tv: #0c121e;
            --card-tv: #162032;
            --border-tv: rgba(255,255,255,0.08);
            --teal: #00897b;
            --teal-bright: #26a69a;
            --gold: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        body {
            background-color: var(--bg-tv);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            user-select: none;
        }
        .header-tv {
            background-color: rgba(12, 18, 30, 0.95);
            border-bottom: 2px solid var(--teal);
            height: 70px;
        }
        .clinic-brand {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #ffffff;
        }
        .clock-tv {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--teal-bright);
        }
        .card-poli {
            background-color: var(--card-tv);
            border: 1px solid var(--border-tv);
            border-radius: 16px;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .card-poli.calling {
            border-color: var(--gold);
            animation: pulseCard 1.5s infinite;
        }
        @keyframes pulseCard {
            0%, 100% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.4); }
            50% { box-shadow: 0 0 35px rgba(245, 158, 11, 0.8); }
        }
        .card-poli-header {
            background: linear-gradient(135deg, var(--teal), #004d40);
            padding: 12px 18px;
            color: #ffffff;
        }
        .poli-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .doctor-name {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.85);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .queue-display-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .queue-number-large {
            font-family: 'JetBrains Mono', monospace;
            font-size: 4.5rem;
            font-weight: 800;
            color: var(--gold);
            line-height: 1;
            letter-spacing: 2px;
            text-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
        }
        .queue-status-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-top: 6px;
            font-weight: 600;
        }
        .next-queue-bar {
            background-color: rgba(0,0,0,0.3);
            border-top: 1px solid var(--border-tv);
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        .marquee-footer {
            height: 45px;
            background-color: #080c14;
            border-top: 1px solid var(--border-tv);
            display: flex;
            align-items: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            overflow: hidden;
            white-space: nowrap;
        }
    </style>
</head>
<body id="displayTvAntrean">
    <!-- Header TV -->
    <header class="header-tv px-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <i class="fa fa-hospital-user text-teal fa-2x"></i>
            <div>
                <div class="clinic-brand"><?= $this->e($klinik_nama ?? 'SIMRS Medars') ?></div>
                <div class="small text-muted">Layar Display Informasi Antrean Pasien</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-4">
            <div class="badge bg-success-subtle text-success border border-success px-3 py-2 small" id="sseTvBadge">
                <i class="fa fa-circle-dot text-success animate-pulse me-1"></i> Real-Time SSE Aktif
            </div>
            <div class="clock-tv" id="tvLiveClock">--:--:-- WIB</div>
        </div>
    </header>

    <!-- Main Container Antrean Poliklinik -->
    <main class="container-fluid p-4" style="height: calc(100vh - 115px);">
        <div class="row g-4 h-100" id="queueCardsContainer">
            <?php if (!empty($poliklinik)): ?>
                <?php foreach ($poliklinik as $poli): ?>
                    <div class="col-lg-4 col-md-6 col-12 h-50">
                        <div class="card-poli" id="cardPoli_<?= $this->e($poli->kdPoli) ?>">
                            <div class="card-poli-header d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="poli-name"><?= $this->e($poli->nmPoli) ?></div>
                                    <div class="doctor-name" id="doc_<?= $this->e($poli->kdPoli) ?>">Dokter Bertugas</div>
                                </div>
                                <span class="badge bg-white text-dark small" id="room_<?= $this->e($poli->kdPoli) ?>">Ruang Layanan</span>
                            </div>
                            <div class="queue-display-box">
                                <div class="queue-number-large" id="current_<?= $this->e($poli->kdPoli) ?>">---</div>
                                <div class="queue-status-label" id="status_<?= $this->e($poli->kdPoli) ?>">MENUNGGU PANGGILAN</div>
                            </div>
                            <div class="next-queue-bar">
                                <span class="text-muted"><i class="fa fa-forward me-1"></i> Antrean Selanjutnya:</span>
                                <span class="fw-bold font-monospace text-teal" id="next_<?= $this->e($poli->kdPoli) ?>">-</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted py-5 fs-4">Tidak ada poliklinik aktif saat ini.</div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer Berjalan (Marquee) -->
    <footer class="marquee-footer px-4">
        <marquee behavior="scroll" direction="left">
            Selamat Datang di <?= $this->e($klinik_nama ?? 'SIMRS Medars') ?>. Harap memperhatikan nomor antrean dan ruang poliklinik Anda saat dipanggil. Pasien Mobile JKN dapat melakukan check-in mandiri di Layar Kiosk.
        </marquee>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/modules/display-tv.js"></script>
</body>
</html>
