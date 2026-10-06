<?php
// --- LOGIKA BACKEND (JANGAN DIHAPUS) ---
session_start();
// Jika sudah login, lempar ke dashboard
if (isset($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'koneksi.php';

// 1. AMBIL DATA SEKOLAH
$nama_sekolah = 'Aplikasi Rapor Digital';
$logo_path = 'uploads/logo-aplikasi.png';

// Cek koneksi sebelum query
if (isset($koneksi)) {
    $q_sekolah = mysqli_query($koneksi, "SELECT nama_sekolah, logo_sekolah FROM sekolah LIMIT 1");
    if ($q_sekolah && mysqli_num_rows($q_sekolah) > 0) {
        $d_sekolah = mysqli_fetch_assoc($q_sekolah);
        $nama_sekolah = $d_sekolah['nama_sekolah'];
        if (!empty($d_sekolah['logo_sekolah']) && file_exists('uploads/'.$d_sekolah['logo_sekolah'])) {
            $logo_path = 'uploads/'.$d_sekolah['logo_sekolah'];
        }
    }

    // 2. AMBIL DATA STATISTIK SEDERHANA (Untuk Tampilan Hero)
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif' LIMIT 1");
    $id_ta_aktif = ($row = mysqli_fetch_assoc($q_ta)) ? $row['id_tahun_ajaran'] : 0;

    $count_siswa = mysqli_fetch_assoc(mysqli_query($koneksi, "
        SELECT COUNT(s.id_siswa) as c
        FROM siswa s
        JOIN kelas k ON s.id_kelas = k.id_kelas
        WHERE s.status_siswa='Aktif' AND k.id_tahun_ajaran='$id_ta_aktif'
    "))['c'] ?? 0;

    $count_guru = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM guru WHERE role='guru'"))['c'] ?? 0;

    $count_kelas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM kelas WHERE id_tahun_ajaran='$id_ta_aktif'"))['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Belajar | <?php echo htmlspecialchars($nama_sekolah); ?></title>
    <!-- Favicon Dinamis dari Logo Sekolah -->
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logo_path); ?>?v=<?php echo time(); ?>">
    
    <!-- Fonts: Quicksand untuk kesan membulat, hangat, dan ramah -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">

    <style>
        :root {
            /* Warna Utama: Teal (Pilihan Anda) */
            --primary: #009688; /* Teal klasik */
            --primary-light: #4db6ac;
            --primary-dark: #00796b;
            --primary-soft: #e0f2f1; /* Sangat lembut untuk background */

            /* Warna Aksen: Lembut & Hangat */
            --accent: #ffb703; /* Kuning/Orange hangat untuk tombol CTA */
            --accent-soft: #ffe8cc;

            /* Warna Netral */
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-body: #fafdfc; /* Off-white dengan sedikit sentuhan hijau/biru */
        }

        body {
            font-family: 'Quicksand', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* --- BENTUK ORGANIK (Gelombang SVG di Background) --- */
        .bg-wave-top {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            z-index: -1;
            opacity: 0.6;
        }

        /* --- NAVBAR --- */
        .navbar {
            padding: 1.5rem 0;
            transition: all 0.3s ease;
        }
        .navbar.scrolled {
            background: rgba(250, 253, 252, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 150, 136, 0.05);
            padding: 0.8rem 0;
        }
        .navbar-brand img {
            height: 45px;
            border-radius: 50%; /* Membuat logo sekolah membulat */
            border: 2px solid var(--primary-light);
            padding: 2px;
            background: white;
        }
        .navbar-brand span {
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 1.2rem;
            margin-left: 10px;
        }

        /* --- TOMBOL --- */
        .btn-custom-primary {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 6px 15px rgba(0, 150, 136, 0.2);
        }
        .btn-custom-primary:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 150, 136, 0.3);
        }

        .btn-custom-accent {
            background-color: var(--accent);
            color: #5c3d00;
            border: none;
            border-radius: 50px;
            padding: 15px 35px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 6px 15px rgba(255, 183, 3, 0.3);
        }
        .btn-custom-accent:hover {
            background-color: #f5a600;
            color: #5c3d00;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 25px rgba(255, 183, 3, 0.4);
        }

        /* --- PLACEHOLDER CANVA (Sangat Penting) --- */
        .canva-placeholder {
            width: 100%;
            border-radius: 30px; /* Sudut membulat organik */
            background-color: rgba(224, 242, 241, 0.5); /* Warna dasar soft teal */
            border: 3px dashed var(--primary-light);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        /* Efek hover pada placeholder agar interaktif */
        .canva-placeholder:hover {
            background-color: rgba(224, 242, 241, 0.8);
            border-style: solid;
        }
        .canva-placeholder::after {
            content: "Area Ilustrasi Canva\A (Background Transparan)";
            white-space: pre;
            text-align: center;
            position: absolute;
            z-index: 1;
        }
        /* Jika gambar benar-benar dimasukkan nanti, kita timpa CSS ini via HTML inline style atau img tag */
        .canva-img-target {
            width: 100%;
            height: auto;
            position: relative;
            z-index: 2;
            object-fit: contain;
            filter: drop-shadow(0 15px 25px rgba(0,0,0,0.05)); /* Bayangan lembut untuk PNG transparan */
        }

        .hero-placeholder { height: 450px; }
        .feature-placeholder { height: 350px; }

        /* --- HERO SECTION --- */
        .hero-section {
            padding: 120px 0 80px;
            position: relative;
            z-index: 1;
        }
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
        }
        .hero-subtitle {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 2.5rem;
            line-height: 1.7;
            font-weight: 500;
        }

        /* --- STATISTIK --- */
        .stats-wrapper {
            background: white;
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 150, 136, 0.08);
            margin-top: -40px;
            position: relative;
            z-index: 5;
            border: 2px solid var(--primary-soft);
        }
        .stat-item h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0;
        }
        .stat-item p {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 0;
        }

        /* --- FEATURES SECTION --- */
        .features-section {
            padding: 100px 0;
        }
        .feature-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }
        .feature-desc {
            font-size: 1.1rem;
            color: var(--text-light);
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }

        .blob-bg {
            position: absolute;
            z-index: -1;
            opacity: 0.1;
        }

        /* --- FOOTER --- */
        footer {
            background-color: var(--primary-soft);
            padding: 60px 0 30px;
            color: var(--primary-dark);
            border-top-left-radius: 50px;
            border-top-right-radius: 50px;
        }

        /* --- MODAL LOGIN --- */
        .modal-content {
            border-radius: 30px;
            border: none;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }
        .modal-header {
            background-color: var(--primary-soft);
            border-bottom: none;
            padding: 30px 30px 15px;
        }
        .modal-body {
            padding: 30px;
        }
        .form-control, .form-select {
            border-radius: 15px;
            padding: 12px 20px;
            border: 2px solid #eee;
            background-color: #fcfcfc;
            font-weight: 500;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(77, 182, 172, 0.1);
        }

        /* Responsive Tweaks */
        @media (max-width: 991px) {
            .hero-title { font-size: 2.5rem; }
            .hero-section { text-align: center; padding-top: 150px; }
            .hero-placeholder { margin-top: 3rem; }
            .features-section .row { text-align: center; }
            .feature-placeholder { margin-bottom: 2rem; }
        }
    </style>
