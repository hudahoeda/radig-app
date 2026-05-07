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
$nama_siswa = $_SESSION['nama_siswa'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['tahun_ajaran'] ?? '-';
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;
$semester_text = ($semester_aktif == 1) ? 'Ganjil' : 'Genap';

// Query untuk mengambil semua nilai sumatif siswa
$query_nilai = mysqli_prepare($koneksi, "
    SELECT 
        mp.nama_mapel,
        mp.kode_mapel,
        p.nama_penilaian,
        p.jenis_penilaian,
        p.subjenis_penilaian,
        p.tanggal_penilaian,
        pdn.nilai
    FROM penilaian_detail_nilai pdn
    JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
    JOIN mata_pelajaran mp ON p.id_mapel = mp.id_mapel
    WHERE pdn.id_siswa = ? AND p.semester = ?
    ORDER BY mp.urutan ASC, p.tanggal_penilaian DESC
");
mysqli_stmt_bind_param($query_nilai, "is", $id_siswa, $semester_aktif);
mysqli_stmt_execute($query_nilai);
$result_nilai = mysqli_stmt_get_result($query_nilai);

// Mengelompokkan nilai berdasarkan mata pelajaran
$nilai_per_mapel = [];
$total_nilai_items = 0;
while ($row = mysqli_fetch_assoc($result_nilai)) {
    $nilai_per_mapel[$row['nama_mapel']][] = $row;
    $total_nilai_items++;
}

// Warna-warni Teal/Orange untuk ikon mapel
$colors = ['#0d9488', '#0f766e', '#f97316', '#ea580c', '#14b8a6', '#065f46'];
?>

<!-- Style Modern TEAL THEME (Senada Dashboard) -->
<style>
    /* COLORS MATCHING DASHBOARD */
    :root {
        --teal-primary: #0d9488;
        --teal-dark: #065f46;
        --teal-light: #ccfbf1;
        --accent-orange: #f97316;
        --bg-body: #f0fdfa; /* Minty background */
        --card-radius: 20px;
    }
    
    body {
        background-color: var(--bg-body);
        font-family: 'Inter', sans-serif;
        color: #0f766e;
    }
    
    /* --- NEW HERO SECTION: COOLER & AGGRESSIVE --- */
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
    
    /* Abstract Glowing Shapes */
    .hero-shape {
        position: absolute;
        border-radius: 50%;
        filter: blur(50px);
        opacity: 0.4;
        z-index: 1;
        animation: floatShape 10s infinite alternate;
    }
    .shape-1 {
        width: 400px; height: 400px;
        background: var(--teal-primary);
        top: -150px; right: -100px;
    }
    .shape-2 {
        width: 250px; height: 250px;
        background: var(--accent-orange);
        bottom: -50px; left: 5%;
        animation-delay: -5s;
    }
    
    @keyframes floatShape {
        0% { transform: translate(0, 0); }
        100% { transform: translate(20px, 40px); }
    }

    .hero-content {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .hero-title-box h2 {
        font-weight: 900;
        font-size: 2.8rem;
        text-shadow: 0 4px 15px rgba(0,0,0,0.4);
        margin-bottom: 0.2rem;
        letter-spacing: -1.5px;
        background: -webkit-linear-gradient(45deg, #fff, #ccfbf1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero-title-box p {
        font-size: 1.1rem; opacity: 0.9; font-weight: 500; letter-spacing: 0.5px; margin-bottom: 0;
    }

    /* Glassmorphism Stats */
    .hero-stats {
        display: flex; gap: 15px;
    }
    .stat-badge {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 12px 25px;
        border-radius: 25px;
        text-align: center;
        min-width: 140px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        transition: transform 0.3s;
    }
    .stat-badge:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.15); }
    .stat-value { font-size: 1.4rem; font-weight: 900; display: block; line-height: 1.2; color: #fff; }
    .stat-label { font-size: 0.7rem; text-transform: uppercase; opacity: 0.8; letter-spacing: 1px; color: #ccfbf1; }

    /* --- END HERO SECTION --- */

    /* Card Mata Pelajaran (Modern Teal Card) */
    .accordion-item {
        border: none;
        margin-bottom: 1.5rem;
        background: transparent;
    }
    .accordion-button {
        background: white;
        border-radius: 20px !important;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        font-weight: 700;
        color: var(--teal-dark);
        border: 1px solid transparent;
        transition: all 0.3s;
    }
    .accordion-button:not(.collapsed) {
        background: #f0fdfa;
        color: var(--teal-dark);
        box-shadow: 0 15px 25px -5px rgba(13, 148, 136, 0.15);
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
        border-color: var(--teal-light);
    }
    .accordion-button:focus { box-shadow: none; }
    .accordion-button::after {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%230f766e'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        transform: scale(1.2);
    }

    .accordion-collapse {
        background: white;
        border-bottom-left-radius: 20px;
        border-bottom-right-radius: 20px;
        box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.05);
        margin-top: -5px; 
        border: 1px solid var(--teal-light);
        border-top: none;
    }
    
    .mapel-icon {
        width: 55px; height: 55px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: 900;
        margin-right: 1.25rem;
        font-size: 1.4rem;
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }

    /* List Nilai Item */
    .nilai-list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 2rem;
        border-bottom: 1px dashed var(--teal-light);
        transition: background 0.2s;
    }
    .nilai-list-item:last-child { border-bottom: none; }
    .nilai-list-item:hover { background-color: #fcfdfe; }
    
    .nilai-title { font-weight: 700; color: var(--teal-dark); margin-bottom: 5px; font-size: 1rem; }
    .nilai-meta { font-size: 0.85rem; color: #64748b; font-weight: 500; }
    .nilai-badge {
        width: 55px; height: 55px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.3rem;
        box-shadow: inset 0 2px 4px rgba(255,255,255,0.5), 0 4px 6px rgba(0,0,0,0.05);
    }
    /* Warna Badge Nilai yang Senada */
    .nilai-badge.high { background: linear-gradient(135deg, var(--teal-primary), #14b8a6); color: white; } 
    .nilai-badge.mid { background: linear-gradient(135deg, var(--accent-orange), #fb923c); color: white; }
    .nilai-badge.low { background: linear-gradient(135deg, #ef4444, #f87171); color: white; }

    .empty-state {
        text-align: center;
        padding: 5rem 1rem;
        background: white; border-radius: 20px;
        border: 2px dashed var(--teal-light);
    }

    /* --- RESPONSIVE ADJUSTMENTS (UPDATED) --- */
    /* Tablet & Small Desktop */
    @media (max-width: 991px) {
        .hero-content { flex-direction: column; text-align: center; gap: 2rem; }
        .hero-stats { width: 100%; justify-content: center; flex-wrap: wrap; }
        .hero-shape { opacity: 0.3; }
        /* Reset stat badge width to avoid stretching too much */
        .stat-badge { flex: 0 0 auto; width: 30%; min-width: 140px; }
    }

    /* Mobile Phones (Portrait) */
    @media (max-width: 576px) {
        .page-hero {
            padding: 2rem 1rem;
            border-radius: 0 0 30px 30px;
            margin-bottom: 2rem;
        }
        .hero-title-box h2 { font-size: 2rem; letter-spacing: -0.5px; }
        .hero-title-box p { font-size: 0.95rem; }
        
        .hero-stats { gap: 10px; width: 100%; }
        .stat-badge { 
            width: 100%; /* Full width on mobile looks cleaner for glassmorphism */
            flex: 0 0 100%; 
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between; /* Horizontal layout for stats on mobile */
        }
        .stat-badge .stat-value { font-size: 1.2rem; order: 2; }
        .stat-badge .stat-label { font-size: 0.75rem; order: 1; }

        .container-fluid.px-4 { padding-left: 1rem !important; padding-right: 1rem !important; }

        /* Accordion Mobile Optimization */
        .accordion-button { padding: 1rem; }
        .mapel-icon {
            width: 40px; height: 40px; font-size: 1rem; margin-right: 0.75rem; border-radius: 12px;
        }
        .accordion-button .fw-bold { font-size: 0.95rem !important; }
        .accordion-button .small { font-size: 0.75rem; }
        /* Hide average badge on very small screens to save space */
        .accordion-button .text-end { display: none !important; }

        /* List Item Mobile Optimization */
        .nilai-list-item { padding: 1rem; }
        .nilai-badge { width: 45px; height: 45px; font-size: 1.1rem; border-radius: 12px; }
        .nilai-title { font-size: 0.9rem; margin-bottom: 2px; }
        .nilai-meta { 
            display: flex; flex-direction: column; gap: 2px; align-items: flex-start;
        }
        .nilai-meta span { margin-right: 0 !important; font-size: 0.7rem; }
    }
</style>

<div class="page-hero">
    <!-- Abstract Shapes for Cool Effect -->
    <div class="hero-shape shape-1"></div>
    <div class="hero-shape shape-2"></div>

    <div class="container-fluid hero-content">
        <div class="hero-title-box">
            <h2>REKAP NILAI AKADEMIK</h2>
            <p><i class="bi bi-mortarboard-fill me-2"></i>Hasil Belajar Anda di Semester Ini</p>
        </div>
        
        <!-- Glassmorphism Stats Cards -->
        <div class="hero-stats">
            <div class="stat-badge">
                <span class="stat-value"><?php echo $tahun_ajaran_aktif; ?></span>
                <span class="stat-label">Tahun Ajaran</span>
            </div>
            <div class="stat-badge">
                <span class="stat-value text-uppercase"><?php echo $semester_text; ?></span>
                <span class="stat-label">Semester</span>
            </div>
            <div class="stat-badge">
                <span class="stat-value"><?php echo count($nilai_per_mapel); ?></span>
                <span class="stat-label">Mapel Dinilai</span>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <?php if (empty($nilai_per_mapel)): ?>
        <div class="empty-state shadow-sm">
            <div class="mb-3 opacity-25 text-teal" style="font-size: 6rem; color: var(--teal-primary);"><i class="bi bi-folder-x"></i></div>
            <h4 class="fw-bolder" style="color: var(--teal-dark);">BELUM ADA DATA NILAI</h4>
            <p class="text-muted">Bapak/Ibu Guru belum menginput nilai sumatif untuk semester ini.</p>
        </div>
    <?php else: ?>
        
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="accordion" id="nilaiAccordion">
                    <?php 
                    $i = 0; 
                    foreach ($nilai_per_mapel as $nama_mapel => $penilaian_list): 
                        $i++;
                        // Warna acak dari palet Teal/Orange
                        $color_index = strlen($nama_mapel) % count($colors);
                        $bg_color = $colors[$color_index];
                        // Inisial Mapel
                        $inisial = strtoupper(substr($nama_mapel, 0, 1));
                        
                        // Hitung rata-rata
                        $total = 0;
                        foreach($penilaian_list as $p) $total += $p['nilai'];
                        $avg = count($penilaian_list) > 0 ? round($total / count($penilaian_list)) : 0;
                        
                        // Warna badge rata-rata
                        $avg_class = ($avg >= 85) ? 'bg-success' : (($avg >= 70) ? 'bg-warning text-dark' : 'bg-danger');
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?php if($i > 1) echo 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $i; ?>">
                                    <div class="d-flex align-items-center w-100 pe-3">
                                        <div class="mapel-icon shadow-sm" style="background: <?php echo $bg_color; ?>;">
                                            <?php echo $inisial; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold" style="font-size: 1.1rem; letter-spacing: -0.5px;"><?php echo htmlspecialchars($nama_mapel); ?></div>
                                            <div class="small opacity-75 fw-medium">
                                                <i class="bi bi-collection-fill me-1 text-teal"></i><?php echo count($penilaian_list); ?> Data Penilaian
                                            </div>
                                        </div>
                                        <div class="d-none d-sm-block text-end">
                                            <span class="badge rounded-pill <?php echo $avg_class; ?> px-3 py-2 shadow-sm fw-bold">RERATA: <?php echo $avg; ?></span>
                                        </div>
                                        <!-- Mobile Only Average Badge (Visible only on XS) -->
                                        <div class="d-block d-sm-none">
                                            <span class="badge rounded-pill <?php echo $avg_class; ?> p-2 shadow-sm"><?php echo $avg; ?></span>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse-<?php echo $i; ?>" class="accordion-collapse collapse <?php if($i == 1) echo 'show'; ?>" data-bs-parent="#nilaiAccordion">
                                <div class="accordion-body p-0">
                                    <?php foreach ($penilaian_list as $penilaian): 
                                        $nilai = $penilaian['nilai'];
                                        $badge_class = 'mid'; // Default Orange
                                        if($nilai >= 85) $badge_class = 'high'; // Teal
                                        elseif($nilai < 70) $badge_class = 'low'; // Red
                                        
                                        $subjenis = !empty($penilaian['subjenis_penilaian']) ? $penilaian['subjenis_penilaian'] : 'Sumatif Lingkup Materi';
                                    ?>
                                        <div class="nilai-list-item">
                                            <div>
                                                <div class="nilai-title"><?php echo htmlspecialchars($penilaian['nama_penilaian']); ?></div>
                                                <div class="nilai-meta">
                                                    <span class="badge bg-light text-secondary border border-secondary border-opacity-25 me-2">
                                                        <i class="bi bi-calendar-event me-1"></i><?php echo date('d M', strtotime($penilaian['tanggal_penilaian'])); ?>
                                                    </span>
                                                    <span style="color: var(--teal-primary); font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px;">
                                                        <?php echo htmlspecialchars($subjenis); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="nilai-badge <?php echo $badge_class; ?>">
                                                <?php echo $nilai; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>