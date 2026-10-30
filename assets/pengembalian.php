<?php
// pengembalian.php - Proses pengembalian alat & perhitungan denda (Petugas/Admin)
require_once __DIR__ . '/config.php';
cek_login(['admin', 'petugas']);
$id_petugas = $_SESSION['user']['id_user'];
$role = $_SESSION['user']['role'];
$tarif_denda = 5000; // Rp 5.000 / hari
$hari_ini = date('Y-m-d');

// ---- Proses Pengembalian / Hapus ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifikasi_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi tidak valid.'); header('Location: pengembalian.php'); exit;
    }
    $aksi = $_POST['action'] ?? '';

    if ($aksi === 'proses' && (int)($_POST['id_peminjaman'] ?? 0) > 0) {
        $id_peminjaman = (int)$_POST['id_peminjaman'];
        $tgl_aktual = sanitize($_POST['tgl_pengembalian_aktual'] ?? $hari_ini);
        $kondisi    = sanitize($_POST['keterangan_kondisi'] ?? 'Baik');
        try {
            $stmt = $pdo->prepare("CALL sp_proses_pengembalian(?, ?, ?, ?, @status_code, @message)");
            $stmt->execute([$id_peminjaman, $tgl_aktual, $kondisi, $id_petugas]);
            $res = $pdo->query("SELECT @status_code AS kode, @message AS pesan")->fetch();
            set_flash((int)$res['kode'] === 200 ? 'success' : 'danger',
                (int)$res['kode'] === 200 ? 'Pengembalian diproses! Denda dihitung dan stok kembali.' : $res['pesan']);
        } catch (PDOException $e) {
            set_flash('danger', 'Gagal memproses: ' . $e->getMessage());
        }
    } elseif ($aksi === 'delete' && $role === 'admin' && (int)($_POST['id_pengembalian'] ?? 0) > 0) {
        $pdo->prepare("DELETE FROM pengembalian WHERE id_pengembalian=?")->execute([(int)$_POST['id_pengembalian']]);
        log_aktivitas($id_petugas, $_SESSION['user']['nama_lengkap'], 'admin', "Menghapus data pengembalian #$_POST[id_pengembalian]");
        set_flash('success', 'Data pengembalian dihapus.');
    }
    header('Location: pengembalian.php'); exit;
}

