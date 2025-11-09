<?php
// dashboard_siswa.php - Konten khusus untuk Siswa

if (!isset($koneksi)) { die('File ini tidak boleh diakses langsung.'); }

// Ambil data aktif
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$data_tahun_aktif = mysqli_fetch_assoc($q_ta_aktif);
$id_tahun_ajaran_aktif = $data_tahun_aktif['id_tahun_ajaran'] ?? 0;
$nama_tahun_ajaran_aktif = $data_tahun_aktif['tahun_ajaran'] ?? 'Tidak Aktif';

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;
$semester_text = ($semester_aktif == 1) ? 'Ganjil' : 'Genap';

// Ambil data siswa yang login dari session
$id_siswa = $_SESSION['id_siswa'] ?? 0;
$nama_siswa = $_SESSION['nama_siswa'] ?? 'Siswa';

if ($id_siswa == 0) {
     echo "<div class='alert alert-danger'>Error: Data siswa tidak ditemukan. Silakan login kembali.</div>";
     include 'footer.php';
     exit;
}

// Ambil detail data siswa
$query_siswa_detail = mysqli_prepare($koneksi,
    "SELECT s.nisn, s.nis, s.foto_siswa, k.nama_kelas, k.fase
     FROM siswa s
     LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
     WHERE s.id_siswa = ?");
mysqli_stmt_bind_param($query_siswa_detail, "i", $id_siswa);
mysqli_stmt_execute($query_siswa_detail);
$siswa_detail = mysqli_fetch_assoc(mysqli_stmt_get_result($query_siswa_detail));
mysqli_stmt_close($query_siswa_detail);

// Tentukan path foto profil siswa
$foto_profil_siswa = 'uploads/default-avatar.png'; // Default avatar
if (!empty($siswa_detail['foto_siswa'])) {
    $path_to_check = 'uploads/foto_siswa/' . $siswa_detail['foto_siswa'];
    if (file_exists($path_to_check)) {
        $foto_profil_siswa = $path_to_check;
    }
}


// Cek status rapor terakhir siswa
$rapor_final = false;
$data_kehadiran = ['sakit' => 0, 'izin' => 0, 'tanpa_keterangan' => 0];
$catatan_wali = '-';
$id_rapor_siswa = null;

$query_rapor_status = mysqli_prepare($koneksi,
    "SELECT id_rapor, status, sakit, izin, tanpa_keterangan, catatan_wali_kelas
     FROM rapor
     WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ?
     ORDER BY id_rapor DESC LIMIT 1");
mysqli_stmt_bind_param($query_rapor_status, "iii", $id_siswa, $semester_aktif, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($query_rapor_status);
$result_rapor = mysqli_stmt_get_result($query_rapor_status);
if ($rapor_data = mysqli_fetch_assoc($result_rapor)) {
    $rapor_final = ($rapor_data['status'] == 'Final');
    $data_kehadiran['sakit'] = $rapor_data['sakit'] ?? 0;
    $data_kehadiran['izin'] = $rapor_data['izin'] ?? 0;
    $data_kehadiran['tanpa_keterangan'] = $rapor_data['tanpa_keterangan'] ?? 0;
    $catatan_wali = !empty($rapor_data['catatan_wali_kelas']) ? htmlspecialchars($rapor_data['catatan_wali_kelas']) : 'Tidak ada catatan.';
    $id_rapor_siswa = $rapor_data['id_rapor'];
}
mysqli_stmt_close($query_rapor_status);


// Ambil 5 nilai sumatif terakhir siswa
$nilai_terakhir = [];
$query_nilai = mysqli_prepare($koneksi,
    "SELECT p.nama_penilaian, p.tanggal_penilaian, mp.nama_mapel, pdn.nilai
     FROM penilaian_detail_nilai pdn
     JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
     JOIN mata_pelajaran mp ON p.id_mapel = mp.id_mapel
     WHERE pdn.id_siswa = ? AND p.jenis_penilaian = 'Sumatif' AND p.semester = ?
     ORDER BY p.tanggal_penilaian DESC, p.id_penilaian DESC
     LIMIT 5");
mysqli_stmt_bind_param($query_nilai, "ii", $id_siswa, $semester_aktif);
mysqli_stmt_execute($query_nilai);
$result_nilai = mysqli_stmt_get_result($query_nilai);
while ($row = mysqli_fetch_assoc($result_nilai)) {
    $nilai_terakhir[] = $row;
}
mysqli_stmt_close($query_nilai);

// Ambil data ekstrakurikuler yang diikuti siswa
$ekskul_siswa = [];
if ($id_rapor_siswa) {
    $query_ekskul = mysqli_prepare($koneksi,
        "SELECT nama_ekskul, keterangan FROM rapor_detail_ekskul WHERE id_rapor = ? ORDER BY nama_ekskul ASC");
    mysqli_stmt_bind_param($query_ekskul, "i", $id_rapor_siswa);
    mysqli_stmt_execute($query_ekskul);
    $result_ekskul = mysqli_stmt_get_result($query_ekskul);
    while($row = mysqli_fetch_assoc($result_ekskul)){
        $ekskul_siswa[] = $row;
    }
    mysqli_stmt_close($query_ekskul);
}
?>
<!-- Style Khusus Dashboard Siswa (Rombak) -->
<style>
    .profile-header-siswa {
        background: linear-gradient(135deg, var(--primary-color), #007bff);
        color: white;
        border-radius: 0.75rem;
        padding: 1.5rem 2rem;
    }
    .profile-pic-siswa {
        width: 70px;
        height: 70px;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .profile-header-siswa h4 { font-weight: 600; }
    .profile-header-siswa p { color: rgba(255,255,255,0.8); }

    .card-rapor-status {
        border-radius: 0.75rem;
    }
    .card-rapor-status .icon-status {
        font-size: 3.5rem;
        margin-bottom: 1rem;
    }
    
    .nilai-item {
        border-bottom: 1px dashed var(--border-color);
        padding: 1rem 0;
    }
     .nilai-item:last-child { border-bottom: none; }
    .nilai-mapel { font-weight: 600; color: var(--bs-dark); }
    .nilai-tanggal { font-size: 0.85em; color: var(--text-muted); }
    .nilai-angka {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .data-list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px dashed var(--border-color);
    }
    .data-list-item:last-child { border-bottom: none; }
    .data-list-item .icon-label i {
        font-size: 1.2rem;
        width: 25px;
    }
</style>

<div class="container-fluid">
    <!-- Header Profil Siswa -->
    <div class="profile-header-siswa mb-4 shadow-sm">
        <div class="d-flex flex-column flex-md-row align-items-center">
            <img src="<?php echo htmlspecialchars($foto_profil_siswa); ?>" alt="Foto Profil" class="rounded-circle profile-pic-siswa me-md-3 mb-2 mb-md-0">
            <div>
                <h4 class="mb-1">Halo, <?php echo htmlspecialchars($nama_siswa); ?>!</h4>
                <p class="mb-0 small">
                    <?php echo htmlspecialchars($siswa_detail['nama_kelas'] ?? 'Kelas ?'); ?> | 
                    NISN: <?php echo htmlspecialchars($siswa_detail['nisn'] ?? '-'); ?> | 
                    T.A: <?php echo $nama_tahun_ajaran_aktif; ?> | 
                    Semester: <?php echo $semester_text; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Rapor & Nilai -->
        <div class="col-lg-7">
            <!-- Card Download Rapor -->
            <div class="card shadow-sm card-rapor-status mb-4">
                <div class="card-body p-4 text-center">
                     <?php if($rapor_final): ?>
                        <i class="bi bi-check-circle-fill text-success icon-status"></i>
                        <h5 class="card-title">Rapor Tersedia</h5>
                        <p class="card-text text-muted mb-3">Rapor Anda untuk Semester <?php echo $semester_text; ?> T.A <?php echo $nama_tahun_ajaran_aktif; ?> sudah difinalisasi.</p>
                        <a href="rapor_pdf.php?id_siswa=<?php echo $id_siswa; ?>" class="btn btn-success btn-lg" target="_blank">
                            <i class="bi bi-download me-2"></i>Download Rapor
                        </a>
                    <?php else: ?>
                         <i class="bi bi-hourglass-split text-warning icon-status"></i>
                         <h5 class="card-title">Rapor Belum Tersedia</h5>
                         <p class="card-text text-muted mb-3">Rapor Anda sedang diproses atau belum difinalisasi oleh wali kelas.</p>
                        <button class="btn btn-secondary btn-lg" disabled>
                            <i class="bi bi-clock-history me-2"></i>Menunggu Finalisasi
                         </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Nilai Terbaru -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Nilai Sumatif Terbaru</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($nilai_terakhir)): ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($nilai_terakhir as $nilai): ?>
                                <li class="nilai-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="nilai-mapel"><?php echo htmlspecialchars($nilai['nama_mapel']); ?></span>
                                        <div class="d-block text-muted small"><?php echo htmlspecialchars($nilai['nama_penilaian']); ?></div>
                                        <small class="nilai-tanggal"><i class="bi bi-calendar3 me-1"></i><?php echo date('d M Y', strtotime($nilai['tanggal_penilaian'])); ?></small>
                                    </div>
                                    <span class="nilai-angka"><?php echo $nilai['nilai']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                         <div class="text-center mt-3">
                            <a href="siswa_lihat_nilai.php" class="btn btn-outline-primary btn-sm">Lihat Semua Nilai <i class="bi bi-arrow-right-short"></i></a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0 fst-italic">Belum ada nilai sumatif yang diinput semester ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Kehadiran, Ekskul, Catatan -->
        <div class="col-lg-5">
            <!-- Card Kehadiran -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2 text-info"></i>Kehadiran</h5>
                </div>
                <div class="card-body p-4">
                     <ul class="list-unstyled mb-0">
                        <li class="data-list-item">
                            <span class="icon-label"><i class="bi bi-bandaid text-danger me-2"></i>Sakit</span>
                            <span class="badge bg-danger rounded-pill fs-6"><?php echo $data_kehadiran['sakit']; ?></span>
                        </li>
                        <li class="data-list-item">
                            <span class="icon-label"><i class="bi bi-envelope-paper text-warning me-2"></i>Izin</span>
                            <span class="badge bg-warning text-dark rounded-pill fs-6"><?php echo $data_kehadiran['izin']; ?></span>
                        </li>
                        <li class="data-list-item">
                            <span class="icon-label"><i class="bi bi-x-circle text-secondary me-2"></i>Tanpa Keterangan</span>
                            <span class="badge bg-secondary rounded-pill fs-6"><?php echo $data_kehadiran['tanpa_keterangan']; ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Card Ekstrakurikuler -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-bicycle me-2 text-success"></i>Ekstrakurikuler</h5>
                </div>
                <div class="card-body p-4">
                     <?php if (!empty($ekskul_siswa)): ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($ekskul_siswa as $ekskul): ?>
                                <li class="data-list-item flex-column align-items-start">
                                    <div class="fw-bold"><?php echo htmlspecialchars($ekskul['nama_ekskul']); ?></div>
                                    <small class="text-muted fst-italic"><?php echo htmlspecialchars($ekskul['keterangan']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                         <p class="text-muted text-center mb-0 fst-italic">Belum ada data ekstrakurikuler.</p>
                    <?php endif; ?>
                </div>
            </div>

             <!-- Card Catatan Wali Kelas -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-chat-left-text me-2 text-primary"></i>Catatan Wali Kelas</h5>
                </div>
                <div class="card-body p-4">
                     <p class="text-muted fst-italic mb-0"><?php echo nl2br($catatan_wali); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>