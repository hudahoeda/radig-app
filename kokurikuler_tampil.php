<?php
include 'header.php';
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// === OPTIMASI PERFORMA (LANGKAH 1): Ambil semua kegiatan dalam satu query ===
// <-- DIMODIFIKASI: Menambah JOIN ke tabel guru untuk ambil nama koordinator -->
$query_kegiatan = "SELECT k.id_kegiatan, k.semester, k.tema_kegiatan, k.bentuk_kegiatan, ta.tahun_ajaran, 
                          g.nama_guru AS nama_koordinator
                   FROM kokurikuler_kegiatan k
                   JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran
                   LEFT JOIN guru g ON k.id_koordinator = g.id_guru 
                   WHERE ta.status = 'Aktif' 
                   ORDER BY k.semester, k.tema_kegiatan ASC";
$result_kegiatan = mysqli_query($koneksi, $query_kegiatan);

$kegiatan_data = [];
$kegiatan_ids = [];
if ($result_kegiatan) {
    while ($row = mysqli_fetch_assoc($result_kegiatan)) {
        $kegiatan_data[] = $row;
        $kegiatan_ids[] = $row['id_kegiatan'];
    }
}

// === OPTIMASI PERFORMA (LANGKAH 2): Ambil semua dimensi dalam satu query ===
$dimensi_per_kegiatan = [];
if (!empty($kegiatan_ids)) {
    $id_list = implode(',', $kegiatan_ids);
    $query_dimensi = mysqli_query($koneksi, "SELECT id_kegiatan, nama_dimensi FROM kokurikuler_target_dimensi WHERE id_kegiatan IN ($id_list)");
    while($dimensi = mysqli_fetch_assoc($query_dimensi)){
        $dimensi_per_kegiatan[$dimensi['id_kegiatan']][] = $dimensi['nama_dimensi'];
    }
}

// Kelompokkan data kegiatan per semester untuk ditampilkan
$kegiatan_per_semester = [1 => [], 2 => []];
foreach ($kegiatan_data as $kegiatan) {
    $kegiatan_per_semester[$kegiatan['semester']][] = $kegiatan;
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    .semester-heading { font-weight: 600; color: var(--secondary-color); padding-bottom: 0.5rem; border-bottom: 2px solid var(--secondary-color); margin-bottom: 1.5rem; display: inline-block; }
    .project-card { transition: all 0.2s ease-in-out; border: 1px solid var(--border-color); border-left: 5px solid var(--primary-color); }
    .project-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    /* Style badge yang lebih modern dan konsisten */
    .dimension-badge {
        background-color: var(--bs-primary-bg-subtle) !important;
        color: var(--bs-primary-text-emphasis) !important;
        border: 1px solid var(--bs-primary-border-subtle);
        font-weight: 500;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Kokurikuler</h1>
                <p class="lead mb-0 opacity-75">Rencanakan kegiatan kokurikuler untuk tahun ajaran aktif.</p>
            </div>
            <a href="kokurikuler_tambah.php" class="btn btn-light mt-3 mt-sm-0">
                <i class="bi bi-plus-circle-fill me-2"></i>Rencanakan Kegiatan Baru
            </a>
        </div>
    </div>

    <?php foreach ($kegiatan_per_semester as $semester => $daftar_kegiatan): ?>
        <div class="semester-group mb-5">
            <h3 class="semester-heading"><i class="bi bi-calendar-range-fill me-2"></i>Kegiatan Semester <?php echo $semester; ?></h3>
            
            <?php if (empty($daftar_kegiatan)): ?>
                <div class="card card-body text-center border-dashed">
                    <p class="text-muted mb-0">Belum ada kegiatan yang direncanakan untuk semester <?php echo $semester; ?>.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($daftar_kegiatan as $kegiatan): ?>
                    <div class="col-lg-6 d-flex">
                        <div class="card w-100 shadow-sm project-card">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title fw-bold text-primary"><?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?></h5>
                                    <span class="badge bg-secondary flex-shrink-0"><?php echo htmlspecialchars($kegiatan['bentuk_kegiatan']); ?></span>
                                </div>
                                <!-- <-- TAMBAHAN: Menampilkan Koordinator -->
                                <p class="small text-muted mb-2">
                                    Koordinator: <strong><?php echo htmlspecialchars($kegiatan['nama_koordinator'] ?? 'Belum Ditentukan'); ?></strong>
                                </p>
                                <hr class="my-2">
                                <p class="mb-2 small text-muted fw-bold">Dimensi Profil yang Ditargetkan:</p>
                                <div class="d-flex flex-wrap flex-grow-1" style="gap: 0.5rem;">
                                    <?php 
                                    $dimensi_list = $dimensi_per_kegiatan[$kegiatan['id_kegiatan']] ?? [];
                                    if (empty($dimensi_list)) {
                                        echo '<span class="text-muted small"><em>Belum ada.</em></span>';
                                    } else {
                                        foreach ($dimensi_list as $dimensi_nama) {
                                            echo '<span class="badge rounded-pill dimension-badge">' . htmlspecialchars($dimensi_nama) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="card-footer bg-light text-end">
                                <!-- <-- TAMBAHAN: Tombol Kelola Tim -->
                                <a href="kokurikuler_kelola_tim.php?id=<?php echo $kegiatan['id_kegiatan']; ?>" class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="Kelola Tim Penilai"><i class="bi bi-people-fill"></i></a>
                                <a href="kokurikuler_edit.php?id=<?php echo $kegiatan['id_kegiatan']; ?>" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Edit Kegiatan"><i class="bi bi-pencil-fill"></i></a>
                                <a href="#" onclick="hapusKegiatan(<?php echo $kegiatan['id_kegiatan']; ?>)" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Hapus Kegiatan"><i class="bi bi-trash-fill"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
});

function hapusKegiatan(id) {
    Swal.fire({
        title: 'Anda Yakin?',
        text: "Kegiatan ini akan dihapus permanen, termasuk semua target dimensi dan data asesmen yang terhubung!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'kokurikuler_aksi.php?aksi=hapus&id=' + id;
        }
    })
}
</script>

<?php
if (isset($_SESSION['pesan'])) {
    // <-- DIMODIFIKASI: Menyesuaikan dengan format JSON
    $pesan_data = json_decode($_SESSION['pesan'], true);
    if (is_array($pesan_data)) {
        echo "<script>Swal.fire(" . json_encode($pesan_data) . ");</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>