<?php
include 'header.php';
include 'koneksi.php';

// Validasi peran
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

// <-- TAMBAHAN: Ambil data user login -->
$id_guru_login = (int)$_SESSION['id_guru'];
$role_login = $_SESSION['role'];


// <-- DIMODIFIKASI: Query diubah total untuk memfilter berdasarkan hak akses -->
$query_kegiatan = "
    SELECT DISTINCT k.id_kegiatan, k.tema_kegiatan, k.semester, k.id_koordinator
    FROM kokurikuler_kegiatan k
    JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran
    LEFT JOIN kokurikuler_tim_penilai kt ON k.id_kegiatan = kt.id_kegiatan
    WHERE 
        ta.status = 'Aktif' 
        AND (
            '$role_login' = 'admin' 
            OR k.id_koordinator = $id_guru_login 
            OR kt.id_guru = $id_guru_login
        )
    ORDER BY k.semester, k.tema_kegiatan
";
$result_kegiatan = mysqli_query($koneksi, $query_kegiatan);
$daftar_kegiatan = mysqli_fetch_all($result_kegiatan, MYSQLI_ASSOC);

// Mengambil semua dimensi yang relevan untuk di-mapping ke setiap kegiatan
$query_dimensi = "SELECT ktd.id_kegiatan, ktd.nama_dimensi 
                  FROM kokurikuler_target_dimensi ktd
                  JOIN kokurikuler_kegiatan kk ON ktd.id_kegiatan = kk.id_kegiatan
                  JOIN tahun_ajaran ta ON kk.id_tahun_ajaran = ta.id_tahun_ajaran
                  WHERE ta.status = 'Aktif'";
$result_dimensi = mysqli_query($koneksi, $query_dimensi);
$dimensi_per_kegiatan = [];
while ($dimensi = mysqli_fetch_assoc($result_dimensi)) {
    $dimensi_per_kegiatan[$dimensi['id_kegiatan']][] = $dimensi['nama_dimensi'];
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }

    .project-card {
        text-decoration: none;
        color: inherit;
        display: block;
        transition: all 0.2s ease-in-out;
    }
    .project-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    }
    .project-card .card-title {
        color: var(--primary-color);
        font-weight: 600;
    }
    .project-card .card-footer {
        background-color: transparent;
        border-top: 1px solid var(--border-color);
        /* font-weight: 600; */ /* Dihapus agar tidak terlalu tebal */
        /* color: var(--secondary-color); */ /* Dihapus agar netral */
    }
    .dimension-badge {
        background-color: #e0f2f1; /* Light Teal */
        color: #00796b; /* Dark Teal */
        font-weight: 500;
        padding: 0.4em 0.8em;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Asesmen Kokurikuler</h1>
        <p class="lead mb-0 opacity-75">Pilih salah satu kegiatan projek di bawah ini untuk memulai proses asesmen.</p>
    </div>

    <?php if (empty($daftar_kegiatan)): ?>
        <div class="card shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-x-circle fs-1 text-muted"></i>
                <h3 class="mt-3">Tidak Ada Kegiatan Kokurikuler</h3>
                <p class="text-muted">Anda tidak ditugaskan sebagai koordinator atau tim penilai pada projek manapun.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($daftar_kegiatan as $kegiatan): ?>
                <div class="col-md-6 col-lg-4">
                    <a href="kokurikuler_input.php?kegiatan=<?php echo $kegiatan['id_kegiatan']; ?>" class="project-card">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <span class="badge bg-primary mb-2">Semester <?php echo $kegiatan['semester']; ?></span>
                                <h4 class="card-title mt-1"><?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?></h4>
                                <hr>
                                <p class="small text-muted fw-bold mb-2">Dimensi yang Dinilai:</p>
                                <div class="d-flex flex-wrap" style="gap: 0.5rem;">
                                    <?php 
                                    $dimensi_list = $dimensi_per_kegiatan[$kegiatan['id_kegiatan']] ?? [];
                                    if (empty($dimensi_list)) {
                                        echo '<span class="text-muted small"><em>Belum ada dimensi yang ditargetkan.</em></span>';
                                    } else {
                                        foreach ($dimensi_list as $dimensi_nama) {
                                            echo '<span class="badge rounded-pill dimension-badge">' . htmlspecialchars($dimensi_nama) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- DIMODIFIKASI: Card footer dengan flexbox dan tombol kondisional -->
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary">Mulai Penilaian <i class="bi bi-arrow-right-short"></i></span>
                                <?php 
                                // <-- TAMBAHAN: Tampilkan tombol Kelola Tim HANYA jika user adalah koordinatornya -->
                                if ($kegiatan['id_koordinator'] == $id_guru_login || $role_login == 'admin'): 
                                ?>
                                    <a href="kokurikuler_kelola_tim.php?id=<?php echo $kegiatan['id_kegiatan']; ?>" class="btn btn-outline-primary btn-sm" onclick="event.stopPropagation();">
                                        <i class="bi bi-people-fill me-1"></i> Kelola Tim
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>