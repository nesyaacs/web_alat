<?php
// users.php - Flat User Management Page
require_once __DIR__ . '/config.php';
check_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('danger', 'Invalid session (CSRF mismatch).');
        header('Location: users.php');
        exit;
    }

    if ($action === 'create') {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $nama_lengkap = sanitize($_POST['nama_lengkap'] ?? '');
        $role = sanitize($_POST['role'] ?? 'peminjam');

        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            set_flash_message('danger', 'All fields are required!');
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                set_flash_message('danger', 'Username already exists!');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $nama_lengkap, $role]);
                log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Added user: $username ($role)");
                set_flash_message('success', 'User added successfully.');
            }
        }
    } elseif ($action === 'update') {
        $id_user = (int)($_POST['id_user'] ?? 0);
        $nama_lengkap = sanitize($_POST['nama_lengkap'] ?? '');
        $role = sanitize($_POST['role'] ?? 'peminjam');
        $password = $_POST['password'] ?? '';

        if (empty($nama_lengkap) || $id_user <= 0) {
            set_flash_message('danger', 'Invalid user data.');
        } else {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, role = ?, password = ? WHERE id_user = ?");
                $stmt->execute([$nama_lengkap, $role, $hash, $id_user]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, role = ? WHERE id_user = ?");
                $stmt->execute([$nama_lengkap, $role, $id_user]);
            }
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Updated user ID #$id_user");
            set_flash_message('success', 'User updated successfully.');
        }
    } elseif ($action === 'delete') {
        $id_user = (int)($_POST['id_user'] ?? 0);
        if ($id_user === (int)$_SESSION['user']['id_user']) {
            set_flash_message('danger', 'You cannot delete your active account!');
        } elseif ($id_user > 0) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
            $stmt->execute([$id_user]);
            log_activity($_SESSION['user']['id_user'], $_SESSION['user']['nama_lengkap'], 'admin', "Deleted user ID #$id_user");
            set_flash_message('success', 'User deleted successfully.');
        }
    }

    header('Location: users.php');
    exit;
}

$search = sanitize($_GET['q'] ?? '');
$role_filter = sanitize($_GET['role'] ?? '');

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR nama_lengkap LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY id_user DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$title = "Manage Users";
$active_page = 'users';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i> User Management</h3>
            <p class="text-muted small mb-0">Manage system user accounts (Admin, Staff, Borrower)</p>
        </div>
        <button class="btn btn-primary rounded-3 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
            <i class="bi bi-plus-lg me-1"></i> Add New User
        </button>
    </div>

    <?= get_flash_message() ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search username or name..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select name="role" class="form-select">
                        <option value="">-- All Roles --</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        <option value="petugas" <?= $role_filter === 'petugas' ? 'selected' : '' ?>>Staff</option>
                        <option value="peminjam" <?= $role_filter === 'peminjam' ? 'selected' : '' ?>>Borrower</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                    <?php if(!empty($search) || !empty($role_filter)): ?>
                        <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
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
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th style="width: 140px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $index => $u): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-danger rounded-pill px-3">ADMIN</span>
                                        <?php elseif ($u['role'] === 'petugas'): ?>
                                            <span class="badge bg-primary rounded-pill px-3">STAFF</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark rounded-pill px-3">BORROWER</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal" data-bs-target="#modalEditUser<?= $u['id_user'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($u['id_user'] !== $_SESSION['user']['id_user']): ?>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirmAction('Delete user <?= htmlspecialchars($u['username']) ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
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

<!-- Modal Add User -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. mourvie_user" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Full name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role Access</label>
                        <select name="role" class="form-select" required>
                            <option value="peminjam">Borrower</option>
                            <option value="petugas">Staff</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals Edit User Outside Table -->
<?php if (!empty($users)): ?>
    <?php foreach ($users as $u): ?>
        <div class="modal fade" id="modalEditUser<?= $u['id_user'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                        
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($u['nama_lengkap']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">User Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                    <option value="petugas" <?= $u['role'] === 'petugas' ? 'selected' : '' ?>>Staff</option>
                                    <option value="peminjam" <?= $u['role'] === 'peminjam' ? 'selected' : '' ?>>Borrower</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">New Password (Optional)</label>
                                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
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