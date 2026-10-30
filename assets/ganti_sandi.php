<?php
// ganti_sandi.php - Mengganti password sendiri (semua role)
require_once __DIR__ . '/config.php';
cek_login();

$user = $_SESSION['user'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifikasi_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid, silakan muat ulang halaman.';
    } else {
        $lama = $_POST['password_lama'] ?? '';
        $baru = $_POST['password_baru'] ?? '';
        $konf = $_POST['password_konfirmasi'] ?? '';

        if ($lama === '' || $baru === '') {
            $error = 'Password lama dan password baru wajib diisi!';
        } elseif (strlen($baru) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($baru !== $konf) {
            $error = 'Konfirmasi password tidak cocok!';
        } else {
            $cek = $pdo->prepare("SELECT password FROM users WHERE id_user = ?");
            $cek->execute([$user['id_user']]);

            if (!password_verify($lama, $cek->fetchColumn())) {
                $error = 'Password lama salah!';
            } else {
                $up = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                $up->execute([password_hash($baru, PASSWORD_BCRYPT), $user['id_user']]);
                log_aktivitas($user['id_user'], $user['nama_lengkap'], $user['role'], 'Mengganti password sendiri');
                set_flash('success', 'Password berhasil diganti! Gunakan password baru saat login berikutnya.');
                header('Location: ganti_sandi.php');
                exit;
            }
        }
    }
}

$title = "Ganti Sandi";
$active_page = 'ganti_sandi';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>
<div class="container py-4">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-key me-2"></i> Ganti Sandi</h4>
            </div>
            <div class="card-body p-4">
                <?= tampil_flash() ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-3 shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <p class="text-muted small mb-4">
                    Login sebagai <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong>
                    (<span class="text-uppercase"><?= htmlspecialchars($user['role']) ?></span>).
                    Masukkan password lama lalu tetapkan password baru Anda.
                </p>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Lama</label>
                        <input type="password" name="password_lama" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Baru</label>
                        <input type="password" name="password_baru" class="form-control" minlength="6" required autocomplete="new-password">
                        <div class="form-text">Minimal 6 karakter.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                        <input type="password" name="password_konfirmasi" class="form-control" minlength="6" required autocomplete="new-password">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2 rounded-3 fw-semibold"><i class="bi bi-check2-circle me-2"></i> Simpan Password Baru</button>
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>