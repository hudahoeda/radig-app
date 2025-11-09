<?php
include 'header.php';
include 'koneksi.php'; // Sebaiknya sertakan koneksi jika diperlukan di masa depan

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Tambah Pengguna Baru</h1>
                <p class="lead mb-0 opacity-75">Menambahkan guru atau admin baru ke dalam sistem.</p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="pengguna_tampil.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar Pengguna
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2" style="color: var(--primary-color);"></i>Formulir Data Pengguna</h5>
        </div>
        <form action="pengguna_aksi.php?aksi=tambah" method="POST" class="needs-validation" novalidate>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nama_guru" class="form-label fw-bold">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_guru" name="nama_guru" placeholder="Masukkan nama lengkap..." required>
                        <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nip" class="form-label fw-bold">NIP <span class="text-muted">(Opsional)</span></label>
                        <input type="text" class="form-control" id="nip" name="nip" placeholder="Masukkan NIP jika ada...">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label fw-bold">Username</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Gunakan username unik..." required>
                        <div class="invalid-feedback">Username wajib diisi.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label fw-bold">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Minimal 6 karakter..." required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                            <div class="invalid-feedback">Password wajib diisi.</div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label fw-bold">Role Sistem</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="" disabled selected>-- Pilih Role --</option>
                        <option value="guru">Guru</option>
                        <option value="admin">Admin</option>
                    </select>
                    <div class="invalid-feedback">Silakan pilih role pengguna.</div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="pengguna_tampil.php" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Skrip untuk validasi form Bootstrap
    (function () {
        'use strict';
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
    })();

    // Skrip untuk toggle lihat/sembunyikan password
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const eyeIcon = togglePassword.querySelector('i');

    togglePassword.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // Ganti ikon mata
        eyeIcon.classList.toggle('bi-eye');
        eyeIcon.classList.toggle('bi-eye-slash');
    });
});
</script>

<?php include 'footer.php'; ?>