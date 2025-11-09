<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_kelas = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_kelas == 0) {
    echo "<script>Swal.fire('Error','ID Kelas tidak valid.','error').then(() => window.location = 'kelas_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data kelas yang akan diedit
$query = mysqli_prepare($koneksi, "SELECT * FROM kelas WHERE id_kelas = ?");
mysqli_stmt_bind_param($query, "i", $id_kelas);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<script>Swal.fire('Error','Data kelas tidak ditemukan.','error').then(() => window.location = 'kelas_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Mengambil jenjang sekolah untuk menentukan opsi fase
$q_sekolah = mysqli_query($koneksi, "SELECT jenjang FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);
$jenjang = $sekolah['jenjang'] ?? 'SMP'; // Default ke SMP jika tidak ada data

?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
    }
    
    /* PERBAIKAN: Style agar Select2 terlihat benar */
    .select2-container--bootstrap-5 .select2-selection {
        width: 100% !important;
        min-height: calc(1.5em + 0.75rem + 2px);
        padding: 0.375rem 0.75rem;
        font-family: inherit;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: var(--bs-body-color);
        background-color: var(--bs-form-control-bg);
        border: var(--bs-border-width) solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding: 0;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        top: 50% !important;
        transform: translateY(-50%) !important;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Data Kelas</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk Kelas: <strong><?php echo htmlspecialchars($data['nama_kelas']); ?></strong></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="kelas_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2" style="color: var(--primary-color);"></i>Formulir Edit Kelas</h5>
        </div>
        <form action="kelas_aksi.php?aksi=update" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="id_kelas" value="<?php echo $data['id_kelas']; ?>">
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nama_kelas" class="form-label fw-bold">Nama Kelas</label>
                        <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" value="<?php echo htmlspecialchars($data['nama_kelas']); ?>" required>
                        <div class="invalid-feedback">Nama kelas wajib diisi.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="fase" class="form-label fw-bold">Fase</label>
                        
                        <select class="form-select" id="fase" name="fase" required>
                            <option value="">-- Pilih Fase --</option>
                            <?php if ($jenjang == 'SD'): ?>
                                <option value="A" <?php if ($data['fase'] == 'A') echo 'selected'; ?>>Fase A (Kelas 1-2)</option>
                                <option value="B" <?php if ($data['fase'] == 'B') echo 'selected'; ?>>Fase B (Kelas 3-4)</option>
                                <option value="C" <?php if ($data['fase'] == 'C') echo 'selected'; ?>>Fase C (Kelas 5-6)</option>
                            <?php elseif ($jenjang == 'SMP'): ?>
                                <option value="D" <?php if ($data['fase'] == 'D') echo 'selected'; ?>>Fase D (Kelas 7-9)</option>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">Fase wajib dipilih.</div>
                        </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="id_wali_kelas" class="form-label fw-bold">Wali Kelas</label>
                        <!-- PERBAIKAN: ID di sini akan digunakan oleh Select2 -->
                        <select class="form-select" id="id_wali_kelas" name="id_wali_kelas" required>
                            <option value="">-- Cari dan Pilih Wali Kelas --</option>
                            <?php
                            $query_guru = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru WHERE role = 'guru' ORDER BY nama_guru");
                            while ($guru = mysqli_fetch_assoc($query_guru)) {
                                $selected = ($guru['id_guru'] == $data['id_wali_kelas']) ? 'selected' : '';
                                echo "<option value='{$guru['id_guru']}' $selected>" . htmlspecialchars($guru['nama_guru']) . "</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Silakan pilih wali kelas.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="id_tahun_ajaran" class="form-label fw-bold">Tahun Ajaran</label>
                        <select class="form-select" id="id_tahun_ajaran" name="id_tahun_ajaran" required>
                            <?php
                            $query_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
                            while ($ta = mysqli_fetch_assoc($query_ta)) {
                                $selected = ($ta['id_tahun_ajaran'] == $data['id_tahun_ajaran']) ? 'selected' : '';
                                echo "<option value='{$ta['id_tahun_ajaran']}' $selected>" . htmlspecialchars($ta['tahun_ajaran']) . "</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Silakan pilih tahun ajaran.</div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="kelas_tampil.php" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Skrip untuk validasi form Bootstrap
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

// --- PENAMBAHAN SCRIPT UNTUK SELECT2 ---
document.addEventListener('DOMContentLoaded', function () {
    // Cek jika jQuery sudah dimuat
    if (window.jQuery) {
        $('#id_wali_kelas').select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik untuk mencari guru...',
            width: '100%'
        });
    } else {
        console.error('jQuery tidak dimuat. Select2 tidak dapat diinisialisasi.');
    }
});
</script>

<?php include 'footer.php'; ?>
