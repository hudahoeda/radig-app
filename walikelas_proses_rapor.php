<?php
// ==========================================================
// 1. INISIALISASI & KONEKSI
// ==========================================================
session_start();
include 'koneksi.php';

// Validasi Login
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru', 'admin'])) {
    // Jika akses via AJAX, kirim JSON error
    if(isset($_POST['ajax_action'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Akses Ditolak']);
        exit;
    }
    // Jika akses biasa, redirect
    header("Location: login.php");
    exit;
}

$id_guru_akses = $_SESSION['id_guru'];

// ==========================================================
// 2. AMBIL DATA GLOBAL
// ==========================================================

// Ambil Tahun Ajaran Aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran = $ta_aktif['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_label = $ta_aktif['tahun_ajaran'] ?? '-';

// Ambil Semester Aktif
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil KKM
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm = mysqli_fetch_assoc($q_kkm)['nilai_pengaturan'] ?? 75;

// Cek Wali Kelas & Ambil FASE (Updated)
// Menambahkan kolom 'fase' dalam pengambilan data
$stmt_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas, fase FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($stmt_kelas, "ii", $id_guru_akses, $id_tahun_ajaran);
mysqli_stmt_execute($stmt_kelas);
$kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kelas));

$id_kelas = $kelas['id_kelas'] ?? 0;
$nama_kelas = $kelas['nama_kelas'] ?? 'Belum Ditentukan';
$fase_kelas = $kelas['fase'] ?? '-'; // Data Fase Dinamis

// Fungsi Helper Agama
function get_relevant_agama($nama_mapel) {
    if (stripos($nama_mapel, 'islam') !== false) return 'Islam';
    if (stripos($nama_mapel, 'kristen') !== false) return 'Kristen';
    if (stripos($nama_mapel, 'katolik') !== false) return 'Katolik';
    if (stripos($nama_mapel, 'hindu') !== false) return 'Hindu';
    if (stripos($nama_mapel, 'budha') !== false) return 'Budha';
    if (stripos($nama_mapel, 'konghucu') !== false) return 'Konghucu';
    return null;
}

// ==========================================================
// 3. HANDLER AJAX
// ==========================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'get_nilai_mapel') {
    ob_clean(); 
    header('Content-Type: application/json');

    $id_kelas_final = (int)$id_kelas;

    // Override ID Kelas jika Admin (untuk keperluan debugging/view admin)
    if ($_SESSION['role'] === 'admin' && isset($_POST['admin_view_id_kelas'])) {
        $id_kelas_final = (int)$_POST['admin_view_id_kelas'];
    }

    if ($id_kelas_final == 0) {
        echo json_encode(['error' => 'ID Kelas tidak ditemukan.']);
        exit;
    }
    
    $req_id_mapel = $_POST['id_mapel'];
    
    // Cek Agama Relevan
    $q_mapel_name = mysqli_query($koneksi, "SELECT nama_mapel FROM mata_pelajaran WHERE id_mapel = $req_id_mapel LIMIT 1");
    $nama_mapel_ajax = mysqli_fetch_assoc($q_mapel_name)['nama_mapel'] ?? '';
    $relevant_agama_ajax = get_relevant_agama($nama_mapel_ajax);
    
    // Filter Siswa
    $siswa_where_clause = "id_kelas = $id_kelas_final AND status_siswa = 'Aktif'";
    if ($relevant_agama_ajax) {
        $siswa_where_clause .= " AND agama = '$relevant_agama_ajax'";
    }

    $q_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap, nisn FROM siswa WHERE $siswa_where_clause ORDER BY nama_lengkap ASC");
    
    $result_data = [];
    
    while ($siswa = mysqli_fetch_assoc($q_siswa)) {
        $id_siswa = $siswa['id_siswa'];
        
        // Kalkulasi Nilai
        $q_nilai = mysqli_query($koneksi, "
            SELECT pdn.nilai, p.bobot_penilaian
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
            WHERE pdn.id_siswa = $id_siswa
              AND p.id_mapel = $req_id_mapel
              AND p.id_kelas = $id_kelas_final 
              AND p.semester = $semester_aktif
              AND p.jenis_penilaian = 'Sumatif'
        ");
        
        $total_skor = 0;
        $total_bobot = 0;
        $jumlah_data = 0;
        
        while ($n = mysqli_fetch_assoc($q_nilai)) {
            $total_skor += ($n['nilai'] * $n['bobot_penilaian']);
            $total_bobot += $n['bobot_penilaian'];
            $jumlah_data++;
        }
        
        $nilai_akhir = 0;
        $status_nilai = 'Kosong'; 
        
        if ($total_bobot > 0) {
            $nilai_akhir = round($total_skor / $total_bobot);
            if ($nilai_akhir < $kkm) {
                $status_nilai = 'Kurang';
            } else {
                $status_nilai = 'Tuntas';
            }
        } else {
            $nilai_akhir = '-';
        }
        
        $result_data[] = [
            'nama' => $siswa['nama_lengkap'],
            'nisn' => $siswa['nisn'],
            'nilai_akhir' => $nilai_akhir,
            'jml_asesmen_diikuti' => $jumlah_data,
            'status' => $status_nilai
        ];
    }
    
    echo json_encode($result_data);
    exit;
}

