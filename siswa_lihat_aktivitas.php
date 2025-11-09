<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    echo "<script>Swal.fire('Akses Ditolak','Anda harus login sebagai Siswa.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_siswa = $_SESSION['id_siswa'];

// Ambil info tahun ajaran dan semester aktif
$q_ta_smt = mysqli_query($koneksi, "
    SELECT 
        (SELECT tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1) as ta_aktif,
        (SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1) as smt_aktif,
        (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1) as id_ta_aktif
");
$data_aktif = mysqli_fetch_assoc($q_ta_smt);
$tahun_ajaran_aktif = $data_aktif['ta_aktif'];
$semester_aktif = $data_aktif['smt_aktif'];
$id_tahun_ajaran_aktif = $data_aktif['id_ta_aktif'];

// --- [FUNGSI BARU] ---
// Fungsi untuk menghitung rata-rata nilai kualitatif
function calculate_average_qualitative($scores) {
    if (empty($scores)) return 'N/A';
    
    $map = ['Sangat Baik' => 4, 'Baik' => 3, 'Cukup' => 2, 'Kurang' => 1];
    $reverse_map = [4 => 'Sangat Baik', 3 => 'Baik', 2 => 'Cukup', 1 => 'Kurang'];
    
    $total_score = 0;
    $count = 0;
    foreach ($scores as $score) {
        if (isset($map[$score])) {
            $total_score += $map[$score];
            $count++;
        }
    }
    
    if ($count == 0) return 'N/A';
    
    $average = round($total_score / $count);
    
    return $reverse_map[$average] ?? 'N/A';
}
// --- [SELESAI FUNGSI BARU] ---

// Query untuk mengambil data projek kokurikuler
$query_projek = mysqli_prepare($koneksi, "
    SELECT 
        k.tema_kegiatan, 
        td.nama_dimensi, 
        a.nilai_kualitatif
    FROM kokurikuler_asesmen a
    JOIN kokurikuler_target_dimensi td ON a.id_target = td.id_target
    JOIN kokurikuler_kegiatan k ON td.id_kegiatan = k.id_kegiatan
    WHERE a.id_siswa = ? AND k.id_tahun_ajaran = ? AND k.semester = ?
");
mysqli_stmt_bind_param($query_projek, "iii", $id_siswa, $id_tahun_ajaran_aktif, $semester_aktif);
mysqli_stmt_execute($query_projek);
$result_projek = mysqli_stmt_get_result($query_projek);

// --- [LOGIKA BARU] Mengelompokkan semua nilai untuk dirata-rata ---
$projek_raw = [];
while ($row = mysqli_fetch_assoc($result_projek)) {
    $projek_raw[$row['tema_kegiatan']][$row['nama_dimensi']][] = $row['nilai_kualitatif'];
}

$data_projek = [];
foreach($projek_raw as $tema => $dimensi_list) {
    foreach($dimensi_list as $dimensi => $scores) {
        $data_projek[$tema][] = [
            'dimensi' => $dimensi,
            'nilai' => calculate_average_qualitative($scores)
        ];
    }
}
// --- [SELESAI LOGIKA BARU] ---


// Query untuk mengambil data ekstrakurikuler
$query_ekskul = mysqli_prepare($koneksi, "
    SELECT 
        e.nama_ekskul,
        k.jumlah_hadir,
        k.total_pertemuan,
        t.deskripsi_tujuan,
        p.nilai
    FROM ekskul_peserta ep
    JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
    LEFT JOIN ekskul_kehadiran k ON ep.id_peserta_ekskul = k.id_peserta_ekskul AND k.semester = ?
    LEFT JOIN ekskul_penilaian p ON ep.id_peserta_ekskul = p.id_peserta_ekskul
    LEFT JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul AND t.semester = ?
    WHERE ep.id_siswa = ? AND e.id_tahun_ajaran = ?
    ORDER BY e.nama_ekskul, t.deskripsi_tujuan
");
mysqli_stmt_bind_param($query_ekskul, "iiii", $semester_aktif, $semester_aktif, $id_siswa, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($query_ekskul);
$result_ekskul = mysqli_stmt_get_result($query_ekskul);

$data_ekskul = [];
while ($row = mysqli_fetch_assoc($result_ekskul)) {
    $data_ekskul[$row['nama_ekskul']]['kehadiran'] = ['hadir' => $row['jumlah_hadir'], 'total' => $row['total_pertemuan']];
    if ($row['deskripsi_tujuan']) {
        $data_ekskul[$row['nama_ekskul']]['penilaian'][] = ['tujuan' => $row['deskripsi_tujuan'], 'nilai' => $row['nilai']];
    }
}
?>

<style>
    .page-header { background: linear-gradient(135deg, #667eea, #764ba2); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }

    .activity-card { border: none; border-radius: 1rem; box-shadow: 0 4px 25px rgba(0,0,0,0.1); overflow: hidden; }
    .activity-card .card-header { font-weight: 600; font-size: 1.2rem; border-bottom: 2px solid rgba(0,0,0,0.05); }
    .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; }
    
    .nilai-badge { font-size: 0.9rem; padding: 0.4em 0.8em; }
    .nilai-sangat-baik { background-color: #d1fae5; color: #065f46; }
    .nilai-baik { background-color: #dbeafe; color: #1e40af; }
    .nilai-cukup { background-color: #fef3c7; color: #92400e; }
    .nilai-kurang { background-color: #fee2e2; color: #991b1b; }

    .ekskul-header {
        background: linear-gradient(135deg, var(--bg-start), var(--bg-end));
        color: white;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow-lg">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Aktivitasku</h1>
                <p class="lead mb-0 opacity-75">Rangkuman projek dan ekstrakurikuler semester ini.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-light mt-3 mt-sm-0"><i class="bi bi-arrow-left me-2"></i>Dashboard</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Projek Kokurikuler -->
        <div class="col-12">
            <h3 class="mb-3"><i class="bi bi-lightbulb-fill text-warning me-2"></i>Projek Kokurikuler</h3>
            <?php if (empty($data_projek)): ?>
                <div class="card activity-card"><div class="card-body text-center p-5 text-muted">Kamu belum mengikuti kegiatan projek semester ini.</div></div>
            <?php else: ?>
                <?php foreach ($data_projek as $tema => $capaian): ?>
                <div class="card activity-card">
                    <div class="card-header bg-white d-flex align-items-center">
                        <div class="icon-box me-3" style="background-color: #fdba74;"><i class="bi bi-palette-fill fs-4"></i></div>
                        <?php echo htmlspecialchars($tema); ?>
                    </div>
                    <div class="card-body">
                        <h6 class="card-subtitle mb-3 text-muted">Nilai Akhir Dimensi yang dikembangkan:</h6>
                        <div class="list-group list-group-flush">
                            <?php foreach ($capaian as $item): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo htmlspecialchars($item['dimensi']); ?></span>
                                <span class="badge rounded-pill nilai-badge <?php echo 'nilai-' . strtolower(str_replace(' ', '-', $item['nilai'])); ?>">
                                    <?php echo htmlspecialchars($item['nilai']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Kolom Ekstrakurikuler -->
        <div class="col-12">
            <h3 class="mt-4 mb-3"><i class="bi bi-joystick text-primary me-2"></i>Ekstrakurikuler</h3>
            <?php if (empty($data_ekskul)): ?>
                 <div class="card activity-card"><div class="card-body text-center p-5 text-muted">Kamu tidak terdaftar pada kegiatan ekstrakurikuler semester ini.</div></div>
            <?php else: ?>
            <div class="row g-4">
                <?php 
                $colors = [['#4c6ef5', '#228be6'], ['#f76707', '#fd7e14'], ['#0ca678', '#20c997'], ['#7048e8', '#9775fa']];
                $icons = ['Pramuka' => 'bi-compass-fill', 'Pencak' => 'bi-universal-access', 'Tari' => 'bi-music-note-beamed', 'Bola' => 'bi-dribbble', 'Volly' => 'bi-dribbble', 'Karawitan' => 'bi-music-player-fill', 'Mengaji' => 'bi-book-half', 'Qiro' => 'bi-book-half'];
                $i = 0;
                foreach ($data_ekskul as $nama_ekskul => $detail): 
                    $color = $colors[$i % count($colors)];
                    $icon_class = 'bi-star-fill'; // Default icon
                    foreach ($icons as $key => $icon) {
                        if (stripos($nama_ekskul, $key) !== false) {
                            $icon_class = $icon;
                            break;
                        }
                    }
                ?>
                <div class="col-md-6">
                    <div class="card activity-card h-100">
                        <div class="card-header ekskul-header" style="--bg-start: <?php echo $color[0]; ?>; --bg-end: <?php echo $color[1]; ?>;">
                            <i class="<?php echo $icon_class; ?> me-2"></i><?php echo htmlspecialchars($nama_ekskul); ?>
                        </div>
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Kehadiran</h6>
                            <?php 
                                $hadir = $detail['kehadiran']['hadir'] ?? 0;
                                $total = $detail['kehadiran']['total'] ?? 0;
                                $persentase = $total > 0 ? round(($hadir / $total) * 100) : 0;
                            ?>
                            <div class="progress mb-3" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $persentase; ?>%; background-color: <?php echo $color[0]; ?>;" aria-valuenow="<?php echo $persentase; ?>">
                                    <?php echo "$hadir / $total pertemuan"; ?>
                                </div>
                            </div>
                            
                            <h6 class="card-subtitle mt-4 mb-2 text-muted">Capaian Kamu</h6>
                            <?php if (empty($detail['penilaian'])): ?>
                                <p class="text-muted fst-italic">Belum ada penilaian capaian untuk ekskul ini.</p>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($detail['penilaian'] as $penilaian): ?>
                                <li class="list-group-item px-0"><?php echo htmlspecialchars($penilaian['tujuan']); ?>: 
                                    <strong style="color: <?php echo $color[0]; ?>;"><?php echo htmlspecialchars($penilaian['nilai']); ?></strong>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php $i++; endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

