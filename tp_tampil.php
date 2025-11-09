<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_mapel = isset($_GET['id_mapel']) ? (int)$_GET['id_mapel'] : 0;
if ($id_mapel == 0) {
    echo "<script>Swal.fire('Error','ID Mata Pelajaran tidak valid.','error').then(() => window.location = 'mapel_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil info mapel
$query_mapel = mysqli_query($koneksi, "SELECT nama_mapel FROM mata_pelajaran WHERE id_mapel=$id_mapel");
$data_mapel = mysqli_fetch_assoc($query_mapel);
if (!$data_mapel) {
    echo "<script>Swal.fire('Error','Mata Pelajaran tidak ditemukan.','error').then(() => window.location = 'mapel_tampil.php');</script>";
    include 'footer.php';
    exit;
}
$nama_mapel = $data_mapel['nama_mapel'];

// Ambil semua TP dan kelompokkan berdasarkan Guru
$query_tp = "SELECT tp.id_tp, tp.semester, tp.kode_tp, tp.deskripsi_tp, g.nama_guru 
             FROM tujuan_pembelajaran tp 
             LEFT JOIN guru g ON tp.id_guru_pembuat = g.id_guru
             WHERE tp.id_mapel = ? 
             AND tp.id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif')
             ORDER BY g.nama_guru, tp.semester, tp.kode_tp ASC";
$stmt = mysqli_prepare($koneksi, $query_tp);
mysqli_stmt_bind_param($stmt, "i", $id_mapel);
mysqli_stmt_execute($stmt);
$result_tp = mysqli_stmt_get_result($stmt);

// Kelompokkan data TP per guru
$tp_per_guru = [];
while ($tp = mysqli_fetch_assoc($result_tp)) {
    $nama_guru = $tp['nama_guru'] ?? '<em>Tidak Diketahui / Dihapus</em>';
    $tp_per_guru[$nama_guru][] = $tp;
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    /* Style untuk baris header grup guru */
    .guru-group-header td {
        background-color: var(--bs-secondary-bg);
        font-weight: bold;
        color: var(--primary-color);
        border-bottom: 2px solid var(--primary-color) !important;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Bank Tujuan Pembelajaran (TP)</h1>
                <p class="lead mb-0 opacity-75">Mata Pelajaran: <strong><?php echo htmlspecialchars($nama_mapel); ?></strong></p>
                <small class="opacity-50 fst-italic">Menampilkan semua TP dari seluruh guru untuk tahun ajaran aktif.</small>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="mapel_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-collection-fill me-2" style="color: var(--primary-color);"></i>Daftar Global TP</h5>
        </div>
        <div class="card-body p-0">
            <?php if(empty($tp_per_guru)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-journal-x fs-1 text-muted"></i>
                    <h4 class="mt-3">Belum Ada TP untuk Mata Pelajaran Ini</h4>
                    <p class="text-muted">TP yang dibuat oleh guru untuk mata pelajaran ini akan muncul di sini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 5%;">No</th>
                                <th style="width: 10%;">Semester</th>
                                <th>Deskripsi TP</th>
                                <th class="text-center" style="width: 15%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tp_per_guru as $nama_guru => $tps): ?>
                                <tr class="guru-group-header">
                                    <td colspan="4">
                                        <h5 class="mb-0"><i class="bi bi-person-check-fill me-2"></i><?php echo htmlspecialchars($nama_guru); ?></h5>
                                    </td>
                                </tr>
                                <?php $no = 1; foreach ($tps as $tp): ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                        <td><?php echo $tp['semester'] == 1 ? 'Ganjil' : 'Genap'; ?></td>
                                        <td>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($tp['kode_tp']); ?></small>
                                            <?php echo htmlspecialchars($tp['deskripsi_tp']); ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="tp_admin_edit.php?id_tp=<?php echo $tp['id_tp']; ?>" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Edit TP (Admin)"><i class="bi bi-pencil-fill"></i></a>
                                                <a href="#" onclick="konfirmasiHapus(<?php echo $tp['id_tp']; ?>, <?php echo $id_mapel; ?>)" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Hapus TP (Admin)"><i class="bi bi-trash-fill"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function konfirmasiHapus(id_tp, id_mapel) {
    Swal.fire({
        title: 'Anda Yakin?',
        html: "Anda akan menghapus TP ini secara permanen. Tindakan ini tidak dapat dibatalkan.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `tp_admin_aksi.php?aksi=hapus&id_tp=${id_tp}&id_mapel_redirect=${id_mapel}`;
        }
    })
}
</script>

<?php include 'footer.php'; ?>