</head>
<body>

    <!-- Ornamen Gelombang Background (Lembut) -->
    <svg class="bg-wave-top" viewBox="0 0 1440 320" xmlns="http://www.w3.org/2000/svg">
        <path fill="#e0f2f1" fill-opacity="1" d="M0,128L48,138.7C96,149,192,171,288,181.3C384,192,480,192,576,170.7C672,149,768,107,864,117.3C960,128,1056,192,1152,208C1248,224,1344,192,1392,176L1440,160L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
    </svg>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo Sekolah" onerror="this.onerror=null; this.src='assets/img/logo_default.png'">
                <span class="d-none d-sm-block"><?php echo htmlspecialchars($nama_sekolah); ?></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list fs-1 text-primary"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link fw-bold text-dark" href="#">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link fw-bold text-dark" href="#fitur">Keunggulan</a></li>
                </ul>
                <div class="d-flex mt-3 mt-lg-0">
                    <button class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk Portal
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <!-- Teks Hero -->
                <div class="col-lg-6" data-aos="fade-right" data-aos-duration="1000">
                    <h1 class="hero-title">Rumah Belajar &<br>Bertumbuh Bersama</h1>
                    <p class="hero-subtitle">
                        Selamat datang di ruang digital keluarga besar <b><?php echo htmlspecialchars($nama_sekolah); ?></b>.
                        Kami percaya setiap anak unik. Mari pantau perkembangan akademik dan karakter mereka dengan penuh kasih sayang melalui portal ini.
                    </p>
                    <button class="btn btn-custom-accent" data-bs-toggle="modal" data-bs-target="#loginModal">
                        Mulai Jelajahi Rapor <i class="bi bi-arrow-right-circle ms-2"></i>
                    </button>
                </div>

                <!-- PLACEHOLDER CANVA 1: HERO -->
                <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1200">
                    <div>
                        <img src="assets/img/ilustrasi-fitur.png" class="canva-img-target" alt="Ilustrasi Utama">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistik Section -->
    <section class="container position-relative">
        <div class="stats-wrapper" data-aos="fade-up" data-aos-duration="800">
            <div class="row text-center g-4">
                <div class="col-md-4">
                    <div class="stat-item">
                        <h3><?php echo number_format($count_siswa); ?></h3>
                        <p>Siswa Aktif</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item border-start border-end border-light">
                        <h3><?php echo number_format($count_guru); ?></h3>
                        <p>Guru Pembimbing</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <h3><?php echo number_format($count_kelas); ?></h3>
                        <p>Ruang Kelas</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Fitur / Cerita Section (Zigzag Layout) -->
    <section id="fitur" class="features-section position-relative">
        <!-- Ornamen Lembut -->
        <svg class="blob-bg" style="top: 10%; right: -5%; width: 400px;" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
          <path fill="#4db6ac" d="M47.7,-60.7C62.8,-49.2,76.8,-36.5,82,-20.5C87.2,-4.5,83.5,14.8,73.1,30.3C62.7,45.8,45.7,57.5,28.2,64.3C10.7,71.1,-7.3,73.1,-24.5,68.6C-41.7,64.1,-58.1,53,-68.8,38C-79.5,23,-84.5,4.1,-80.7,-13.4C-76.9,-30.9,-64.3,-47,-50.2,-58.5C-36.1,-70,-20.5,-76.9,-2.8,-73.5C14.9,-70,29.8,-56.3,47.7,-60.7Z" transform="translate(100 100)"></path>
        </svg>

        <div class="container">
            <!-- Fitur 1: Gambar Kiri, Teks Kanan -->
            <div class="row align-items-center mb-5 pb-5">
                <div class="col-lg-5 order-2 order-lg-1" data-aos="fade-right">
                    <!-- PLACEHOLDER CANVA 2 -->
                    <div>
                        <img src="assets/img/ilustrasi-utama.png" class="canva-img-target" alt="Ilustrasi Evaluasi">
                    </div>
                </div>
                <div class="col-lg-6 offset-lg-1 order-1 order-lg-2 mb-4 mb-lg-0" data-aos="fade-left">
                    <h2 class="feature-title">Evaluasi yang Membangun</h2>
                    <p class="feature-desc">
                        Kami tidak hanya menilai angka, tetapi menghargai proses. Melalui portal ini, orang tua dapat melihat laporan nilai akademik, catatan wali kelas, hingga perkembangan ekstrakurikuler anak dengan tampilan yang ramah dan mudah dipahami.
                    </p>
                    <ul class="list-unstyled fw-medium" style="color: var(--text-dark);">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Detail nilai per mata pelajaran</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Rekapitulasi absensi transparan</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Laporan PTS & Semester</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 2: Teks Kiri, Gambar Kanan -->
            <div class="row align-items-center mt-5">
                <div class="col-lg-6 mb-4 mb-lg-0" data-aos="fade-right">
                    <h2 class="feature-title">Sinergi Guru & Orang Tua</h2>
                    <p class="feature-desc">
                        Pendidikan adalah kolaborasi. Aplikasi ini mempermudah guru dalam menginput nilai dengan sistem terpusat, dan membantu orang tua tetap terhubung dengan perkembangan anak di sekolah.
                    </p>
                    <ul class="list-unstyled fw-medium" style="color: var(--text-dark);">
                        <li class="mb-2"><i class="bi bi-heart-fill text-primary me-2"></i> Input nilai yang mudah bagi Guru</li>
                        <li class="mb-2"><i class="bi bi-heart-fill text-primary me-2"></i> Cetak rapor satu klik</li>
                        <li class="mb-2"><i class="bi bi-heart-fill text-primary me-2"></i> Keamanan privasi data siswa terjaga</li>
                    </ul>
                </div>
                <div class="col-lg-5 offset-lg-1" data-aos="fade-left">
                    <!-- PLACEHOLDER CANVA 3 -->
                    <div>
                        <img src="assets/img/ilustrasi-utama.png" class="canva-img-target" alt="Ilustrasi Sinergi">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Hangat -->
    <footer>
        <div class="container text-center">
            <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($nama_sekolah); ?></h4>
            <p class="mb-4">Mendidik dengan hati, menginspirasi sepenuh jiwa.</p>
            <div class="d-flex justify-content-center gap-3 mb-4">
                <a href="#" class="text-primary-dark"><i class="bi bi-instagram fs-4"></i></a>
                <a href="#" class="text-primary-dark"><i class="bi bi-facebook fs-4"></i></a>
                <a href="#" class="text-primary-dark"><i class="bi bi-youtube fs-4"></i></a>
            </div>
            <hr class="border-secondary opacity-25">
            <p class="mb-0 fs-6">&copy; <?php echo date('Y'); ?> Rapor Digital. Dibuat dengan cinta untuk pendidikan Indonesia.</p>
        </div>
    </footer>

    <!-- MODAL LOGIN (Modern 2-Column Layout) -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content overflow-hidden">
                <div class="row g-0">
                    <!-- Left Side: Branding / Canva Placeholder -->
                    <div class="col-md-5 d-none d-md-flex flex-column align-items-center justify-content-center p-4 text-center" style="background: linear-gradient(135deg, var(--primary-light), var(--primary)); color: white;">
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo Sekolah" onerror="this.onerror=null; this.src='assets/img/logo_default.png'" style="height: 80px; margin-bottom: 20px; border-radius: 50%; background: white; padding: 5px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($nama_sekolah); ?></h4>
                        <p class="opacity-75 mb-4" style="font-size: 0.9rem;">Portal terpadu untuk kemajuan pendidikan anak bangsa.</p>

                        <!-- Illustration -->
                        <div>
                            <img src="assets/img/ilustrasi-login.png" class="canva-img-target" alt="Login Illustration" style="max-height: 200px;">
                        </div>
                    </div>

                    <!-- Right Side: Login Form -->
                    <div class="col-md-7">
                        <div class="modal-header d-flex flex-column align-items-center text-center p-4 pb-0" style="background: transparent;">
                            <button type="button" class="btn-close position-absolute top-0 end-0 m-4" data-bs-dismiss="modal" aria-label="Close"></button>
                            <!-- Mobile logo fallback (only visible on mobile) -->
                            <img src="<?php echo htmlspecialchars($logo_path); ?>" class="d-md-none mb-3" alt="Logo Sekolah" onerror="this.onerror=null; this.src='assets/img/logo_default.png'" style="height: 60px; border-radius: 50%; background: white; padding: 3px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">

                            <h4 class="modal-title fw-bold text-primary-dark" id="loginModalLabel">Selamat Datang Kembali</h4>
                            <p class="text-muted mb-0 fs-6">Silakan masuk menggunakan akun Anda</p>
                        </div>
                        <div class="modal-body p-4 pt-3">
                            <form action="proses_login.php" method="POST" id="formLogin">
                                <div class="mb-3">
                                    <label for="role" class="form-label fw-bold text-dark"><i class="bi bi-person-badge me-2 text-primary"></i>Masuk Sebagai</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="" disabled selected>Pilih Peran Anda...</option>
                                        <option value="siswa">Siswa / Orang Tua</option>
                                        <option value="guru">Guru Mata Pelajaran</option>
                                        <option value="walikelas">Wali Kelas</option>
                                        <option value="pembina">Pembina Ekstrakurikuler</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="username" class="form-label fw-bold text-dark"><i class="bi bi-person me-2 text-primary"></i>Username / NISN</label>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username..." required autocomplete="off">
                                </div>
                                <div class="mb-4">
                                    <label for="password" class="form-label fw-bold text-dark"><i class="bi bi-key me-2 text-primary"></i>Kata Sandi</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control border-end-0" id="password" name="password" placeholder="Masukkan kata sandi..." required>
                                        <button class="btn btn-outline-secondary border-start-0 bg-white" type="button" id="togglePassword">
                                            <i class="bi bi-eye-slash" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="d-grid mt-2">
                                    <button type="submit" class="btn btn-custom-primary btn-lg" id="btnLogin">Masuk Sekarang</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/sweetalert2.all.min.js"></script>

    <script>
        // Inisialisasi Animasi AOS
        AOS.init({
            once: true,
            offset: 50
        });

        // Efek Navbar Transparan ke Solid
        $(window).scroll(function() {
            if ($(document).scrollTop() > 50) {
                $('.navbar').addClass('scrolled');
            } else {
                $('.navbar').removeClass('scrolled');
            }
        });

        // Toggle Tampilkan Password
        $('#togglePassword').click(function() {
            const passwordInput = $('#password');
            const icon = $('#toggleIcon');

            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            }
        });

        // SweetAlert untuk notifikasi dari backend
        <?php if(isset($_SESSION['login_error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Mohon Maaf',
                text: '<?php echo addslashes($_SESSION['login_error']); ?>',
                confirmButtonColor: '#009688',
                confirmButtonText: 'Coba Lagi'
            });
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['pesan'])): ?>
            Swal.fire({
                icon: 'info',
                title: 'Pemberitahuan',
                text: '<?php echo addslashes($_SESSION['pesan']); ?>',
                confirmButtonColor: '#009688'
            });
            <?php unset($_SESSION['pesan']); ?>
        <?php endif; ?>
    </script>
</body>
</html>