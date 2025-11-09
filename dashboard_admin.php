<?php
// Gunakan include_once agar tidak terjadi error jika file sudah dipanggil di file induk
include_once 'koneksi.php';

// --- LOGIKA PENGAMBILAN DATA BARU UNTUK DASHBOARD ---

// Mengambil ID Tahun Ajaran yang sedang aktif
$query_tahun_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$data_tahun_aktif = mysqli_fetch_assoc($query_tahun_aktif);
$id_tahun_ajaran_aktif = $data_tahun_aktif['id_tahun_ajaran'] ?? 0;
$nama_tahun_ajaran_aktif = $data_tahun_aktif['tahun_ajaran'] ?? 'Tidak Aktif';

// Ambil semester aktif
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;
$semester_text = ($semester_aktif == 1) ? 'Ganjil' : 'Genap';

// =================================================================================
// DATA UNTUK KPI CARDS (SESUAI SCREENSHOT)
// =================================================================================
// 1. Jumlah Siswa Aktif ("Peserta Didik")
$query_total_siswa_aktif = mysqli_query($koneksi, "SELECT COUNT(id_siswa) as total FROM siswa WHERE status_siswa = 'Aktif'");
$total_siswa_aktif = mysqli_fetch_assoc($query_total_siswa_aktif)['total'] ?? 0;

// 2. Jumlah Guru Aktif ("GTK")
$query_total_guru_aktif = mysqli_query($koneksi, "SELECT COUNT(id_guru) as total FROM guru WHERE role = 'guru'");
$total_guru_aktif = mysqli_fetch_assoc($query_total_guru_aktif)['total'] ?? 0;

// 3. Jumlah Kelas ("Kelas/Rombel") - [QUERY BARU]
$query_total_kelas = mysqli_query($koneksi, "SELECT COUNT(id_kelas) as total FROM kelas WHERE id_tahun_ajaran = '$id_tahun_ajaran_aktif'");
$total_kelas_aktif = mysqli_fetch_assoc($query_total_kelas)['total'] ?? 0;

