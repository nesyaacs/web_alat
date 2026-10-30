<?php
// index.php - Dashboard ringkas (menyesuaikan role)
require_once __DIR__ . '/assets/config.php';
cek_login();
$user = $_SESSION['user']; $role = $user['role']; $id_user = $user['id_user'];

$stat = [];
if ($role === 'admin') $stat[] = ['Pengguna', $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(), 'people', 'primary'];
$stat[] = ['Total Alat',    $pdo->query("SELECT COUNT(*) FROM alat")->fetchColumn(), 'box-seam', 'success'];
$stat[] = ['Menunggu',      $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='Pending'")->fetchColumn(), 'clock-history', 'warning'];
$stat[] = ['Pengembalian',  $pdo->query("SELECT COUNT(*) FROM pengembalian")->fetchColumn(), 'arrow-counterclockwise', 'info'];

if ($role === 'admin') {
    $recent = $pdo->query("SELECT * FROM log_aktivitas ORDER BY id_log DESC LIMIT 5")->fetchAll();
} elseif ($role === 'petugas') {
    $recent = $pdo->query("SELECT p.*, u.nama_lengkap AS peminjam,
        GROUP_CONCAT(CONCAT(a.nama_alat,' (',dp.jumlah,')') SEPARATOR ', ') AS item
        FROM peminjaman p JOIN users u ON p.id_user=u.id_user
        JOIN detail_peminjaman dp ON p.id_peminjaman=dp.id_peminjaman JOIN alat a ON dp.id_alat=a.id_alat
        WHERE p.status='Pending' GROUP BY p.id_peminjaman ORDER BY p.id_peminjaman ASC LIMIT 5")->fetchAll();
} else {
    $recent = $pdo->query("SELECT p.*, GROUP_CONCAT(CONCAT(a.nama_alat,' (',dp.jumlah,')') SEPARATOR ', ') AS item
        FROM peminjaman p JOIN detail_peminjaman dp ON p.id_peminjaman=dp.id_peminjaman
        JOIN alat a ON dp.id_alat=a.id_alat WHERE p.id_user=$id_user
        GROUP BY p.id_peminjaman ORDER BY p.id_peminjaman DESC LIMIT 5")->fetchAll();
}

$title = "Beranda"; $active_page = 'dashboard';
require_once __DIR__ . '/assets/header.php';
require_once __DIR__ . '/assets/navbar.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h3 class="fw-bold mb-1">Halo, <?= htmlspecialchars($user['nama_lengkap']) ?></h3>
            <p class="text-muted small mb-0">Role: <span class="text-uppercase fw-bold text-primary"><?= htmlspecialchars($role) ?></span></p></div>
        <?php if ($role === 'peminjam'): ?><a href="pinjam.php" class="btn btn-primary rounded-3 px-3"><i class="bi bi-plus-circle me-1"></i> Ajukan Pinjam</a><?php endif; ?>
    </div>

    <?= tampil_flash() ?>

    <div class="row g-3 mb-4">
        <?php foreach ($stat as $s): ?>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-sm border-start border-4 border-<?= $s[3] ?> h-100"><div class="card-body d-flex align-items-center">
                    <div class="bg-<?= $s[3] ?> bg-opacity-10 text-<?= $s[3] ?> p-3 rounded-circle me-3"><i class="bi bi-<?= $s[2] ?> fs-3"></i></div>
                    <div><h6 class="text-muted small text-uppercase fw-semibold mb-1"><?= $s[0] ?></h6><h4 class="fw-bold mb-0"><?= $s[1] ?></h4></div>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($role === 'admin'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-2"></i> Log Aktivitas Terbaru</h5><a href="log.php" class="btn btn-sm btn-outline-primary">Semua</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Waktu</th><th>Pengguna</th><th>Role</th><th>Aktivitas</th></tr></thead>
                <tbody>
                <?php if (empty($recent)): ?><tr><td colspan="4" class="text-center py-4 text-muted">Belum ada aktivitas.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                    <tr><td class="small text-muted"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td><td class="fw-semibold"><?= htmlspecialchars($r['nama_user'] ?: 'System') ?></td><td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($r['role']) ?></span></td><td class="small"><?= htmlspecialchars($r['aktivitas']) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div></div>
        </div>
    <?php elseif ($role === 'petugas'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0"><i class="bi bi-exclamation-circle text-warning me-2"></i> Pengajuan Menunggu Persetujuan</h5><a href="peminjaman.php" class="btn btn-sm btn-primary">Kelola</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Kode</th><th>Peminjam</th><th>Alat</th><th>Tgl Pinjam</th></tr></thead>
                <tbody>
                <?php if (empty($recent)): ?><tr><td colspan="4" class="text-center py-4 text-muted">Tidak ada pengajuan.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                    <tr><td class="fw-bold text-primary"><?= htmlspecialchars($r['kode_peminjaman']) ?></td><td><?= htmlspecialchars($r['peminjam']) ?></td><td><small><?= htmlspecialchars($r['item']) ?></small></td><td class="small"><?= tgl($r['tgl_pinjam']) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div></div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i> Pengajuan Terakhir Anda</h5><a href="riwayat.php" class="btn btn-sm btn-outline-primary">Selengkapnya</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Kode</th><th>Alat</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($recent)): ?><tr><td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat peminjaman.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                    <tr><td class="fw-bold text-primary"><?= htmlspecialchars($r['kode_peminjaman']) ?></td><td><small><?= htmlspecialchars($r['item']) ?></small></td><td class="small"><?= tgl($r['tgl_pinjam']) ?></td><td class="small"><?= tgl($r['tgl_kembali_rencana']) ?></td><td><?= badge_status($r['status']) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div></div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/assets/footer.php'; ?>