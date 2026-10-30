<?php
// pinjam.php - Form pengajuan peminjaman alat
require_once __DIR__ . '/config.php';
cek_login(['peminjam', 'admin']);

$id_user = $_SESSION['user']['id_user'];
$preselect = (int)($_GET['id_alat'] ?? 0);

$tools = $pdo->query("SELECT * FROM alat WHERE stok > 0 ORDER BY nama_alat")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifikasi_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid.';
    } else {
        $id_alat             = (int)($_POST['id_alat'] ?? 0);
        $jumlah              = (int)($_POST['jumlah'] ?? 1);
        $tgl_pinjam          = sanitize($_POST['tgl_pinjam'] ?? '');
        $tgl_kembali_rencana = sanitize($_POST['tgl_kembali_rencana'] ?? '');
        $catatan             = sanitize($_POST['catatan'] ?? '');

        if ($id_alat <= 0 || $jumlah <= 0 || $tgl_pinjam === '' || $tgl_kembali_rencana === '') {
            $error = 'Lengkapi semua field!';
        } elseif ($tgl_kembali_rencana < $tgl_pinjam) {
            $error = 'Tanggal kembali tidak boleh sebelum tanggal pinjam!';
        } else {
            // Cek stok alat
            $stmt = $pdo->prepare("SELECT nama_alat, stok FROM alat WHERE id_alat = ?");
            $stmt->execute([$id_alat]);
            $alat = $stmt->fetch();

            if (!$alat) {
                $error = 'Alat tidak ditemukan.';
            } elseif ($jumlah > $alat['stok']) {
                $error = "Jumlah ($jumlah) melebihi stok ({$alat['stok']})!";
            } else {
                try {
                    // Simpan transaksi peminjaman dengan transaksi database
                    $pdo->beginTransaction();

                    // Buat kode transaksi unik, cth: PMJ-20261009-001
                    $prefix = 'PMJ-' . date('Ymd') . '-';
                    $n = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE kode_peminjaman LIKE ?");
                    $n->execute([$prefix . '%']);
                    $kode = $prefix . str_pad($n->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);

                    $pdo->prepare("INSERT INTO peminjaman (kode_peminjaman, id_user, tgl_pinjam, tgl_kembali_rencana, status, catatan) VALUES (?,?,?,?,?,?)")
                            ->execute([$kode, $id_user, $tgl_pinjam, $tgl_kembali_rencana, 'Pending', $catatan]);
                    $id_peminjaman = $pdo->lastInsertId();

                    $pdo->prepare("INSERT INTO detail_peminjaman (id_peminjaman, id_alat, jumlah) VALUES (?,?,?)")
                            ->execute([$id_peminjaman, $id_alat, $jumlah]);

                    log_aktivitas($id_user, $_SESSION['user']['nama_lengkap'], 'peminjam', "Mengajukan peminjaman $kode ({$alat['nama_alat']} x$jumlah)");

                    $pdo->commit();
                    set_flash('success', 'Pengajuan peminjaman terkirim! Menunggu persetujuan petugas.');
                    header('Location: riwayat.php');
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
                }
            }
        }
    }
}

$hari_ini = date('Y-m-d');
$tgl_default = date('Y-m-d', strtotime('+3 days'));

$title = "Ajukan Peminjaman";
$active_page = 'pinjam';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>
<div class="container py-4">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-plus me-2"></i> Form Peminjaman Alat</h4>
            </div>
            <div class="card-body p-4">
                <?= tampil_flash() ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-3 shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (empty($tools)): ?>
                    <div class="alert alert-warning text-center py-4">
                        <i class="bi bi-exclamation-circle fs-2 d-block mb-2"></i>
                        Maaf, tidak ada alat yang tersedia untuk dipinjam.
                    </div>
                <?php else: ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Alat</label>
                            <select name="id_alat" class="form-select" required>
                                <option value="">-- Pilih Alat --</option>
                                <?php foreach ($tools as $t): ?>
                                    <option value="<?= $t['id_alat'] ?>" <?= $preselect===(int)$t['id_alat']?'selected':'' ?>>
                                        <?= htmlspecialchars($t['nama_alat']) ?> (Stok: <?= $t['stok'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jumlah (unit)</label>
                            <input type="number" name="jumlah" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-semibold">Tanggal Pinjam</label>
                                <input type="date" name="tgl_pinjam" class="form-control" value="<?= $hari_ini ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-semibold">Batas Kembali</label>
                                <input type="date" name="tgl_kembali_rencana" class="form-control" value="<?= $tgl_default ?>" min="<?= $hari_ini ?>" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Keperluan / Catatan</label>
                            <textarea name="catatan" class="form-control" rows="3" placeholder="Tuliskan keperluan peminjaman..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2 rounded-3 fw-semibold"><i class="bi bi-send me-2"></i> Kirim Pengajuan</button>
                            <a href="alat.php" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>