// ==========================================================
// 4. LOGIKA DATA TAMPILAN (PHP)
// ==========================================================
include 'header.php';

// Validasi Akses Halaman
if (!$kelas) {
    echo "
    <div class='container mt-5'>
        <div class='card border-danger shadow-sm'>
            <div class='card-body text-center p-5'>
                <i class='bi bi-shield-lock-fill text-danger display-1 mb-3'></i>
                <h2 class='fw-bold'>Akses Terbatas</h2>
                <p class='text-muted'>Anda tidak terdaftar sebagai Wali Kelas di Tahun Ajaran <strong>$tahun_ajaran_label</strong>.</p>
                <a href='dashboard.php' class='btn btn-secondary mt-3'>Kembali ke Dashboard</a>
            </div>
        </div>
    </div>";
    include 'footer.php';
    exit;
}

// Hitung Base Siswa
$q_siswa_count = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif'");
$total_siswa_kelas = mysqli_fetch_assoc($q_siswa_count)['total'] ?? 0;

// Persiapan Data Monitoring
$monitoring_data = [];
$query_mapel_ajar = "
    SELECT gm.id_mapel, gm.id_guru, m.nama_mapel, m.kode_mapel, g.nama_guru, g.nip
    FROM guru_mengajar gm
    JOIN mata_pelajaran m ON gm.id_mapel = m.id_mapel
    JOIN guru g ON gm.id_guru = g.id_guru
    WHERE gm.id_kelas = $id_kelas AND gm.id_tahun_ajaran = $id_tahun_ajaran
    ORDER BY m.urutan ASC
";
$result_mapel = mysqli_query($koneksi, $query_mapel_ajar);

