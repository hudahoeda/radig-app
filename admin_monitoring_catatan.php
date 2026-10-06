<?php
include 'header.php';
include 'koneksi.php';

// Validasi role
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

// Ambil semua tahun ajaran untuk filter
$query_ta_all = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran, status FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
$daftar_ta = mysqli_fetch_all($query_ta_all, MYSQLI_ASSOC);

// Tentukan tahun ajaran yang akan ditampilkan
$id_ta_terpilih = $_GET['id_ta'] ?? null;
if ($id_ta_terpilih === null) {
    foreach ($daftar_ta as $ta) {
        if ($ta['status'] == 'Aktif') {
            $id_ta_terpilih = $ta['id_tahun_ajaran'];
            break;
        }
    }
    if ($id_ta_terpilih === null && !empty($daftar_ta)) {
        $id_ta_terpilih = $daftar_ta[0]['id_tahun_ajaran'];
    }
}

// 1. Ambil Data Statistik Kategori untuk Chart
$q_stats = mysqli_prepare($koneksi, "
    SELECT cgw.kategori_catatan, COUNT(*) as jumlah
    FROM catatan_guru_wali cgw
    JOIN siswa s ON cgw.id_siswa = s.id_siswa
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE k.id_tahun_ajaran = ?
    GROUP BY cgw.kategori_catatan
");
mysqli_stmt_bind_param($q_stats, "i", $id_ta_terpilih);
mysqli_stmt_execute($q_stats);
$res_stats = mysqli_stmt_get_result($q_stats);

$chart_labels = [];
$chart_data = [];
$total_semua_catatan = 0;
while($row = mysqli_fetch_assoc($res_stats)) {
    $chart_labels[] = $row['kategori_catatan'];
    $chart_data[] = $row['jumlah'];
    $total_semua_catatan += $row['jumlah'];
}

// 2. Ambil Data Top 3 Guru Wali Paling Aktif
$q_top = mysqli_prepare($koneksi, "
    SELECT g.id_guru, g.nama_guru, COUNT(cgw.id_catatan) as total_catatan
    FROM guru g
    JOIN siswa s ON g.id_guru = s.id_guru_wali
    JOIN kelas k ON s.id_kelas = k.id_kelas
    JOIN catatan_guru_wali cgw ON s.id_siswa = cgw.id_siswa
    WHERE k.id_tahun_ajaran = ?
    GROUP BY g.id_guru, g.nama_guru
    ORDER BY total_catatan DESC
    LIMIT 3
");
mysqli_stmt_bind_param($q_top, "i", $id_ta_terpilih);
mysqli_stmt_execute($q_top);
$res_top = mysqli_stmt_get_result($q_top);
$top_guru_list = mysqli_fetch_all($res_top, MYSQLI_ASSOC);

// 3. Ambil data Guru Wali dan siswa binaannya untuk list utama
$query_guru_wali = mysqli_prepare($koneksi, "
    SELECT 
        g.id_guru, g.nama_guru, COUNT(DISTINCT s.id_siswa) as jumlah_binaan
    FROM guru g
    JOIN siswa s ON g.id_guru = s.id_guru_wali
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.status_siswa = 'Aktif' AND k.id_tahun_ajaran = ?
    GROUP BY g.id_guru, g.nama_guru
    ORDER BY g.nama_guru ASC
");
mysqli_stmt_bind_param($query_guru_wali, "i", $id_ta_terpilih);
mysqli_stmt_execute($query_guru_wali);
$result_guru_wali = mysqli_stmt_get_result($query_guru_wali);

$data_monitoring = [];
while ($guru = mysqli_fetch_assoc($result_guru_wali)) {
    $id_guru = $guru['id_guru'];
    
    // Ambil siswa dan catatan mereka
    $q_siswa = mysqli_prepare($koneksi, "
        SELECT s.id_siswa, s.nama_lengkap, k.nama_kelas, 
               (SELECT COUNT(*) FROM catatan_guru_wali WHERE id_siswa = s.id_siswa) as jumlah_catatan
        FROM siswa s
        JOIN kelas k ON s.id_kelas = k.id_kelas
        WHERE s.id_guru_wali = ? AND k.id_tahun_ajaran = ? AND s.status_siswa = 'Aktif'
        ORDER BY s.nama_lengkap ASC
    ");
    mysqli_stmt_bind_param($q_siswa, "ii", $id_guru, $id_ta_terpilih);
    mysqli_stmt_execute($q_siswa);
    $res_siswa = mysqli_stmt_get_result($q_siswa);
    
    $siswa_list = [];
    while($siswa = mysqli_fetch_assoc($res_siswa)){
        // Ambil catatan detail untuk siswa ini
        $q_catatan = mysqli_prepare($koneksi, "SELECT * FROM catatan_guru_wali WHERE id_siswa = ? ORDER BY tanggal_catatan DESC");
        mysqli_stmt_bind_param($q_catatan, "i", $siswa['id_siswa']);
        mysqli_stmt_execute($q_catatan);
        $res_catatan = mysqli_stmt_get_result($q_catatan);
        $siswa['catatan'] = mysqli_fetch_all($res_catatan, MYSQLI_ASSOC);
        $siswa_list[] = $siswa;
    }
    $guru['siswa_binaan'] = $siswa_list;
    $data_monitoring[] = $guru;
}
?>

<!-- Tambahkan Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Styling UI/UX Baru */
    :root {
        --primary-soft: #eef2fa;
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --text-dark: #2b2d42;
        --text-muted: #8d99ae;
        --accent-orange: #ff9f1c;
        --accent-green: #2ec4b6;
    }

    body {
        background-color: #f8f9fa;
    }

    /* Hero Header Card */
    .hero-card {
        background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
        border-radius: 1.25rem;
        border-left: 6px solid var(--primary-color);
        margin-bottom: 2rem;
    }

    .hero-title {
        color: var(--text-dark);
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    .stat-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        height: 100%;
        border: 1px solid #edf2f7;
    }

    .top-guru-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        margin-bottom: 0.8rem;
        background: var(--primary-soft);
        border-radius: 0.8rem;
        transition: transform 0.2s;
    }

    .top-guru-item:hover {
        transform: translateX(5px);
    }

    .top-guru-avatar {
        width: 45px;
        height: 45px;
        background: var(--primary-color);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        margin-right: 15px;
    }

    /* Guru Cards */
    .guru-card {
        border-radius: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid #edf2f7;
    }
    .guru-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.06) !important;
    }

    .guru-avatar {
        width: 50px;
        height: 50px;
        background: var(--primary-soft);
        color: var(--primary-color);
        font-weight: bold;
        font-size: 1.2rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Accordion Siswa (Card-like) */
    .student-accordion .accordion-item {
        border: 1px solid #edf2f7;
        border-radius: 0.75rem !important;
        margin-bottom: 0.75rem;
        overflow: hidden;
    }

    .student-accordion .accordion-button {
        background-color: #ffffff;
        padding: 1rem 1.25rem;
        font-weight: 600;
        color: var(--text-dark);
        box-shadow: none !important;
    }

    .student-accordion .accordion-button:not(.collapsed) {
        background-color: var(--primary-soft);
        color: var(--primary-color);
    }

    /* Timeline Catatan */
    .timeline-wrapper {
        position: relative;
        padding-left: 1.5rem;
        margin-top: 0.5rem;
    }
    .timeline-wrapper::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background-color: #e2e8f0;
    }

    .catatan-card {
        position: relative;
        background: #fff;
        border: 1px solid #edf2f7;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .catatan-card::before {
        content: '';
        position: absolute;
        left: -1.8rem;
        top: 1.2rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--primary-color);
        border: 3px solid #fff;
        box-shadow: 0 0 0 2px var(--primary-soft);
    }

    .catatan-kategori {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
    }
