<?php
// logout.php - Keluar dari aplikasi
require_once __DIR__ . '/assets/config.php';

if (isset($_SESSION['user'])) {
    $u = $_SESSION['user'];
    log_aktivitas($u['id_user'], $u['nama_lengkap'], $u['role'], 'Logout dari sistem');
}

// Hapus session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}
session_destroy();

// Mulai session baru hanya untuk pesan
session_start();
set_flash('success', 'Anda berhasil keluar dari sistem.');
header('Location: login.php');
exit;
