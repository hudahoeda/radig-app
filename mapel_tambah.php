<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang untuk mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php'; // Tambahkan footer agar script Swal bisa dieksekusi
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
                <h1 class="mb-1">Tambah Mata Pelajaran</h1>
                <p class="lead mb-0 opacity-75">Menambahkan mata pelajaran baru ke dalam sistem.</p>
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
                <i class="bi bi-journal-plus me-2" style="color: var(--primary-color);"></i>
                Formulir Mata Pelajaran
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="mapel_aksi.php?aksi=tambah" method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="kode_mapel" class="form-label fw-bold">Kode Mapel</label>
                        <input type="text" class="form-control" id="kode_mapel" name="kode_mapel" placeholder="Contoh: MTK" maxlength="10" required>
                        <div class="form-text">Gunakan kode unik, maksimal 10 karakter.</div>
                        <div class="invalid-feedback">Kode mapel wajib diisi.</div>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="nama_mapel" class="form-label fw-bold">Nama Mata Pelajaran</label>
                        <input type="text" class="form-control" id="nama_mapel" name="nama_mapel" placeholder="Contoh: Matematika" required>
                        <div class="invalid-feedback">Nama mata pelajaran wajib diisi.</div>
                    </div>
                </div>
                
                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <a href="mapel_tampil.php" class="btn btn-secondary me-2">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-floppy-fill me-2"></i>Simpan Mata Pelajaran
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