</style>

<div class="container-fluid py-4">

    <div class="card border-0 shadow-sm hero-card">
        <div class="row g-0">
            <div class="col-md-7 p-4 p-lg-5 d-flex flex-column justify-content-center">
                <h1 class="hero-title mb-2">Monitoring Catatan Guru Wali</h1>
                <p class="lead text-muted mb-4">Pantau rekam jejak, evaluasi perkembangan siswa, dan lihat statistik performa Guru Wali.</p>
                <form action="" method="GET" class="d-flex align-items-center bg-white p-2 rounded-pill shadow-sm" style="max-width: 400px; border: 1px solid #e2e8f0;">
                    <i class="bi bi-calendar3 ms-3 me-2 text-primary fs-5"></i>
                    <select name="id_ta" id="id_ta" class="form-select border-0 bg-transparent fw-semibold shadow-none" onchange="this.form.submit()" style="cursor: pointer;">
                        <?php foreach($daftar_ta as $ta): ?>
                        <option value="<?php echo $ta['id_tahun_ajaran']; ?>" <?php if($id_ta_terpilih == $ta['id_tahun_ajaran']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($ta['tahun_ajaran']) . ($ta['status'] == 'Aktif' ? ' (Tahun Aktif)' : ''); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="col-md-5 d-none d-md-flex align-items-center justify-content-center p-4">
                <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Ilustrasi Monitoring" class="img-fluid" style="max-height: 160px;">
            </div>
        </div>
    </div>

    <!-- Analitik Dashboard -->
    <div class="row g-4 mb-4">
        <!-- Grafik Kategori Catatan -->
        <div class="col-lg-7">
            <div class="stat-card">
                <h5 class="fw-bold mb-1">Persentase Bidang Catatan</h5>
                <p class="text-muted small mb-4">Distribusi total <?php echo $total_semua_catatan; ?> catatan yang diberikan di tahun ajaran ini.</p>

                <?php if ($total_semua_catatan > 0): ?>
                    <div style="position: relative; height:250px; width:100%; display: flex; justify-content: center;">
                        <canvas id="kategoriChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-pie-chart text-muted fs-1 mb-2"></i>
                        <p class="text-muted">Belum ada data catatan untuk dibuat grafik.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top 3 Guru Wali -->
        <div class="col-lg-5">
            <div class="stat-card">
                <h5 class="fw-bold mb-1">Top 3 Guru Wali</h5>
                <p class="text-muted small mb-4">Guru yang paling aktif memberikan catatan pembinaan.</p>

                <?php if (!empty($top_guru_list)): ?>
                    <?php
                    $medals = ['🥇', '🥈', '🥉'];
                    foreach ($top_guru_list as $index => $tg):
                    ?>
                        <div class="top-guru-item">
                            <div class="top-guru-avatar">
                                <?php echo substr(htmlspecialchars($tg['nama_guru']), 0, 1); ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($tg['nama_guru']); ?> <?php echo $medals[$index]; ?></h6>
                                <small class="text-muted"><?php echo $tg['total_catatan']; ?> Catatan diberikan</small>
                            </div>
                            <div class="ms-auto text-end">
                                <span class="badge bg-primary rounded-pill">Rank #<?php echo $index+1; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-trophy text-muted fs-1 mb-2"></i>
                        <p class="text-muted">Belum ada data ranking guru.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Data Monitoring Guru -->
    <h4 class="fw-bold mb-3 mt-5"><i class="bi bi-card-checklist me-2 text-primary"></i> Detail Monitoring per Guru Wali</h4>

    <?php if (empty($data_monitoring)): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="Kosong" style="width: 120px; opacity: 0.5;" class="mb-3">
                <h4 class="text-muted fw-bold">Belum Ada Data</h4>
                <p class="text-muted">Tidak ada data Guru Wali untuk tahun ajaran yang dipilih.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($data_monitoring as $guru): ?>
            <div class="col-12 col-xl-6">
                <div class="card border-0 shadow-sm guru-card h-100">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="guru-avatar shadow-sm">
                                <?php echo substr(htmlspecialchars($guru['nama_guru']), 0, 1); ?>
                            </div>
                            <div>
                                <h5 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($guru['nama_guru']); ?></h5>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 mt-1">
                                    <?php echo $guru['jumlah_binaan']; ?> Siswa Binaan
                                </span>
                            </div>
                        </div>

                        <!-- TOMBOL CETAK LAPORAN PDF -->
                        <div>
                            <a href="admin_laporan_catatan_pdf.php?id_guru=<?php echo $guru['id_guru']; ?>&id_ta=<?php echo $id_ta_terpilih; ?>" target="_blank" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak Laporan
                            </a>
                        </div>
                    </div>

                    <div class="card-body px-4 pb-4">
                        <hr class="text-muted opacity-25 mt-0 mb-3">

                        <div class="accordion student-accordion" id="acc-guru-<?php echo $guru['id_guru']; ?>">
                            <?php foreach ($guru['siswa_binaan'] as $siswa): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#siswa-<?php echo $siswa['id_siswa']; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                            <div>
                                                <span class="d-block fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></span>
                                                <small class="text-muted fw-normal">Kelas <?php echo htmlspecialchars($siswa['nama_kelas']); ?></small>
                                            </div>
                                            <span class="badge <?php echo $siswa['jumlah_catatan'] > 0 ? 'bg-primary' : 'bg-secondary opacity-50'; ?> rounded-pill">
                                                <?php echo $siswa['jumlah_catatan']; ?> Catatan
                                            </span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="siswa-<?php echo $siswa['id_siswa']; ?>" class="accordion-collapse collapse" data-bs-parent="#acc-guru-<?php echo $guru['id_guru']; ?>">
                                    <div class="accordion-body bg-light p-3 p-md-4 border-top">

                                        <?php if (empty($siswa['catatan'])): ?>
                                            <div class="text-center text-muted py-3">
                                                <span class="fs-1 d-block mb-2">📝</span>
                                                <small>Belum ada catatan untuk siswa ini.</small>
                                            </div>
                                        <?php else: ?>
                                            <div class="timeline-wrapper">
                                                <?php foreach ($siswa['catatan'] as $catatan): ?>
                                                <div class="catatan-card">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="catatan-kategori text-primary">
                                                            <i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($catatan['kategori_catatan']); ?>
                                                        </span>
                                                        <small class="text-muted fw-semibold">
                                                            <?php echo date('d M Y, H:i', strtotime($catatan['tanggal_catatan'])); ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-0 text-dark" style="font-size: 0.95rem; line-height: 1.5;">
                                                        <?php echo nl2br(htmlspecialchars($catatan['isi_catatan'])); ?>
                                                    </p>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Render Chart.js -->
<?php if ($total_semua_catatan > 0): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('kategoriChart').getContext('2d');

    // Data dari PHP
    const labels = <?php echo json_encode($chart_labels); ?>;
    const data = <?php echo json_encode($chart_data); ?>;

    // Warna custom
    const bgColors = [
        'rgba(67, 97, 238, 0.8)',   // Primary
        'rgba(46, 196, 182, 0.8)',  // Teal/Green
        'rgba(255, 159, 28, 0.8)',  // Orange
        'rgba(231, 111, 81, 0.8)',  // Red
        'rgba(155, 93, 229, 0.8)'   // Purple
    ];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: bgColors,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: { family: "'Poppins', sans-serif", size: 11 },
                        usePointStyle: true,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) label += ': ';
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let value = context.raw;
                            let percentage = Math.round((value / total) * 100) + '%';
                            label += value + ' catatan (' + percentage + ')';
                            return label;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>