<?php
include 'header.php';
include 'koneksi.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Mengambil data tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_aktif = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT tahun_ajaran FROM tahun_ajaran WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif"))['tahun_ajaran'] ?? 'N/A';

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Logika PHP untuk Rangkuman Global (sudah efisien, tidak perlu diubah)
$labels_mapel = [];
$persen_mapel = [];
$global_total_nilai_terinput = 0;
$global_target_total_nilai = 0;

$query_semua_mapel = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel FROM mata_pelajaran ORDER BY urutan ASC, nama_mapel ASC");

while ($mapel_global = mysqli_fetch_assoc($query_semua_mapel)) {
    $id_mapel_global = $mapel_global['id_mapel'];
    $mapel_target_nilai = 0;
    
    // Query untuk mengambil semua penilaian sumatif per mapel, dikelompokkan per kelas
    $q_penilaian_mapel = mysqli_query($koneksi, "
        SELECT 
            p.id_kelas, 
            COUNT(p.id_penilaian) as jml_penilaian,
            (SELECT COUNT(s.id_siswa) FROM siswa s WHERE s.id_kelas = p.id_kelas AND s.status_siswa = 'Aktif') as jml_siswa
        FROM penilaian p
        JOIN kelas k ON p.id_kelas = k.id_kelas
        WHERE p.id_mapel = $id_mapel_global 
          AND p.semester = $semester_aktif 
          AND p.jenis_penilaian = 'Sumatif'
          AND k.id_tahun_ajaran = $id_tahun_ajaran_aktif
        GROUP BY p.id_kelas
    ");
    
    if(mysqli_num_rows($q_penilaian_mapel) > 0) {
        while($penilaian_mapel = mysqli_fetch_assoc($q_penilaian_mapel)) {
            $jml_penilaian = $penilaian_mapel['jml_penilaian'];
            $jml_siswa = $penilaian_mapel['jml_siswa'];
            $mapel_target_nilai += $jml_siswa * $jml_penilaian;
        }

        // Hitung total nilai yang sudah terinput untuk mapel ini di semua kelas
        $q_nilai_terinput_mapel = mysqli_query($koneksi, "
            SELECT COUNT(pdn.id_detail_nilai) as total 
            FROM penilaian_detail_nilai pdn 
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
            JOIN kelas k ON p.id_kelas = k.id_kelas
            WHERE p.id_mapel = $id_mapel_global 
              AND p.semester = $semester_aktif 
              AND p.jenis_penilaian = 'Sumatif'
              AND k.id_tahun_ajaran = $id_tahun_ajaran_aktif
        ");
        $mapel_nilai_terinput = mysqli_fetch_assoc($q_nilai_terinput_mapel)['total'];
        $persentase_mapel = ($mapel_target_nilai > 0) ? round(($mapel_nilai_terinput / $mapel_target_nilai) * 100) : 0;
        
        $labels_mapel[] = $mapel_global['nama_mapel'];
        $persen_mapel[] = $persentase_mapel;
        
        $global_total_nilai_terinput += $mapel_nilai_terinput;
        $global_target_total_nilai += $mapel_target_nilai;
    }
}
$persentase_global = ($global_target_total_nilai > 0) ? round(($global_total_nilai_terinput / $global_target_total_nilai) * 100) : 0;
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    
    /* [ROMBAK] Style Accordion Dibuat Lebih Modern */
    .accordion-item {
        border: none;
        border-radius: 0.5rem !important;
        margin-bottom: 1rem;
        box-shadow: var(--card-shadow);
        overflow: hidden; /* Penting untuk rounded corner */
    }
    .accordion-header {
        border-bottom: 1px solid var(--border-color);
    }
    .accordion-button {
        font-weight: 600;
        font-size: 1.1rem;
        color: var(--text-dark);
        background-color: #fff;
    }
    .accordion-button:not(.collapsed) {
        background-color: #e0f2f1; /* Tema Teal Muda */
        color: var(--secondary-color);
        box-shadow: none;
    }
    .accordion-button:focus {
        box-shadow: 0 0 0 0.25rem rgba(38, 166, 154, 0.25); /* Fokus Teal */
    }
    .accordion-button::after {
        /* Ganti ikon accordion */
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2300796b'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    }
    .accordion-body {
        padding: 0; /* Hapus padding default */
    }

    /* [BARU] Style untuk KPI Cards */
    .kpi-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .kpi-card .card-title {
        color: var(--text-muted);
        font-weight: 500;
    }
    .kpi-chart-container {
        position: relative;
        width: 100%;
    }
    
    /* [BARU] Style untuk List Progres di dalam Accordion */
    .progress-list-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem 1.25rem;
    }
    @media (min-width: 768px) {
        .progress-list-item {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }
    .progress-list-info {
        flex: 1;
    }
    .progress-list-info .mapel-name {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 1.05rem;
    }
    .progress-list-info .guru-name {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    
    .progress-list-chart {
        width: 100%;
        max-width: 400px;
    }
    .progress-list-chart .progress-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }
    .progress-list-chart .progress-label .fw-bold {
        color: var(--text-dark);
    }
    .progress-list-chart .progress {
        height: 10px;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Progres Penilaian</h1>
        <p class="lead mb-0 opacity-75">
            Monitoring input nilai sumatif T.A. <?php echo $tahun_ajaran_aktif; ?> - Semester <?php echo $semester_aktif; ?>.
        </p>
    </div>
    
    <!-- [ROMBAK] Chart dipindah ke KPI Cards -->
    <h4 class="mb-3">Rangkuman Global</h4>
    <div class="row g-4 mb-4">
        <!-- Card 1: Progres Global (Doughnut) -->
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm h-100 kpi-card">
                <div class="card-body text-center">
                    <h5 class="card-title mb-3">Progres Keseluruhan</h5>
                    <div class="kpi-chart-container" style="max-width: 220px; height: 220px; margin: auto;">
                        <canvas id="globalProgresChart"></canvas>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);" class="text-center">
                            <div style="font-size: 2.5rem; font-weight: 700; color: var(--primary-color);"><?php echo $persentase_global; ?>%</div>
                            <div class="small text-muted">Tuntas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card 2: Angka Global -->
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm h-100 kpi-card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Rangkuman Nilai (Sumatif)</h5>
                    <div class="display-4 fw-bold text-dark"><?php echo number_format($global_total_nilai_terinput); ?></div>
                    <p class="text-muted fs-5 mb-0">Total nilai telah diinput</p>
                    <hr>
                    <div classs="h5 fw-bold text-muted"><?php echo number_format($global_target_total_nilai); ?></div>
                    <p class="text-muted mb-0">Target total nilai (Siswa x Penilaian)</p>
                </div>
            </div>
        </div>

        <!-- Card 3: Progres per Mapel (Bar Chart) -->
        <div class="col-lg-4 col-md-12">
            <div class="card shadow-sm h-100 kpi-card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Progres per Mata Pelajaran</h5>
                    <div class="kpi-chart-container" style="height: 220px;">
                        <canvas id="progresMapelChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <hr class="my-4">

    <!-- [ROMBAK] Tampilan Accordion Kelas -->
    <h4 class="mb-3">Rincian Progres per Kelas</h4>
    <div class="accordion" id="accordionKelas">
    <?php
    $query_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas ASC");
    if ($query_kelas && mysqli_num_rows($query_kelas) > 0):
        $first = true;
        while($kelas = mysqli_fetch_assoc($query_kelas)):
            $id_kelas = $kelas['id_kelas'];
    ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $id_kelas; ?>">
                <button class="accordion-button <?php if (!$first) echo 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $id_kelas; ?>">
                    <i class="bi bi-door-open-fill me-3" style="color: var(--primary-color);"></i>
                    <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                </button>
            </h2>
            <div id="collapse<?php echo $id_kelas; ?>" class="accordion-collapse collapse <?php if ($first) echo 'show'; ?>" data-bs-parent="#accordionKelas">
                <div class="accordion-body">
                    <!-- [ROMBAK] Mengganti Table dengan List Group -->
                    <div class="list-group list-group-flush">
                        <?php
                        $q_detail = "
                            SELECT
                                p.id_mapel,
                                mp.nama_mapel,
                                (
                                    SELECT g.nama_guru
                                    FROM guru g
                                    JOIN guru_mengajar gm ON g.id_guru = gm.id_guru
                                    WHERE gm.id_mapel = p.id_mapel 
                                      AND gm.id_kelas = p.id_kelas
                                      AND gm.id_tahun_ajaran = ?
                                    LIMIT 1
                                ) as nama_guru,
                                COUNT(DISTINCT p.id_penilaian) as total_penilaian,
                                COUNT(pdn.id_detail_nilai) as total_nilai_terinput
                            FROM penilaian p
                            JOIN mata_pelajaran mp ON p.id_mapel = mp.id_mapel
                            LEFT JOIN penilaian_detail_nilai pdn ON p.id_penilaian = pdn.id_penilaian
                            WHERE p.id_kelas = ? AND p.semester = ? AND p.jenis_penilaian = 'Sumatif'
                            GROUP BY p.id_mapel, mp.nama_mapel, p.id_kelas
                            ORDER BY mp.urutan, mp.nama_mapel
                        ";
                        $stmt_detail = mysqli_prepare($koneksi, $q_detail);
                        mysqli_stmt_bind_param($stmt_detail, "iii", $id_tahun_ajaran_aktif, $id_kelas, $semester_aktif);
                        mysqli_stmt_execute($stmt_detail);
                        $result_mapel = mysqli_stmt_get_result($stmt_detail);

                        $q_jumlah_siswa = mysqli_query($koneksi, "SELECT count(id_siswa) as total FROM siswa WHERE id_kelas=$id_kelas AND status_siswa='Aktif'");
                        $jumlah_siswa = mysqli_fetch_assoc($q_jumlah_siswa)['total'];

                        if (mysqli_num_rows($result_mapel) > 0) {
                            while($mapel = mysqli_fetch_assoc($result_mapel)):
                                $target_total_nilai = $jumlah_siswa * $mapel['total_penilaian'];
                                $persentase = ($target_total_nilai > 0) ? round(($mapel['total_nilai_terinput'] / $target_total_nilai) * 100) : 0;
                                $progress_color = 'bg-primary'; // Tema Teal
                                if ($persentase == 100) $progress_color = 'bg-success';
                                elseif ($persentase == 0) $progress_color = 'bg-warning';
                        ?>
                        <!-- Item List Baru -->
                        <div class="list-group-item progress-list-item">
                            <div class="progress-list-info">
                                <div class="mapel-name"><?php echo htmlspecialchars($mapel['nama_mapel']); ?></div>
                                <div class="guru-name">Oleh: <?php echo htmlspecialchars($mapel['nama_guru'] ?? 'Belum Ditugaskan'); ?></div>
                            </div>
                            <div class="progress-list-chart">
                                <div class="progress-label">
                                    <span><?php echo $mapel['total_nilai_terinput']; ?> / <?php echo $target_total_nilai; ?> nilai</span>
                                    <span class="fw-bold"><?php echo $persentase; ?>%</span>
                                </div>
                                <div class="progress" role="progressbar" title="<?php echo $persentase; ?>%">
                                    <div class="progress-bar <?php echo $progress_color; ?>" style="width: <?php echo $persentase; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <!-- Akhir Item List -->
                        <?php endwhile;
                        } else {
                            echo "<div class='list-group-item text-center text-muted'>Belum ada rencana penilaian sumatif yang dibuat untuk kelas ini.</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
        $first = false;
        endwhile;
    else:
    ?>
        <div class="card card-body text-center"><p class="text-muted mb-0">Tidak ada kelas yang terdaftar pada tahun ajaran aktif.</p></div>
    <?php
    endif;
    ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // [ROMBAK] Sesuaikan warna chart agar terlihat di background putih
    Chart.defaults.color = '#6c757d'; // Warna teks muted
    Chart.defaults.font.family = "'Poppins', sans-serif";

    // Chart 1: Global Progress (Doughnut)
    const globalCtx = document.getElementById('globalProgresChart');
    if (globalCtx) {
        new Chart(globalCtx, {
            type: 'doughnut',
            data: { 
                datasets: [{ 
                    data: [<?php echo $persentase_global; ?>, 100 - <?php echo $persentase_global; ?>], 
                    backgroundColor: ['var(--primary-color)', '#e9ecef'], // Warna Teal dan Abu-abu
                    borderWidth: 0,
                    cutout: '80%'
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    tooltip: { enabled: false }, 
                    legend: { display: false } 
                } 
            }
        });
    }
    
    // Chart 2: Progres per Mapel (Bar)
    const mapelCtx = document.getElementById('progresMapelChart');
    if (mapelCtx) {
        new Chart(mapelCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_mapel); ?>,
                datasets: [{ 
                    data: <?php echo json_encode($persen_mapel); ?>, 
                    backgroundColor: 'rgba(38, 166, 154, 0.7)', // Warna Teal transparan
                    borderRadius: 4, 
                    barThickness: 10, 
                }]
            },
            options: {
                indexAxis: 'y', 
                responsive: true, 
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        beginAtZero: true, 
                        max: 100, 
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }, // Grid abu-abu muda
                        ticks: { 
                            callback: function(value) { return value + "%" } 
                        } 
                    },
                    y: { 
                        grid: { display: false }, 
                        ticks: { font: { size: 10 } } 
                    }
                },
                plugins: { 
                    legend: { display: false }, 
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)', // Tooltip gelap
                        titleFont: { weight: 'bold'}, 
                        bodyFont: { size: 14},
                        displayColors: false,
                        callbacks: { 
                            label: function(context) { return `Progres: ${context.raw}%`; } 
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>