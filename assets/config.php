<?php
// ============================================================
// config.php - Konfigurasi database, session, dan fungsi bantu
// Aplikasi Peminjaman Alat (SiPinjam)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Konfigurasi Database ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_peminjaman_alat');

// --- URL aplikasi ---
define('BASE_URL', 'http://localhost/NESYA_XII_RPL1/project2/');

// Membuat koneksi PDO ke MySQL
function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die("<div style='padding:20px;font-family:sans-serif;background:#f8d7da;color:#721c24;border-radius:6px'>
                <h3>Koneksi Database Gagal</h3>
                <p>Pastikan MySQL di XAMPP sudah jalan dan database <b>" . DB_NAME . "</b> sudah di-import.</p>
                <small>Error: " . htmlspecialchars($e->getMessage()) . "</small></div>");
        }
    }
    return $pdo;
}

// Alias agar kode lama tetap jalan
$pdo = db();

// --- Fungsi bantu umum ---

// Membersihkan input dari karakter berbahaya (anti XSS)
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

// Format angka menjadi Rupiah
function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Format tanggal menjadi dd/mm/YYYY
function tgl($tanggal) {
    return $tanggal ? date('d/m/Y', strtotime($tanggal)) : '-';
}

// Membuat badge status peminjaman
function badge_status($status) {
    $map = [
        'Pending'      => ['warning text-dark', 'Menunggu'],
        'Disetujui'    => ['success', 'Disetujui'],
        'Ditolak'      => ['danger', 'Ditolak'],
        'Dikembalikan' => ['info text-dark', 'Dikembalikan'],
    ];
    [$warna, $label] = $map[$status] ?? ['secondary', $status];
    return "<span class='badge bg-$warna rounded-pill px-3'>$label</span>";
}

// Membuat badge kondisi alat
function badge_kondisi($kondisi) {
    $map = [
        'Baik'          => ['success', 'Baik'],
        'Rusak Ringan'  => ['warning text-dark', 'Rusak Ringan'],
        'Rusak Berat'   => ['danger', 'Rusak Berat'],
        'Rusak'         => ['warning text-dark', 'Rusak'],
        'Hilang'        => ['danger', 'Hilang'],
    ];
    [$warna, $label] = $map[$kondisi] ?? ['secondary', $kondisi];
    return "<span class='badge bg-$warna rounded-pill px-3'>$label</span>";
}

// --- CSRF Token (keamanan form) ---
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifikasi_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// --- Kontrol Hak Akses (RBAC) ---
function cek_login($role_diizinkan = []) {
    if (empty($_SESSION['user']['id_user'])) {
        set_flash('danger', 'Silakan login terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
    if (!empty($role_diizinkan) && !in_array($_SESSION['user']['role'], $role_diizinkan)) {
        set_flash('danger', 'Akses ditolak! Anda tidak memiliki hak untuk halaman ini.');
        header('Location: index.php');
        exit;
    }
}

// --- Log Aktivitas ---
function log_aktivitas($id_user, $nama_user, $role, $aktivitas) {
    try {
        $stmt = db()->prepare("INSERT INTO log_aktivitas (id_user, nama_user, role, aktivitas) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_user, $nama_user, $role, $aktivitas]);
    } catch (PDOException $e) {
        // abaikan bila log gagal
    }
}

// --- Flash Message (notifikasi sekali tampil) ---
function set_flash($tipe, $pesan) {
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function tampil_flash() {
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $ikon = $f['tipe'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    return "<div class='alert alert-{$f['tipe']} alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm' role='alert'>
                <i class='bi {$ikon} me-2 fs-5'></i>
                <div>" . htmlspecialchars($f['pesan']) . "</div>
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

// === Alias kompatibilitas (nama fungsi lama agar semua halaman tetap jalan) ===
function check_role($role_diizinkan = []) { cek_login($role_diizinkan); }
function verify_csrf_token($token) { return verifikasi_csrf($token); }
function generate_csrf_token() { return csrf_token(); }
function set_flash_message($tipe, $pesan) { set_flash($tipe, $pesan); }
function get_flash_message() { return tampil_flash(); }
function log_activity(...$args) { log_aktivitas(...$args); }
