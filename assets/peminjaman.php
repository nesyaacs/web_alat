<?php
// peminjaman.php - Flat Borrowing Management Page
require_once __DIR__ . '/config.php';
check_role(['admin', 'petugas']);

$id_petugas = $_SESSION['user']['id_user'];
$user_role = $_SESSION['user']['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    $id_peminjaman = (int)($_POST['id_peminjaman'] ?? 0);

    if (!verify_csrf_token($token)) {
        set_flash_message('danger', 'Invalid session.');
        header('Location: peminjaman.php');
        exit;
    }

    if ($action === 'approve' && $id_peminjaman > 0) {
        try {
            $stmt = $pdo->prepare("CALL sp_setujui_peminjaman(?, ?, @status_code, @message)");
            $stmt->execute([$id_peminjaman, $id_petugas]);
            
            $res = $pdo->query("SELECT @status_code AS status_code, @message AS message")->fetch();
            
            if ($res['status_code'] == 200) {
                set_flash_message('success', 'Borrowing request approved! Inventory stock updated.');
            } else {
                set_flash_message('danger', $res['message']);
            }
        } catch (PDOException $e) {
            set_flash_message('danger', 'Approval failed: ' . $e->getMessage());
        }
    } elseif ($action === 'reject' && $id_peminjaman > 0) {
        $catatan = sanitize($_POST['catatan'] ?? 'Rejected by staff.');
        try {
            $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'Ditolak', id_petugas = ?, catatan = ? WHERE id_peminjaman = ?");
            $stmt->execute([$id_petugas, $catatan, $id_peminjaman]);
            log_activity($id_petugas, $_SESSION['user']['nama_lengkap'], $user_role, "Rejected borrowing request TRX #$id_peminjaman");
            set_flash_message('success', 'Borrowing request rejected.');
        } catch (PDOException $e) {
            set_flash_message('danger', 'Rejection failed: ' . $e->getMessage());
        }
    } elseif ($action === 'delete' && $id_peminjaman > 0 && $user_role === 'admin') {
        try {
            $stmt = $pdo->prepare("DELETE FROM peminjaman WHERE id_peminjaman = ?");
            $stmt->execute([$id_peminjaman]);
            log_activity($id_petugas, $_SESSION['user']['nama_lengkap'], 'admin', "Deleted borrowing TRX #$id_peminjaman");
            set_flash_message('success', 'Borrowing record deleted.');
        } catch (PDOException $e) {
            set_flash_message('danger', 'Deletion failed: ' . $e->getMessage());
        }
    }

    header('Location: peminjaman.php');
    exit;
}

$search = sanitize($_GET['q'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');

$sql = "
    SELECT p.*, u.nama_lengkap AS peminjam_nama, pet.nama_lengkap AS petugas_nama,
           GROUP_CONCAT(CONCAT(a.nama_alat, ' (Qty: ', dp.jumlah, ', Stock: ', a.stok, ')') SEPARATOR '<br>') AS item_details
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN users pet ON p.id_petugas = pet.id_user
    JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
    JOIN alat a ON dp.id_alat = a.id_alat
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.kode_peminjaman LIKE ? OR u.nama_lengkap LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY p.id_peminjaman ORDER BY p.id_peminjaman DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$peminjaman_list = $stmt->fetchAll();

$title = "Manage Borrowing Requests";
$active_page = 'peminjaman';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-arrow-left-right text-primary me-2"></i> Equipment Borrowing Transactions</h3>
            <p class="text-muted small mb-0">Verify requests, approve loan applications, and manage transaction history</p>
        </div>
    </div>

    <?= get_flash_message() ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search TRX code or borrower..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select name="status" class="form-select">
                        <option value="">-- All Statuses --</option>
                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="Disetujui" <?= $status_filter === 'Disetujui' ? 'selected' : '' ?>>Approved</option>
                        <option value="Ditolak" <?= $status_filter === 'Ditolak' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Dikembalikan" <?= $status_filter === 'Dikembalikan' ? 'selected' : '' ?>>Returned</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                    <?php if(!empty($search) || !empty($status_filter)): ?>
                        <a href="peminjaman.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>TRX Code</th>
                            <th>Borrower</th>
                            <th>Items Requested</th>
                            <th>Borrow Date - Due Date</th>
                            <th>Status</th>
                            <th>Staff Approved</th>
                            <th style="width: 160px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($peminjaman_list)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No borrowing records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($peminjaman_list as $p): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($p['kode_peminjaman']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($p['peminjam_nama']) ?></td>
                                    <td><small><?= $p['item_details'] ?></small></td>
                                    <td class="small">
                                        <?= date('d/m/Y', strtotime($p['tgl_pinjam'])) ?> to <br>
                                        <strong class="text-danger"><?= date('d/m/Y', strtotime($p['tgl_kembali_rencana'])) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($p['status'] === 'Pending'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3">Pending</span>
                                        <?php elseif ($p['status'] === 'Disetujui'): ?>
                                            <span class="badge bg-success rounded-pill px-3">Approved</span>
                                        <?php elseif ($p['status'] === 'Ditolak'): ?>
                                            <span class="badge bg-danger rounded-pill px-3">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark rounded-pill px-3">Returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($p['petugas_nama'] ?: '-') ?></td>
                                    <td class="text-center">
                                        <?php if ($p['status'] === 'Pending'): ?>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirmAction('Approve this loan? Stock will be deducted automatically.');">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success me-1"><i class="bi bi-check-lg"></i> Approve</button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalReject<?= $p['id_peminjaman'] ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php else: ?>
                                            <?php if ($user_role === 'admin'): ?>
                                                <form action="" method="POST" class="d-inline" onsubmit="return confirmAction('Delete this transaction?');">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
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

<?php if (!empty($peminjaman_list)): ?>
    <?php foreach ($peminjaman_list as $p): ?>
        <?php if ($p['status'] === 'Pending'): ?>
            <div class="modal fade" id="modalReject<?= $p['id_peminjaman'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                            
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">Reject Borrowing Request</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Reason for rejecting TRX <strong><?= htmlspecialchars($p['kode_peminjaman']) ?></strong>:</p>
                                <textarea name="catatan" class="form-control" rows="3" placeholder="Rejection reason..." required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i> Confirm Rejection</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>