<?php
// Set variabel halaman untuk Active State di Sidebar
$page = 'admin_laporan';
$title = 'Pusat Laporan & Cetak';

include 'header.php';
include 'koneksi.php';

// ==========================================================
// 1. VALIDASI AKSES
// ==========================================================
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// ==========================================================
// 2. DATA GLOBAL
// ==========================================================
// Ambil Tahun Ajaran Aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran_aktif = $d_ta['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_aktif = $d_ta['tahun_ajaran'] ?? '-';

// Ambil Semester Aktif
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// --- STATISTIK RINGKASAN (UPGRADE) ---
// 1. Total Kelas
$q_count_kelas = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif");
$total_kelas_global = mysqli_fetch_assoc($q_count_kelas)['total'];

// 2. Total Siswa Aktif
$q_count_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa WHERE status_siswa = 'Aktif'");
$total_siswa_global = mysqli_fetch_assoc($q_count_siswa)['total'];

// 3. Progress Global Finalisasi Rapor
// Menghitung berapa rapor yang sudah status 'Final' dari total siswa aktif
$q_rapor_global = mysqli_query($koneksi, "
    SELECT COUNT(r.id_rapor) as total 
    FROM rapor r 
    JOIN siswa s ON r.id_siswa = s.id_siswa 
    WHERE r.status = 'Final' 
    AND r.semester = $semester_aktif 
    AND r.id_tahun_ajaran = $id_tahun_ajaran_aktif 
    AND s.status_siswa = 'Aktif'
");
$total_final_global = mysqli_fetch_assoc($q_rapor_global)['total'];
$persen_global = ($total_siswa_global > 0) ? round(($total_final_global / $total_siswa_global) * 100) : 0;
?>

<style>
    /* --- TEAL THEME OVERRIDES --- */
    :root {
        --teal-primary: #009688; /* Teal 500 */
        --teal-dark: #00796b;    /* Teal 700 */
        --teal-light: #b2dfdb;   /* Teal 100 */
    }

    /* Header Gradient */
    .page-header {
        background: linear-gradient(135deg, var(--teal-primary), var(--teal-dark));
        padding: 2.5rem 2rem; 
        border-radius: 0.75rem; 
        color: white;
        margin-bottom: 2rem;
    }
    .page-header h1 { font-weight: 700; }

    /* Override Bootstrap Colors */
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
    
    /* Subtle BG for icon containers */
    .bg-teal-subtle {
        background-color: rgba(0, 150, 136, 0.1) !important;
        color: var(--teal-primary) !important;
    }

    /* Table & Progress Styling */
    .table-hover tbody tr:hover {
        background-color: rgba(0, 150, 136, 0.02);
    }
    .progress { height: 0.6rem; border-radius: 1rem; }
    
    /* Card KPI Hover Effect */
    .card-stat {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card-stat:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    .icon-box-stat {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
    }
</style>

<div class="container-fluid">
    
    <!-- PAGE HEADER -->
    <div class="page-header shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Pusat Laporan & Cetak</h1>
                <p class="lead mb-0 opacity-75">
                    Tahun Ajaran: <b><?php echo $tahun_ajaran_aktif; ?></b> | Semester: <b><?php echo $semester_aktif; ?></b>
                </p>
            </div>
            <div class="d-none d-md-block">
                <i class="bi bi-printer-fill display-4 opacity-25"></i>
            </div>
        </div>
    </div>

    <!-- [UPGRADE] RINGKASAN STATUS -->
    <div class="row g-4 mb-4">
        <!-- Card Total Kelas -->
        <div class="col-md-4">
            <div class="card card-stat h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box-stat bg-teal-subtle text-primary me-3">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Kelas</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $total_kelas_global; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card Total Siswa -->
        <div class="col-md-4">
            <div class="card card-stat h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box-stat bg-teal-subtle text-primary me-3">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Siswa Aktif</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $total_siswa_global; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card Progress Global -->
        <div class="col-md-4">
            <div class="card card-stat h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="text-muted text-uppercase mb-0" style="font-size: 0.75rem; letter-spacing: 0.5px;">Rapor Final</h6>
                        <span class="fw-bold text-primary"><?php echo $persen_global; ?>%</span>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $persen_global; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $total_final_global; ?> dari <?php echo $total_siswa_global; ?> siswa selesai</small>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CARD: DAFTAR KELAS -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-list-task me-2"></i>Progres Rapor per Kelas</h5>
            
            <!-- [UPGRADE] SEARCH BOX -->
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="searchInput" class="form-control border-start-0 bg-light" placeholder="Cari kelas atau wali...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="tableLaporan">
                    <thead class="bg-light text-uppercase small text-muted">
                        <tr>
                            <th class="ps-4 py-3" width="35%">Kelas & Wali Kelas</th>
                            <th width="15%" class="text-center">Jumlah Siswa</th>
                            <th width="30%">Status Finalisasi</th>
                            <th width="20%" class="text-center">Aksi & Cetak</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // [UPDATE] Ambil foto_guru dari tabel guru
                    $query_kelas = "SELECT k.id_kelas, k.nama_kelas, g.nama_guru, g.foto_guru 
                                    FROM kelas k 
                                    LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
                                    WHERE k.id_tahun_ajaran = $id_tahun_ajaran_aktif 
                                    ORDER BY k.nama_kelas ASC";
                    $result_kelas = mysqli_query($koneksi, $query_kelas);

                    if ($result_kelas && mysqli_num_rows($result_kelas) > 0) {
                        while($kelas = mysqli_fetch_assoc($result_kelas)):
                            $id_kelas = $kelas['id_kelas'];
                            $nama_guru = $kelas['nama_guru'] ?? 'Belum Ada';
                            $foto_guru = $kelas['foto_guru'] ?? '';

                            // Cek File Foto
                            $path_foto = 'uploads/guru_photos/' . $foto_guru;
                            $has_foto = !empty($foto_guru) && file_exists($path_foto);

                            // Hitung progres
                            $q_siswa = mysqli_query($koneksi, "SELECT COUNT(id_siswa) as total FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif'");
                            $jumlah_siswa = mysqli_fetch_assoc($q_siswa)['total'] ?? 0;

                            $q_rapor_final = mysqli_query($koneksi, "SELECT COUNT(id_rapor) as total FROM rapor WHERE id_kelas = $id_kelas AND status = 'Final' AND semester = $semester_aktif AND id_tahun_ajaran = $id_tahun_ajaran_aktif");
                            $jumlah_rapor_final = mysqli_fetch_assoc($q_rapor_final)['total'] ?? 0;

                            $persentase = ($jumlah_siswa > 0) ? round(($jumlah_rapor_final / $jumlah_siswa) * 100) : 0;
                            
                            // Warna Progress
                            $progress_color = 'bg-primary'; // Teal default
                            if ($persentase == 100) $progress_color = 'bg-success';
                            elseif ($persentase == 0) $progress_color = 'bg-secondary';
                            elseif ($persentase < 50) $progress_color = 'bg-warning text-dark';
                    ?>
                        <tr class="search-item">
                            <!-- Kolom Kelas & Wali -->
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-teal-subtle rounded-3 p-2 text-center me-3" style="min-width: 50px;">
                                        <h6 class="mb-0 fw-bold text-primary"><?php echo str_replace('Kelas ', '', $kelas['nama_kelas']); ?></h6>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark class-name"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h6>
                                        <div class="d-flex align-items-center mt-1 teacher-name">
                                            <?php if ($has_foto): ?>
                                                <img src="<?php echo $path_foto; ?>" alt="Foto" class="rounded-circle me-2 shadow-sm" style="width: 24px; height: 24px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle me-2 bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center text-secondary" style="width: 24px; height: 24px; font-size: 0.75rem;">
                                                    <i class="bi bi-person-fill"></i>
                                                </div>
                                            <?php endif; ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($nama_guru); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom Jumlah Siswa -->
                            <td class="text-center">
                                <span class="badge bg-light text-dark border shadow-sm rounded-pill px-3">
                                    <?php echo $jumlah_siswa; ?> Siswa
                                </span>
                            </td>
                            
                            <!-- Kolom Progres -->
                            <td>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="fw-bold <?php echo $persentase == 100 ? 'text-success' : 'text-primary'; ?>">
                                        <?php echo $persentase; ?>% Selesai
                                    </small>
                                    <small class="text-muted"><?php echo $jumlah_rapor_final; ?>/<?php echo $jumlah_siswa; ?></small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar <?php echo $progress_color; ?>" role="progressbar" style="width: <?php echo $persentase; ?>%"></div>
                                </div>
                            </td>
                            
                            <!-- Kolom Aksi -->
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-gear-fill me-1"></i> Kelola
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <li><h6 class="dropdown-header text-uppercase small">Aksi Kelas</h6></li>
                                        <li>
                                            <a class="dropdown-item" href="admin_laporan_siswa.php?id_kelas=<?php echo $id_kelas; ?>">
                                                <i class="bi bi-search me-2 text-primary"></i>Detail Siswa
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header text-uppercase small">Cetak Leger</h6></li>
                                        <li>
                                            <a class="dropdown-item" href="leger_pdf.php?id_kelas=<?php echo $id_kelas; ?>" target="_blank">
                                                <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Format PDF
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="leger_excel.php?id_kelas=<?php echo $id_kelas; ?>" target="_blank">
                                                <i class="bi bi-file-earmark-excel me-2 text-success"></i>Format Excel
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    } else {
                        echo '<tr><td colspan="4"><div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3"></i>Belum ada data kelas.</div></td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light py-3 border-top-0">
            <small class="text-muted"><i class="bi bi-info-circle me-1"></i> Klik tombol <b>Kelola</b> untuk melihat detail siswa atau mencetak Leger Nilai.</small>
        </div>
    </div>
    
</div>

<script>
// [UPGRADE] Script Pencarian Sederhana
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#tableLaporan tbody tr.search-item');

    rows.forEach(row => {
        let className = row.querySelector('.class-name').textContent.toLowerCase();
        let teacherName = row.querySelector('.teacher-name').textContent.toLowerCase();
        
        if (className.includes(filter) || teacherName.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

<?php 
// SweetAlert Notifikasi
if (isset($_SESSION['pesan']) || isset($_SESSION['pesan_error'])) {
    $pesan = $_SESSION['pesan'] ?? $_SESSION['pesan_error'];
    $icon = isset($_SESSION['pesan']) ? 'success' : 'error';
    $title = isset($_SESSION['pesan']) ? 'Berhasil' : 'Gagal';
    
    echo "<script>
        Swal.fire({
            icon: '{$icon}',
            title: '{$title}',
            text: '{$pesan}',
            confirmButtonColor: '#009688'
        });
    </script>";
    
    unset($_SESSION['pesan']);
    unset($_SESSION['pesan_error']);
}
include 'footer.php'; 
?>