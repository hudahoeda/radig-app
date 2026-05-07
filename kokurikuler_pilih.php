<?php
include 'header.php';
include 'koneksi.php';

// Validasi peran
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_guru_login = (int)$_SESSION['id_guru'];
$role_login = $_SESSION['role'];

// Ambil tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran = $ta_aktif['id_tahun_ajaran'] ?? 0;

// === 1. Ambil Kegiatan yang Relevan dengan Guru ===
// Kita gunakan DISTINCT agar jika guru mengajar banyak kelas di 1 kegiatan, kegiatannya muncul sekali saja
$query_kegiatan = "
    SELECT DISTINCT k.id_kegiatan, k.tema_kegiatan, k.semester, k.id_koordinator
    FROM kokurikuler_kegiatan k
    LEFT JOIN kokurikuler_tim_penilai kt ON k.id_kegiatan = kt.id_kegiatan
    WHERE k.id_tahun_ajaran = ? 
    AND (
        ? = 'admin' 
        OR k.id_koordinator = ? 
        OR kt.id_guru = ?
    )
    ORDER BY k.semester, k.tema_kegiatan
";
$stmt = mysqli_prepare($koneksi, $query_kegiatan);
mysqli_stmt_bind_param($stmt, "issi", $id_tahun_ajaran, $role_login, $id_guru_login, $id_guru_login);
mysqli_stmt_execute($stmt);
$daftar_kegiatan = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// === 2. Siapkan Data Tambahan (Dimensi & Tugas Kelas) ===
$kegiatan_ids = array_column($daftar_kegiatan, 'id_kegiatan');
$dimensi_per_kegiatan = [];
$tugas_kelas_per_kegiatan = [];

if (!empty($kegiatan_ids)) {
    $id_list = implode(',', $kegiatan_ids);

    // A. Ambil Dimensi
    $q_dim = mysqli_query($koneksi, "SELECT id_kegiatan, nama_dimensi FROM kokurikuler_target_dimensi WHERE id_kegiatan IN ($id_list)");
    while ($row = mysqli_fetch_assoc($q_dim)) {
        $dimensi_per_kegiatan[$row['id_kegiatan']][] = $row['nama_dimensi'];
    }

    // B. Ambil Info Kelas yang Dinilai
    // Jika Admin/Koordinator -> Ambil semua kelas di kokurikuler_kelas_terlibat
    // Jika Anggota Tim -> Ambil kelas dari kokurikuler_tim_penilai sesuai ID Guru
    foreach ($daftar_kegiatan as $keg) {
        $id_k = $keg['id_kegiatan'];
        
        if ($role_login == 'admin' || $keg['id_koordinator'] == $id_guru_login) {
            // Koordinator/Admin melihat SEMUA kelas sasaran
            $q_kelas = "SELECT k.nama_kelas FROM kokurikuler_kelas_terlibat kkt JOIN kelas k ON kkt.id_kelas = k.id_kelas WHERE kkt.id_kegiatan = $id_k ORDER BY k.nama_kelas";
        } else {
            // Anggota tim hanya melihat kelas yang ditugaskan kepadanya
            $q_kelas = "SELECT k.nama_kelas FROM kokurikuler_tim_penilai ktp JOIN kelas k ON ktp.id_kelas = k.id_kelas WHERE ktp.id_kegiatan = $id_k AND ktp.id_guru = $id_guru_login ORDER BY k.nama_kelas";
        }
        
        $res_kelas = mysqli_query($koneksi, $q_kelas);
        $kelas_list = [];
        while($row_k = mysqli_fetch_assoc($res_kelas)) {
            $kelas_list[] = $row_k['nama_kelas'];
        }
        $tugas_kelas_per_kegiatan[$id_k] = $kelas_list;
    }
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .project-card { text-decoration: none; color: inherit; display: block; transition: all 0.2s ease-in-out; border: 1px solid #dee2e6; }
    .project-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); border-color: var(--primary-color); }
    .project-card .card-title { color: var(--primary-color); font-weight: 700; }
    .dimension-badge { background-color: #e0f2f1; color: #00796b; font-weight: 500; padding: 0.3em 0.6em; font-size: 0.8rem; }
    .kelas-info { background-color: #f8f9fa; padding: 8px; border-radius: 6px; font-size: 0.85rem; color: #555; border: 1px dashed #ccc; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Asesmen Kokurikuler</h1>
        <p class="lead mb-0 opacity-75">Pilih kegiatan di bawah ini untuk melakukan penilaian siswa.</p>
    </div>

    <?php if (empty($daftar_kegiatan)): ?>
        <div class="card shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-journal-x fs-1 text-muted"></i>
                <h3 class="mt-3">Tidak Ada Kegiatan</h3>
                <p class="text-muted">Anda belum ditugaskan dalam kegiatan kokurikuler manapun untuk tahun ajaran aktif.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($daftar_kegiatan as $kegiatan): 
                $id = $kegiatan['id_kegiatan'];
                $is_koordinator = ($kegiatan['id_koordinator'] == $id_guru_login);
            ?>
                <div class="col-md-6 col-lg-4">
                    <a href="kokurikuler_input.php?kegiatan=<?php echo $id; ?>" class="project-card h-100 card shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge bg-primary">Semester <?php echo $kegiatan['semester']; ?></span>
                                <?php if($is_koordinator): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Koordinator</span>
                                <?php endif; ?>
                            </div>
                            
                            <h5 class="card-title mb-3"><?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?></h5>
                            
                            <!-- Info Kelas yang Dinilai -->
                            <div class="kelas-info mb-3">
                                <i class="bi bi-people-fill me-1"></i> <strong>Tugas Penilaian:</strong><br>
                                <?php 
                                $kelas_list = $tugas_kelas_per_kegiatan[$id] ?? [];
                                if (empty($kelas_list)) {
                                    echo '<span class="text-danger fst-italic">Belum ada kelas yang ditugaskan.</span>';
                                } else {
                                    // Batasi tampilan jika terlalu banyak
                                    $tampil = array_slice($kelas_list, 0, 5);
                                    echo implode(', ', $tampil);
                                    if (count($kelas_list) > 5) echo ', dll...';
                                }
                                ?>
                            </div>

                            <div class="mt-auto">
                                <p class="small text-muted fw-bold mb-1">Dimensi:</p>
                                <div class="d-flex flex-wrap" style="gap: 4px;">
                                    <?php 
                                    $dimensi = $dimensi_per_kegiatan[$id] ?? [];
                                    if (empty($dimensi)) {
                                        echo '<span class="text-muted small">-</span>';
                                    } else {
                                        foreach ($dimensi as $d) {
                                            echo '<span class="badge rounded-pill dimension-badge">' . htmlspecialchars($d) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-bold small">Buka Penilaian <i class="bi bi-arrow-right"></i></span>
                            
                            <?php if ($is_koordinator || $role_login == 'admin'): ?>
                                <button onclick="window.location.href='kokurikuler_kelola_tim.php?id=<?php echo $id; ?>'; return false;" class="btn btn-sm btn-outline-secondary z-index-2">
                                    <i class="bi bi-gear-fill"></i> Tim
                                </button>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>