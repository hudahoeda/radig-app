<?php
// Set variabel halaman untuk Active State di Sidebar
$page = 'dashboard_penilaian';
$title = 'Dashboard Progres Penilaian';

include 'header.php';
include 'koneksi.php';

// ==========================================================
// 1. VALIDASI AKSES & INISIALISASI
// ==========================================================
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil Tahun Ajaran Aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran_aktif = $ta_aktif['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_label = $ta_aktif['tahun_ajaran'] ?? '-';

// Ambil Semester & KKM
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;
// Ambil KKM
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm = mysqli_fetch_assoc($q_kkm)['nilai_pengaturan'] ?? 75;

// --- FUNGSI BANTUAN ---
function get_relevant_agama($nama_mapel) {
    if (stripos($nama_mapel, 'islam') !== false) return 'Islam';
    if (stripos($nama_mapel, 'kristen') !== false) return 'Kristen';
    if (stripos($nama_mapel, 'katolik') !== false) return 'Katolik';
    if (stripos($nama_mapel, 'hindu') !== false) return 'Hindu';
    if (stripos($nama_mapel, 'budha') !== false) return 'Budha';
    if (stripos($nama_mapel, 'konghucu') !== false) return 'Konghucu';
    return null; 
}

function get_relevant_siswa_count($koneksi, $id_kelas, $id_mapel) {
    global $id_tahun_ajaran_aktif; 
    $q_mapel = mysqli_query($koneksi, "SELECT nama_mapel FROM mata_pelajaran WHERE id_mapel = $id_mapel LIMIT 1");
    $nama_mapel = mysqli_fetch_assoc($q_mapel)['nama_mapel'] ?? '';
    $relevant_agama = get_relevant_agama($nama_mapel);

    $siswa_where = "id_kelas = $id_kelas AND status_siswa = 'Aktif'";
    if ($relevant_agama) {
        $siswa_where .= " AND agama = '" . mysqli_real_escape_string($koneksi, $relevant_agama) . "'";
    }
    $q_count = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa WHERE $siswa_where");
    return mysqli_fetch_assoc($q_count)['total'] ?? 0;
}

// ==========================================================
// 2. LOGIKA STATISTIK GLOBAL
// ==========================================================
$labels_mapel = [];
$persen_mapel = [];
$global_total_nilai_terinput = 0;
$global_target_total_nilai = 0;

$query_semua_mapel = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel FROM mata_pelajaran ORDER BY urutan ASC");

while ($mapel_global = mysqli_fetch_assoc($query_semua_mapel)) {
    $id_mapel_global = $mapel_global['id_mapel'];
    $nama_mapel_global = $mapel_global['nama_mapel'];
    $relevant_agama_global = get_relevant_agama($nama_mapel_global);

    $q_kelas_ajar = mysqli_query($koneksi, "
        SELECT DISTINCT k.id_kelas
        FROM kelas k
        JOIN guru_mengajar gm ON k.id_kelas = gm.id_kelas
        WHERE gm.id_mapel = $id_mapel_global AND k.id_tahun_ajaran = $id_tahun_ajaran_aktif
    ");
    
    $mapel_target = 0;
    $mapel_realisasi = 0;

    while($kelas_ajar = mysqli_fetch_assoc($q_kelas_ajar)) {
        $id_kelas = $kelas_ajar['id_kelas'];
        
        // Asesmen Sumatif
        $q_jml_asesmen = mysqli_query($koneksi, "
            SELECT COUNT(id_penilaian) as jml_asesmen FROM penilaian 
            WHERE id_mapel = $id_mapel_global AND id_kelas = $id_kelas
            AND semester = $semester_aktif AND jenis_penilaian = 'Sumatif'
        ");
        $jml_asesmen = mysqli_fetch_assoc($q_jml_asesmen)['jml_asesmen'];

        $relevant_siswa_count = get_relevant_siswa_count($koneksi, $id_kelas, $id_mapel_global);
        $target_kelas = $jml_asesmen * $relevant_siswa_count;
        $mapel_target += $target_kelas;

        // Realisasi
        $q_realisasi_kelas_str = "
            SELECT COUNT(pdn.id_detail_nilai) as total 
            FROM penilaian_detail_nilai pdn 
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
            JOIN siswa s ON pdn.id_siswa = s.id_siswa
            WHERE p.id_mapel = $id_mapel_global AND p.id_kelas = $id_kelas 
            AND p.semester = $semester_aktif AND p.jenis_penilaian = 'Sumatif'
            AND s.status_siswa = 'Aktif' " . ($relevant_agama_global ? "AND s.agama = '$relevant_agama_global'" : "") . "
        ";
        $q_realisasi_kelas = mysqli_query($koneksi, $q_realisasi_kelas_str);
        $realisasi_kelas = mysqli_fetch_assoc($q_realisasi_kelas)['total'];
        $mapel_realisasi += $realisasi_kelas;
    }

    if ($mapel_target > 0) {
        $persen = round(($mapel_realisasi / $mapel_target) * 100);
        $labels_mapel[] = $mapel_global['nama_mapel'];
        $persen_mapel[] = min(100, $persen);
        $global_target_total_nilai += $mapel_target;
        $global_total_nilai_terinput += $mapel_realisasi;
    }
}

$persentase_global = ($global_target_total_nilai > 0) ? round(($global_total_nilai_terinput / $global_target_total_nilai) * 100) : 0;
$persentase_global = min(100, $persentase_global);
?>

<style>
    /* --- TEAL THEME OVERRIDES --- */
    :root {
        --teal-primary: #009688; /* Teal 500 */
        --teal-dark: #00796b;    /* Teal 700 */
        --teal-light: #b2dfdb;   /* Teal 100 */
    }

    /* Override Header Gradient */
    .page-header {
        background: linear-gradient(135deg, var(--teal-primary), var(--teal-dark));
        padding: 2.5rem 2rem; 
        border-radius: 0.75rem; 
        color: white;
    }
    .page-header h1 { font-weight: 700; }
    
    /* Override Bootstrap Colors for this Page Scope */
    .text-primary { color: var(--teal-primary) !important; }
    .bg-primary { background-color: var(--teal-primary) !important; }
    .btn-primary { 
        background-color: var(--teal-primary) !important; 
        border-color: var(--teal-primary) !important; 
    }
    .btn-primary:hover {
        background-color: var(--teal-dark) !important; 
        border-color: var(--teal-dark) !important; 
    }
    .btn-outline-primary {
        color: var(--teal-primary) !important;
        border-color: var(--teal-primary) !important;
    }
    .btn-outline-primary:hover {
        background-color: var(--teal-primary) !important;
        color: white !important;
    }
    .badge.bg-primary { background-color: var(--teal-primary) !important; }
    
    /* Subtle BG for icon containers */
    .bg-teal-subtle {
        background-color: rgba(0, 150, 136, 0.1) !important;
        color: var(--teal-primary) !important;
    }

    /* Card KPI Style */
    .card-kpi {
        border-left: 5px solid transparent;
        transition: transform 0.2s;
    }
    .card-kpi:hover {
        transform: translateY(-3px);
    }
    .text-gray-300 { color: #dddfeb!important; }
</style>

<!-- CONTAINER UTAMA -->
<div class="container-fluid">

    <!-- PAGE HEADER -->
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Dashboard Progres Penilaian</h1>
        <p class="lead mb-0 opacity-75">
            Monitoring input nilai Sumatif seluruh kelas | TA: <b><?php echo $tahun_ajaran_label; ?></b> (Semester <?php echo $semester_aktif; ?>)
        </p>
    </div>

    <!-- STATS CARDS ROW -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Target (Teal Border) -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow h-100 py-2 border-0 card-kpi" style="border-left-color: var(--teal-primary);">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Target Total Nilai</div>
                            <div class="h3 mb-0 fw-bold text-gray-800"><?php echo number_format($global_target_total_nilai); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-flag-fill fs-1 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Realisasi (Success Border - Tetap Hijau atau Teal Gelap) -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow h-100 py-2 border-0 card-kpi" style="border-left-color: #20c997;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Nilai Masuk</div>
                            <div class="h3 mb-0 fw-bold text-gray-800"><?php echo number_format($global_total_nilai_terinput); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle-fill fs-1 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Progress Bar -->
        <div class="col-xl-6 col-md-12">
            <div class="card shadow h-100 border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark small text-uppercase mb-0">Progress Keseluruhan</h6>
                        <span class="fw-bold text-primary"><?php echo $persentase_global; ?>%</span>
                    </div>
                    <div class="progress mb-3" style="height: 25px;">
                        <!-- Menggunakan style manual untuk warna bar agar sesuai tema -->
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                             style="width: <?php echo $persentase_global; ?>%; background-color: var(--teal-primary);"
                             aria-valuenow="<?php echo $persentase_global; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $persentase_global; ?>%
                        </div>
                    </div>
                    <p class="mb-0 small text-muted">
                        <i class="bi bi-info-circle me-1"></i> Persentase ini menunjukkan kelengkapan input nilai Sumatif dari seluruh guru.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART SECTION -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-bar-chart-fill me-2"></i>Sebaran Progres per Mata Pelajaran</h6>
        </div>
        <div class="card-body">
            <div style="height: 350px; position: relative;">
                <canvas id="chartMapel"></canvas>
            </div>
        </div>
    </div>

    <!-- ACCORDION KELAS -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-list-task me-2"></i>Detail Progres per Kelas</h6>
        </div>
        <div class="card-body p-0">
            <div class="accordion accordion-flush" id="accordionKelas">
                <?php
                $q_kelas = mysqli_query($koneksi, "
                    SELECT k.id_kelas, k.nama_kelas, g.nama_guru as wali_kelas
                    FROM kelas k
                    LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru
                    WHERE k.id_tahun_ajaran = $id_tahun_ajaran_aktif 
                    ORDER BY k.nama_kelas ASC
                ");

                if (mysqli_num_rows($q_kelas) > 0):
                    $count = 0;
                    while($kelas = mysqli_fetch_assoc($q_kelas)):
                        $id_kelas = $kelas['id_kelas'];
                        $q_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif'");
                        $jml_siswa_total = mysqli_fetch_assoc($q_siswa)['jml'];
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php echo $id_kelas; ?>">
                        <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $id_kelas; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                <div>
                                    <span class="fw-bold text-dark fs-6">
                                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                    </span>
                                    <small class="text-muted ms-2 d-none d-md-inline">
                                        <i class="bi bi-person me-1"></i>Wali: <?php echo htmlspecialchars($kelas['wali_kelas'] ?? '-'); ?>
                                    </small>
                                </div>
                                <span class="badge bg-light text-dark border"><?php echo $jml_siswa_total; ?> Siswa</span>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse<?php echo $id_kelas; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionKelas">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="bg-light text-uppercase small text-muted">
                                        <tr>
                                            <th class="ps-4 py-3" width="5%">No</th>
                                            <th width="30%">Mata Pelajaran</th>
                                            <th width="25%">Guru Pengampu</th>
                                            <th width="25%">Progres</th>
                                            <th class="text-center" width="15%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $q_mapel_ajar = mysqli_query($koneksi, "
                                            SELECT gm.id_mapel, m.nama_mapel, g.nama_guru, g.id_guru
                                            FROM guru_mengajar gm
                                            JOIN mata_pelajaran m ON gm.id_mapel = m.id_mapel
                                            JOIN guru g ON gm.id_guru = g.id_guru
                                            WHERE gm.id_kelas = $id_kelas AND gm.id_tahun_ajaran = $id_tahun_ajaran_aktif
                                            ORDER BY m.urutan ASC
                                        ");

                                        if (mysqli_num_rows($q_mapel_ajar) > 0) {
                                            $no_mapel = 1;
                                            while($mapel = mysqli_fetch_assoc($q_mapel_ajar)) {
                                                $id_mapel = $mapel['id_mapel'];
                                                $relevant_siswa_count = get_relevant_siswa_count($koneksi, $id_kelas, $id_mapel);
                                                $relevant_agama = get_relevant_agama($mapel['nama_mapel']);
                                                
                                                $q_stat = mysqli_query($koneksi, "
                                                    SELECT COUNT(id_penilaian) as jml_asesmen FROM penilaian 
                                                    WHERE id_kelas = $id_kelas AND id_mapel = $id_mapel 
                                                    AND semester = $semester_aktif AND jenis_penilaian = 'Sumatif'
                                                ");
                                                $jml_asesmen = mysqli_fetch_assoc($q_stat)['jml_asesmen'];
                                                $target_nilai = $jml_asesmen * $relevant_siswa_count;
                                                
                                                $q_nilai_masuk_str = "
                                                    SELECT COUNT(pdn.id_detail_nilai) as jml_masuk
                                                    FROM penilaian_detail_nilai pdn
                                                    JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
                                                    JOIN siswa s ON pdn.id_siswa = s.id_siswa
                                                    WHERE p.id_kelas = $id_kelas AND p.id_mapel = $id_mapel 
                                                    AND p.semester = $semester_aktif AND p.jenis_penilaian = 'Sumatif'
                                                    AND s.status_siswa = 'Aktif' " . ($relevant_agama ? "AND s.agama = '$relevant_agama'" : "");
                                                $q_nilai = mysqli_query($koneksi, $q_nilai_masuk_str);
                                                $jml_masuk = mysqli_fetch_assoc($q_nilai)['jml_masuk'];

                                                $persen = ($target_nilai > 0) ? round(($jml_masuk / $target_nilai) * 100) : 0;
                                                $persen = min(100, $persen);
                                                
                                                // Tentukan Warna & Status
                                                $status_class = 'bg-primary'; $status_txt = 'Proses';
                                                // Progress bar color mapping
                                                $progress_bar_color = 'bg-primary'; 

                                                if ($jml_asesmen == 0) { 
                                                    $status_class = 'bg-secondary'; $status_txt = 'Kosong'; $persen = 0; 
                                                    $progress_bar_color = 'bg-secondary';
                                                }
                                                elseif ($relevant_siswa_count == 0) { 
                                                    $status_class = 'bg-light text-dark border'; $status_txt = 'N/A'; $persen = 100; 
                                                    $progress_bar_color = 'bg-success';
                                                }
                                                elseif ($persen >= 100) { 
                                                    $status_class = 'bg-success'; $status_txt = 'Selesai'; 
                                                    $progress_bar_color = 'bg-success';
                                                }
                                                elseif ($persen == 0) { 
                                                    $status_class = 'bg-warning text-dark'; $status_txt = 'Pending'; 
                                                    $progress_bar_color = 'bg-warning';
                                                }
                                        ?>
                                        <tr>
                                            <td class="ps-4"><?php echo $no_mapel++; ?></td>
                                            <td>
                                                <span class="fw-bold d-block text-dark"><?php echo htmlspecialchars($mapel['nama_mapel']); ?></span>
                                                <small class="text-muted" style="font-size: 0.8rem;">
                                                    Target: <?php echo $relevant_siswa_count; ?> Siswa 
                                                    <?php echo $relevant_agama ? "<span class='badge bg-light text-secondary border'>$relevant_agama</span>" : ""; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <!-- Icon guru dengan warna Teal lembut -->
                                                    <div class="bg-teal-subtle rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width:30px; height:30px; font-size:0.8rem;">
                                                        <?php echo strtoupper(substr($mapel['nama_guru'], 0, 2)); ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($mapel['nama_guru']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center mb-1 justify-content-between">
                                                    <span class="badge <?php echo $status_class; ?> me-2"><?php echo $status_txt; ?></span>
                                                    <span class="small fw-bold"><?php echo $persen; ?>%</span>
                                                </div>
                                                <div class="progress" style="height: 4px;">
                                                    <div class="progress-bar <?php echo $progress_bar_color == 'bg-primary' ? '' : $progress_bar_color; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $persen; ?>%; <?php echo $progress_bar_color == 'bg-primary' ? 'background-color: var(--teal-primary);' : ''; ?>">
                                                    </div>
                                                </div>
                                                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                                    <?php echo number_format($jml_masuk); ?> dari <?php echo number_format($target_nilai); ?> nilai
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <?php if($jml_asesmen > 0 && $relevant_siswa_count > 0): ?>
                                                    <button class="btn btn-sm btn-outline-primary btn-lihat-detail rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;"
                                                            data-bs-toggle="modal" data-bs-target="#detailNilaiModalAdmin"
                                                            data-id-mapel="<?php echo $id_mapel; ?>"
                                                            data-id-kelas="<?php echo $id_kelas; ?>"
                                                            data-nama-mapel="<?php echo htmlspecialchars($mapel['nama_mapel']); ?>"
                                                            data-nama-guru="<?php echo htmlspecialchars($mapel['nama_guru']); ?>"
                                                            title="Lihat Detail">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-light border rounded-circle" style="width: 32px; height: 32px; padding: 0;" disabled><i class="bi bi-slash-circle text-muted"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php 
                                            }
                                        } else {
                                            echo "<tr><td colspan='6' class='text-center py-4 text-muted fst-italic'>Tidak ada mapel diatur untuk kelas ini.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                    endwhile;
                else:
                ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-folder-x fs-1 d-block mb-3"></i>
                        Data kelas tidak ditemukan untuk tahun ajaran ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETAIL NILAI (Standard Bootstrap Modal) -->
<div class="modal fade" id="detailNilaiModalAdmin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalLabelAdmin"><i class="bi bi-table me-2"></i>Detail Nilai Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-white border-bottom shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="bg-teal-subtle p-2 rounded me-3">
                            <i class="bi bi-journal-bookmark-fill fs-4"></i>
                        </div>
                        <div>
                            <small class="d-block text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Mata Pelajaran & Guru</small>
                            <span id="modalSubTitleAdmin" class="fw-bold text-dark fs-5">...</span>
                        </div>
                    </div>
                </div>
                
                <div id="loadingStateAdmin" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small">Mengambil data nilai...</p>
                </div>

                <div id="contentTableAdmin" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="bg-light text-uppercase small text-muted">
                                <tr>
                                    <th class="ps-4" width="5%">No</th>
                                    <th width="30%">Nama Siswa</th>
                                    <th width="20%">NISN</th>
                                    <th class="text-center" width="15%">Jml Asesmen</th>
                                    <th class="text-center" width="15%">Nilai Akhir</th>
                                    <th class="text-center" width="15%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableBodyAdmin"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- SETUP SIDEBAR ACTIVE STATE ---
    // Mencari link sidebar yang href-nya mengandung 'dashboard_penilaian.php'
    const currentUrl = window.location.href;
    const sidebarLinks = document.querySelectorAll('.nav-link, .sidebar-link'); // Sesuaikan selector dengan tema Anda
    sidebarLinks.forEach(link => {
        if (link.href.includes('dashboard_penilaian.php')) {
            link.classList.add('active'); // Class standar bootstrap/admin template
            // Jika ada parent collapse/dropdown, buka juga
            const parentCollapse = link.closest('.collapse');
            if (parentCollapse) {
                parentCollapse.classList.add('show');
                const parentToggle = document.querySelector(`[data-bs-target="#${parentCollapse.id}"]`);
                if(parentToggle) parentToggle.classList.remove('collapsed');
            }
        }
    });

    // --- CHART (Warna Teal) ---
    const ctx = document.getElementById('chartMapel');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_mapel); ?>,
                datasets: [{
                    label: 'Input (%)',
                    data: <?php echo json_encode($persen_mapel); ?>,
                    backgroundColor: '#009688', // Teal
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { borderDash: [2] } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // --- MODAL AJAX ---
    const detailModal = document.getElementById('detailNilaiModalAdmin');
    const loadingState = document.getElementById('loadingStateAdmin');
    const contentTable = document.getElementById('contentTableAdmin');
    const tableBody = document.getElementById('modalTableBodyAdmin');
    const modalSubTitle = document.getElementById('modalSubTitleAdmin');
    
    document.querySelectorAll('.btn-lihat-detail').forEach(button => {
        button.addEventListener('click', function() {
            const idMapel = this.getAttribute('data-id-mapel');
            const idKelas = this.getAttribute('data-id-kelas');
            
            modalSubTitle.textContent = `${this.getAttribute('data-nama-mapel')} | ${this.getAttribute('data-nama-guru')}`;
            loadingState.classList.remove('d-none');
            contentTable.classList.add('d-none');
            
            const formData = new FormData();
            formData.append('ajax_action', 'get_nilai_mapel');
            formData.append('id_mapel', idMapel);
            formData.append('admin_id_kelas', idKelas);
            formData.append('admin_access', 'true');
            formData.append('admin_view_id_kelas', idKelas); // Fallback nama param

            fetch('walikelas_proses_rapor.php', {
                method: 'POST', body: formData
            })
            .then(r => r.json())
            .then(data => {
                loadingState.classList.add('d-none');
                contentTable.classList.remove('d-none');
                
                if (data.error) {
                    tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${data.error}</td></tr>`;
                    return;
                }
                if (!data || data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted">Data kosong</td></tr>`;
                    return;
                }

                let html = '';
                data.forEach((siswa, i) => {
                    let badge = 'bg-secondary';
                    let statusText = siswa.status;
                    let icon = '';

                    if (siswa.status === 'Tuntas') {
                         badge = 'bg-success';
                         icon = '<i class="bi bi-check-lg me-1"></i>';
                    } else if (siswa.nilai_akhir > 0) {
                        badge = 'bg-warning text-dark';
                        icon = '<i class="bi bi-hourglass-split me-1"></i>';
                    } else {
                        badge = 'bg-danger';
                        icon = '<i class="bi bi-x-lg me-1"></i>';
                    }

                    html += `
                        <tr>
                            <td class="ps-4">${i+1}</td>
                            <td class="fw-bold text-dark">${siswa.nama}</td>
                            <td>${siswa.nisn}</td>
                            <td class="text-center">${siswa.jml_asesmen_diikuti}</td>
                            <td class="text-center fw-bold text-primary" style="font-size:1.1rem;">${siswa.nilai_akhir == '-' ? 0 : siswa.nilai_akhir}</td>
                            <td class="text-center"><span class="badge ${badge}">${icon}${statusText}</span></td>
                        </tr>`;
                });
                tableBody.innerHTML = html;
            })
            .catch(e => {
                loadingState.classList.add('d-none');
                contentTable.classList.remove('d-none');
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Gagal memuat data. Cek koneksi server.</td></tr>`;
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>