if (mysqli_num_rows($result_mapel) > 0) {
    while ($row = mysqli_fetch_assoc($result_mapel)) {
        $id_mapel = $row['id_mapel'];
        $nama_mapel = $row['nama_mapel'];

        // Logic Siswa Relevan (Agama)
        $relevant_agama = get_relevant_agama($nama_mapel);
        $relevant_siswa_count = $total_siswa_kelas;
        
        if ($relevant_agama) {
            $q_relevant = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif' AND agama = '$relevant_agama'");
            $relevant_siswa_count = mysqli_fetch_assoc($q_relevant)['total'] ?? 0;
        }

        // Hitung Asesmen Guru
        $q_asesmen = mysqli_query($koneksi, "SELECT COUNT(*) as jumlah FROM penilaian WHERE id_kelas = $id_kelas AND id_mapel = $id_mapel AND semester = $semester_aktif AND jenis_penilaian = 'Sumatif'");
        $jml_asesmen = mysqli_fetch_assoc($q_asesmen)['jumlah'] ?? 0;

        $target_nilai = $jml_asesmen * $relevant_siswa_count;
        
        // Hitung Nilai Masuk
        $q_nilai_masuk = mysqli_query($koneksi, "
            SELECT COUNT(pdn.id_detail_nilai) as jumlah 
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
            JOIN siswa s ON pdn.id_siswa = s.id_siswa
            WHERE p.id_kelas = {$id_kelas} AND p.id_mapel = {$id_mapel} 
              AND p.semester = {$semester_aktif} AND p.jenis_penilaian = 'Sumatif'
              AND s.status_siswa = 'Aktif' " . ($relevant_agama ? "AND s.agama = '{$relevant_agama}'" : "") . "
        ");
        $jml_nilai_masuk = mysqli_fetch_assoc($q_nilai_masuk)['jumlah'] ?? 0;

        // Logic Persentase & Badge
        $persentase = 0;
        $status_badge = '';
        $status_text = '';
        $progress_color = '';

        if ($target_nilai > 0) {
            $persentase = round(($jml_nilai_masuk / $target_nilai) * 100);
        }

        if ($jml_asesmen == 0) {
            $status_badge = 'bg-soft-danger';
            $status_text = 'Belum Ada Nilai';
            $progress_color = 'bg-danger';
            $persentase = 0;
        } elseif ($relevant_siswa_count == 0 && $relevant_agama) {
            $status_badge = 'bg-soft-secondary';
            $status_text = 'Tidak Relevan';
            $progress_color = 'bg-secondary';
            $persentase = 100; 
        } elseif ($persentase >= 100) {
            $status_badge = 'bg-soft-success';
            $status_text = 'Lengkap';
            $progress_color = 'bg-success';
            $persentase = 100;
        } elseif ($persentase >= 50) {
            $status_badge = 'bg-soft-primary';
            $status_text = 'Proses Input';
            $progress_color = 'bg-primary';
        } else {
            $status_badge = 'bg-soft-warning';
            $status_text = 'Baru Mulai';
            $progress_color = 'bg-warning';
        }
        
        $target_siswa_display = $relevant_agama ? "$relevant_siswa_count ($relevant_agama)" : "$relevant_siswa_count";

        $monitoring_data[] = [
            'id_mapel' => $id_mapel,
            'nama_mapel' => $nama_mapel,
            'kode_mapel' => $row['kode_mapel'],
            'nama_guru' => $row['nama_guru'],
            'nip_guru' => $row['nip'],
            'jml_asesmen' => $jml_asesmen,
            'jml_nilai' => $jml_nilai_masuk,
            'target_nilai' => $target_nilai,
            'relevant_siswa_count' => $relevant_siswa_count,
            'persentase' => $persentase,
            'badge' => $status_badge,
            'text' => $status_text,
            'progress_color' => $progress_color,
            'target_siswa_display' => $target_siswa_display
        ];
    }
}
?>

<!-- ========================================================== -->
<!-- 5. TAMPILAN UI (HTML & CSS) -->
<!-- ========================================================== -->

<style>
    /* * PERBAIKAN WARNA: MENGGUNAKAN HEX LANGSUNG AGAR PASTI MUNCUL 
     * Mencegah masalah text putih pada background putih
     */

    body { background-color: #f8f9fa; }

    /* Latar Belakang Gradient Teal - Pakai !important agar tidak tertimpa */
    .bg-gradient-teal {
        background-color: #00897b !important; /* Fallback Solid */
        background: linear-gradient(135deg, #00897b 0%, #00695c 100%) !important;
        color: #ffffff !important;
    }
    
    /* Text Teal Khusus */
    .text-teal { 
        color: #00897b !important; 
    }
    
    /* Background Soft (Pastel) dengan warna text yang kontras */
    .bg-soft-success { 
        background-color: #d1e7dd !important; 
        color: #0f5132 !important; 
    }
    .bg-soft-danger { 
        background-color: #f8d7da !important; 
        color: #842029 !important; 
    }
    .bg-soft-warning { 
        background-color: #fff3cd !important; 
        color: #664d03 !important; 
    }
    .bg-soft-primary { 
        background-color: #cfe2ff !important; 
        color: #084298 !important; 
    }
    .bg-soft-secondary { 
        background-color: #e2e3e5 !important; 
        color: #41464b !important; 
    }
    .bg-soft-teal { 
        background-color: #e0f2f1 !important; 
        color: #004d40 !important; 
    }

    /* Cards Modern */
    .card-modern {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        background: #fff; /* Pastikan defaultnya putih */
        overflow: hidden;
    }
    .card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    /* Table Styles */
    .table-modern thead th {
        background-color: #f1f5f9;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .table-modern tbody td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .table-modern tbody tr:last-child td { border-bottom: none; }
    
    /* Avatar Initials */
    .avatar-initial {
        width: 38px;
        height: 38px;
        background: #e0f2f1; /* Light Teal */
        color: #00695c; /* Dark Teal */
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
    }

    /* Progress Bar */
    .progress-thin {
        height: 8px;
        border-radius: 4px;
        background-color: #edf2f7;
    }
    
    /* Stats Icon */
    .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
</style>

<div class="container-fluid py-4 px-md-4">
    
    <!-- HEADER / HERO SECTION -->
    <div class="row mb-4">
        <div class="col-12">
            <!-- Menambahkan text-white dan style inline fallback -->
            <div class="card-modern bg-gradient-teal text-white p-4 p-md-5 position-relative overflow-hidden">
                <!-- Decorative Circles - PERBAIKAN: Menggunakan RGBA Inline untuk Transparansi Pasti -->
                <!-- Menghilangkan class 'bg-white opacity-10' dan mengganti dengan background-color rgba -->
                <div class="position-absolute top-0 end-0 translate-middle rounded-circle" 
                     style="width: 200px; height: 200px; background-color: rgba(255, 255, 255, 0.1);"></div>
                <div class="position-absolute bottom-0 start-0 translate-middle rounded-circle" 
                     style="width: 150px; height: 150px; background-color: rgba(255, 255, 255, 0.1);"></div>

                <div class="row align-items-center position-relative" style="z-index: 2;">
                    <div class="col-lg-8 mb-3 mb-lg-0">
                        <span class="badge bg-white text-teal mb-2 px-3 py-2 rounded-pill fw-bold">
                            <i class="bi bi-calendar-check me-1"></i> TA: <?php echo $tahun_ajaran_label; ?>
                        </span>
                        <h1 class="display-5 fw-bold mb-1 text-white"><?php echo htmlspecialchars($nama_kelas); ?></h1>
                        <p class="mb-0 fs-5 opacity-75 text-white">
                            Fase: <strong><?php echo htmlspecialchars($fase_kelas); ?></strong> &bull; Semester <?php echo $semester_aktif; ?>
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <!-- Perbaikan box info wali kelas dengan RGBA juga -->
                        <div class="d-inline-flex align-items-center rounded-3 p-3 border border-white border-opacity-25 backdrop-blur" 
                             style="background-color: rgba(255, 255, 255, 0.1);">
                            <div class="text-start me-4">
                                <small class="d-block opacity-75 text-white text-uppercase" style="font-size: 0.7rem;">Wali Kelas</small>
                                <span class="fw-bold fs-5 text-white"><?php echo $_SESSION['nama_lengkap'] ?? 'Guru'; ?></span>
                            </div>
                            <div class="vr bg-white opacity-50 mx-3" style="height: 30px;"></div>
                            <div class="text-start">
                                <small class="d-block opacity-75 text-white text-uppercase" style="font-size: 0.7rem;">Total Siswa</small>
                                <span class="fw-bold fs-5 text-white"><?php echo $total_siswa_kelas; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SUMMARY STATISTICS -->
    <div class="row g-4 mb-4">
        <!-- Card 1: Total Mapel -->
        <div class="col-md-6 col-xl-3">
            <div class="card-modern h-100 p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-primary me-3">
                        <i class="bi bi-book"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 text-uppercase" style="font-size: 0.75rem;">Mata Pelajaran</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo count($monitoring_data); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card 2: Selesai -->
        <div class="col-md-6 col-xl-3">
            <div class="card-modern h-100 p-3">
                <?php $selesai = array_filter($monitoring_data, fn($i) => $i['persentase'] >= 100); ?>
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-success me-3">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 text-uppercase" style="font-size: 0.75rem;">Penilaian Lengkap</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo count($selesai); ?> <small class="fs-6 text-muted">Mapel</small></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Belum Ada -->
        <div class="col-md-6 col-xl-3">
            <div class="card-modern h-100 p-3">
                <?php $kosong = array_filter($monitoring_data, fn($i) => $i['jml_asesmen'] == 0); ?>
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-danger me-3">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 text-uppercase" style="font-size: 0.75rem;">Belum Input</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo count($kosong); ?> <small class="fs-6 text-muted">Mapel</small></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: Action Button -->
        <div class="col-md-6 col-xl-3">
            <div class="card-modern h-100 bg-soft-teal border border-success border-opacity-10 d-flex align-items-center justify-content-center p-3 position-relative">
                <a href="walikelas_cetak_rapor.php" class="stretched-link text-decoration-none text-center">
                    <div class="icon-box bg-white text-teal rounded-circle mx-auto mb-2 shadow-sm" style="width: 40px; height: 40px; font-size: 1.2rem;">
                        <i class="bi bi-printer-fill"></i>
                    </div>
                    <span class="fw-bold text-teal">Menu Cetak Rapor</span>
                    <br>
                    <small class="text-teal opacity-75" style="font-size: 0.7rem;">Klik jika nilai sudah lengkap</small>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE SECTION -->
    <div class="card-modern">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-activity text-teal me-2"></i>Monitoring Penilaian Guru
            </h5>
            <div class="d-flex gap-2">
                <span class="badge bg-light text-muted border px-3">KKM: <?php echo $kkm; ?></span>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-modern table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengampu</th>
                        <th style="min-width: 250px;">Progres Input</th>
                        <th class="text-center">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monitoring_data)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <img src="assets/img/no-data.svg" alt="Empty" style="width: 100px; opacity: 0.5;">
                                <p class="text-muted mt-3 fw-bold">Belum ada data mata pelajaran.</p>
                                <small class="text-muted">Hubungi kurikulum untuk plotting guru.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($monitoring_data as $mapel): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted"><?php echo $no++; ?></td>
                            <td>
                                <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($mapel['nama_mapel']); ?></div>
                                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 0.7rem;">
                                    <?php echo htmlspecialchars($mapel['kode_mapel'] ?? '-'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-initial me-3 flex-shrink-0">
                                        <?php echo strtoupper(substr($mapel['nama_guru'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark" style="font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($mapel['nama_guru']); ?>
                                        </div>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            Target: <strong><?php echo $mapel['target_siswa_display']; ?></strong> Siswa
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge <?php echo $mapel['badge']; ?> rounded-pill" style="font-size: 0.7rem;">
                                        <?php echo $mapel['text']; ?>
                                    </span>
                                    <span class="fw-bold text-muted" style="font-size: 0.8rem;"><?php echo $mapel['persentase']; ?>%</span>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar <?php echo $mapel['progress_color']; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $mapel['persentase']; ?>%">
                                    </div>
                                </div>
                                <small class="text-muted mt-1 d-block" style="font-size: 0.7rem;">
                                    <i class="bi bi-database me-1"></i> Data: <?php echo $mapel['jml_nilai']; ?> / <?php echo $mapel['target_nilai']; ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <?php if ($mapel['jml_asesmen'] > 0 && $mapel['relevant_siswa_count'] > 0): ?>
                                    <button class="btn btn-sm btn-outline-success rounded-pill px-3"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailNilaiModal"
                                            data-id="<?php echo $mapel['id_mapel']; ?>"
                                            data-nama="<?php echo htmlspecialchars($mapel['nama_mapel']); ?>"
                                            data-guru="<?php echo htmlspecialchars($mapel['nama_guru']); ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light text-muted border rounded-pill px-3" disabled title="Tidak ada data">
                                        <i class="bi bi-lock"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL DETAIL NILAI -->
<div class="modal fade" id="detailNilaiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-gradient-teal text-white border-0 py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3">
                        <i class="bi bi-calculator fs-4 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white">Prediksi Nilai Rapor</h5>
                        <small class="opacity-75 text-white" id="modalSubTitle">Memuat info...</small>
                    </div>
                </div>
                <!-- Tombol Close Putih (Bootstrap) -->
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0 bg-light">
                <!-- Loading State -->
                <div id="loadingState" class="text-center py-5">
                    <div class="spinner-border text-teal" style="width: 3rem; height: 3rem;" role="status"></div>
                    <p class="mt-3 text-muted fw-semibold">Sedang Mengkalkulasi Nilai...</p>
                </div>

                <!-- Table Container -->
                <div id="contentTable" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="bg-white text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3" width="5%">#</th>
                                    <th width="30%">Nama Siswa</th>
                                    <th width="15%">NISN</th>
                                    <th class="text-center" width="15%">Jml Asesmen</th>
                                    <th class="text-center" width="15%">Nilai Akhir</th>
                                    <th class="text-center" width="20%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableBody" class="bg-white">
                                <!-- Data Injected via JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 bg-soft-warning border-top border-warning border-opacity-25">
                         <div class="d-flex">
                            <i class="bi bi-info-circle-fill text-warning fs-5 me-3"></i>
                            <div>
                                <small class="text-dark d-block fw-bold">Disclaimer:</small>
                                <small class="text-muted">
                                    Nilai di atas adalah kalkulasi sementara (rata-rata tertimbang Sumatif). 
                                    Nilai riil rapor dapat berubah jika guru menambah nilai baru sebelum cetak rapor.
                                </small>
                            </div>
                         </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-white">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailModal = document.getElementById('detailNilaiModal');
    const loadingState = document.getElementById('loadingState');
    const contentTable = document.getElementById('contentTable');
    const tableBody = document.getElementById('modalTableBody');
    const modalSubTitle = document.getElementById('modalSubTitle');

    detailModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const idMapel = button.getAttribute('data-id');
        const namaMapel = button.getAttribute('data-nama');
        const namaGuru = button.getAttribute('data-guru');

        modalSubTitle.textContent = `${namaMapel} — ${namaGuru}`;
        
        loadingState.classList.remove('d-none');
        contentTable.classList.add('d-none');
        tableBody.innerHTML = '';

        const formData = new FormData();
        formData.append('ajax_action', 'get_nilai_mapel');
        formData.append('id_mapel', idMapel);

        fetch('walikelas_proses_rapor.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loadingState.classList.add('d-none');
            contentTable.classList.remove('d-none');

            if (data && data.error) {
                 tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4 fw-bold"><i class="bi bi-x-circle me-2"></i>Error: ${data.error}</td></tr>`;
                 return;
            }

            if (data.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                            Tidak ada data siswa aktif yang relevan untuk mata pelajaran ini.
                        </td>
                    </tr>`;
                return;
            }

            let html = '';
            data.forEach((siswa, index) => {
                let badgeClass = 'bg-soft-secondary';
                let nilaiDisplay = siswa.nilai_akhir;
                let statusIcon = '';
                let textClass = 'text-dark';

                if (siswa.nilai_akhir === '-' || siswa.nilai_akhir === 0) {
                    nilaiDisplay = '<span class="text-muted small fst-italic">Belum ada</span>';
                    badgeClass = 'bg-light text-secondary border';
                    siswa.status = 'Menunggu';
                } else if (siswa.status === 'Tuntas') {
                    badgeClass = 'bg-soft-success';
                    statusIcon = '<i class="bi bi-check-circle-fill me-1"></i>';
                    textClass = 'text-success';
                } else {
                    badgeClass = 'bg-soft-danger';
                    statusIcon = '<i class="bi bi-x-circle-fill me-1"></i>';
                    textClass = 'text-danger';
                }

                html += `
                    <tr>
                        <td class="ps-4 text-muted fw-bold">${index + 1}</td>
                        <td class="fw-semibold text-dark">${siswa.nama}</td>
                        <td class="text-muted font-monospace">${siswa.nisn}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-3">${siswa.jml_asesmen_diikuti}</span>
                        </td>
                        <td class="text-center fw-bold fs-5 ${textClass}">${nilaiDisplay}</td>
                        <td class="text-center">
                            <span class="badge ${badgeClass} rounded-pill px-3 py-2">${statusIcon}${siswa.status}</span>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            loadingState.classList.add('d-none');
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Gagal memuat data. Silakan coba lagi.</td></tr>';
            contentTable.classList.remove('d-none');
        });
    });
});
</script>

<?php include 'footer.php'; ?>