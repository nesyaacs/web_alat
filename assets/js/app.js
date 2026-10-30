// SiPinjam - Skrip aplikasi (dipakai semua halaman)
document.addEventListener('DOMContentLoaded', function () {
    // Validasi form Bootstrap
    document.querySelectorAll('.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
            form.classList.add('was-validated');
        });
    });
    // Auto-tutup notifikasi
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () { bootstrap.Alert.getOrCreateInstance(alert).close(); }, 5000);
    });
});

// Konfirmasi sebelum aksi penting (dan alias lama)
function konfirmasi(pesan) { return confirm(pesan || 'Yakin ingin melanjutkan?'); }
function confirmAction(pesan) { return konfirmasi(pesan); }

// Login: pastikan username & password kosong saat halaman dibuka (anti-autofill)
function bersihkanLogin() {
    var f = document.getElementById('loginForm');
    if (!f) return;
    var u = f.querySelector('[name="username"]');
    var p = f.querySelector('[name="password"]');
    if (u) u.value = '';
    if (p) p.value = '';
}
window.addEventListener('pageshow', bersihkanLogin);
document.addEventListener('DOMContentLoaded', bersihkanLogin);