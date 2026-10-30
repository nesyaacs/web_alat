<?php
// laporan.php - Laporan peminjaman siap cetak (Petugas/Admin)
require_once __DIR__ . '/config.php';
cek_login(['petugas', 'admin']);

$tgl_mulai   = sanitize($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_selesai = sanitize($_GET['tgl_selesai'] ?? date('Y-m-t'));
$status      = sanitize($_GET['status'] ?? '');

// Ambil data laporan dalam rentang tanggal
$sql = "
    SELECT p.*, u.nama_lengkap AS peminjam, pet.nama_lengkap AS petugas,
           GROUP_CONCAT(CONCAT(a.nama_alat,' (Qty: ',dp.jumlah,')') SEPARATOR ', ') AS item,
           pg.tgl_pengembalian_aktual, pg.terlambat_hari, pg.denda
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN users pet ON p.id_petugas = pet.id_user
    JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
    JOIN alat a ON dp.id_alat = a.id_alat
    LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
    WHERE p.tgl_pinjam BETWEEN ? AND ?
";
$params = [$tgl_mulai, $tgl_selesai];
if ($status !== '') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}
$sql .= " GROUP BY p.id_peminjaman ORDER BY p.id_peminjaman DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Total denda
$total_denda = 0;
foreach ($data as $r) {
    $total_denda += (float)$r['denda'];
}

$title = "Laporan";
$active_page = 'laporan';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>
<div class="container py-4">
    <!-- Filter -->
    <div class="card shadow-sm mb-4 btn-print-hide">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-printer text-primary me-2"></i> Laporan Peminjaman Alat</h5>
            <button onclick="window.print()" class="btn btn-primary rounded-3 px-3"><i class="bi bi-printer me-1"></i> Cetak</button>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Tanggal Mulai</label>
                    <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tgl_mulai) ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Tanggal Selesai</label>
                    <input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($tgl_selesai) ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <option value="Pending" <?= $status==='Pending'?'selected':'' ?>>Menunggu</option>
                        <option value="Disetujui" <?= $status==='Disetujui'?'selected':'' ?>>Disetujui</option>
                        <option value="Ditolak" <?= $status==='Ditolak'?'selected':'' ?>>Ditolak</option>
                        <option value="Dikembalikan" <?= $status==='Dikembalikan'?'selected':'' ?>>Dikembalikan</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Terapkan</button>
                    <a href="laporan.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Isi Laporan -->
    <div class="card shadow-sm border p-4 bg-white">
        <div class="text-center border-bottom pb-3 mb-4">
            <h3 class="fw-bold mb-1 text-uppercase">Laporan Peminjaman Alat</h3>
            <p class="text-muted mb-1">Periode: <strong><?= tgl($tgl_mulai) ?></strong> s.d. <strong><?= tgl($tgl_selesai) ?></strong></p>
        </div>

        <div class="row g-2 mb-3 text-center small">
            <div class="col">
                <div class="p-2 border rounded bg-light">
                    <div class="text-muted">Total Transaksi</div>
                    <div class="fw-bold fs-6"><?= count($data) ?> Trx</div>
                </div>
            </div>
            <div class="col">
                <div class="p-2 border rounded bg-light">
                    <div class="text-muted">Total Denda</div>
                    <div class="fw-bold fs-6 text-danger"><?= rupiah($total_denda) ?></div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0" style="font-size:0.9rem;">
                <thead class="table-light text-center"><tr><th>No</th><th>Kode</th><th>Peminjam</th><th>Alat</th><th>Pinjam</th><th>Batas Kembali</th><th>Tgl Kembali</th><th>Status</th><th>Denda</th></tr></thead>
                <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="9" class="text-center py-3 text-muted">Tidak ada transaksi.</td></tr>
                <?php else: foreach ($data as $i => $r): ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($r['kode_peminjaman']) ?></td>
                        <td><?= htmlspecialchars($r['peminjam']) ?></td>
                        <td><?= htmlspecialchars($r['item']) ?></td>
                        <td class="text-center"><?= tgl($r['tgl_pinjam']) ?></td>
                        <td class="text-center"><?= tgl($r['tgl_kembali_rencana']) ?></td>
                        <td class="text-center"><?= tgl($r['tgl_pengembalian_aktual']) ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td class="text-end"><?= $r['denda'] > 0 ? rupiah($r['denda']) : '-' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-5 pt-3 d-none d-print-flex justify-content-between">
            <div class="col-4 text-center">
                <p class="mb-5">Diketahui oleh,<br><strong>Kepala Laboratorium</strong></p>
                <p class="mt-4 mb-0">( _________________________ )</p>
            </div>
            <div class="col-4 text-center">
                <p class="mb-5">Dicetak: <?= date('d F Y') ?><br><strong>Petugas</strong></p>
                <p class="mt-4 mb-0"><strong>( <?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?> )</strong></p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>