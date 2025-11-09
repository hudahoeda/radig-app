<?php
include 'header.php';
include 'koneksi.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error',title: 'Akses Ditolak'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID Kelas dari URL dan pastikan valid
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    // Gunakan SweetAlert untuk pesan error yang konsisten
    echo "<script>Swal.fire('Error','ID Kelas tidak valid atau tidak ditemukan.','error').then(() => window.location = 'kelas_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil nama kelas untuk ditampilkan di judul
$query_kelas = mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas=$id_kelas");
if (mysqli_num_rows($query_kelas) == 0) {
    echo "<script>Swal.fire('Error','Kelas tidak ditemukan.','error').then(() => window.location = 'kelas_tampil.php');</script>";
    include 'footer.php';
    exit;
}
$data_kelas = mysqli_fetch_assoc($query_kelas);
$nama_kelas = $data_kelas['nama_kelas'];
?>

<style>
    /* Mengadopsi gaya yang sama dari halaman sebelumnya untuk konsistensi */
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { 
        box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
        font-weight: 600; 
    }
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Tambah Siswa Baru</h1>
                <p class="lead mb-0 opacity-75">Menambahkan siswa ke Kelas: <?php echo htmlspecialchars($nama_kelas); ?></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="siswa_tampil.php?id_kelas=<?php echo $id_kelas; ?>" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar Siswa
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="bi bi-person-plus-fill me-2" style="color: var(--primary-color);"></i>
                Formulir Data Siswa
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="siswa_aksi.php?aksi=tambah" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nama_lengkap" class="form-label fw-bold">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Contoh: Budi Setiawan" required>
                        <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nisn" class="form-label fw-bold">NISN</label>
                        <input type="text" class="form-control" id="nisn" name="nisn" placeholder="Masukkan 10 digit NISN" required pattern="\d{10}">
                        <div class="invalid-feedback">NISN wajib diisi dan harus 10 digit angka.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="nis" class="form-label fw-bold">NIS <span class="text-muted">(Opsional)</span></label>
                        <input type="text" class="form-control" id="nis" name="nis" placeholder="Masukkan NIS jika ada">
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="username" class="form-label fw-bold">Username</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Contoh: budi.setiawan" required>
                        <div class="form-text">Gunakan format yang seragam, misal: nama.depan</div>
                        <div class="invalid-feedback">Username wajib diisi.</div>
                    </div>
                </div>

                <div class="alert alert-info border-0" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Password default untuk siswa baru akan diatur sama dengan **NISN** mereka.
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <a href="siswa_tampil.php?id_kelas=<?php echo $id_kelas; ?>" class="btn btn-secondary me-2">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-floppy-fill me-2"></i>Simpan Data Siswa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script untuk validasi form Bootstrap
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php include 'footer.php'; ?>