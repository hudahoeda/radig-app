<?php
include 'koneksi.php';
include 'header.php';

// Validasi peran, hanya guru yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_guru_login = (int)$_SESSION['id_guru'];
$id_tp = isset($_GET['id_tp']) ? (int)$_GET['id_tp'] : 0;

if ($id_tp == 0) {
    echo "<script>Swal.fire('Error','ID Tujuan Pembelajaran tidak valid.','error').then(() => window.location = 'tp_guru_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data TP yang akan diedit, pastikan TP ini milik guru yang sedang login.
$query_get_tp = "SELECT * FROM tujuan_pembelajaran WHERE id_tp = ? AND id_guru_pembuat = ?";
$stmt_get_tp = mysqli_prepare($koneksi, $query_get_tp);
mysqli_stmt_bind_param($stmt_get_tp, "ii", $id_tp, $id_guru_login);
mysqli_stmt_execute($stmt_get_tp);
$result_tp = mysqli_stmt_get_result($stmt_get_tp);
$data_tp = mysqli_fetch_assoc($result_tp);

if (!$data_tp) {
    echo "<script>Swal.fire('Akses Ditolak','TP tidak ditemukan atau Anda tidak berhak mengubahnya.','error').then(() => window.location = 'tp_guru_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID tahun ajaran aktif
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta_aktif = mysqli_fetch_assoc($q_ta_aktif);
$id_tahun_ajaran_aktif = $d_ta_aktif['id_tahun_ajaran'] ?? 0;
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
                <h1 class="mb-1">Edit Tujuan Pembelajaran</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk TP dengan kode: <strong><?php echo htmlspecialchars($data_tp['kode_tp']); ?></strong></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="tp_guru_tampil.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar TP
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2" style="color: var(--primary-color);"></i>Formulir Edit Tujuan Pembelajaran</h5>
        </div>
        <form action="tp_guru_aksi.php?aksi=update" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="id_tp" value="<?php echo $data_tp['id_tp']; ?>">
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="id_mapel" class="form-label fw-bold">Mata Pelajaran</label>
                        <select class="form-select" id="id_mapel" name="id_mapel" required>
                            <option value="" disabled>-- Pilih Mata Pelajaran --</option>
                            <?php
                            $query_mapel = "SELECT DISTINCT m.id_mapel, m.nama_mapel FROM guru_mengajar gm JOIN mata_pelajaran m ON gm.id_mapel = m.id_mapel WHERE gm.id_guru = ? AND gm.id_tahun_ajaran = ?";
                            $stmt_mapel = mysqli_prepare($koneksi, $query_mapel);
                            mysqli_stmt_bind_param($stmt_mapel, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
                            mysqli_stmt_execute($stmt_mapel);
                            $result_mapel = mysqli_stmt_get_result($stmt_mapel);
                            while ($mapel = mysqli_fetch_assoc($result_mapel)) {
                                $selected = ($mapel['id_mapel'] == $data_tp['id_mapel']) ? 'selected' : '';
                                echo "<option value='{$mapel['id_mapel']}' {$selected}>" . htmlspecialchars($mapel['nama_mapel']) . "</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Silakan pilih mata pelajaran.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="kode_tp" class="form-label fw-bold">Kode TP <span class="text-muted">(Opsional)</span></label>
                        <input type="text" class="form-control" id="kode_tp" name="kode_tp" placeholder="Contoh: B.IND.7.1" value="<?php echo htmlspecialchars($data_tp['kode_tp']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="deskripsi_tp" class="form-label fw-bold">Deskripsi Lengkap Tujuan Pembelajaran</label>
                    <textarea class="form-control" id="deskripsi_tp" name="deskripsi_tp" rows="4" required><?php echo htmlspecialchars($data_tp['deskripsi_tp']); ?></textarea>
                    <div class="invalid-feedback">Deskripsi TP tidak boleh kosong.</div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="semester" class="form-label fw-bold">Semester</label>
                        <select class="form-select" id="semester" name="semester" required>
                            <option value="1" <?php if($data_tp['semester'] == 1) echo 'selected'; ?>>Ganjil</option>
                            <option value="2" <?php if($data_tp['semester'] == 2) echo 'selected'; ?>>Genap</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="tp_guru_tampil.php" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Perubahan</button>
            </div>
        </form>
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