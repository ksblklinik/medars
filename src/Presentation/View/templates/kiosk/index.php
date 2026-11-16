<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="csrf-token" content="<?= $this->e($csrf_token ?? '') ?>">
    <title>Layar Kiosk Mandiri - <?= $this->e($klinik_nama ?? 'SIMRS Medars') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-kiosk: #0f172a;
            --surface-kiosk: rgba(30, 41, 59, 0.7);
            --border-kiosk: rgba(255, 255, 255, 0.1);
            --teal: #00897b;
            --teal-light: #26a69a;
            --blue: #2563eb;
            --green: #10b981;
            --purple: #8b5cf6;
        }
        body {
            background-color: var(--bg-kiosk);
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
            user-select: none;
            -webkit-user-select: none;
            touch-action: manipulation;
        }
        .kiosk-wrapper {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px;
        }
        .kiosk-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-kiosk);
            padding-bottom: 20px;
        }
        .kiosk-title {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
        }
        .kiosk-card-action {
            background: var(--surface-kiosk);
            border: 2px solid var(--border-kiosk);
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            text-decoration: none;
            color: #ffffff;
        }
        .kiosk-card-action:active, .kiosk-card-action:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--teal-light);
            background: rgba(45, 62, 80, 0.9);
            color: #ffffff;
        }
        .action-icon {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 24px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .icon-daftar { background: linear-gradient(135deg, var(--teal), #004d40); }
        .icon-checkin { background: linear-gradient(135deg, var(--green), #047857); }
        .icon-consent { background: linear-gradient(135deg, var(--blue), #1e40af); }
        .action-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; }
        .action-desc { font-size: 0.95rem; color: #94a3b8; line-height: 1.5; }
        .kiosk-clock {
            font-family: 'JetBrains Mono', monospace;
            font-size: 2rem;
            font-weight: 700;
            color: var(--teal-light);
        }
        /* Virtual Keypad Styling */
        .keypad-btn {
            font-size: 1.8rem;
            font-weight: 700;
            height: 70px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="kiosk-wrapper">
        <!-- Header -->
        <header class="kiosk-header">
            <div class="d-flex align-items-center gap-3">
                <i class="fa fa-hospital-user text-teal fa-3x"></i>
                <div>
                    <h1 class="kiosk-title mb-0"><?= $this->e($klinik_nama ?? 'SIMRS Medars') ?></h1>
                    <div class="text-muted fs-5">Anjungan Pendaftaran & Check-in Mandiri Pasien</div>
                </div>
            </div>
            <div class="text-end">
                <div class="kiosk-clock" id="kioskClock">--:--:-- WIB</div>
                <div class="text-muted small"><?= date('l, d F Y') ?></div>
            </div>
        </header>

        <!-- Menu Utama Kiosk (3 Tombol Touch Utama) -->
        <main class="my-auto">
            <div class="row g-4 justify-content-center">
                <!-- 1. Ambil Antrean Baru -->
                <div class="col-lg-4 col-12">
                    <div class="kiosk-card-action" id="btnKioskAmbilAntrean">
                        <div class="action-icon icon-daftar">
                            <i class="fa fa-ticket-simple"></i>
                        </div>
                        <h2 class="action-title">Ambil Antrean Baru</h2>
                        <p class="action-desc">Pasien datang langsung (Walk-in) untuk mendapatkan nomor antrean pendaftaran poliklinik hari ini.</p>
                        <span class="badge bg-teal-subtle text-teal border border-teal px-3 py-2 fs-6 mt-2">Sentuh untuk Memulai</span>
                    </div>
                </div>

                <!-- 2. Check-in Mobile JKN -->
                <div class="col-lg-4 col-12">
                    <div class="kiosk-card-action" id="btnKioskCheckInJkn">
                        <div class="action-icon icon-checkin">
                            <i class="fa fa-qrcode"></i>
                        </div>
                        <h2 class="action-title">Check-in Mobile JKN</h2>
                        <p class="action-desc">Pasien yang telah mendaftar online melalui aplikasi Mobile JKN / Antrean Online BPJS.</p>
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 fs-6 mt-2">Scan QR / Masukkan NIK</span>
                    </div>
                </div>

                <!-- 3. Persetujuan Umum (General Consent) -->
                <div class="col-lg-4 col-12">
                    <div class="kiosk-card-action" id="btnKioskGeneralConsent">
                        <div class="action-icon icon-consent">
                            <i class="fa fa-file-signature"></i>
                        </div>
                        <h2 class="action-title">General Consent</h2>
                        <p class="action-desc">Persetujuan umum hak dan kewajiban pasien serta persetujuan medis mandiri dengan tanda tangan digital.</p>
                        <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 fs-6 mt-2">Persetujuan Pasien</span>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="d-flex justify-content-between align-items-center text-muted small border-top pt-3" style="border-color: var(--border-kiosk) !important;">
            <div><i class="fa fa-info-circle me-1"></i> Sentuh salah satu menu di atas untuk memulai pelayanan secara mandiri.</div>
            <div>Bantuan Petugas: <span class="fw-bold text-teal">Tekan Tombol Bantuan di Loket 1</span></div>
        </footer>
    </div>

    <!-- Modal Mandiri: Check-In Mobile JKN -->
    <div class="modal fade" id="modalKioskCheckIn" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary py-3">
                    <h4 class="modal-title fw-bold"><i class="fa fa-qrcode text-success me-2"></i>Check-in Antrean Mobile JKN</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="alertKioskCheckIn" class="alert d-none mb-3"></div>
                    <div class="mb-4 text-center">
                        <label class="form-label fs-5 mb-2">Scan Barcode / Masukkan Nomor Rekam Medis (RM):</label>
                        <input type="text" id="kioskInputRm" class="form-control form-control-lg text-center fs-3 font-monospace fw-bold" placeholder="Contoh: 000123" autofocus>
                    </div>

                    <!-- Virtual Keypad Layar Sentuh -->
                    <div class="row g-2 justify-content-center" style="max-width: 400px; margin: 0 auto;">
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                            <div class="col-4">
                                <button type="button" class="btn btn-secondary keypad-btn w-100" data-key="<?= $i ?>"><?= $i ?></button>
                            </div>
                        <?php endfor; ?>
                        <div class="col-4">
                            <button type="button" class="btn btn-danger keypad-btn w-100" id="btnKeypadClear"><i class="fa fa-backspace"></i></button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-secondary keypad-btn w-100" data-key="0">0</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-success keypad-btn w-100" id="btnKeypadSubmit"><i class="fa fa-check"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Mandiri: Formulir Persetujuan Umum (General Consent) -->
    <div class="modal fade" id="modalKioskConsent" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary py-3">
                    <h4 class="modal-title fw-bold"><i class="fa fa-file-signature text-primary me-2"></i>Formulir Persetujuan Umum (General Consent)</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="card bg-secondary-subtle text-dark p-3 mb-3" style="max-height: 250px; overflow-y: auto;">
                        <h6 class="fw-bold">PERSETUJUAN UMUM PELAYANAN KESEHATAN (GENERAL CONSENT)</h6>
                        <p class="small mb-2">1. Pasien dan/atau keluarga menyetujui untuk menerima perawatan medis, diagnostik, pemeriksaan fisik, dan tindakan rawat jalan yang dianggap perlu oleh dokter yang bertugas.</p>
                        <p class="small mb-2">2. Pasien memahami hak dan kewajiban pasien sesuai dengan Undang-Undang Kesehatan yang berlaku di Republik Indonesia.</p>
                        <p class="small mb-0">3. Pasien memberikan persetujuan pelepasan informasi medis kepada penjamin pembayaran (BPJS Kesehatan / Asuransi terkait) untuk keperluan penagihan dan klaim pembiayaan.</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label small fw-bold text-muted">Nomor Rekam Medis (RM):</label>
                            <input type="text" id="consentNoRm" class="form-control form-control-lg font-monospace" placeholder="Ketik No. RM pasien">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label small fw-bold text-muted">Nama Pemberi Persetujuan:</label>
                            <input type="text" id="consentNamaPj" class="form-control form-control-lg" placeholder="Nama Pasien / Keluarga">
                        </div>
                    </div>

                    <!-- Digital Signature Pad -->
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-muted">Tanda Tangan Digital Pasien / Keluarga:</label>
                        <div class="bg-white rounded p-1 border">
                            <canvas id="signaturePad" width="700" height="180" style="width: 100%; height: 180px; touch-action: none; cursor: crosshair;"></canvas>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <button type="button" class="btn btn-outline-light btn-sm" id="btnClearSignature"><i class="fa fa-eraser me-1"></i> Hapus Tanda Tangan</button>
                            <span class="small text-muted">Sentuh dan goreskan tanda tangan pada area di atas.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary px-4" id="btnSimpanConsent"><i class="fa fa-check me-1"></i> Saya Setuju & Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cetak Tiket Antrean (Simulation) -->
    <div class="modal fade" id="modalKioskTicket" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content bg-white text-dark text-center p-4">
                <div class="border-bottom pb-2 mb-3">
                    <h5 class="fw-bold mb-1"><?= $this->e($klinik_nama ?? 'SIMRS Medars') ?></h5>
                    <div class="small text-muted"><?= date('d/m/Y H:i:s') ?></div>
                </div>
                <div class="text-muted small uppercase">NOMOR ANTREAN ANDA</div>
                <div class="font-monospace fw-bold display-4 text-teal my-2" id="ticketNoReg">001</div>
                <div class="fw-bold" id="ticketPoli">Poli Umum</div>
                <div class="small text-muted mb-3" id="ticketDokter">dr. Budi Wicaksono</div>
                <div class="badge bg-light text-muted border p-2 mb-3 small d-block">Simpan tiket antrean ini hingga nomor Anda dipanggil oleh petugas.</div>
                <button type="button" class="btn btn-teal text-white w-100" data-bs-dismiss="modal"><i class="fa fa-check me-1"></i> Selesai (Ambil Kertas)</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/modules/kiosk.js"></script>
</body>
</html>
