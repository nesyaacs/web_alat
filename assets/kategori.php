<?php
// kategori.php - Flat Category Management Page
require_once __DIR__ . '/config.php';
check_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('danger', 'Invalid session.');
        header('Location: kategori.php');
        exit;
    }

    if ($action === 'create') {
        $nama_kategori = sanitize($_POST['nama_kategori'] ?? '');
        $keterangan = sanitize($_POST['keterangan'] ?? '');

        if (empty($nama_kategori)) {
            set_flash_message('danger', 'Category name is required!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, keterangan) VALUES (?, ?)");
            $stmt->execute([$nama_kategori, $keterangan]);
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Added category: $nama_kategori");
            set_flash_message('success', 'Category added successfully.');
        }
    } elseif ($action === 'update') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $nama_kategori = sanitize($_POST['nama_kategori'] ?? '');
        $keterangan = sanitize($_POST['keterangan'] ?? '');

        if ($id_kategori <= 0 || empty($nama_kategori)) {
            set_flash_message('danger', 'Invalid category data.');
        } else {
            $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori = ?, keterangan = ? WHERE id_kategori = ?");
            $stmt->execute([$nama_kategori, $keterangan, $id_kategori]);
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Updated category ID #$id_kategori");
            set_flash_message('success', 'Category updated successfully.');
        }
    } elseif ($action === 'delete') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        if ($id_kategori > 0) {
            $stmt = $pdo->prepare("DELETE FROM kategori WHERE id_kategori = ?");
            $stmt->execute([$id_kategori]);
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Deleted category ID #$id_kategori");
            set_flash_message('success', 'Category deleted successfully.');
        }
    }

    header('Location: kategori.php');
    exit;
}

$stmt = $pdo->query("
    SELECT k.*, COUNT(a.id_alat) AS total_alat 
    FROM kategori k 
    LEFT JOIN alat a ON k.id_kategori = a.id_kategori 
    GROUP BY k.id_kategori 
    ORDER BY k.id_kategori DESC
");
$kategori_list = $stmt->fetchAll();

$title = "Manage Categories";
$active_page = 'kategori';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-tags text-primary me-2"></i> Equipment Categories</h3>
            <p class="text-muted small mb-0">Organize and group equipment items by category</p>
        </div>
        <button class="btn btn-primary rounded-3 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
            <i class="bi bi-plus-lg me-1"></i> Add Category
        </button>
    </div>

    <?= get_flash_message() ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Total Items</th>
                            <th style="width: 140px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kategori_list)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No categories added yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($kategori_list as $index => $k): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                                    <td><?= htmlspecialchars($k['keterangan'] ?: '-') ?></td>
                                    <td><span class="badge bg-secondary rounded-pill px-3"><?= $k['total_alat'] ?> items</span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal" data-bs-target="#modalEditKategori<?= $k['id_kategori'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirmAction('Delete this category?');">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_kategori" value="<?= $k['id_kategori'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
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

<div class="modal fade" id="modalTambahKategori" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name</label>
                        <input type="text" name="nama_kategori" class="form-control" placeholder="e.g. Multimedia" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Category description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($kategori_list)): ?>
    <?php foreach ($kategori_list as $k): ?>
        <div class="modal fade" id="modalEditKategori<?= $k['id_kategori'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_kategori" value="<?= $k['id_kategori'] ?>">
                        
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category Name</label>
                                <input type="text" name="nama_kategori" class="form-control" value="<?= htmlspecialchars($k['nama_kategori']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($k['keterangan']) ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>