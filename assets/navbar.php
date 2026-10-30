<?php
// navbar.php - Menu Navigasi Sesuai Tabel Fitur UKK
$current_user = $_SESSION['user'] ?? null;
$role = $current_user['role'] ?? '';
$active_page = $active_page ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold fs-4" href="index.php">
            <i class="bi bi-tools me-2"></i> Mourvie Peminjaman Alat
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($role === 'admin'): ?>
                    <!-- Fitur Admin (Sesuai Tabel UKK) -->
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'dashboard' ? 'active fw-bold' : '' ?>" href="index.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'users' ? 'active fw-bold' : '' ?>" href="users.php">
                            <i class="bi bi-people me-1"></i> User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'alat' ? 'active fw-bold' : '' ?>" href="alat.php">
                            <i class="bi bi-box-seam me-1"></i> Alat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'kategori' ? 'active fw-bold' : '' ?>" href="kategori.php">
                            <i class="bi bi-tags me-1"></i> Kategori
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'peminjaman' ? 'active fw-bold' : '' ?>" href="peminjaman.php">
                            <i class="bi bi-arrow-left-right me-1"></i> Data Peminjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'pengembalian' ? 'active fw-bold' : '' ?>" href="pengembalian.php">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Pengembalian
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'log' ? 'active fw-bold' : '' ?>" href="log.php">
                            <i class="bi bi-journal-text me-1"></i> Log Aktifitas
                        </a>
                    </li>
                <?php elseif ($role === 'petugas'): ?>
                    <!-- Fitur Petugas (Sesuai Tabel UKK) -->
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'dashboard' ? 'active fw-bold' : '' ?>" href="index.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'persetujuan' ? 'active fw-bold' : '' ?>" href="persetujuan.php">
                            <i class="bi bi-check2-square me-1"></i> Menyetujui Peminjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'pengembalian' ? 'active fw-bold' : '' ?>" href="pengembalian.php">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Memantau Pengembalian
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'laporan' ? 'active fw-bold' : '' ?>" href="laporan.php">
                            <i class="bi bi-printer me-1"></i> Mencetak Laporan
                        </a>
                    </li>
                <?php elseif ($role === 'peminjam'): ?>
                    <!-- Fitur Peminjam (Sesuai Tabel UKK) -->
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'dashboard' ? 'active fw-bold' : '' ?>" href="index.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'alat' ? 'active fw-bold' : '' ?>" href="alat.php">
                            <i class="bi bi-grid me-1"></i> Melihat Daftar Alat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'pinjam' ? 'active fw-bold' : '' ?>" href="pinjam.php">
                            <i class="bi bi-plus-circle me-1"></i> Mengajukan Peminjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'riwayat' ? 'active fw-bold' : '' ?>" href="riwayat.php">
                            <i class="bi bi-clock-history me-1"></i> Mengembalikan Alat
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($current_user): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-1 py-1 px-3 rounded-pill" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle fs-5"></i>
                            <span class="d-none d-md-inline ms-1"><?= htmlspecialchars($current_user['nama_lengkap']) ?></span>
                            <span class="badge bg-light text-primary ms-1 text-uppercase small"><?= htmlspecialchars($current_user['role']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><span class="dropdown-item-text text-muted small">Login sebagai <strong><?= htmlspecialchars($current_user['username']) ?></strong></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="ganti_sandi.php">
                                    <i class="bi bi-key me-2"></i> Ganti Sandi
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-danger d-flex align-items-center" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-light text-primary font-weight-bold btn-sm rounded-pill px-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
