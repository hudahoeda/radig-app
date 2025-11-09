<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_tp = isset($_GET['id_tp']) ? (int)$_GET['id_tp'] : 0;
if ($id_tp == 0) {
    echo "<script>Swal.fire('Error','ID Tujuan Pembelajaran tidak valid.','error').then(() => window.location = 'mapel_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data TP yang akan diedit (tanpa filter guru, karena ini admin)
$stmt_get_tp = mysqli_prepare($koneksi, "SELECT tp.*, g.nama_guru FROM tujuan_pembelajaran tp LEFT JOIN guru g ON tp.id_guru_pembuat = g.id_guru WHERE tp.id_tp = ?");
mysqli_stmt_bind_param($stmt_get_tp, "i", $id_tp);
mysqli_stmt_execute($stmt_get_tp);
$data_tp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_tp));

if (!$data_tp) {
    echo "<script>Swal.fire('Error','TP tidak ditemukan.','error').then(() => window.location = 'mapel_tampil.php');</script>";
    include 'footer.php';
    exit;
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Tujuan Pembelajaran (Admin)</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk TP: <strong><?php echo htmlspecialchars($data_tp['kode_tp']); ?></strong></p>
                <small class="opacity-50 fst-italic">Dibuat oleh: <?php echo htmlspecialchars($data_tp['nama_guru'] ?? 'N/A'); ?></small>
            </div>
            <a href="tp_tampil.php?id_mapel=<?php echo $data_tp['id_mapel']; ?>" class="btn btn-outline-light mt-3 mt-sm-0"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2" style="color: var(--primary-color);"></i>Formulir Edit TP</h5>
        </div>
        <form action="tp_admin_aksi.php?aksi=update" method="POST">
            <input type="hidden" name="id_tp" value="<?php echo $data_tp['id_tp']; ?>">
            <input type="hidden" name="id_mapel_redirect" value="<?php echo $data_tp['id_mapel']; ?>">

            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="id_guru_pembuat" class="form-label fw-bold">Guru Pembuat</label>
                        <select class="form-select" id="id_guru_pembuat" name="id_guru_pembuat" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php
                            $query_guru = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru WHERE role = 'guru' ORDER BY nama_guru");
                            while ($guru = mysqli_fetch_assoc($query_guru)) {
                                $selected = ($guru['id_guru'] == $data_tp['id_guru_pembuat']) ? 'selected' : '';
                                echo "<option value='{$guru['id_guru']}' $selected>" . htmlspecialchars($guru['nama_guru']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="kode_tp" class="form-label fw-bold">Kode TP <span class="text-muted">(Opsional)</span></label>
                        <input type="text" class="form-control" id="kode_tp" name="kode_tp" value="<?php echo htmlspecialchars($data_tp['kode_tp']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="deskripsi_tp" class="form-label fw-bold">Deskripsi Lengkap TP</label>
                    <textarea class="form-control" id="deskripsi_tp" name="deskripsi_tp" rows="4" required><?php echo htmlspecialchars($data_tp['deskripsi_tp']); ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="semester" class="form-label fw-bold">Semester</label>
                        <select class="form-select" id="semester" name="semester" required>
                            <option value="1" <?php if($data_tp['semester'] == 1) echo 'selected'; ?>>Ganjil</option>
                            <option value="2" <?php if($data_tp['semester'] == 2) echo 'selected'; ?>>Genap</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="tp_tampil.php?id_mapel=<?php echo $data_tp['id_mapel']; ?>" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>