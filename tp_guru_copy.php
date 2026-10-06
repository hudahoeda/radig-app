<?php
include 'koneksi.php';
include 'header.php';

// Validasi peran, hanya guru yang bisa mengakses
if ($_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk peran Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_guru_login = $_SESSION['id_guru'];

// Ambil TA Aktif
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status='Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta_aktif);
if (!$ta_aktif) {
    echo "<script>Swal.fire('Error','Tidak ada Tahun Ajaran Aktif!','error').then(() => window.location = 'tp_guru_tampil.php');</script>";
    include 'footer.php';
    exit;
}
$id_ta_aktif = $ta_aktif['id_tahun_ajaran'];
$nama_ta_aktif = $ta_aktif['tahun_ajaran'];

// Ambil TA Sumber dari GET
$id_ta_sumber = isset($_GET['id_ta_sumber']) ? (int)$_GET['id_ta_sumber'] : 0;
if ($id_ta_sumber == 0) {
    header("Location: tp_guru_tampil.php");
    exit;
}

// Info TA Sumber
$q_ta_sumber = mysqli_query($koneksi, "SELECT tahun_ajaran FROM tahun_ajaran WHERE id_tahun_ajaran = $id_ta_sumber");
$nama_ta_sumber = ($ta_sumber = mysqli_fetch_assoc($q_ta_sumber)) ? $ta_sumber['tahun_ajaran'] : 'Tidak Diketahui';

// Ambil semua TP milik guru ini di TA sumber
$query_tp = "
    SELECT tp.id_tp, tp.id_mapel, tp.semester, tp.kode_tp, tp.deskripsi_tp, tp.kktp, m.nama_mapel
    FROM tujuan_pembelajaran tp
    JOIN mata_pelajaran m ON tp.id_mapel = m.id_mapel
    WHERE tp.id_guru_pembuat = $id_guru_login AND tp.id_tahun_ajaran = $id_ta_sumber
    ORDER BY m.nama_mapel, tp.semester, tp.kode_tp
";
$result_tp = mysqli_query($koneksi, $query_tp);

$tp_per_mapel = [];
while ($tp = mysqli_fetch_assoc($result_tp)) {
    $tp_per_mapel[$tp['nama_mapel']][] = $tp;
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-files me-2"></i>Pilih TP untuk Disalin</h1>
        <a href="tp_guru_tampil.php" class="btn btn-secondary mt-3 mt-sm-0"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
    </div>

    <div class="alert alert-info border-info shadow-sm mb-4">
        <strong>Tahun Ajaran Sumber:</strong> <?php echo htmlspecialchars($nama_ta_sumber); ?> <br>
        <strong>Tujuan (TA Aktif):</strong> <?php echo htmlspecialchars($nama_ta_aktif); ?>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-card-checklist me-2"></i>Daftar Tujuan Pembelajaran</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tp_per_mapel)): ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Tidak ada Tujuan Pembelajaran yang ditemukan.</h5>
                </div>
            <?php else: ?>
                <form action="tp_guru_aksi.php" method="POST" id="formCopySelected">
                    <input type="hidden" name="aksi" value="copy_tp_selected">
                    <input type="hidden" name="id_ta_sumber" value="<?php echo $id_ta_sumber; ?>">

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 5%;"><input type="checkbox" id="checkAll" class="form-check-input" checked></th>
                                    <th style="width: 10%;" class="text-center">Semester</th>
                                    <th style="width: 15%;">Kode TP</th>
                                    <th>Deskripsi Tujuan Pembelajaran</th>
                                    <th style="width: 10%;" class="text-center">KKTP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tp_per_mapel as $nama_mapel => $tps): ?>
                                    <tr class="table-secondary">
                                        <td colspan="5" class="fw-bold text-primary">
                                            <i class="bi bi-book-half me-2"></i><?php echo htmlspecialchars($nama_mapel); ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($tps as $tp): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="tp_ids[]" value="<?php echo $tp['id_tp']; ?>" class="form-check-input check-item" checked>
                                        </td>
                                        <td class="text-center fw-bold"><?php echo htmlspecialchars($tp['semester']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($tp['kode_tp']); ?></span></td>
                                        <td><?php echo htmlspecialchars($tp['deskripsi_tp']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($tp['kktp']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 bg-light border-top text-end">
                        <button type="submit" class="btn btn-warning text-dark fw-bold px-4 py-2 shadow-sm" id="btnSubmit">
                            <i class="bi bi-clipboard-check-fill me-2"></i>Salin TP Terpilih
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.check-item').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

document.getElementById('formCopySelected')?.addEventListener('submit', function(e) {
    let checked = document.querySelectorAll('.check-item:checked').length;
    if (checked === 0) {
        e.preventDefault();
        Swal.fire('Peringatan', 'Silakan pilih minimal satu Tujuan Pembelajaran untuk disalin.', 'warning');
        return;
    }

    let btn = document.getElementById('btnSubmit');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyalin...';
    btn.disabled = true;
});
</script>

<?php include 'footer.php'; ?>
