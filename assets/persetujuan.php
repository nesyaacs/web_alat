<?php
// persetujuan.php - Menyetujui Peminjaman (Fitur Petugas UKK)
require_once __DIR__ . '/config.php';
check_role(['petugas', 'admin']);

$id_petugas = $_SESSION['user']['id_user'];

// Process Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    $id_peminjaman = (int)($_POST['id_peminjaman'] ?? 0);

    if (!verify_csrf_token($token)) {
        set_flash_message('danger', 'Sesi tidak valid (CSRF mismatch).');
        header('Location: persetujuan.php');
        exit;
    }

    if ($action === 'approve' && $id_peminjaman > 0) {
        try {
            $stmt = $pdo->prepare("CALL sp_setujui_peminjaman(?, ?, @status_code, @message)");
            $stmt->execute([$id_peminjaman, $id_petugas]);
            
            $res = $pdo->query("SELECT @status_code AS status_code, @message AS message")->fetch();
            
            if ($res['status_code'] == 200) {
                set_flash_message('success', 'Pengajuan peminjaman berhasil disetujui! Stok alat telah diperbarui.');
            } else {
                set_flash_message('danger', $res['message']);
            }
        } catch (PDOException $e) {
            set_flash_message('danger', 'Gagal memproses persetujuan: ' . $e->getMessage());
        }
    } elseif ($action === 'reject' && $id_peminjaman > 0) {
        $catatan = sanitize($_POST['catatan'] ?? 'Ditolak oleh petugas.');
        try {
            $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'Ditolak', id_petugas = ?, catatan = ? WHERE id_peminjaman = ?");
            $stmt->execute([$id_petugas, $catatan, $id_peminjaman]);
            log_activity($id_petugas, $_SESSION['user']['nama_lengkap'], 'petugas', "Menolak pengajuan peminjaman ID #$id_peminjaman");
            set_flash_message('success', 'Pengajuan peminjaman berhasil ditolak.');
        } catch (PDOException $e) {
            set_flash_message('danger', 'Gagal menolak pengajuan: ' . $e->getMessage());
        }
    }

    header('Location: persetujuan.php');
    exit;
}

// Fetch Pending Requests
$sql = "
    SELECT p.*, u.nama_lengkap AS peminjam_nama, u.username AS peminjam_username,
           GROUP_CONCAT(CONCAT(a.nama_alat, ' (Jumlah: ', dp.jumlah, ', Stok: ', a.stok, ')') SEPARATOR '<br>') AS item_details
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
    JOIN alat a ON dp.id_alat = a.id_alat
    WHERE p.status = 'Pending'
    GROUP BY p.id_peminjaman
    ORDER BY p.id_peminjaman ASC
";
$pending_list = $pdo->query($sql)->fetchAll();

$title = "Menyetujui Peminjaman";
$active_page = 'persetujuan';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-check2-square text-primary me-2"></i> Menyetujui Peminjaman Alat</h3>
            <p class="text-muted small mb-0">Fitur Petugas: Verifikasi dan setujui pengajuan peminjaman dari peminjam/siswa</p>
        </div>
    </div>

    <?= get_flash_message() ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode TRX</th>
                            <th>Nama Peminjam</th>
                            <th>Item Alat & Stok</th>
                            <th>Tgl Pinjam - Rencana Kembali</th>
                            <th>Catatan Keperluan</th>
                            <th style="width: 180px;" class="text-center">Aksi Persetujuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_list)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                    Semua pengajuan telah diproses! Tidak ada persetujuan tertunda.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_list as $p): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($p['kode_peminjaman']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($p['peminjam_nama']) ?></div>
                                        <small class="text-muted">@<?= htmlspecialchars($p['peminjam_username']) ?></small>
                                    </td>
                                    <td><small><?= $p['item_details'] ?></small></td>
                                    <td class="small">
                                        <?= date('d/m/Y', strtotime($p['tgl_pinjam'])) ?> s/d <br>
                                        <strong class="text-danger"><?= date('d/m/Y', strtotime($p['tgl_kembali_rencana'])) ?></strong>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($p['catatan'] ?: '-') ?></small></td>
                                    <td class="text-center">
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirmAction('Setujui peminjaman ini? Stok alat akan berkurang otomatis.');">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success me-1">
                                                <i class="bi bi-check-lg"></i> Setujui
                                            </button>
                                        </form>

                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalReject<?= $p['id_peminjaman'] ?>">
                                            <i class="bi bi-x-lg"></i> Tolak
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Dialogs Outside Table -->
<?php if (!empty($pending_list)): ?>
    <?php foreach ($pending_list as $p): ?>
        <div class="modal fade" id="modalReject<?= $p['id_peminjaman'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                        
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Tolak Pengajuan Peminjaman</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Alasan penolakan TRX <strong><?= htmlspecialchars($p['kode_peminjaman']) ?></strong>:</p>
                            <textarea name="catatan" class="form-control" rows="3" placeholder="Alasan penolakan..." required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i> Konfirmasi Penolakan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
