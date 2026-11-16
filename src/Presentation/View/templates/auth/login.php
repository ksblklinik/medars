<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($judul ?? 'Login SIMRS Medars') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/medars-app.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #004d40 0%, #00897b 50%, #4db6ac 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: fadeIn 0.4s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            background: #00897b;
            color: #ffffff;
            padding: 25px 20px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .btn-medical {
            background: #00897b;
            border-color: #00897b;
            color: #ffffff;
            font-weight: 600;
        }
        .btn-medical:hover, .btn-medical:focus {
            background: #00695c;
            border-color: #00695c;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fa fa-hospital-user fa-3x mb-2"></i>
            <h4 class="fw-bold mb-0">SIMRS MEDARS</h4>
            <small class="text-white-50">Sistem Informasi Rumah Sakit & Rekam Medis</small>
        </div>
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small d-flex align-items-center mb-3">
                    <i class="fa fa-triangle-exclamation me-2"></i>
                    <div><?= $this->e($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="/auth/login" method="POST" id="formLogin">
                <?= $csrf_input ?? '' ?>
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold">Username / NIP</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold">Kata Sandi</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-medical w-100 py-2">
                    <i class="fa fa-right-to-bracket me-2"></i> Masuk ke Sistem
                </button>
            </form>
        </div>
        <div class="bg-light text-center py-2 border-top small text-muted">
            &copy; <?= date('Y') ?> SIMRS Medars &bull; Clean Architecture
        </div>
    </div>
</body>
</html>
