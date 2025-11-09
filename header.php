<?php
// --- SEMUA LOGIKA PHP ANDA TETAP SAMA ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role'])) {
    header("location:index.php?pesan=belum_login");
    exit();
}
$role = $_SESSION['role'];
$nama_pengguna = $_SESSION['nama_guru'] ?? $_SESSION['nama_siswa'] ?? 'Pengguna';
$current_page = basename($_SERVER['PHP_SELF']);
$foto_profil_path = 'uploads/guruc.png'; // Pastikan Anda punya file ini sebagai default

if (isset($koneksi)) {
    if ($role == 'admin' || $role == 'guru') {
        $id_pengguna = $_SESSION['id_guru'];
        $query_foto = mysqli_query($koneksi, "SELECT foto_guru FROM guru WHERE id_guru = $id_pengguna");
        if ($data_foto = mysqli_fetch_assoc($query_foto)) {
            $foto_filename = $data_foto['foto_guru'];
            $path_to_check = 'uploads/guru_photos/' . $foto_filename;
            if (!empty($foto_filename) && file_exists($path_to_check)) {
                $foto_profil_path = $path_to_check;
            }
        }
    } elseif ($role == 'siswa' && isset($_SESSION['id_siswa'])) {
        $id_pengguna = $_SESSION['id_siswa'];
        $query_foto = mysqli_query($koneksi, "SELECT foto_siswa FROM siswa WHERE id_siswa = $id_pengguna");
        if ($data_foto = mysqli_fetch_assoc($query_foto)) {
            $foto_filename = $data_foto['foto_siswa'];
            // Asumsi path foto siswa, sesuaikan jika perlu
            $path_to_check = 'uploads/foto_siswa/' . $foto_filename; 
            if (!empty($foto_filename) && file_exists($path_to_check)) {
                $foto_profil_path = $path_to_check;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- ... existing code ... -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aplikasi Rapor Digital</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- [PENAMBAHAN] CSS untuk Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- [PENAMBAHAN] JavaScript untuk Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        :root {
            /* Palet Warna Anda (Tetap Sama) */
            --primary-color: #26a69a; /* Teal */
            --secondary-color: #00796b; /* Teal Gelap */
            --sidebar-bg: #004d40; /* Teal Sangat Gelap untuk Sidebar */
            --sidebar-text: rgba(255, 255, 255, 0.85);
            --sidebar-text-active: #ffffff;
            --sidebar-bg-hover: #00695C;
            --sidebar-bg-active: #00796b;
            --background-light: #f4f7f6; /* Off-white yang lembut */
            --text-dark: #333;
            --text-muted: #6c757d;
            --border-color: #e0e0e0;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            transition: all 0.3s;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 999;
            display: flex;
            flex-direction: column;
        }
        #sidebar.active {
            margin-left: -260px;
        }
        #sidebar .sidebar-header {
            padding: 1.2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }
        .sidebar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--sidebar-text-active);
        }
        .sidebar-brand img {
            height: 30px;
            width: auto;
            margin-right: 10px;
        }

        .mcd-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            overflow-y: auto; /* Agar bisa di-scroll */
            flex-grow: 1; /* Memenuhi sisa ruang */
        }
        .mcd-menu li {
            position: relative;
        }
        .mcd-menu li a {
            display: block;
            text-decoration: none;
            padding: 15px 20px;
            color: var(--sidebar-text);
            height: 60px; /* Disesuaikan agar lebih lega */
            position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        .mcd-menu li a i {
            float: left;
            font-size: 1.4rem; /* Disesuaikan */
            margin: 0 15px 0 0;
            line-height: 30px; /* Vertikal align */
        }
        .mcd-menu li a strong {
            display: block;
            text-transform: uppercase;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .mcd-menu li a small {
            display: block;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
        }
        .mcd-menu li a i, .mcd-menu li a strong, .mcd-menu li a small {
            position: relative;
            transition: all 300ms linear;
        }

        /* Efek Hover dan Animasi */
        .mcd-menu li:hover > a {
            background-color: var(--sidebar-bg-hover);
            color: var(--sidebar-text-active);
        }
        .mcd-menu li:hover > a i {
            -webkit-animation: moveFromTop 300ms ease-in-out;
            animation: moveFromTop 300ms ease-in-out;
        }
        .mcd-menu li:hover a strong {
            -webkit-animation: moveFromLeft 300ms ease-in-out;
            animation: moveFromLeft 300ms ease-in-out;
        }
        .mcd-menu li:hover a small {
            -webkit-animation: moveFromRight 300ms ease-in-out;
            animation: moveFromRight 300ms ease-in-out;
        }

        /* Gaya Link Aktif */
        .mcd-menu li a.active {
            position: relative;
            color: var(--sidebar-text-active);
            background-color: var(--sidebar-bg-active);
            border:0;
            border-left: 4px solid var(--primary-color);
            border-right: 4px solid var(--primary-color);
            margin: 0 -4px;
            padding-left: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .mcd-menu li a.active:before {
            content: "";
            position: absolute;
            top: 42%;
            left: 0;
            border-left: 5px solid var(--primary-color);
            border-top: 5px solid transparent;
            border-bottom: 5px solid transparent;
        }
        .mcd-menu li a.active:after {
            content: "";
            position: absolute;
            top: 42%;
            right: 0;
            border-right: 5px solid var(--primary-color);
            border-top: 5px solid transparent;
            border-bottom: 5px solid transparent;
        }

        .mcd-menu .sidebar-heading {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            padding: 10px 20px;
            margin-top: 1rem;
            background-color: rgba(0,0,0,0.2);
        }
        .mcd-menu .logout-link a {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
        }


        @-webkit-keyframes moveFromTop { from { transform: translateY(200%); } to { transform: translateY(0%); } }
        @keyframes moveFromTop { from { transform: translateY(200%); } to { transform: translateY(0%); } }
        @-webkit-keyframes moveFromLeft { from { transform: translateX(200%); } to { transform: translateX(0%); } }
        @keyframes moveFromLeft { from { transform: translateX(200%); } to { transform: translateX(0%); } }
        @-webkit-keyframes moveFromRight { from { transform: translateX(-200%); } to { transform: translateX(0%); } }
        @keyframes moveFromRight { from { transform: translateX(-200%); } to { transform: translateX(0%); } }

        .mcd-menu::-webkit-scrollbar { width: 8px; }
        .mcd-menu::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .mcd-menu::-webkit-scrollbar-thumb {
            background-color: var(--secondary-color);
            border-radius: 10px;
            border: 2px solid var(--sidebar-bg);
        }

        #content { width: 100%; padding-left: 260px; min-height: 100vh; transition: all 0.3s; }
        #sidebar.active + #content { padding-left: 0; }
        .top-navbar { padding: 0.8rem 1.5rem; background: #fff; border-bottom: 1px solid var(--border-color); box-shadow: var(--card-shadow); }
        .top-navbar .dropdown-toggle::after { display: none; }
        .dropdown-menu { border-radius: 10px; border: 1px solid var(--border-color); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .main-content { padding: 2rem; }
        .card { border: none; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 1.5rem; }
        .welcome-banner { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: #fff; border-radius: 12px; padding: 2rem; }
        .welcome-banner h4 { font-weight: 600; }
        .app-footer { padding: 1rem 2rem; background-color: #fff; border-top: 1px solid var(--border-color); font-size: 0.85rem; transition: all 0.3s; }
        .app-footer a { color: var(--text-muted); text-decoration: none; transition: color 0.2s ease; }
        .app-footer a:hover { color: var(--primary-color); }
        #sidebar.active + #content .app-footer { padding: 1rem; }
        @media (max-width: 768px) {
            #sidebar { margin-left: -260px; }
            #sidebar.active { margin-left: 0; }
            #content { padding-left: 0; }
            .top-navbar { padding: 0.8rem 1rem; }
            .main-content { padding: 1rem; }
        }
        
        #pengembangModal .modal-header { background-color: var(--sidebar-bg); color: white; }
        #pengembangModal .profile-pic { width: 150px; height: 150px; object-fit: cover; border: 5px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        #pengembangModal .list-group-item { border: none; padding-left: 0; padding-right: 0; }
        #pengembangModal .list-group-item i { width: 25px; text-align: center; color: var(--primary-color); }
        #pengembangModal h5 { font-weight: 600; color: var(--secondary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 8px; margin-top: 1.5rem; }
    </style>
</head>
<body>

<!-- ====================================================== -->
<!-- ### KODE BANNER IMPERSONATE DIMASUKKAN DI SINI ### -->
<!-- ====================================================== -->
<?php
// Cek apakah ini adalah sesi penyamaran (impersonate)
if (isset($_SESSION['admin_asal_id'])) {
    
    echo '
    <div style="
        background-color: #ffc107; 
        color: #333; 
        padding: 10px 20px; 
        text-align: center; 
        font-weight: bold; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        z-index: 9999;
        border-bottom: 2px solid #e0a800;
        font-family: Arial, sans-serif;
    ">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Anda sedang login sebagai pengguna lain. 
        <a href="admin_aksi.php?aksi=kembali" style="
            color: #000; 
            background-color: #fff; 
            padding: 5px 10px; 
            border-radius: 5px; 
            text-decoration: none; 
            margin-left: 15px;
            border: 1px solid #333;
        ">
            <i class="bi bi-box-arrow-right me-1"></i> Kembali ke Akun Admin
        </a>
    </div>
    <div style="height: 50px;"></div> 
    '; 
    // Spacer agar konten di bawahnya tidak tertutup banner
}
?>
<!-- ====================================================== -->
<!-- ### AKHIR KODE BANNER IMPERSONATE ### -->
<!-- ====================================================== -->

<div class="wrapper">
    <nav id="sidebar">
        <!-- ... existing code ... -->
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand text-decoration-none d-flex align-items-center">
                <img src="uploads/logo-aplikasi.png" alt="Logo">
                <span>Rapor Digital</span>
            </a>
        </div>
        
        <ul class="mcd-menu">
            <li>
                <a href="dashboard.php" class="<?php if ($current_page == 'dashboard.php') echo 'active'; ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <strong>Dashboard</strong>
                    <small>Halaman utama aplikasi</small>
                </a>
            </li>
            
            <?php if ($role == 'admin'): ?>
                <li class="sidebar-heading">Manajemen Master</li>
                <li><a href="pengguna_tampil.php" class="<?php if (in_array($current_page, ['pengguna_tampil.php', 'pengguna_tambah.php', 'pengguna_edit.php'])) echo 'active'; ?>"><i class="bi bi-people-fill"></i><strong>Kelola Pengguna</strong><small>Admin, Guru & Siswa</small></a></li>
                <li><a href="kelas_tampil.php" class="<?php if (in_array($current_page, ['kelas_tampil.php', 'kelas_tambah.php', 'kelas_edit.php', 'siswa_tampil.php'])) echo 'active'; ?>"><i class="bi bi-door-open-fill"></i><strong>Kelas & Siswa</strong><small>Manajemen data kelas</small></a></li>
                <li><a href="admin_kenaikan_kelas.php" class="<?php if ($current_page == 'admin_kenaikan_kelas.php') echo 'active'; ?>"><i class="bi bi-graph-up"></i><strong>Kenaikan Kelas</strong><small>Proses data kenaikan</small></a></li>
                <li><a href="mutasi_siswa_tampil.php" class="<?php if (in_array($current_page, ['mutasi_siswa_tampil.php', 'kelola_mutasi.php'])) echo 'active'; ?>"><i class="bi bi-arrows-expand"></i><strong>Mutasi Siswa</strong><small>Kelola siswa pindahan</small></a></li>
                <li><a href="admin_penetapan_guru_wali.php" class="<?php if ($current_page == 'admin_penetapan_guru_wali.php') echo 'active'; ?>"><i class="bi bi-person-check-fill"></i><strong>Guru Wali</strong><small>Tetapkan & Kelola</small></a></li>
                <li><a href="mapel_tampil.php" class="<?php if (in_array($current_page, ['mapel_tampil.php', 'tp_tampil.php'])) echo 'active'; ?>"><i class="bi bi-book-half"></i><strong>Mapel & TP</strong><small>Manajemen mata pelajaran</small></a></li>
                <li><a href="mapel_urutkan.php" class="<?php if ($current_page == 'mapel_urutkan.php') echo 'active'; ?>"><i class="bi bi-list-ol"></i><strong>Urutan Mapel</strong><small>Atur urutan di rapor</small></a></li>
                <li><a href="kokurikuler_tampil.php" class="<?php if (in_array($current_page, ['kokurikuler_tampil.php', 'kokurikuler_tambah.php'])) echo 'active'; ?>"><i class="bi bi-palette-fill"></i><strong>Kokurikuler</strong><small>Kegiatan & Dimensi</small></a></li>
                <li><a href="admin_ekskul.php" class="<?php if ($current_page == 'admin_ekskul.php') echo 'active'; ?>"><i class="bi bi-bicycle"></i><strong>Ekstrakurikuler</strong><small>Manajemen ekskul</small></a></li>
                <li><a href="pengaturan_tampil.php" class="<?php if ($current_page == 'pengaturan_tampil.php') echo 'active'; ?>"><i class="bi bi-gear-fill"></i><strong>Pengaturan</strong><small>Data & Info Sekolah</small></a></li>
                
                <li class="sidebar-heading">Laporan & Monitoring</li>
                <li><a href="admin_monitoring_catatan.php" class="<?php if ($current_page == 'admin_monitoring_catatan.php') echo 'active'; ?>"><i class="bi bi-person-video3"></i><strong>Monitoring GW</strong><small>Lihat catatan Guru Wali</small></a></li>
                <li><a href="admin_progres_penilaian.php" class="<?php if ($current_page == 'admin_progres_penilaian.php') echo 'active'; ?>"><i class="bi bi-graph-up-arrow"></i><strong>Progres Penilaian</strong><small>Pantau input nilai</small></a></li>
                <li><a href="admin_laporan_kelas.php" class="<?php if ($current_page == 'admin_laporan_kelas.php') echo 'active'; ?>"><i class="bi bi-printer-fill"></i><strong>Cetak Rapor</strong><small>Cetak rapor & leger</small></a></li>

            <?php elseif ($role == 'guru'): ?>
                <?php
                if (!isset($koneksi) && file_exists('koneksi.php')) { include 'koneksi.php'; }
                $id_guru_login = $_SESSION['id_guru'];
                $id_ta_aktif = 0;
                $q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
                if($d_ta_aktif = mysqli_fetch_assoc($q_ta_aktif)){ $id_ta_aktif = $d_ta_aktif['id_tahun_ajaran']; }
                
                $cek_mengajar = mysqli_query($koneksi, "SELECT 1 FROM guru_mengajar WHERE id_guru = $id_guru_login AND id_tahun_ajaran = $id_ta_aktif LIMIT 1");
                $is_pengampu = mysqli_num_rows($cek_mengajar) > 0;
                
                $cek_walas = mysqli_query($koneksi, "SELECT 1 FROM kelas WHERE id_wali_kelas = $id_guru_login AND id_tahun_ajaran = $id_ta_aktif LIMIT 1");
                $is_walas = mysqli_num_rows($cek_walas) > 0;

                $cek_pembina = mysqli_query($koneksi, "SELECT 1 FROM ekstrakurikuler WHERE id_pembina = $id_guru_login AND id_tahun_ajaran = $id_ta_aktif LIMIT 1");
                $is_pembina = mysqli_num_rows($cek_pembina) > 0;

                $cek_guru_wali = mysqli_query($koneksi, "SELECT 1 FROM siswa WHERE id_guru_wali = $id_guru_login AND status_siswa = 'Aktif' LIMIT 1");
                $is_guru_wali = mysqli_num_rows($cek_guru_wali) > 0;
                
                if ($is_pengampu || $is_guru_wali) { echo '<li class="sidebar-heading">Tugas Utama</li>'; }

                if ($is_guru_wali) { ?>
                    <li><a href="guru_wali_dashboard.php" class="<?php if (strpos($current_page, 'guru_wali') !== false) echo 'active'; ?>"><i class="bi bi-person-rolodex"></i><strong>Panel Guru Wali</strong><small>Bimbingan & Portofolio</small></a></li>
                <?php }
                
                if ($is_pengampu) { ?>
                    <li><a href="tp_guru_tampil.php" class="<?php if (strpos($current_page, 'tp_guru') !== false) echo 'active'; ?>"><i class="bi bi-card-checklist"></i><strong>Kelola TP Saya</strong><small>Tujuan Pembelajaran</small></a></li>
                    <li><a href="penilaian_tampil.php" class="<?php if (strpos($current_page, 'penilaian') !== false) echo 'active'; ?>"><i class="bi bi-journal-text"></i><strong>Bank Nilai Akademik</strong><small>Input nilai sumatif</small></a></li>
                    <li><a href="kokurikuler_pilih.php" class="<?php if (strpos($current_page, 'kokurikuler') !== false) echo 'active'; ?>"><i class="bi bi-award-fill"></i><strong>Asesmen Kokurikuler</strong><small>Input nilai projek</small></a></li>
                <?php }

                if ($is_pembina) { ?>
                    <li class="sidebar-heading">Pembina Ekstrakurikuler</li>
                    <li><a href="pembina_ekskul.php" class="<?php if ($current_page == 'pembina_ekskul.php') echo 'active'; ?>"><i class="bi bi-flag-fill"></i><strong>Tujuan Ekskul</strong><small>Kelola tujuan ekskul</small></a></li>
                    <li><a href="pembina_penilaian_ekskul.php" class="<?php if ($current_page == 'pembina_penilaian_ekskul.php') echo 'active'; ?>"><i class="bi bi-check2-circle"></i><strong>Input Penilaian</strong><small>Penilaian ekskul</small></a></li>
                <?php }
                if ($is_walas) { ?>
                    <li class="sidebar-heading">Wali Kelas</li>
                    <li><a href="walikelas_data_rapor.php" class="<?php if ($current_page == 'walikelas_data_rapor.php') echo 'active'; ?>"><i class="bi bi-person-lines-fill"></i><strong>Input Data Rapor</strong><small>Absensi & Catatan</small></a></li>
                    <li><a href="walikelas_identitas_siswa.php" class="<?php if ($current_page == 'walikelas_identitas_siswa.php') echo 'active'; ?>"><i class="bi bi-person-vcard"></i><strong>Identitas Siswa</strong><small>Kelola data siswa</small></a></li>
                    <li><a href="walikelas_daftarkan_ekskul.php" class="<?php if ($current_page == 'walikelas_daftarkan_ekskul.php') echo 'active'; ?>"><i class="bi bi-person-check-fill"></i><strong>Kelola Ekskul</strong><small>Kelola ekskul siswa</small></a></li>
                    <li><a href="walikelas_proses_rapor.php" class="<?php if ($current_page == 'walikelas_proses_rapor.php') echo 'active'; ?>"><i class="bi bi-pencil-square"></i><strong>Proses Intrakurikuler</strong><small>Proses nilai akhir</small></a></li>
                    <li><a href="walikelas_proses_kokurikuler.php" class="<?php if ($current_page == 'walikelas_proses_kokurikuler.php') echo 'active'; ?>"><i class="bi bi-chat-quote-fill"></i><strong>Proses Kokurikuler</strong><small>Proses nilai projek</small></a></li>
                    <li><a href="walikelas_cetak_rapor.php" class="<?php if ($current_page == 'walikelas_cetak_rapor.php') echo 'active'; ?>"><i class="bi bi-printer-fill"></i><strong>Cetak Rapor</strong><small>Cetak rapor & leger</small></a></li>
                <?php } ?>
            <?php endif; ?>

            <li class="logout-link">
                 <a href="logout.php">
                    <i class="bi bi-box-arrow-left"></i>
                    <strong>Logout</strong>
                    <small>Keluar dari aplikasi</small>
                </a>
            </li>
        </ul>
    </nav>

    <div id="content">
        <nav class="top-navbar navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-light me-3">
                    <i class="bi bi-list"></i>
                </button>
                
                <span class="navbar-text fw-bold text-dark d-none d-md-block">
                    <?php 
                        if ($role == 'admin') echo "Panel Administrator";
                        elseif ($role == 'guru') echo "Panel Guru";
                        elseif ($role == 'siswa') echo "Portal Siswa";
                   ?>
                </span>

                <ul class="navbar-nav ms-auto">
                    <!-- [BARU] Tombol Chat -->
                    <?php if ($role != 'admin'): // Sembunyikan untuk admin ?>
                    <li class="nav-item me-3 d-flex align-items-center">
                        <a href="chat.php" class="btn btn-outline-secondary rounded-circle position-relative" data-bs-toggle="tooltip" title="Pesan" data-bs-placement="bottom">
                            <i class="bi bi-chat-dots-fill"></i>
                            <span id="chat-notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none; border: 2px solid white;"></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <!-- [SELESAI BARU] -->

                    <li class="nav-item me-2 d-flex align-items-center">
                        <button class="btn btn-outline-secondary rounded-circle" type="button" data-bs-toggle="modal" data-bs-target="#pengembangModal" title="Info Pengembang" data-bs-placement="bottom">
                           <i class="bi bi-info-lg"></i>
                        </button>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo $foto_profil_path; ?>" alt="Foto Profil" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($nama_pengguna); ?></div>
                                <small class="text-muted text-capitalize"><?php echo htmlspecialchars($role); ?></small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <!-- [PERBAIKAN] Logika untuk link profil dinamis -->
                            <?php
                            // Tentukan halaman profil berdasarkan role
                            $link_profil = "profil_edit.php"; // Default untuk admin/guru
                            if ($role == 'siswa') {
                                $link_profil = "profil_siswa_edit.php";
                            }
                            ?>
                            <li><a class="dropdown-item" href="<?php echo $link_profil; ?>">Profil Saya</a></li>
                            <!-- [AKHIR PERBAIKAN] -->
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="main-content">
<div class="modal fade" id="pengembangModal" tabindex="-1" aria-labelledby="pengembangModalLabel" aria-hidden="true">
    <!-- ... existing code ... -->
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pengembangModalLabel"><i class="bi bi-person-badge me-2"></i> Profil Pengembang Aplikasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <img src="uploads/pengembang.jpg" class="rounded-circle profile-pic" alt="Foto Pengembang">
                    <h3 class="mt-3 mb-0">Angga Agus Kariyawan</h3>
                    <p class="text-muted">Guru & Pegiat Teknologi Pendidikan</p>
                </div>
                
                <div class="row">
                    <div class="col-md-5">
                        <h5><i class="bi bi-person-fill"></i> Tentang Saya</h5>
                        <p class="text-muted" style="text-align: justify;">
                            Seorang Pengajar Bahasa Inggris di SMP Negeri 3 Ngantang Satu Atap dengan minat tinggi terhadap teknologi, khususnya aplikasi dan desain multimedia. Menginisiasi transformasi digital di sekolah dan aktif dalam berbagai peran komunitas pendidikan.
                        </p>
                        
                        <h5><i class="bi bi-telephone-fill"></i> Hubungi Saya</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-whatsapp me-3"></i> +62 812 315 988 611
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-instagram me-3"></i> @angga_tenggek
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-geo-alt-fill me-3"></i> Ngantang, Kab. Malang
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-7">
                        <h5><i class="bi bi-briefcase-fill"></i> Riwayat & Peran</h5>
                        <ul class="list-group list-group-flush">
                             <li class="list-group-item">Pengajar Bahasa Inggris, SMPN 3 Ngantang Satu Atap (GTT 2007, PPPK 2022)</li>
                            <li class="list-group-item">Wakil Kepala Sekolah</li>
                            <li class="list-group-item">Ketua MGMP Bahasa Inggris SMP Kabupaten Malang</li>
                            <li class="list-group-item">Guru Penggerak Angkatan 2</li>
                            <li class="list-group-item">Koordinator & Pengajar Praktik PGP Angkatan 7 & 10</li>
                            <li class="list-group-item">Instruktur Nasional (Guru Pembelajar & PKB)</li>
                        </ul>

                        <h5 class="mt-4"><i class="bi bi-lightbulb-fill"></i> Karya Aplikasi</h5>
                        <p class="text-muted">
                            Selain aplikasi rapor ini, beberapa karya lain yang telah dikembangkan antara lain:
                            <br>
                            <span class="badge bg-secondary m-1">Web Sekolah & LMS</span>
                            <span class="badge bg-secondary m-1">PPDB Online</span>
                            <span class="badge bg-secondary m-1">CBT (Computer Based Test)</span>
                            <span class="badge bg-secondary m-1">Jurnal Mengajar Online</span>
                            <span class="badge bg-secondary m-1">E-Buku Induk</span>
                            <span class="badge bg-secondary m-1">Aplikasi Android (Exambrowser & Materi)</span>
                            <span class="badge bg-secondary m-1">Aplikasi Excel VBA (PAUS & Rapor)</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>