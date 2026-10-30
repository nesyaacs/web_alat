<?php
// log.php - Log aktivitas / audit trail (khusus Admin)
require_once __DIR__ . '/config.php';
cek_login(['admin']);

// Hapus semua log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    if (!verifikasi_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi tidak valid.');
    } else {
        $pdo->query("TRUNCATE TABLE log_aktivitas");
        log_aktivitas($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Menghapus seluruh log aktivitas");
        set_flash('success', 'Semua log aktivitas telah dibersihkan.');
    }
    header('Location: log.php');
    exit;
}

// Pencarian log
$cari = sanitize($_GET['q'] ?? '');
$sql = "SELECT * FROM log_aktivitas WHERE 1=1";
$params = [];
if ($cari !== '') {
    $sql .= " AND (nama_user LIKE ? OR aktivitas LIKE ? OR role LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY id_log DESC LIMIT 200"; // gaya cepat & efisien
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$title = "Log Aktivitas";
$active_page = 'log';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i> Log Aktivitas</h3>
            <p class="text-muted small mb-0">Jejak audit semua aktivitas pengguna</p>
        </div>
        <?php if (!empty($logs)): ?>
            <form method="POST" onsubmit="return konfirmasi('Hapus semua log aktivitas?');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-outline-danger btn-sm rounded-3"><i class="bi bi-trash me-1"></i> Bersihkan Log</button>
            </form>
        <?php endif; ?>
    </div>

    <?= tampil_flash() ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Cari pengguna, role, atau aktivitas..." value="<?= htmlspecialchars($cari) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Cari</button>
                    <a href="log.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>#</th><th>Waktu</th><th>Pengguna</th><th>Role</th><th>Aktivitas</th></tr></thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada log.</td></tr>
                <?php else: foreach ($logs as $i => $l): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="small text-muted" style="white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($l['nama_user'] ?: 'System') ?></td>
                        <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($l['role'] ?: '-') ?></span></td>
                        <td><?= htmlspecialchars($l['aktivitas']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>