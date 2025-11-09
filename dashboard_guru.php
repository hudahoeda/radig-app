<?php
// dashboard_guru.php - Konten khusus untuk Guru

// Pastikan file ini dipanggil oleh file induk dan koneksi sudah tersedia
if (!isset($koneksi)) { die('File ini tidak boleh diakses langsung.'); }

// Ambil data aktif
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$data_tahun_aktif = mysqli_fetch_assoc($q_ta_aktif);
$id_tahun_ajaran_aktif = $data_tahun_aktif['id_tahun_ajaran'] ?? 0;
$nama_tahun_ajaran_aktif = $data_tahun_aktif['tahun_ajaran'] ?? 'Tidak Aktif';

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;
$semester_text = ($semester_aktif == 1) ? 'Ganjil' : 'Genap';

// Mengambil data sesi guru
$id_guru_login = $_SESSION['id_guru'];
$nama_guru = $_SESSION['nama_guru'];

// Ambil foto profil guru
$query_foto = mysqli_query($koneksi, "SELECT foto_guru FROM guru WHERE id_guru = $id_guru_login");
$foto_profil_guru = 'uploads/guruc.png'; // Default
if ($data_foto = mysqli_fetch_assoc($query_foto)) {
    $foto_filename = $data_foto['foto_guru'];
    $path_to_check = 'uploads/guru_photos/' . $foto_filename; // Sesuaikan path jika perlu
    if (!empty($foto_filename) && file_exists($path_to_check)) {
        $foto_profil_guru = $path_to_check;
    }
}

// --- LOGIKA UTAMA: Cek Peran Wali Kelas & Mapel yang Diajar ---
$is_walas = false;
$id_kelas_wali = 0;
$nama_kelas_wali = '';
$mapel_diajar_walas = []; // Mapel yg diajar guru ini DI KELAS PERWALIANNYA
$mapel_diajar_lain = []; // Mapel yg diajar guru ini DI KELAS LAIN (jika ada)
$mapel_diajar_lain_grouped = []; // [BARU] Untuk tampilan yang lebih rapi

