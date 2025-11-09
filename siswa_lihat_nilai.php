<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo "<script>Swal.fire('Akses Ditolak','Anda harus login sebagai Siswa.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_siswa = $_SESSION['id_siswa'];
$nama_siswa = $_SESSION['nama_siswa'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['tahun_ajaran'];
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

// Query untuk mengambil semua nilai sumatif siswa
$query_nilai = mysqli_prepare($koneksi, "
    SELECT 
        mp.nama_mapel,
        p.nama_penilaian,
        p.jenis_penilaian,
        p.subjenis_penilaian,
        pdn.nilai
    FROM penilaian_detail_nilai pdn
    JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
    JOIN mata_pelajaran mp ON p.id_mapel = mp.id_mapel
    WHERE pdn.id_siswa = ? AND p.semester = ?
    ORDER BY mp.nama_mapel ASC, p.tanggal_penilaian ASC
");
mysqli_stmt_bind_param($query_nilai, "is", $id_siswa, $semester_aktif);
mysqli_stmt_execute($query_nilai);
$result_nilai = mysqli_stmt_get_result($query_nilai);

// Mengelompokkan nilai berdasarkan mata pelajaran
$nilai_per_mapel = [];
while ($row = mysqli_fetch_assoc($result_nilai)) {
    $nilai_per_mapel[$row['nama_mapel']][] = $row;
}

?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .accordion-button:not(.collapsed) {
        background-color: #e0f2f1; color: var(--secondary-color);
        box-shadow: inset 0 -1px 0 rgba(0,0,0,.125);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Rincian Nilai Saya</h1>
                <p class="lead mb-0 opacity-75">Tahun Ajaran <?php echo $tahun_ajaran_aktif; ?> - Semester <?php echo $semester_aktif; ?></p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-light mt-3 mt-sm-0"><i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($nilai_per_mapel)): ?>
                <div class="text-center p-5">
                    <i class="bi bi-journal-x fs-1 text-muted"></i>
                    <h4 class="mt-3">Belum Ada Nilai</h4>
                    <p class="text-muted">Saat ini belum ada data nilai sumatif yang diinput oleh guru untuk semester ini.</p>
                </div>
            <?php else: ?>
                <div class="accordion" id="nilaiAccordion">
                    <?php $i = 0; foreach ($nilai_per_mapel as $nama_mapel => $penilaian_list): $i++; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?php if($i > 1) echo 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $i; ?>">
                                    <strong class="fs-6"><?php echo htmlspecialchars($nama_mapel); ?></strong>
                                </button>
                            </h2>
                            <div id="collapse-<?php echo $i; ?>" class="accordion-collapse collapse <?php if($i == 1) echo 'show'; ?>" data-bs-parent="#nilaiAccordion">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nama Penilaian</th>
                                                    <th>Jenis</th>
                                                    <th class="text-center">Nilai</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($penilaian_list as $penilaian): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($penilaian['nama_penilaian']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info text-dark">
                                                            <?php echo htmlspecialchars($penilaian['jenis_penilaian']); ?>
                                                            <?php echo $penilaian['subjenis_penilaian'] ? ' (' . htmlspecialchars($penilaian['subjenis_penilaian']) . ')' : ''; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center fw-bold fs-5"><?php echo $penilaian['nilai']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
