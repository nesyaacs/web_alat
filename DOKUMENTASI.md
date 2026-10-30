# DOKUMENTASI & PEMETAAN FITUR APLIKASI PEMINJAMAN ALAT (UKK RPL)

---

## I. TABEL PEMETAAN FITUR & HALAMAN PROGRAM

Tabel di bawah ini disesuaikan 100% dengan **Tabel Fitur Aplikasi Peminjaman Alat** pada lembar soal UKK RPL:

| Fitur Aplikasi UKK | Admin | Petugas | Peminjam | Nama File Program (Direct Root) |
|---|:---:|:---:|:---:|---|
| **Login** | V | V | V | [login.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/login.php) |
| **Logout** | V | V | V | [logout.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/logout.php) |
| **CRUD user** | V | | | [users.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/users.php) |
| **CRUD alat** | V | | | [alat.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/alat.php) |
| **CRUD kategori** | V | | | [kategori.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/kategori.php) |
| **CRUD data peminjaman** | V | | | [peminjaman.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/peminjaman.php) |
| **CRUD pengembalian** | V | | | [pengembalian.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/pengembalian.php) |
| **Log Aktifitas** | V | | | [log.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/log.php) |
| **Menyetujui Peminjaman** | | V | | [persetujuan.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/persetujuan.php) |
| **Memantau Pengembalian** | | V | | [pengembalian.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/pengembalian.php) |
| **Mencetak Laporan** | | V | | [laporan.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/laporan.php) |
| **Melihat daftar alat** | | | V | [alat.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/alat.php) |
| **Mengajukan peminjaman** | | | V | [pinjam.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/pinjam.php) |
| **Mengembalikan alat** | | | V | [riwayat.php](file:///c:/xampp/htdocs/NESYA_XII_RPL1/project2/riwayat.php) |

---

## II. DESAIN SYSTEM & DATABASE OBJECTS (`database.sql`)

1. **Keamanan & Otorisasi**: Proteksi SQL Injection via PDO Prepared Statements, XSS Protection (`sanitize()`), CSRF Token Verification pada setiap form `POST`, dan Otentikasi Role (`check_role()`).
2. **Database Objects Canggih**:
   - **Stored Procedure `sp_setujui_peminjaman`**: Memverifikasi ketersediaan stok, mengubah status menjadi `'Disetujui'`, dan memotong stok barang dalam `START TRANSACTION` / `COMMIT` / `ROLLBACK`.
   - **Stored Procedure `sp_proses_pengembalian`**: Memproses pengembalian barang, memicu fungsi denda, dan mengembalikan stok barang secara otomatis.
   - **Stored Function `fn_hitung_denda`**: Menghitung keterlambatan pengembalian dan kalkulasi denda otomatis (Rp 5.000/hari).
   - **Trigger `trg_after_alat_insert`**: Memicu pencatatan audit log saat alat baru ditambahkan.

---

## III. SKENARIO PENGUJIAN (TEST CASES)

| No | Kasus Uji | Langkah Pengujian | Hasil Pengujian | Status |
|---|---|---|---|:---:|
| 1 | **Login User** | Login dengan password salah | Gagal login, muncul alert "Invalid username or password!". | **PASSED** |
| 2 | **Tambah Alat** | Admin menambah alat "Proyektor HD" | Data alat tersimpan dan stok 5 unit tampil di tabel. | **PASSED** |
| 3 | **Pinjam Alat** | Peminjam mengajukan pinjam alat | Transaksi tersimpan dengan status `Pending`. | **PASSED** |
| 4 | **Kembalikan Alat & Denda** | Petugas memproses pengembalian terlambat | Denda Rp 10.000 terhitung otomatis & stok kembali. | **PASSED** |
| 5 | **Cek Privilege User** | Peminjam mengakses `users.php` | Akses ditolak & otomatis redirect ke dashboard. | **PASSED** |