// 1. Cek apakah guru ini wali kelas di T.A Aktif
$query_walas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ? LIMIT 1");
mysqli_stmt_bind_param($query_walas, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($query_walas);
$result_walas = mysqli_stmt_get_result($query_walas);
if ($data_walas = mysqli_fetch_assoc($result_walas)) {
    $is_walas = true;
    $id_kelas_wali = $data_walas['id_kelas'];
    $nama_kelas_wali = $data_walas['nama_kelas'];
}
mysqli_stmt_close($query_walas);

// 2. Ambil SEMUA mapel yang diajar guru ini di T.A Aktif, beserta kelasnya
$query_mengajar = mysqli_prepare($koneksi,
    "SELECT gm.id_mapel, mp.nama_mapel, gm.id_kelas, k.nama_kelas
     FROM guru_mengajar gm
     JOIN mata_pelajaran mp ON gm.id_mapel = mp.id_mapel
     JOIN kelas k ON gm.id_kelas = k.id_kelas
     WHERE gm.id_guru = ? AND gm.id_tahun_ajaran = ?
     ORDER BY k.nama_kelas, mp.urutan, mp.nama_mapel ASC"); // Urutkan per kelas, lalu mapel
mysqli_stmt_bind_param($query_mengajar, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($query_mengajar);
$result_mengajar = mysqli_stmt_get_result($query_mengajar);

$is_pengampu = (mysqli_num_rows($result_mengajar) > 0); // Guru mengajar sesuatu

while ($row = mysqli_fetch_assoc($result_mengajar)) {
    $mapel_info = [
        'id_mapel' => $row['id_mapel'],
        'nama_mapel' => $row['nama_mapel'],
        'id_kelas' => $row['id_kelas'],
        'nama_kelas' => $row['nama_kelas']
    ];
    // Pisahkan: mapel di kelas perwalian vs mapel di kelas lain
    if ($is_walas && $row['id_kelas'] == $id_kelas_wali) {
        $mapel_diajar_walas[] = $mapel_info;
    } else {
        $mapel_diajar_lain[] = $mapel_info; // Data mentah
        $mapel_diajar_lain_grouped[$row['nama_mapel']][] = $mapel_info; // Kelompokkan per mapel
    }
}
mysqli_stmt_close($query_mengajar);


// --- LOGIKA DATA UNTUK WALI KELAS (Jika $is_walas) ---
$jumlah_siswa_walas = 0;
$jumlah_rapor_final_walas = 0;
$progres_rapor_walas = 0;
$siswa_absen_tinggi = 0; 
$data_belum_lengkap = 0; 
$batas_absen = 10; 

if ($is_walas) {
    // Jumlah siswa
    $query_siswa_walas = mysqli_prepare($koneksi, "SELECT COUNT(id_siswa) as total_siswa FROM siswa WHERE id_kelas = ? AND status_siswa = 'Aktif'");
    mysqli_stmt_bind_param($query_siswa_walas, "i", $id_kelas_wali);
    mysqli_stmt_execute($query_siswa_walas);
    $jumlah_siswa_walas = mysqli_fetch_assoc(mysqli_stmt_get_result($query_siswa_walas))['total_siswa'] ?? 0;
    mysqli_stmt_close($query_siswa_walas);

    // Jumlah rapor final & progres
    $query_rapor_final_walas = mysqli_prepare($koneksi, "SELECT COUNT(id_rapor) as total_final FROM rapor WHERE id_kelas = ? AND status = 'Final' AND semester = ? AND id_tahun_ajaran = ?");
    mysqli_stmt_bind_param($query_rapor_final_walas, "iii", $id_kelas_wali, $semester_aktif, $id_tahun_ajaran_aktif);
    mysqli_stmt_execute($query_rapor_final_walas);
    $jumlah_rapor_final_walas = mysqli_fetch_assoc(mysqli_stmt_get_result($query_rapor_final_walas))['total_final'] ?? 0;
    mysqli_stmt_close($query_rapor_final_walas);
    $progres_rapor_walas = ($jumlah_siswa_walas > 0) ? round(($jumlah_rapor_final_walas / $jumlah_siswa_walas) * 100) : 0;

    // Hitung siswa absen tinggi
    $query_absen = mysqli_prepare($koneksi, "SELECT COUNT(id_rapor) as total_absen_tinggi FROM rapor WHERE id_kelas = ? AND semester = ? AND id_tahun_ajaran = ? AND (sakit + izin + tanpa_keterangan) > ?");
    $absen_limit_days = $batas_absen; 
    mysqli_stmt_bind_param($query_absen, "iiii", $id_kelas_wali, $semester_aktif, $id_tahun_ajaran_aktif, $absen_limit_days);
    mysqli_stmt_execute($query_absen);
    $siswa_absen_tinggi = mysqli_fetch_assoc(mysqli_stmt_get_result($query_absen))['total_absen_tinggi'] ?? 0;
    mysqli_stmt_close($query_absen);

    // Hitung siswa data belum lengkap (catatan wali kelas kosong)
    $query_data_lengkap = mysqli_prepare($koneksi, "SELECT COUNT(id_rapor) as total_belum_lengkap FROM rapor WHERE id_kelas = ? AND semester = ? AND id_tahun_ajaran = ? AND (catatan_wali_kelas IS NULL OR catatan_wali_kelas = '')");
    mysqli_stmt_bind_param($query_data_lengkap, "iii", $id_kelas_wali, $semester_aktif, $id_tahun_ajaran_aktif);
    mysqli_stmt_execute($query_data_lengkap);
    $data_belum_lengkap = mysqli_fetch_assoc(mysqli_stmt_get_result($query_data_lengkap))['total_belum_lengkap'] ?? 0;
    mysqli_stmt_close($query_data_lengkap);
}

?>

<style>
    /* Styling for profile header */
    .profile-header-guru {
        /* [PERBAIKAN] Menggunakan variabel warna tema yang benar, bukan URL gambar yang rusak */
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 1.5rem 2rem; /* Menyamakan padding dengan file admin/siswa */
        border-radius: 0.75rem;
        color: white;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
    }
    .profile-pic-guru {
        width: 80px; height: 80px; object-fit: cover; border: 3px solid rgba(255,255,255,0.8);
    }
    .profile-header-guru h4 { font-weight: 700; }
    .profile-header-guru .badge { font-size: 0.9em; padding: 0.5em 0.75em; }

    /* Card styling */
    .card { transition: all 0.3s ease; }
    .card:hover { box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    .walas-card { border-left: 5px solid var(--bs-success); }
    .mapel-card { border-left: 5px solid var(--bs-info); }
    .walas-card .card-header { background-color: var(--bs-success-bg-subtle); border-bottom: 2px solid var(--bs-success); }
    .mapel-card .card-header { background-color: var(--bs-info-bg-subtle); border-bottom: 2px solid var(--bs-info); }

    /* Quick stats */
    .quick-stats .stat-item { text-align: center; }
    .quick-stats .stat-number { font-size: 1.8rem; font-weight: 700; color: var(--bs-primary); }
    .quick-stats .stat-label { font-size: 0.8rem; color: var(--bs-secondary-color); text-transform: uppercase; }

    /* Task list */
    .task-list .list-group-item { border: none; padding: 0.75rem 0.25rem; font-size: 0.9rem; border-bottom: 1px dashed var(--bs-border-color-translucent); }
    .task-list .list-group-item:last-child { border-bottom: none; }
    .task-list .badge { cursor: pointer; font-size: 0.8em; padding: 0.4em 0.6em; }

    /* Mapel list in Walas Card */
    .mapel-list-walas .list-group-item { padding: 0.75rem 1rem; border-color: var(--bs-border-color-translucent); }
    .mapel-list-walas .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.8rem; }

    /* Chart container */
    .chart-container { position: relative; height: 180px; width: 180px; margin: 1rem auto; }

     /* Responsive adjustments for mapel list buttons */
    @media (max-width: 575.98px) {
        .mapel-list-walas .mapel-actions { flex-direction: column; align-items: stretch !important; width: 100%; margin-top: 0.5rem;}
        .mapel-list-walas .mapel-actions .btn { margin-bottom: 0.25rem; width: 100%; }
         .mapel-list-walas .mapel-actions .btn:last-child { margin-bottom: 0; }
    }
</style>

<div class="container-fluid">
    <!-- Header Profil Guru -->
    <div class="card shadow-sm profile-header-guru mb-4">
        <div class="d-flex align-items-center">
            <img src="<?php echo htmlspecialchars($foto_profil_guru); ?>" alt="Foto Profil" class="rounded-circle profile-pic-guru me-3">
            <div>
                <h4>Selamat Datang, <?php echo htmlspecialchars($nama_guru); ?>!</h4>
                <p class="mb-1">Tahun Ajaran: <b><?php echo htmlspecialchars($nama_tahun_ajaran_aktif); ?></b> | Semester: <b><?php echo $semester_text; ?></b></p>
                <div>
                    <?php if ($is_walas): ?>
                        <span class="badge bg-success"><i class="bi bi-house-door-fill me-1"></i> Wali Kelas (<?php echo htmlspecialchars($nama_kelas_wali); ?>)</span>
                    <?php endif; ?>
                    <?php if ($is_pengampu): ?>
                         <?php if (!$is_walas || ($is_walas && !empty($mapel_diajar_lain)) ): ?>
                            <span class="badge bg-info text-dark"><i class="bi bi-journal-bookmark-fill me-1"></i> Guru Mata Pelajaran</span>
                         <?php endif; ?>
                    <?php endif; ?>
                     <?php if (!$is_walas && !$is_pengampu): ?>
                         <span class="badge bg-secondary">Belum ada penugasan</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Utama (Wali Kelas atau Mapel Spesialis) -->
        <!-- [MODIFIKASI] Logika kolom diubah: jika walas & ngajar kelas lain, kolom 7/5. Jika hanya 1 peran, kolom 8/4 -->
        <div class="<?php echo ($is_walas && !empty($mapel_diajar_lain)) ? 'col-lg-7' : 'col-lg-8'; ?>"> 

            <?php if ($is_walas): ?>
            <!-- Card Utama untuk Wali Kelas -->
            <div class="card shadow-sm walas-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-house-door-fill me-2 text-success"></i> Dashboard Wali Kelas - <?php echo htmlspecialchars($nama_kelas_wali); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4 quick-stats">
                        <div class="col-4 stat-item border-end">
                            <div class="stat-number"><?php echo $jumlah_siswa_walas; ?></div>
                            <div class="stat-label">Total Siswa</div>
                        </div>
                        <div class="col-4 stat-item border-end">
                            <div class="stat-number text-danger"><?php echo $siswa_absen_tinggi; ?></div>
                            <div class="stat-label">Absensi Tinggi</div>
                            <small class="text-muted">(> <?php echo $batas_absen; ?> Hari)</small>
                        </div>
                         <div class="col-4 stat-item">
                            <div class="stat-number text-warning"><?php echo $data_belum_lengkap; ?></div>
                            <div class="stat-label">Data Belum Lengkap</div>
                            <small class="text-muted">(Cttn. Walas)</small>
                        </div>
                    </div>

                    <div class="row align-items-center">
                        <div class="col-md-5 text-center">
                             <h6 class="mb-1">Progres Finalisasi Rapor</h6>
                             <div class="chart-container">
                                 <canvas id="walasRaporChart"></canvas>
                             </div>
                             <small class="text-muted"><?php echo $jumlah_rapor_final_walas . ' dari ' . $jumlah_siswa_walas; ?> rapor difinalisasi</small>
                        </div>
                        <div class="col-md-7">
                            <h6 class="mt-3 mt-md-0">Tugas Wali Kelas:</h6>
                            <ul class="list-group list-group-flush task-list mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Input Absensi & Catatan Rapor
                                    <?php if ($data_belum_lengkap > 0): ?>
                                    <span class="badge bg-warning text-dark rounded-pill" onclick="window.location.href='walikelas_data_rapor.php'">Perlu Tindakan</span>
                                    <?php else: ?>
                                     <span class="badge bg-success rounded-pill">Selesai</span>
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Cek Kelengkapan Nilai Mapel
                                    <!-- ====================================================== -->
                                    <!-- [PERBAIKAN] Link diubah ke walikelas_proses_rapor.php -->
                                    <!-- ====================================================== -->
                                    <span class="badge bg-light text-dark rounded-pill" onclick="window.location.href='walikelas_proses_rapor.php'">Lihat Progres</span> 
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Proses Nilai Akhir & Deskripsi
                                    <span class="badge bg-primary rounded-pill" onclick="window.location.href='walikelas_proses_rapor.php'">Proses Sekarang</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Finalisasi Rapor Akhir Semester
                                     <?php if ($progres_rapor_walas < 100): ?>
                                     <span class="badge bg-secondary rounded-pill" onclick="window.location.href='walikelas_cetak_rapor.php'">Belum Siap</span>
                                     <?php else: ?>
                                     <span class="badge bg-success rounded-pill" onclick="window.location.href='walikelas_cetak_rapor.php'">Siap Cetak</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                             <div class="d-grid gap-2 d-sm-flex justify-content-sm-start mt-3">
                                <a href="walikelas_data_rapor.php" class="btn btn-outline-success"><i class="bi bi-person-lines-fill me-2"></i>Input Data Rapor</a>
                                <a href="walikelas_cetak_rapor.php" class="btn btn-success"><i class="bi bi-printer-fill me-2"></i>Cetak Rapor & Leger</a>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Daftar Mapel yang Diajar Wali Kelas di Kelasnya -->
                    <h5 class="mb-3"><i class="bi bi-journals me-2"></i>Kelola Pembelajaran Kelas <?php echo htmlspecialchars($nama_kelas_wali); ?></h5>
                    <?php if (!empty($mapel_diajar_walas)): ?>
                        <div class="list-group mapel-list-walas">
                            <?php foreach ($mapel_diajar_walas as $mapel): ?>
                                <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                                    <span class="fw-bold mb-2 mb-sm-0"><?php echo htmlspecialchars($mapel['nama_mapel']); ?></span>
                                    <div class="btn-group btn-group-sm mapel-actions" role="group">
                                        <a href="tp_guru_tampil.php?fokus_mapel=<?php echo $mapel['id_mapel']; ?>" class="btn btn-outline-secondary">
                                            <i class="bi bi-card-checklist me-1"></i>Kelola TP
                                        </a>
                                        <a href="penilaian_tampil.php?id_kelas=<?php echo $id_kelas_wali; ?>&id_mapel=<?php echo $mapel['id_mapel']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-input-cursor-text me-1"></i>Input Nilai
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light">Anda belum ditugaskan mengajar mata pelajaran apapun di kelas perwalian Anda.</div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endif; ?> 

            <!-- [MODIFIKASI] Tampilan ini HANYA muncul jika guru BUKAN walas, TAPI mengajar -->
            <?php if (!$is_walas && $is_pengampu): ?>
            <!-- Tampilan HANYA untuk Guru Mapel Spesialis (bukan Wali Kelas) -->
            <div class="card shadow-sm mapel-card">
                 <div class="card-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-bookmark-fill me-2 text-info"></i> Dashboard Guru Mata Pelajaran</h5>
                </div>
                <div class="card-body">
                     <?php if (!empty($mapel_diajar_lain_grouped)): ?>
                         <p class="text-muted mb-3">Berikut adalah mata pelajaran dan kelas yang Anda ajar:</p>
                        <?php foreach ($mapel_diajar_lain_grouped as $nama_mapel_spesialis => $kelas_diajar): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <h6 class="fw-bold"><?php echo htmlspecialchars($nama_mapel_spesialis); ?></h6>
                                <?php foreach ($kelas_diajar as $detail_mapel): ?>
                                <div class="d-flex justify-content-between align-items-center mb-1 ps-3">
                                    <span><i class="bi bi-door-open me-2"></i>Kelas <?php echo htmlspecialchars($detail_mapel['nama_kelas']); ?></span>
                                     <div class="btn-group btn-group-sm" role="group">
                                        <a href="tp_guru_tampil.php?fokus_mapel=<?php echo $detail_mapel['id_mapel']; ?>" class="btn btn-outline-secondary">
                                            <i class="bi bi-card-checklist me-1"></i>Kelola TP
                                        </a>
                                        <a href="penilaian_tampil.php?id_kelas=<?php echo $detail_mapel['id_kelas']; ?>&id_mapel=<?php echo $detail_mapel['id_mapel']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-input-cursor-text me-1"></i>Input Nilai
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <div class="alert alert-light">Anda belum ditugaskan mengajar mata pelajaran apapun.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?> 

        </div>

        <!-- Kolom Samping (Mapel Kelas Lain / Pengumuman) -->
        <div class="<?php echo ($is_walas && !empty($mapel_diajar_lain)) ? 'col-lg-5' : 'col-lg-4'; ?>"> 
             
             <!-- [MODIFIKASI] Tampilan ini HANYA muncul jika guru adalah walas DAN mengajar kelas lain -->
             <?php if ($is_walas && !empty($mapel_diajar_lain_grouped)): ?>
             <div class="card shadow-sm mapel-card">
                 <div class="card-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-bookmark-fill me-2 text-info"></i>Tugas Mengajar di Kelas Lain</h5>
                </div>
                <div class="card-body">
                     <p class="text-muted mb-3 small">Selain menjadi wali kelas, Anda juga mengajar:</p>
                    <?php foreach ($mapel_diajar_lain_grouped as $nama_mapel_spesialis => $kelas_diajar): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <h6 class="fw-bold"><?php echo htmlspecialchars($nama_mapel_spesialis); ?></h6>
                            <?php foreach ($kelas_diajar as $detail_mapel): ?>
                            <div class="d-flex justify-content-between align-items-center mb-1 ps-3">
                                <span><i class="bi bi-door-open me-2"></i>Kelas <?php echo htmlspecialchars($detail_mapel['nama_kelas']); ?></span>
                                 <div class="btn-group btn-group-sm" role="group">
                                    <a href="tp_guru_tampil.php?fokus_mapel=<?php echo $detail_mapel['id_mapel']; ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-card-checklist me-1"></i>Kelola TP
                                    </a>
                                    <a href="penilaian_tampil.php?id_kelas=<?php echo $detail_mapel['id_kelas']; ?>&id_mapel=<?php echo $detail_mapel['id_mapel']; ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-input-cursor-text me-1"></i>Input Nilai
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
             <?php endif; ?> 

             <!-- [MODIFIKASI] Tampilan ini HANYA muncul jika guru HANYA punya 1 peran (walas saja / guru mapel saja) -->
             <?php if (!($is_walas && !empty($mapel_diajar_lain))): ?> 
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                         <h5 class="mb-0"><i class="bi bi-megaphone-fill me-2 text-warning"></i> Pengumuman Sekolah</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fst-italic">Belum ada pengumuman terbaru.</p>
                        
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2 text-info"></i> Panduan Cepat</h5>
                    </div>
                    <div class="card-body">
                         <?php if ($is_walas): // Hanya Walas ?>
                             <ul class="list-group list-group-flush small">
                                <li class="list-group-item"><b class="text-primary">1. Kelola Mapel Anda:</b> Atur TP dan Input Nilai untuk mapel yang Anda ajar di kelas ini melalui bagian "Kelola Pembelajaran".</li>
                                <li class="list-group-item"><b class="text-primary">2. Lengkapi Data Rapor:</b> Masuk ke "Input Data Rapor" untuk mengisi absensi dan catatan.</li>
                                <li class="list-group-item"><b class="text-primary">3. Proses & Finalisasi:</b> Jika semua nilai mapel lengkap, lakukan "Proses Nilai Akhir" lalu finalisasi agar rapor siap cetak.</li>
                                <li class="list-group-item"><b class="text-primary">4. Cetak:</b> Gunakan menu "Cetak Rapor & Leger".</li>
                            </ul>
                         <?php else: // Hanya Guru Mapel ?>
                             <ul class="list-group list-group-flush small">
                                <li class="list-group-item"><b class="text-primary">1. Kelola TP:</b> Buat/edit Tujuan Pembelajaran (TP) untuk mapel Anda melalui tombol "Kelola TP".</li>
                                <li class="list-group-item"><b class="text-primary">2. Input Nilai:</b> Masuk ke "Input Nilai" untuk membuat agenda penilaian dan mengisi nilai siswa per TP.</li>
                                <li class="list-group-item"><b class="text-primary">3. Kokurikuler (Jika Ada):</b> Jangan lupa mengisi asesmen kokurikuler jika Anda terlibat.</li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?> 
        </div>
    </div>
</div>

<?php if ($is_walas): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi Chart untuk Progres Finalisasi Rapor Wali Kelas
    const ctxWalasRapor = document.getElementById('walasRaporChart');
    if (ctxWalasRapor) {
        const progresRapor = <?php echo $progres_rapor_walas; ?>;
        const sisaProgres = 100 - progresRapor;

        // Register the datalabels plugin
        Chart.register(ChartDataLabels);

        new Chart(ctxWalasRapor, {
            type: 'doughnut',
            data: {
                // labels: ['Final', 'Draft'], // Optional: uncomment if you need labels on hover
                datasets: [{
                    data: [progresRapor, sisaProgres],
                    backgroundColor: [
                        '#198754', // Warna hijau untuk Final (sesuaikan dengan --bs-success)
                        '#e9ecef'  // Warna abu-abu untuk Draft (sesuaikan dengan --bs-light)
                    ],
                    borderColor: '#fff', // White border between segments
                    borderWidth: 3,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%', // Adjust thickness of the doughnut
                plugins: {
                    legend: {
                        display: false // Hide legend
                    },
                    tooltip: {
                        enabled: false // Disable tooltips on hover
                    },
                    datalabels: { // Configure datalabels plugin
                        display: true,
                        formatter: (value, context) => {
                             // Only show the label for the 'Final' segment (index 0)
                             if (context.dataIndex === 0 && value > 0) { // [MODIFIKASI] Hanya tampilkan jika lebih dari 0
                                 return value + '%';
                             } else {
                                 return ''; // Hide label for the 'Draft' segment
                             }
                        },
                        color: '#343a40', // Color of the percentage text (dark grey)
                        font: {
                            size: '24', // Font size of the percentage
                            weight: 'bold'
                        },
                         anchor: 'center', // Position the label in the center
                         align: 'center'
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>