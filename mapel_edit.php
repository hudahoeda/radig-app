<?php
include 'header.php';
include 'koneksi.php';

// Pastikan hanya admin yang bisa mengakses
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID mapel dari URL dan validasi
$id_mapel = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_mapel <= 0) {
    echo "<script>Swal.fire('Error','ID Mata Pelajaran tidak valid.','error').then(() => window.location = 'mapel_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data mapel yang akan diedit dari database menggunakan prepared statement
$query = mysqli_prepare($koneksi, "SELECT id_mapel, kode_mapel, nama_mapel FROM mata_pelajaran WHERE id_mapel = ?");
mysqli_stmt_bind_param($query, "i", $id_mapel);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);
$data = mysqli_fetch_assoc($result);

// Jika data tidak ditemukan, kembalikan ke halaman tampil
if (!$data) {
    echo "<script>Swal.fire('Error','Data Mata Pelajaran tidak ditemukan.','error').then(() => window.location = 'mapel_tampil.php');</script>";
    include 'footer.php';
    exit;
}
?>

<style>
    /* Gaya konsisten untuk semua halaman formulir */
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
                <h1 class="mb-1">Edit Mata Pelajaran</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk: <?php echo htmlspecialchars($data['nama_mapel']); ?></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="mapel_tampil.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar Mapel
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="bi bi-pencil-square me-2" style="color: var(--primary-color);"></i>
                Formulir Edit Mata Pelajaran
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="mapel_aksi.php?aksi=update" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id_mapel" value="<?php echo $data['id_mapel']; ?>">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="kode_mapel" class="form-label fw-bold">Kode Mapel</label>
                        <input type="text" class="form-control" id="kode_mapel" name="kode_mapel" value="<?php echo htmlspecialchars($data['kode_mapel']); ?>" maxlength="10" required>
                        <div class="form-text">Gunakan kode unik, maksimal 10 karakter.</div>
                        <div class="invalid-feedback">Kode mapel wajib diisi.</div>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="nama_mapel" class="form-label fw-bold">Nama Mata Pelajaran</label>
                        <input type="text" class="form-control" id="nama_mapel" name="nama_mapel" value="<?php echo htmlspecialchars($data['nama_mapel']); ?>" required>
                        <div class="invalid-feedback">Nama mata pelajaran wajib diisi.</div>
                    </div>
                </div>
                
                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <a href="mapel_tampil.php" class="btn btn-secondary me-2">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-floppy-fill me-2"></i>Simpan Perubahan
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