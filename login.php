<?php
// login.php - Halaman masuk aplikasi
require_once __DIR__ . '/assets/config.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifikasi_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi!';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id_user'      => $user['id_user'],
                    'username'     => $user['username'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role'         => $user['role'],
                ];
                log_aktivitas($user['id_user'], $user['nama_lengkap'], $user['role'], 'Login ke sistem');
                set_flash('success', 'Selamat datang, ' . $user['nama_lengkap'] . '!');
                header('Location: index.php');
                exit;
            } else {
                $error = 'Username atau password salah!';
            }
        }
    }
}

$title = "Masuk";
require_once __DIR__ . '/assets/header.php';
?>
<div class="container d-flex flex-column justify-content-center align-items-center flex-grow-1 py-5">
    <div class="col-12 col-sm-9 col-md-7 col-lg-5 col-xl-4">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4 p-sm-5">
                <div class="text-center mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-flex mb-3">
                        <i class="bi bi-tools fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Mourvie SiPinjamA</h4>
                    <p class="text-muted small mb-0">Sistem Peminjaman Alat</p>
                </div>

                <?= tampil_flash() ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="loginForm" class="needs-validation" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required autofocus autocomplete="off" autocapitalize="off" spellcheck="false">
                            <div class="invalid-feedback">Username wajib diisi.</div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required autocomplete="new-password">
                            <div class="invalid-feedback">Password wajib diisi.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/assets/footer.php'; ?>