// 4. Jumlah Rapor Final ("Rapor Siap Cetak")
$query_rapor_final = mysqli_query($koneksi, "
    SELECT COUNT(id_rapor) as total_final
    FROM rapor
    WHERE status = 'Final'
    AND id_tahun_ajaran = '$id_tahun_ajaran_aktif'
    AND semester = '$semester_aktif'
");
$jumlah_rapor_final = mysqli_fetch_assoc($query_rapor_final)['total_final'] ?? 0;
$progres_siap_cetak = ($total_siswa_aktif > 0) ? round(($jumlah_rapor_final / $total_siswa_aktif) * 100) : 0;


// =================================================================================
// DATA UNTUK KONTEN (Menggunakan data Anda yang sudah ada)
// =================================================================================

// 1. GURU & TUJUAN PEMBELAJARAN (TP)
$query_mapel_dengan_tp = mysqli_query($koneksi, "
    SELECT COUNT(DISTINCT id_mapel) as total_mapel_ada_tp
    FROM tujuan_pembelajaran
    WHERE id_tahun_ajaran = '$id_tahun_ajaran_aktif' AND semester = '$semester_aktif'
");
$mapel_dengan_tp = mysqli_fetch_assoc($query_mapel_dengan_tp)['total_mapel_ada_tp'] ?? 0;
$query_total_mapel = mysqli_query($koneksi, "SELECT COUNT(id_mapel) as total_mapel FROM mata_pelajaran");
$total_mapel = mysqli_fetch_assoc($query_total_mapel)['total_mapel'] ?? 0;
$persen_tp_mapel = ($total_mapel > 0) ? round(($mapel_dengan_tp / $total_mapel) * 100) : 0;

// 2. NILAI YANG MASUK (INPUT TERBARU)
$query_nilai_terbaru = mysqli_query($koneksi, "
    SELECT
        p.nama_penilaian, p.jenis_penilaian, p.subjenis_penilaian, p.tanggal_penilaian,
        mp.nama_mapel, g.nama_guru, k.nama_kelas
    FROM penilaian p
    JOIN mata_pelajaran mp ON p.id_mapel = mp.id_mapel
    JOIN guru g ON p.id_guru = g.id_guru
    JOIN kelas k ON p.id_kelas = k.id_kelas
    WHERE p.semester = '$semester_aktif' AND k.id_tahun_ajaran = '$id_tahun_ajaran_aktif'
    ORDER BY p.tanggal_penilaian DESC, p.id_penilaian DESC
    LIMIT 5
");
$nilai_terbaru = [];
while ($row = mysqli_fetch_assoc($query_nilai_terbaru)) {
    $nilai_terbaru[] = $row;
}

// 3. AKTIVITAS GURU
$hari_ini = date('Y-m-d');
$query_guru_terbaru = mysqli_query($koneksi, "SELECT nama_guru, terakhir_login FROM guru WHERE role='guru' AND terakhir_login IS NOT NULL ORDER BY terakhir_login DESC LIMIT 1");
$guru_terakhir_login = mysqli_fetch_assoc($query_guru_terbaru);
$query_guru_login_hari_ini = mysqli_query($koneksi, "
    SELECT COUNT(id_guru) as jumlah FROM guru WHERE role='guru' AND DATE(terakhir_login) = '$hari_ini'
");
$jumlah_guru_login_hari_ini = mysqli_fetch_assoc($query_guru_login_hari_ini)['jumlah'] ?? 0;
$persen_guru_aktif_hari_ini = ($total_guru_aktif > 0) ? round(($jumlah_guru_login_hari_ini / $total_guru_aktif) * 100) : 0;

// 4. JUMLAH PESERTA EKSTRAKURIKULER
$query_ekskul = mysqli_query($koneksi, "
    SELECT e.nama_ekskul, COUNT(ep.id_peserta_ekskul) as jumlah_peserta
    FROM ekstrakurikuler e
    LEFT JOIN ekskul_peserta ep ON e.id_ekskul = ep.id_ekskul
    WHERE e.id_tahun_ajaran = '$id_tahun_ajaran_aktif'
    GROUP BY e.id_ekskul
    ORDER BY jumlah_peserta DESC
");
$data_ekskul = [];
while ($row = mysqli_fetch_assoc($query_ekskul)) {
    $data_ekskul[] = $row;
}
$labels_ekskul = json_encode(array_column($data_ekskul, 'nama_ekskul'));
$jumlah_peserta_ekskul = json_encode(array_column($data_ekskul, 'jumlah_peserta'));

?>

<!-- Style baru untuk dashboard admin -->
<style>
    .welcome-banner-admin {
        background: linear-gradient(135deg, var(--primary-color), #007bff);
        color: white;
        border-radius: 0.75rem;
        padding: 2.5rem 2rem;
        position: relative;
        overflow: hidden;
    }
    .welcome-banner-admin h3 {
        font-weight: 700;
        z-index: 2;
        position: relative;
    }
    .welcome-banner-admin p {
        z-index: 2;
        position: relative;
        color: rgba(255,255,255,0.8);
    }
    /* Icon ilustrasi di background */
    .welcome-banner-admin::after {
        content: '\f4f7'; /* Ganti dengan icon Bootstrap lain jika perlu */
        font-family: 'bootstrap-icons';
        font-size: 10rem;
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%) rotate(-15deg);
        opacity: 0.15;
        z-index: 1;
    }
    
    .kpi-card-admin {
        border-radius: 0.5rem;
        color: white;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .kpi-card-admin:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .kpi-card-admin .kpi-number {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }
    .kpi-card-admin .kpi-title {
        font-size: 1rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    .kpi-card-admin .kpi-link {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        font-size: 0.9rem;
    }
    .kpi-card-admin .kpi-link:hover {
        color: white;
    }
    .kpi-card-admin .kpi-icon {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 4rem;
        opacity: 0.2;
    }
    
    /* Warna KPI Cards */
    .bg-kpi-blue { background-color: #0d6efd; }
    .bg-kpi-green { background-color: #198754; }
    .bg-kpi-yellow { background-color: #ffc107; color: #333 !important; }
    .bg-kpi-yellow .kpi-link { color: rgba(0,0,0,0.7); }
    .bg-kpi-yellow .kpi-link:hover { color: #000; }
    .bg-kpi-red { background-color: #dc3545; }

    .chart-container {
        position: relative;
        height: 280px; /* Atur tinggi default chart */
        width: 100%;
    }
    .list-item-sm {
        font-size: 0.9em;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
</style>

<div class="container-fluid">
    <!-- Banner Selamat Datang (Sesuai Screenshot) -->
    <div class="welcome-banner-admin mb-4">
        <h3>Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama_guru'] ?? 'Admin'); ?>!</h3>
        <p class="mb-0">Anda login sebagai Admin. | T.A: <?php echo $nama_tahun_ajaran_aktif; ?> | Semester: <?php echo $semester_text; ?></p>
    </div>

    <!-- Baris 4 KPI Cards (Sesuai Screenshot) -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card-admin bg-kpi-blue h-100">
                <div class="kpi-number"><?php echo $total_siswa_aktif; ?></div>
                <div class="kpi-title">Peserta Didik Aktif</div>
                <a href="kelas_tampil.php" class="kpi-link">Selengkapnya <i class="bi bi-arrow-right-short"></i></a>
                <i class="bi bi-people-fill kpi-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card-admin bg-kpi-green h-100">
                <div class="kpi-number"><?php echo $total_guru_aktif; ?></div>
                <div class="kpi-title">Guru Tenaga Kependidikan</div>
                <a href="pengguna_tampil.php?role=guru" class="kpi-link">Selengkapnya <i class="bi bi-person-video3 kpi-icon"></i></a>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card-admin bg-kpi-yellow h-100">
                <div class="kpi-number"><?php echo $total_kelas_aktif; ?></div>
                <div class="kpi-title">Jumlah Kelas / Rombel</div>
                <a href="kelas_tampil.php" class="kpi-link">Selengkapnya <i class="bi bi-door-open-fill kpi-icon"></i></a>
            </div>
        </div>
         <div class="col-xl-3 col-md-6">
            <div class="kpi-card-admin bg-kpi-red h-100">
                <div class="kpi-number"><?php echo $jumlah_rapor_final; ?></div>
                <div class="kpi-title">Rapor Siap Cetak</div>
                <a href="admin_laporan_kelas.php" class="kpi-link">Selengkapnya <i class="bi bi-printer-fill kpi-icon"></i></a>
            </div>
        </div>
    </div>

    <!-- Baris Konten 2 Kolom (Menggunakan data Anda yang sudah ada) -->
    <h4 class="mb-3">Ringkasan Sistem</h4>
    <div class="row g-4">
        <!-- Kolom Kiri -->
        <div class="col-xl-7">
            <!-- Progres Tujuan Pembelajaran -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-bullseye me-2 text-primary"></i>Progres Tujuan Pembelajaran (TP)</h5>
                    <a href="mapel_tampil.php" class="btn btn-sm btn-outline-primary">Kelola TP <i class="bi bi-arrow-right-short"></i></a>
                </div>
                <div class="card-body">
                    <p>Total Mapel dengan TP: <strong><?php echo $mapel_dengan_tp; ?> dari <?php echo $total_mapel; ?> Mapel</strong></p>
                    <div class="progress mb-3" style="height: 15px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $persen_tp_mapel; ?>%;" aria-valuenow="<?php echo $persen_tp_mapel; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $persen_tp_mapel; ?>%</div>
                    </div>
                </div>
            </div>

             <!-- Partisipasi Ekstrakurikuler -->
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-bicycle me-2 text-info"></i>Partisipasi Ekstrakurikuler</h5>
                     <a href="admin_ekskul.php" class="btn btn-sm btn-outline-info">Kelola Ekskul <i class="bi bi-arrow-right-short"></i></a>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="ekskulChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div class="col-xl-5">
            <!-- Aktivitas Guru -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-person-check-fill me-2 text-success"></i>Aktivitas Guru Hari Ini</h5>
                    <span class="badge bg-success"><?php echo date('d M Y'); ?></span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                             <span class="fs-4 fw-bold"><?php echo $jumlah_guru_login_hari_ini; ?></span> / <?php echo $total_guru_aktif; ?> Guru
                             <div class="small text-muted">Telah Login Hari Ini</div>
                        </div>
                        <div style="width: 80px; height: 80px;">
                             <canvas id="guruAktifChart"></canvas>
                        </div>
                    </div>
                    <h6>Akses Terakhir:</h6>
                    <?php if ($guru_terakhir_login) : ?>
                        <p class="mb-2">
                            <i class="bi bi-clock-history text-success me-1"></i>
                            <span class="fw-bold"><?php echo htmlspecialchars($guru_terakhir_login['nama_guru']); ?></span>
                            <small class="text-muted ms-2">(<?php echo date('H:i', strtotime($guru_terakhir_login['terakhir_login'])); ?> WIB)</small>
                        </p>
                    <?php else : ?>
                        <p class="text-muted mb-2">Belum ada guru yang login.</p>
                    <?php endif; ?>
                     <a href="pengguna_tampil.php?role=guru" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-search me-1"></i> Lihat Semua Guru</a>
                </div>
            </div>

            <!-- Input Nilai Terbaru -->
            <div class="card shadow-sm">
                 <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-pencil-square me-2 text-warning"></i>Input Nilai Terbaru</h5>
                    <a href="admin_progres_penilaian.php" class="btn btn-sm btn-outline-warning">Lihat Progres <i class="bi bi-arrow-right-short"></i></a>
                </div>
                <div class="card-body px-0 py-2">
                    <?php if (empty($nilai_terbaru)) : ?>
                        <p class="text-muted text-center my-3 px-3">Belum ada data nilai yang dimasukkan semester ini.</p>
                    <?php else : ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($nilai_terbaru as $nilai) :
                                $icon_jenis = $nilai['jenis_penilaian'] == 'Sumatif' ? 'bi-journal-check text-primary' : 'bi-journal-text text-info';
                            ?>
                                <li class="list-group-item list-item-sm d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><i class="<?php echo $icon_jenis; ?> me-2"></i><?php echo htmlspecialchars($nilai['nama_mapel']); ?> (<?php echo htmlspecialchars($nilai['nama_kelas']); ?>)</div>
                                        <div class="text-muted small ms-4">
                                            <?php echo htmlspecialchars($nilai['nama_penilaian']); ?><br>
                                            Oleh: <?php echo htmlspecialchars($nilai['nama_guru']); ?>
                                        </div>
                                    </div>
                                    <small class="text-muted flex-shrink-0 ms-2"><?php echo date('d M', strtotime($nilai['tanggal_penilaian'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div> <!-- End Kolom Kanan -->

    </div> <!-- End Row Konten Utama -->

</div> <!-- End Container Fluid -->

<!-- Script Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
     Chart.register(ChartDataLabels);
     Chart.defaults.plugins.datalabels.display = false; // Nonaktifkan datalabels secara global

    // 1. Chart Aktivitas Guru Hari Ini (Doughnut Kecil)
     const ctxGuruAktif = document.getElementById('guruAktifChart');
     if (ctxGuruAktif) {
          new Chart(ctxGuruAktif, {
                type: 'doughnut',
                data: {
                     datasets: [{
                          data: [<?php echo $persen_guru_aktif_hari_ini; ?>, 100 - <?php echo $persen_guru_aktif_hari_ini; ?>],
                          backgroundColor: ['#198754', '#e9ecef'],
                          borderColor: '#fff',
                          borderWidth: 2,
                          cutout: '70%'
                     }]
                },
                options: {
                     responsive: true,
                     maintainAspectRatio: false,
                     plugins: {
                          legend: { display: false },
                          tooltip: { enabled: false },
                          datalabels: {
                              display: true,
                              formatter: (value, context) => {
                                  return context.dataIndex === 0 ? value + '%' : '';
                              },
                              color: '#198754',
                              font: { weight: 'bold', size: 14 }
                          }
                     }
                }
          });
     }

    // 2. Chart Partisipasi Ekskul (Horizontal Bar)
    const ctxEkskul = document.getElementById('ekskulChart');
    if (ctxEkskul) {
        new Chart(ctxEkskul, {
            type: 'bar',
            data: {
                labels: <?php echo $labels_ekskul; ?>,
                datasets: [{
                    label: 'Jumlah Peserta',
                    data: <?php echo $jumlah_peserta_ekskul; ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.6)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false },
                     datalabels: {
                         display: true,
                         anchor: 'end',
                         align: 'end',
                         color: '#555'
                    }
                }
            }
        });
    }
});
</script>