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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS untuk Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript untuk Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        :root {
            /* Palet Warna Anda */
            --primary-color: #26a69a; /* Teal */
            --primary-gradient: linear-gradient(135deg, #26a69a 0%, #00897b 100%);
            --secondary-color: #00796b; /* Teal Gelap */
            
            /* Sidebar Variables */
            --sidebar-bg: #004d40;
            --sidebar-text: rgba(255, 255, 255, 0.85);
            --sidebar-text-active: #ffffff;
            --sidebar-bg-hover: #00695C;
            --sidebar-bg-active: #00796b;
            
            /* General Variables */
            --background-light: #f4f7f6;
            --text-dark: #333333; /* Hitam Pekat */
            --text-muted: #6c757d;
            --border-color: #e0e0e0;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --navbar-height: 80px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            font-size: 0.95rem;
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        /* --- SIDEBAR STYLE (TIDAK DIUBAH) --- */
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
        #sidebar.active { margin-left: -260px; }
        
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
            overflow-y: auto;
            flex-grow: 1;
        }
        .mcd-menu li { position: relative; }
        .mcd-menu li a {
            display: block;
            text-decoration: none;
            padding: 15px 20px;
            color: var(--sidebar-text);
            height: 60px;
            position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        .mcd-menu li a i {
            float: left;
            font-size: 1.4rem;
            margin: 0 15px 0 0;
            line-height: 30px;
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

        .mcd-menu li:hover > a {
            background-color: var(--sidebar-bg-hover);
            color: var(--sidebar-text-active);
        }
        .mcd-menu li:hover > a i { animation: moveFromTop 300ms ease-in-out; }
        .mcd-menu li:hover a strong { animation: moveFromLeft 300ms ease-in-out; }
        .mcd-menu li:hover a small { animation: moveFromRight 300ms ease-in-out; }

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
            content: ""; position: absolute; top: 42%; left: 0;
            border-left: 5px solid var(--primary-color);
            border-top: 5px solid transparent; border-bottom: 5px solid transparent;
        }
        .mcd-menu li a.active:after {
            content: ""; position: absolute; top: 42%; right: 0;
            border-right: 5px solid var(--primary-color);
            border-top: 5px solid transparent; border-bottom: 5px solid transparent;
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

        @keyframes moveFromTop { from { transform: translateY(200%); } to { transform: translateY(0%); } }
        @keyframes moveFromLeft { from { transform: translateX(200%); } to { transform: translateX(0%); } }
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

        /* --- [UPDATED] MODERN NAVBAR & BANNER STYLES --- */
        
        /* 1. Navbar Container */
        .top-navbar { 
            padding: 1rem 2rem; 
            background: #ffffff; /* Solid White agar teks hitam jelas */
            border-bottom: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        /* 2. Brand Text / Page Title */
        .navbar-brand-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            letter-spacing: 0.5px;
            font-size: 1.25rem;
            margin-left: 10px;
            /* Fallback color */
            color: var(--primary-color);
        }

        /* 3. Navigation Buttons (Hamburger, Chat, Info) */
        .nav-btn-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px; /* Lebih rounded */
            background: #f8f9fa; /* Light gray bg */
            color: #495057; /* Dark gray icon */
            border: 1px solid #e9ecef;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            text-decoration: none;
        }

        .nav-btn-icon:hover {
            background: #e0f2f1; /* Hint of teal */
            color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(38, 166, 154, 0.15);
        }
        
        .nav-btn-icon.active-chat {
             background: #e0f2f1;
             color: var(--primary-color);
        }

        /* 4. User Profile Dropdown Trigger */
        .user-profile-wrapper {
            padding: 6px 8px 6px 6px;
            border-radius: 50px;
            background: #ffffff;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            cursor: pointer;
            text-decoration: none !important;
            margin-left: 15px;
        }

        .user-profile-wrapper:hover, .user-profile-wrapper[aria-expanded="true"] {
            background: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(38, 166, 154, 0.1);
        }

        .user-avatar-frame {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            padding: 2px;
            background: var(--primary-gradient); /* Teal Gradient Ring */
            margin-right: 12px;
        }

        .user-avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
        }

        .user-info-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            margin-right: 15px;
        }
        
        .user-name { 
            font-weight: 700; 
            font-size: 0.95rem; 
            color: #212529 !important; /* Force Black */
        } 
        .user-role { 
            font-size: 0.75rem; 
            color: var(--primary-color); 
            font-weight: 500;
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        /* 5. Dropdown Menu Modern */
        .dropdown-menu-animate {
            border: 0;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            border-radius: 16px;
            margin-top: 15px !important;
            animation: dropdownFade 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
            padding: 8px;
            background-color: #ffffff;
            min-width: 220px;
        }
        
        .dropdown-item {
            padding: 12px 16px;
            font-size: 0.9rem;
            color: #333 !important;
            border-radius: 10px;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background-color: #e0f2f1; /* Teal lembut */
            color: var(--secondary-color) !important;
            transform: translateX(5px);
        }

        .dropdown-item.text-danger:hover {
            background-color: #fff5f5;
            color: #dc3545 !important;
        }

        .dropdown-header-custom {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 8px;
            text-align: center;
            border: 1px dashed #ced4da;
        }

        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* 6. Impersonate Banner Styles */
        .impersonate-wrapper {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2000;
            width: auto;
            max-width: 90%;
            animation: slideDown 0.5s ease-out;
        }

        .impersonate-card {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(245, 124, 0, 0.4);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(5px);
        }

        .impersonate-text {
            font-weight: 600;
            font-size: 0.9rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .btn-impersonate-back {
            background: white;
            color: #f57c00;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .btn-impersonate-back:hover {
            background: #fff3e0;
            color: #e65100;
            transform: scale(1.05);
        }
        
        @keyframes slideDown {
            from { top: -100px; opacity: 0; }
            to { top: 20px; opacity: 1; }
        }

        /* Spacer agar konten tidak tertutup fixed banner jika ada */
        .impersonate-spacer { height: 80px; }

        @media (max-width: 768px) {
            #sidebar { margin-left: -260px; }
            #sidebar.active { margin-left: 0; }
            #content { padding-left: 0; }
            .top-navbar { padding: 0.8rem 1rem; }
            .main-content { padding: 1rem; }
            .user-info-text { display: none; } /* Hide user text on mobile */
            .user-avatar-frame { margin-right: 0; }
            .user-profile-wrapper { padding: 4px; margin-left: 10px; }
        }
        
        /* Modal Styles */
        #pengembangModal .modal-header { background-color: var(--sidebar-bg); color: white; }
        #pengembangModal .profile-pic { width: 150px; height: 150px; object-fit: cover; border: 5px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        #pengembangModal .list-group-item { border: none; padding-left: 0; padding-right: 0; }
        #pengembangModal .list-group-item i { width: 25px; text-align: center; color: var(--primary-color); }
        #pengembangModal h5 { font-weight: 600; color: var(--secondary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 8px; margin-top: 1.5rem; }
    </style>
</head>
<body>

<!-- ====================================================== -->
<!-- ### BANNER IMPERSONATE MODERN ### -->
<!-- ====================================================== -->
<?php
// Cek apakah ini adalah sesi penyamaran (impersonate)
if (isset($_SESSION['admin_asal_id'])) {
    echo '
    <div class="impersonate-wrapper">
        <div class="impersonate-card">
            <i class="bi bi-incognito fs-4"></i>
            <div class="impersonate-text">
                Mode Penyamaran Aktif
            </div>
            <a href="admin_aksi.php?aksi=kembali" class="btn-impersonate-back">
                <i class="bi bi-arrow-left-circle-fill me-1"></i> KEMBALI ADMIN
            </a>
        </div>
    </div>
    <!-- Spacer untuk menurunkan konten agar tidak tertutup banner -->
    <div class="impersonate-spacer"></div>
    '; 
}
?>
<!-- ====================================================== -->
<!-- ### AKHIR BANNER IMPERSONATE ### -->
<!-- ====================================================== -->

<div class="wrapper">
    <nav id="sidebar">
        <!-- ... LOGIKA SIDEBAR TETAP SAMA ... -->
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
                    <li><a href="walikelas_proses_rapor.php" class="<?php if ($current_page == 'walikelas_proses_rapor.php') echo 'active'; ?>"><i class="bi bi-pencil-square"></i><strong>Pantau Nilai</strong><small>Memantau Proggres Nilai</small></a></li>
                    <li><a href="walikelas_proses_kokurikuler.php" class="<?php if ($current_page == 'walikelas_proses_kokurikuler.php') echo 'active'; ?>"><i class="bi bi-chat-quote-fill"></i><strong>Proses Kokurikuler</strong><small>Proses nilai projek</small></a></li>
                    <li><a href="walikelas_cetak_rapor.php" class="<?php if ($current_page == 'walikelas_cetak_rapor.php') echo 'active'; ?>"><i class="bi bi-printer-fill"></i><strong>Cetak Rapor</strong><small>Cetak rapor & leger</small></a></li>
                <?php } ?>
            <?php elseif ($role == 'siswa'): ?>
                 <li class="sidebar-heading">Menu Siswa</li>
                 <li><a href="siswa_lihat_nilai.php" class="<?php if ($current_page == 'siswa_lihat_nilai.php') echo 'active'; ?>"><i class="bi bi-clipboard2-data-fill menu-icon"></i><div class="menu-text"><strong>Lihat Nilai</strong><small>Nilai Formatif & Sumatif</small></div></a></li>
                 <li><a href="siswa_lihat_aktivitas.php" class="<?php if ($current_page == 'siswa_lihat_aktivitas.php') echo 'active'; ?>"><i class="bi bi-award-fill"></i><div class="menu-text"><strong>Lihat Aktivitas</strong><small>Kokurikuler & Ekstrakurikuler</small></div></a></li>
                 <li><a href="rapor_pdf.php?id_siswa=<?php echo $_SESSION['id_siswa'] ?? 0; ?>" target="_blank"><i class="bi bi-file-earmark-pdf-fill menu-icon"></i><div class="menu-text"><strong>Download Rapor</strong><small>Unduh rapor semester</small></div></a></li>
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
        <!-- [MODIFIKASI] NAVBAR SUPER MODERN -->
        <nav class="top-navbar">
            <div class="container-fluid d-flex align-items-center justify-content-between p-0">
                
                <div class="d-flex align-items-center">
                    <!-- Tombol Sidebar Toggle -->
                    <button type="button" id="sidebarCollapse" class="nav-btn-icon me-3">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    
                    <!-- Judul Halaman -->
                    <span class="navbar-brand-text d-none d-md-block">
                        <?php 
                            if ($role == 'admin') echo "Administrator Panel";
                            elseif ($role == 'guru') echo "Workspace Guru";
                            elseif ($role == 'siswa') echo "Portal Akademik Siswa";
                        ?>
                    </span>
                </div>

                <div class="d-flex align-items-center">
                    <!-- Tombol Chat -->
                    <?php if ($role != 'admin'): ?>
                    <a href="chat.php" class="nav-btn-icon me-3 position-relative" data-bs-toggle="tooltip" title="Pesan" data-bs-placement="bottom">
                        <i class="bi bi-chat-text-fill fs-5"></i>
                        <span id="chat-notification-badge" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="display: none;">
                            <span class="visually-hidden">Pesan baru</span>
                        </span>
                    </a>
                    <?php endif; ?>

                    <!-- Tombol Info -->
                    <button class="nav-btn-icon me-3 d-none d-sm-flex" type="button" data-bs-toggle="modal" data-bs-target="#pengembangModal" title="Info Pengembang">
                       <i class="bi bi-info-circle-fill fs-5"></i>
                    </button>

                    <!-- Profile Dropdown -->
                    <div class="dropdown">
                        <a class="user-profile-wrapper" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-frame">
                                <img src="<?php echo $foto_profil_path; ?>" alt="Foto Profil" class="user-avatar-img">
                            </div>
                            <div class="user-info-text d-none d-md-flex">
                                <span class="user-name"><?php echo htmlspecialchars($nama_pengguna); ?></span>
                                <span class="user-role"><?php echo htmlspecialchars($role); ?></span>
                            </div>
                            <i class="bi bi-chevron-down small text-muted d-none d-md-block me-2"></i>
                        </a>
                        
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-animate" aria-labelledby="navbarDropdown">
                            <li>
                                <div class="dropdown-header-custom">
                                    <small class="text-muted d-block" style="font-size: 0.7rem;">ACCOUNT</small>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($nama_pengguna); ?></span>
                                </div>
                            </li>
                            <?php
                            $link_profil = "profil_edit.php";
                            if ($role == 'siswa') {
                                $link_profil = "profil_siswa_edit.php";
                            }
                            ?>
                            <li><a class="dropdown-item" href="<?php echo $link_profil; ?>"><i class="bi bi-person-gear me-2 text-primary"></i> Pengaturan Profil</a></li>
                            <li class="d-sm-none"><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#pengembangModal"><i class="bi bi-info-circle me-2 text-info"></i> Info Pengembang</a></li>
                            <li><hr class="dropdown-divider my-2"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Keluar Aplikasi</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <main class="main-content">

<!-- MODAL INFO PENGEMBANG (TIDAK DIUBAH) -->
<div class="modal fade" id="pengembangModal" tabindex="-1" aria-labelledby="pengembangModalLabel" aria-hidden="true">
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