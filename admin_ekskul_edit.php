<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Admin
if ($_SESSION['role'] !== 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID dari URL dan validasi
$id_ekskul = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_ekskul <= 0) {
    echo "<script>Swal.fire('Error','ID Ekstrakurikuler tidak valid.','error').then(() => window.location = 'admin_ekskul.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data ekskul yang akan diedit menggunakan prepared statement
$query_ekskul = mysqli_prepare($koneksi, "SELECT id_ekskul, nama_ekskul, id_pembina FROM ekstrakurikuler WHERE id_ekskul = ?");
mysqli_stmt_bind_param($query_ekskul, "i", $id_ekskul);
mysqli_stmt_execute($query_ekskul);
$result_ekskul = mysqli_stmt_get_result($query_ekskul);
$data_ekskul = mysqli_fetch_assoc($result_ekskul);

// Jika data tidak ditemukan
if (!$data_ekskul) {
    echo "<script>Swal.fire('Error','Data Ekstrakurikuler tidak ditemukan.','error').then(() => window.location = 'admin_ekskul.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil semua data guru untuk pilihan dropdown
$q_guru = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru WHERE role = 'guru' ORDER BY nama_guru ASC");

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
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Ekstrakurikuler</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk: <?php echo htmlspecialchars($data_ekskul['nama_ekskul']); ?></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="admin_ekskul.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="bi bi-pencil-square me-2" style="color: var(--primary-color);"></i>
                Formulir Edit Ekskul
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="admin_ekskul_aksi.php?aksi=update" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id_ekskul" value="<?php echo $data_ekskul['id_ekskul']; ?>">
                
                <div class="mb-3">
                    <label for="nama_ekskul" class="form-label fw-bold">Nama Ekstrakurikuler</label>
                    <input type="text" name="nama_ekskul" id="nama_ekskul" class="form-control" value="<?php echo htmlspecialchars($data_ekskul['nama_ekskul']); ?>" required>
                    <div class="invalid-feedback">Nama ekskul wajib diisi.</div>
                </div>
                <div class="mb-3">
                    <label for="id_pembina" class="form-label fw-bold">Pilih Pembina</label>
                    <select name="id_pembina" id="id_pembina" class="form-select" required>
                        <option value="" disabled>-- Pilih Guru Pembina --</option>
                        <?php while ($guru = mysqli_fetch_assoc($q_guru)) : ?>
                            <?php $selected = ($guru['id_guru'] == $data_ekskul['id_pembina']) ? 'selected' : ''; ?>
                            <option value="<?php echo $guru['id_guru']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($guru['nama_guru']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback">Silakan pilih pembina.</div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <a href="admin_ekskul.php" class="btn btn-secondary me-2">
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