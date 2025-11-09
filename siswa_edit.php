<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_siswa = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_siswa == 0) {
    echo "<script>Swal.fire('Error','ID Siswa tidak valid.','error').then(() => window.location = 'pengguna_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Menggunakan prepared statement untuk keamanan
$stmt = mysqli_prepare($koneksi, "SELECT * FROM siswa WHERE id_siswa=?");
mysqli_stmt_bind_param($stmt, "i", $id_siswa);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<script>Swal.fire('Error','Siswa tidak ditemukan.','error').then(() => window.location = 'pengguna_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

// Ambil daftar kelas untuk tahun ajaran aktif
$daftar_kelas = [];
if ($id_tahun_ajaran_aktif > 0) {
    $query_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas ASC");
    $daftar_kelas = mysqli_fetch_all($query_kelas, MYSQLI_ASSOC);
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25); }
    .nav-tabs .nav-link.active { color: var(--primary-color); border-color: var(--primary-color) var(--primary-color) #fff; font-weight: 600; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Siswa</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk: <strong><?php echo htmlspecialchars($data['nama_lengkap']); ?></strong></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="pengguna_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2" style="color: var(--primary-color);"></i>Formulir Data Siswa</h5>
        </div>
        <form action="siswa_aksi.php?aksi=update" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="id_siswa" value="<?php echo $data['id_siswa']; ?>">
            
            <div class="card-body p-4">
                 <div class="alert alert-warning">Perhatian: Saat ini Anda hanya dapat mengubah data akun dan data kelas. Untuk data siswa lainnya, silakan impor ulang melalui file Excel.</div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nama_lengkap" class="form-label fw-bold">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($data['nama_lengkap']); ?>" required>
                        <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nisn" class="form-label fw-bold">NISN (Unik)</label>
                        <input type="text" class="form-control" id="nisn" name="nisn" value="<?php echo htmlspecialchars($data['nisn']); ?>" required>
                         <div class="invalid-feedback">NISN wajib diisi.</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nis" class="form-label fw-bold">NIS <span class="text-muted">(Opsional)</span></label>
                        <input type="text" class="form-control" id="nis" name="nis" value="<?php echo htmlspecialchars($data['nis']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label fw-bold">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($data['username']); ?>" required>
                        <div class="invalid-feedback">Username wajib diisi.</div>
                    </div>
                </div>
                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label fw-bold">Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button>
                        </div>
                        <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="id_kelas" class="form-label fw-bold">Kelas (Tahun Ajaran Aktif)</label>
                        <select class="form-select" id="id_kelas" name="id_kelas">
                            <option value="">-- Hapus dari Kelas --</option>
                            <?php foreach ($daftar_kelas as $kelas): ?>
                                <option value="<?php echo $kelas['id_kelas']; ?>" <?php if ($data['id_kelas'] == $kelas['id_kelas']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                         <div class="form-text">Mengganti kelas akan otomatis mengganti wali kelas siswa.</div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="pengguna_tampil.php" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Validasi Bootstrap
    (function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation'); Array.prototype.slice.call(forms).forEach(function (form) { form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false); }); })();
    
    // Toggle Password
    const togglePassword = document.querySelector('#togglePassword');
    if (togglePassword) {
        const password = document.querySelector('#password');
        const eyeIcon = togglePassword.querySelector('i');
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            eyeIcon.classList.toggle('bi-eye');
            eyeIcon.classList.toggle('bi-eye-slash');
        });
    }
});
</script>

<?php include 'footer.php'; ?>