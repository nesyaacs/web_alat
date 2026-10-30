<?php
// riwayat.php - Riwayat peminjaman peminjam
require_once __DIR__ . '/config.php';
cek_login(['peminjam', 'admin']);

$id_user = $_SESSION['user']['id_user'];

$sql = "
    SELECT p.*, pet.nama_lengkap AS petugas,
           GROUP_CONCAT(CONCAT(a.nama_alat,' (Qty: ',dp.jumlah,')') SEPARATOR ', ') AS item,
           pg.tgl_pengembalian_aktual, pg.terlambat_hari, pg.denda, pg.keterangan_kondisi
    FROM peminjaman p
    LEFT JOIN users pet ON p.id_petugas = pet.id_user
    JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
    JOIN alat a ON dp.id_alat = a.id_alat
    LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
    WHERE p.id_user = ?
    GROUP BY p.id_peminjaman
    ORDER BY p.id_peminjaman DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_user]);
$riwayat = $stmt->fetchAll();

$title = "Riwayat Saya";
$active_page = 'riwayat';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i> Riwayat Peminjaman</h3>
            <p class="text-muted small mb-0">Pantau status persetujuan, tenggat, dan denda</p>
        </div>
        <a href="pinjam.php" class="btn btn-primary rounded-3 px-3 shadow-sm"><i class="bi bi-plus-lg me-1"></i> Ajukan Lagi</a>
    </div>

    <?= tampil_flash() ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Kode</th><th>Alat</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status</th><th>Info Pengembalian</th></tr></thead>
                <tbody>
                <?php if (empty($riwayat)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat peminjaman.</td></tr>
                <?php else: foreach ($riwayat as $r): ?>
                    <tr>
                        <td class="fw-bold text-primary"><?= htmlspecialchars($r['kode_peminjaman']) ?></td>
                        <td><small class="fw-semibold"><?= htmlspecialchars($r['item']) ?></small></td>
                        <td class="small"><?= tgl($r['tgl_pinjam']) ?></td>
                        <td class="small fw-bold"><?= tgl($r['tgl_kembali_rencana']) ?></td>
                        <td><?= badge_status($r['status']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'Dikembalikan'): ?>
                                <?php if ($r['terlambat_hari'] > 0): ?>
                                    <span class="text-danger fw-semibold small">Terlambat <?= $r['terlambat_hari'] ?> hari (Denda: <?= rupiah($r['denda']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i> Tepat waktu</span>
                                <?php endif; ?>
                            <?php elseif ($r['status'] === 'Disetujui'): ?>
                                <small class="text-primary fw-semibold d-block"><i class="bi bi-box-seam me-1"></i> Alat sedang dipinjam</small>
                                <small class="text-muted">Silakan kembalikan ke petugas lab.</small>
                            <?php elseif ($r['status'] === 'Ditolak'): ?>
                                <small class="text-danger">Catatan: <?= htmlspecialchars($r['catatan'] ?: 'Tidak disetujui') ?></small>
                            <?php else: ?>
                                <small class="text-muted">Menunggu review petugas...</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>