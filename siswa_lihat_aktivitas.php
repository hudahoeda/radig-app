<?php
// Gunakan include_once agar aman
include_once 'koneksi.php';
include_once 'header.php'; // Asumsi header.php memuat Sidebar & Navbar

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
$semester_text = ($semester_aktif == 1) ? 'Ganjil' : 'Genap';
$id_tahun_ajaran_aktif = $data_aktif['id_ta_aktif'];

// --- [FUNGSI BANTUAN] ---
function calculate_average_qualitative($scores) {
    if (empty($scores)) return 'N/A';
    
    $map = ['Sangat Baik' => 4, 'Baik' => 3, 'Cukup' => 2, 'Kurang' => 1];
    $reverse_map = [4 => 'Sangat Berkembang', 3 => 'Berkembang Sesuai Harapan', 2 => 'Mulai Berkembang', 1 => 'Belum Berkembang'];
    
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

// --- [DATA PROJEK KOKURIKULER] ---
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

// --- [DATA EKSTRAKURIKULER] ---
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

<!-- Style Modern TEAL THEME (Senada Dashboard) -->
<style>
    /* COLORS MATCHING DASHBOARD */
    :root {
        --teal-primary: #0d9488;
        --teal-dark: #065f46;
        --teal-light: #ccfbf1;
        --accent-orange: #f97316;
        --bg-body: #f0fdfa;
        --card-radius: 20px;
    }
    
    body {
        background-color: var(--bg-body);
        font-family: 'Inter', sans-serif;
        color: #0f766e;
    }
    
    /* --- HERO SECTION --- */
    .page-hero {
        background: linear-gradient(135deg, var(--teal-dark) 0%, #042f2e 100%);
        padding: 3.5rem 2rem;
        border-radius: 0 0 50px 50px;
        margin-bottom: 3rem;
        box-shadow: 0 20px 50px -15px rgba(6, 95, 70, 0.7);
        position: relative;
        overflow: hidden;
        color: white;
        border-bottom: 6px solid var(--accent-orange);
    }
    
    .hero-shape {
        position: absolute; border-radius: 50%; filter: blur(50px); opacity: 0.4; z-index: 1;
        animation: floatShape 10s infinite alternate;
    }
    .shape-1 { width: 300px; height: 300px; background: var(--teal-primary); top: -100px; right: -50px; }
    .shape-2 { width: 200px; height: 200px; background: var(--accent-orange); bottom: -50px; left: 5%; animation-delay: -5s; }
    @keyframes floatShape { 0% { transform: translate(0, 0); } 100% { transform: translate(20px, 40px); } }

    .hero-content { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; }
    
    .hero-title-box h2 {
        font-weight: 900; font-size: 2.5rem; text-shadow: 0 4px 15px rgba(0,0,0,0.4); margin-bottom: 0.2rem;
        background: -webkit-linear-gradient(45deg, #fff, #ccfbf1); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .hero-title-box p { font-size: 1rem; opacity: 0.9; font-weight: 500; letter-spacing: 0.5px; margin-bottom: 0; }

    .hero-stats { display: flex; gap: 10px; }
    .stat-badge {
        background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 10px 20px; border-radius: 20px; text-align: center; min-width: 100px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2); transition: transform 0.3s;
    }
    .stat-badge:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.15); }
    .stat-value { font-size: 1.2rem; font-weight: 900; display: block; line-height: 1.2; color: #fff; }
    .stat-label { font-size: 0.65rem; text-transform: uppercase; opacity: 0.8; letter-spacing: 1px; color: #ccfbf1; }

    /* --- CONTENT CARDS --- */
    .section-title {
        color: var(--teal-dark); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1.5rem;
        display: flex; align-items: center; font-size: 1.25rem;
    }
    .section-title i { color: var(--accent-orange); margin-right: 10px; font-size: 1.5rem; }

    .activity-card {
        background: white; border-radius: 20px; border: 1px solid var(--teal-light);
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05); overflow: hidden; height: 100%;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .activity-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px -10px rgba(13, 148, 136, 0.2); }

    /* Project Specific */
    .project-header {
        background: var(--teal-light); padding: 1.25rem; border-bottom: 2px solid var(--teal-primary);
        color: var(--teal-dark); font-weight: 700; display: flex; align-items: center;
    }
    .project-icon {
        width: 45px; height: 45px; background: white; border-radius: 12px; 
        display: flex; align-items: center; justify-content: center; margin-right: 15px;
        color: var(--teal-primary); font-size: 1.2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    /* Ekskul Specific */
    .ekskul-header {
        background: linear-gradient(135deg, var(--bg-start), var(--bg-end)); padding: 1.25rem;
        color: white; font-weight: 700; display: flex; align-items: center;
    }
    .ekskul-header i { font-size: 1.4rem; margin-right: 12px; }
    
    .list-item-custom {
        padding: 1rem 1.25rem; border-bottom: 1px dashed #e2e8f0; display: flex; justify-content: justify-between; align-items: center;
    }
    .list-item-custom:last-child { border-bottom: none; }
    
    .badge-nilai { font-size: 0.75rem; padding: 6px 12px; border-radius: 30px; font-weight: 700; }
    /* Warna Badge Projek */
    .bg-sb { background: #dcfce7; color: #166534; } /* Sangat Berkembang */
    .bg-bsh { background: #dbeafe; color: #1e40af; } /* Berkembang Sesuai Harapan */
    .bg-mb { background: #fef9c3; color: #854d0e; } /* Mulai Berkembang */
    .bg-bb { background: #fee2e2; color: #991b1b; } /* Belum Berkembang */

    /* Empty State */
    .empty-state {
        text-align: center; padding: 4rem 1rem; background: white; border-radius: 20px;
        border: 2px dashed var(--teal-light); color: #94a3b8;
    }

    @media (max-width: 991px) {
        .hero-content { flex-direction: column; text-align: center; gap: 2rem; }
        .hero-stats { width: 100%; justify-content: center; }
        .stat-badge { flex: 1; }
    }
    @media (max-width: 576px) {
        .page-hero { padding: 2.5rem 1.5rem; }
        .hero-title-box h2 { font-size: 1.8rem; }
        .list-item-custom { flex-direction: column; align-items: flex-start; gap: 8px; }
        .list-item-custom .badge { align-self: flex-start; }
    }
</style>

<div class="page-hero">
    <div class="hero-shape shape-1"></div>
    <div class="hero-shape shape-2"></div>
    <div class="container-fluid hero-content">
        <div class="hero-title-box">
            <h2>AKTIVITASKU</h2>
            <p><i class="bi bi-star-fill me-2 text-warning"></i>Rekap Kokurikuler & Ekstrakurikuler</p>
        </div>
        <div class="hero-stats">
            <div class="stat-badge">
                <span class="stat-value"><?php echo count($data_projek); ?></span>
                <span class="stat-label">Kokurikuler</span>
            </div>
            <div class="stat-badge">
                <span class="stat-value"><?php echo count($data_ekskul); ?></span>
                <span class="stat-label">Ekstrakurikuler</span>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">

    <!-- 1. PROJEK KOKURIKULER (P5) -->
    <div class="row mb-5">
        <div class="col-12">
            <h3 class="section-title"><i class="bi bi-lightbulb-fill"></i>KOKURIKULER</h3>
            
            <?php if (empty($data_projek)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox-fill fs-1 mb-3 d-block opacity-25"></i>
                    Belum ada data Kokurikuler untuk semester ini.
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($data_projek as $tema => $capaian): ?>
                    <div class="col-lg-6">
                        <div class="activity-card">
                            <div class="project-header">
                                <div class="project-icon"><i class="bi bi-palette-fill"></i></div>
                                <div>
                                    <small class="text-uppercase opacity-75 d-block" style="font-size: 0.7rem; letter-spacing: 1px;">TEMA PROJEK</small>
                                    <?php echo htmlspecialchars($tema); ?>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="bg-light px-4 py-2 border-bottom">
                                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Capaian Dimensi</small>
                                </div>
                                <?php foreach ($capaian as $item): 
                                    $val = $item['nilai'];
                                    $bg_cls = 'bg-light text-dark';
                                    if(strpos($val, 'Sangat') !== false) $bg_cls = 'bg-sb';
                                    elseif(strpos($val, 'Sesuai') !== false) $bg_cls = 'bg-bsh';
                                    elseif(strpos($val, 'Mulai') !== false) $bg_cls = 'bg-mb';
                                    elseif(strpos($val, 'Belum') !== false) $bg_cls = 'bg-bb';
                                ?>
                                <div class="list-item-custom">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-check-circle-fill text-teal me-2 opacity-50"></i>
                                        <span class="fw-medium text-dark"><?php echo htmlspecialchars($item['dimensi']); ?></span>
                                    </div>
                                    <span class="badge badge-nilai <?php echo $bg_cls; ?>">
                                        <?php echo htmlspecialchars($item['nilai']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. EKSTRAKURIKULER -->
    <div class="row">
        <div class="col-12">
            <h3 class="section-title"><i class="bi bi-trophy-fill"></i>EKSTRAKURIKULER</h3>
            
            <?php if (empty($data_ekskul)): ?>
                <div class="empty-state">
                    <i class="bi bi-joystick fs-1 mb-3 d-block opacity-25"></i>
                    Kamu tidak terdaftar pada kegiatan ekstrakurikuler semester ini.
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php 
                    $colors = [['#0d9488', '#115e59'], ['#f97316', '#c2410c'], ['#0ea5e9', '#0369a1'], ['#8b5cf6', '#6d28d9']];
                    $icons = ['Pramuka' => 'bi-compass', 'Pencak' => 'bi-shield-shaded', 'Tari' => 'bi-music-note-beamed', 'Bola' => 'bi-dribbble', 'Volly' => 'bi-circle', 'Komputer' => 'bi-laptop', 'Musik' => 'bi-music-player'];
                    $i = 0;
                    foreach ($data_ekskul as $nama_ekskul => $detail): 
                        $color = $colors[$i % count($colors)];
                        $icon_class = 'bi-star-fill';
                        foreach ($icons as $key => $icon) {
                            if (stripos($nama_ekskul, $key) !== false) { $icon_class = $icon; break; }
                        }
                    ?>
                    <div class="col-lg-6">
                        <div class="activity-card">
                            <div class="ekskul-header" style="--bg-start: <?php echo $color[0]; ?>; --bg-end: <?php echo $color[1]; ?>;">
                                <i class="<?php echo $icon_class; ?>"></i>
                                <?php echo htmlspecialchars($nama_ekskul); ?>
                            </div>
                            <div class="card-body p-4">
                                <!-- Kehadiran -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-end mb-1">
                                        <small class="text-uppercase fw-bold text-muted" style="font-size: 0.75rem;">Kehadiran</small>
                                        <?php 
                                            $hadir = $detail['kehadiran']['hadir'] ?? 0;
                                            $total = $detail['kehadiran']['total'] ?? 0;
                                            $persentase = $total > 0 ? round(($hadir / $total) * 100) : 0;
                                        ?>
                                        <span class="fw-bold" style="color: <?php echo $color[0]; ?>"><?php echo $persentase; ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 10px; border-radius: 10px; background: #f1f5f9;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $persentase; ?>%; background-color: <?php echo $color[0]; ?>; border-radius: 10px;"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">
                                        Hadir <b><?php echo $hadir; ?></b> dari <?php echo $total; ?> pertemuan
                                    </small>
                                </div>
                                
                                <!-- Penilaian -->
                                <div>
                                    <small class="text-uppercase fw-bold text-muted mb-2 d-block" style="font-size: 0.75rem;">Catatan Capaian</small>
                                    <?php if (empty($detail['penilaian'])): ?>
                                        <p class="text-muted fst-italic small">Belum ada penilaian.</p>
                                    <?php else: ?>
                                        <div class="d-flex flex-column gap-2">
                                            <?php foreach ($detail['penilaian'] as $penilaian): ?>
                                            <div class="d-flex align-items-start bg-light p-2 rounded-3 border">
                                                <i class="bi bi-bookmark-fill me-2 mt-1" style="color: <?php echo $color[0]; ?>; font-size: 0.8rem;"></i>
                                                <div>
                                                    <div class="fw-bold text-dark" style="font-size: 0.85rem; line-height: 1.3;"><?php echo htmlspecialchars($penilaian['nilai']); ?></div>
                                                    <div class="text-muted small" style="font-size: 0.8rem;"><?php echo htmlspecialchars($penilaian['tujuan']); ?></div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
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