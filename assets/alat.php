<?php
// alat.php - Flat Equipment Catalog & Inventory Page
require_once __DIR__ . '/config.php';
check_role();

$user_role = $_SESSION['user']['role'];

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// Admin Actions
if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('danger', 'Invalid session.');
        header('Location: alat.php');
        exit;
    }

    if ($action === 'create') {
        $nama_alat   = sanitize($_POST['nama_alat'] ?? '');
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $merek       = sanitize($_POST['merek'] ?? '');
        $stok        = (int)($_POST['stok'] ?? 0);
        $kondisi     = sanitize($_POST['kondisi'] ?? 'Baik');

        if (empty($nama_alat) || $id_kategori <= 0 || $stok < 0) {
            set_flash_message('danger', 'Please complete details correctly!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO alat (id_kategori, nama_alat, merek, stok, kondisi) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_kategori, $nama_alat, $merek, $stok, $kondisi]);
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Added equipment: $nama_alat (Stock: $stok)");
            set_flash_message('success', 'Equipment added successfully.');
        }
    } elseif ($action === 'update') {
        $id_alat     = (int)($_POST['id_alat'] ?? 0);
        $nama_alat   = sanitize($_POST['nama_alat'] ?? '');
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $merek       = sanitize($_POST['merek'] ?? '');
        $stok        = (int)($_POST['stok'] ?? 0);
        $kondisi     = sanitize($_POST['kondisi'] ?? 'Baik');

        if ($id_alat <= 0 || empty($nama_alat) || $id_kategori <= 0 || $stok < 0) {
            set_flash_message('danger', 'Invalid data.');
        } else {
            $stmt = $pdo->prepare("UPDATE alat SET id_kategori = ?, nama_alat = ?, merek = ?, stok = ?, kondisi = ? WHERE id_alat = ?");
            $stmt->execute([$id_kategori, $nama_alat, $merek, $stok, $kondisi, $id_alat]);
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Updated equipment ID #$id_alat ($nama_alat)");
            set_flash_message('success', 'Equipment updated successfully.');
        }
    } elseif ($action === 'delete') {
        $id_alat = (int)($_POST['id_alat'] ?? 0);
        if ($id_alat > 0) {
            $stmt = $pdo->prepare("DELETE FROM alat WHERE id_alat = ?");
            $stmt->execute([$id_alat]);
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Deleted equipment ID #$id_alat");
            set_flash_message('success', 'Equipment deleted successfully.');
        }
    }

    header('Location: alat.php');
    exit;
}

// Search & Filter
$search   = sanitize($_GET['q'] ?? '');
$cat_filter = (int)($_GET['kategori'] ?? 0);

$sql = "SELECT a.*, k.nama_kategori FROM alat a JOIN kategori k ON a.id_kategori = k.id_kategori WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (a.nama_alat LIKE ? OR a.merek LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($cat_filter > 0) {
    $sql .= " AND a.id_kategori = ?";
    $params[] = $cat_filter;
}

$sql .= " ORDER BY a.id_alat DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tools = $stmt->fetchAll();

$title = "Equipment Inventory & Catalog";
$active_page = 'alat';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-box-seam text-primary me-2"></i> Equipment Catalog & Inventory</h3>
            <p class="text-muted small mb-0">Browse real-time stock and manage laboratory equipment items</p>
        </div>
        <?php if ($user_role === 'admin'): ?>
            <button class="btn btn-primary rounded-3 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahAlat">
                <i class="bi bi-plus-lg me-1"></i> Add New Item
            </button>
        <?php elseif ($user_role === 'peminjam'): ?>
            <a href="pinjam.php" class="btn btn-primary rounded-3 px-3 shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Request Loan
            </a>
        <?php endif; ?>
    </div>

    <?= get_flash_message() ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search item name or brand..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select name="kategori" class="form-select">
                        <option value="0">-- All Categories --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id_kategori'] ?>" <?= $cat_filter === (int)$c['id_kategori'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                    <?php if(!empty($search) || $cat_filter > 0): ?>
                        <a href="alat.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
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
                            <th style="width: 60px;">#</th>
                            <th>Equipment Name</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th class="text-center">Stock</th>
                            <th>Condition</th>
                            <th style="width: 140px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tools)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No equipment items found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tools as $index => $t): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($t['nama_alat']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['nama_kategori']) ?></span></td>
                                    <td><?= htmlspecialchars($t['merek'] ?: '-') ?></td>
                                    <td class="text-center">
                                        <?php if ($t['stok'] > 0): ?>
                                            <span class="badge bg-success rounded-pill px-3"><?= $t['stok'] ?> units</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t['kondisi'] === 'Baik'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Good</span>
                                        <?php elseif ($t['kondisi'] === 'Rusak Ringan'): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Minor Damage</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Severe Damage</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($user_role === 'admin'): ?>
                                            <button class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal" data-bs-target="#modalEditAlat<?= $t['id_alat'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirmAction('Delete item <?= htmlspecialchars($t['nama_alat']) ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_alat" value="<?= $t['id_alat'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($t['stok'] > 0): ?>
                                                <a href="pinjam.php?id_alat=<?= $t['id_alat'] ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                                    <i class="bi bi-plus-lg me-1"></i> Borrow
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary rounded-pill px-3" disabled>Empty</button>
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

<?php if ($user_role === 'admin'): ?>
    <div class="modal fade" id="modalTambahAlat" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Add Equipment Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Equipment Name</label>
                            <input type="text" name="nama_alat" class="form-control" placeholder="e.g. Epson HD Projector" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="id_kategori" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id_kategori'] ?>"><?= htmlspecialchars($c['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Brand / Manufacturer</label>
                            <input type="text" name="merek" class="form-control" placeholder="e.g. Epson, Canon, Tekiro">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Stock Quantity</label>
                                <input type="number" name="stok" class="form-control" min="0" value="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Condition</label>
                                <select name="kondisi" class="form-select">
                                    <option value="Baik">Good</option>
                                    <option value="Rusak Ringan">Minor Damage</option>
                                    <option value="Rusak Berat">Severe Damage</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Save Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($tools)): ?>
        <?php foreach ($tools as $t): ?>
            <div class="modal fade" id="modalEditAlat<?= $t['id_alat'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="" method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id_alat" value="<?= $t['id_alat'] ?>">
                            
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">Edit Equipment Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Equipment Name</label>
                                    <input type="text" name="nama_alat" class="form-control" value="<?= htmlspecialchars($t['nama_alat']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Category</label>
                                    <select name="id_kategori" class="form-select" required>
                                        <?php foreach ($categories as $c): ?>
                                            <option value="<?= $c['id_kategori'] ?>" <?= $t['id_kategori'] == $c['id_kategori'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['nama_kategori']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Brand / Manufacturer</label>
                                    <input type="text" name="merek" class="form-control" value="<?= htmlspecialchars($t['merek']) ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Stock Quantity</label>
                                        <input type="number" name="stok" class="form-control" min="0" value="<?= $t['stok'] ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Condition</label>
                                        <select name="kondisi" class="form-select">
                                            <option value="Baik" <?= $t['kondisi'] === 'Baik' ? 'selected' : '' ?>>Good</option>
                                            <option value="Rusak Ringan" <?= $t['kondisi'] === 'Rusak Ringan' ? 'selected' : '' ?>>Minor Damage</option>
                                            <option value="Rusak Berat" <?= $t['kondisi'] === 'Rusak Berat' ? 'selected' : '' ?>>Severe Damage</option>
                                        </select>
                                    </div>
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
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>