$aktif = $pdo->query("
    SELECT p.*, u.nama_lengkap AS peminjam, GROUP_CONCAT(CONCAT(a.nama_alat,' (',dp.jumlah,')') SEPARATOR ', ') AS item
    FROM peminjaman p JOIN users u ON p.id_user=u.id_user
    JOIN detail_peminjaman dp ON p.id_peminjaman=dp.id_peminjaman JOIN alat a ON dp.id_alat=a.id_alat
    WHERE p.status='Disetujui' GROUP BY p.id_peminjaman ORDER BY p.tgl_kembali_rencana ASC
")->fetchAll();

$riwayat = $pdo->query("
    SELECT pg.*, p.kode_peminjaman, u.nama_lengkap AS peminjam, pet.nama_lengkap AS petugas
    FROM pengembalian pg JOIN peminjaman p ON pg.id_peminjaman=p.id_peminjaman
    JOIN users u ON p.id_user=u.id_user JOIN users pet ON pg.id_petugas=pet.id_user
    ORDER BY pg.id_pengembalian DESC
")->fetchAll();

$title = "Pengembalian & Denda"; $active_page = 'pengembalian';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h3 class="fw-bold mb-1"><i class="bi bi-arrow-counterclockwise text-primary me-2"></i> Pengembalian Alat & Denda</h3>
            <p class="text-muted small mb-0">Proses pengembalian, cek kondisi, hitung keterlambatan (<?= rupiah($tarif_denda) ?>/hari)</p></div>
    </div>
    <?= tampil_flash() ?>

    <h6 class="fw-bold mb-2 text-uppercase text-muted small"><i class="bi bi-clock-history me-1"></i> Sedang Dipinjam (<?= count($aktif) ?>)</h6>
    <div class="card shadow-sm mb-4"><div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Kode</th><th>Peminjam</th><th>Alat</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status Waktu</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($aktif)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada alat yang sedang dipinjam.</td></tr>
            <?php else: foreach ($aktif as $a):
                $telat = $hari_ini > $a['tgl_kembali_rencana'];
                $telat_hari = $telat ? (int)((strtotime($hari_ini) - strtotime($a['tgl_kembali_rencana'])) / 86400) : 0;
            ?>
                <tr>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($a['kode_peminjaman']) ?></td>
                    <td><?= htmlspecialchars($a['peminjam']) ?></td>
                    <td><small><?= $a['item'] ?></small></td>
                    <td class="small"><?= tgl($a['tgl_pinjam']) ?></td>
                    <td class="small fw-bold <?= $telat ? 'text-danger' : '' ?>"><?= tgl($a['tgl_kembali_rencana']) ?></td>
                    <td><?= $telat ? "<span class='badge bg-danger rounded-pill px-3'>Telat $telat_hari hari</span> <div class='small text-danger fw-semibold mt-1'>Denda: " . rupiah($telat_hari * $tarif_denda) . "</div>" : "<span class='badge bg-success rounded-pill px-3'>Tepat waktu</span>" ?></td>
                    <td class="text-center"><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalProses" onclick="isiProses(<?= $a['id_peminjaman'] ?>,'<?= htmlspecialchars($a['kode_peminjaman']) ?>','<?= htmlspecialchars($a['peminjam']) ?>')"><i class="bi bi-box-arrow-in-down me-1"></i> Proses</button></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>

    <h6 class="fw-bold mb-2 text-uppercase text-muted small"><i class="bi bi-check2-circle me-1"></i> Riwayat Pengembalian (<?= count($riwayat) ?>)</h6>
    <div class="card shadow-sm"><div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Kode</th><th>Peminjam</th><th>Tgl Kembali</th><th>Kondisi</th><th>Keterlambatan</th><th>Denda</th><th>Petugas</th><?= $role === 'admin' ? '<th class="text-center">Aksi</th>' : '' ?></tr></thead>
            <tbody>
            <?php if (empty($riwayat)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada riwayat pengembalian.</td></tr>
            <?php else: foreach ($riwayat as $r): ?>
                <tr>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($r['kode_peminjaman']) ?></td>
                    <td><?= htmlspecialchars($r['peminjam']) ?></td>
                    <td><?= tgl($r['tgl_pengembalian_aktual']) ?></td>
                    <td><?= badge_kondisi($r['keterangan_kondisi']) ?></td>
                    <td><?= $r['terlambat_hari'] > 0 ? "<span class='text-danger fw-bold'>{$r['terlambat_hari']} hari</span>" : '<span class="text-success">Tepat waktu</span>' ?></td>
                    <td class="fw-bold"><?= rupiah($r['denda']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($r['petugas']) ?></td>
                    <?php if ($role === 'admin'): ?>
                        <td class="text-center">
                            <form method="POST" class="d-inline" onsubmit="return konfirmasi('Hapus data pengembalian ini?');">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_pengembalian" value="<?= $r['id_pengembalian'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>

<div class="modal fade" id="modalProses" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="proses">
            <input type="hidden" name="id_peminjaman" id="prosesId" value="0">
            <div class="modal-header"><h5 class="modal-title fw-bold">Proses Pengembalian</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-semibold">Transaksi</label><input type="text" class="form-control" id="prosesKode" value="" disabled></div>
                <div class="mb-3"><label class="form-label fw-semibold">Tanggal Kembali Aktual</label><input type="date" name="tgl_pengembalian_aktual" class="form-control" value="<?= $hari_ini ?>" required><small class="text-muted">Tarif denda: <strong><?= rupiah($tarif_denda) ?>/hari</strong> jika telat</small></div>
                <div class="mb-3"><label class="form-label fw-semibold">Kondisi Dikembalikan</label>
                    <select name="keterangan_kondisi" class="form-select">
                        <option value="Baik">Baik</option>
                        <option value="Rusak">Rusak</option>
                        <option value="Hilang">Hilang</option>
                    </select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Konfirmasi</button></div>
        </form>
    </div></div>
</div>

<script>
function isiProses(id, kode, peminjam) {
    document.getElementById('prosesId').value = id;
    document.getElementById('prosesKode').value = kode + ' - ' + peminjam;
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>