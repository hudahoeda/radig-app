<?php
// --- MULAI DEBUGGING ---
// Tampilkan semua error agar kita tahu apa masalahnya
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- SELESAI DEBUGGING ---

session_start();

// 1. PERIKSA FILE KONEKSI
$koneksi_path = 'koneksi.php';
if (!file_exists($koneksi_path)) {
    // Jika file tidak ada, hentikan script dan beri pesan jelas
    die("Error Kritis: File koneksi di path '<b>$koneksi_path</b>' tidak ditemukan. Pastikan path dan nama file sudah benar.");
}
require $koneksi_path;

// 2. PERIKSA VARIABEL KONEKSI
// Ganti '$koneksi' di baris ini jika nama variabel di file Anda berbeda (misal: $conn, $db)
if (!isset($koneksi) || $koneksi === null) {
    die("Error Kritis: Variabel koneksi '<b>$koneksi</b>' tidak ditemukan di dalam file '<b>$koneksi_path</b>'.<br>Mungkin nama variabelnya berbeda (misal: $conn atau $db?)");
}
if (mysqli_connect_errno()) {
    die("Error Kritis: Gagal terhubung ke database. Pesan error: " . mysqli_connect_error());
}

// 3. AMBIL DATA SEKOLAH (DENGAN PENGECEKAN ERROR)
$nama_sekolah = 'Nama Sekolah Anda'; // Default
$logo_path = 'assets/img/default-logo.png'; // Default

$sql_sekolah = "SELECT nama_sekolah, logo_sekolah FROM sekolah WHERE id_sekolah = 1";
$query_sekolah = mysqli_query($koneksi, $sql_sekolah);

if (!$query_sekolah) {
    // Tampilkan error jika query gagal
    die("Error query database: " . mysqli_error($koneksi));
}

$data_sekolah = mysqli_fetch_assoc($query_sekolah);

if ($data_sekolah) {
    $nama_sekolah = $data_sekolah['nama_sekolah'] ?? $nama_sekolah;
    $logo_filename = $data_sekolah['logo_sekolah'] ?? null;

    if ($logo_filename && file_exists('uploads/' . $logo_filename)) {
        $logo_path = 'uploads/' . $logo_filename;
    }
}

// 4. AMBIL DATA UNTUK TESTIMONI (BARU)
$testimonials = [];
$default_avatar_guru = 'https://placehold.co/100x100/4A148C/FFFFFF?text=Guru';
$default_avatar_siswa = 'https://placehold.co/100x100/FFAB00/FFFFFF?text=Siswa';

// Ambil 2 Guru (yang punya foto)
$sql_guru = "SELECT nama_guru, foto_guru FROM guru WHERE role = 'guru' AND foto_guru IS NOT NULL AND foto_guru != '' ORDER BY RAND() LIMIT 2";
$query_guru = mysqli_query($koneksi, $sql_guru);
if ($query_guru) {
    while ($guru = mysqli_fetch_assoc($query_guru)) {
        $foto_path_guru = 'uploads/' . $guru['foto_guru'];
        $testimonials[] = [
            'nama' => $guru['nama_guru'],
            'foto' => (file_exists($foto_path_guru)) ? $foto_path_guru : $default_avatar_guru,
            'fallback' => $default_avatar_guru,
            'role' => 'Guru',
            'quote' => 'Radig sangat memudahkan saya memantau perkembangan siswa. Fitur portofolio digitalnya luar biasa!'
        ];
    }
}

// Ambil 2 Siswa (yang punya foto)
// Asumsi foto siswa juga ada di folder 'uploads/'
$sql_siswa = "SELECT nama_lengkap, foto_siswa FROM siswa WHERE status_siswa = 'Aktif' AND foto_siswa IS NOT NULL AND foto_siswa != '' ORDER BY RAND() LIMIT 2";
$query_siswa = mysqli_query($koneksi, $sql_siswa);
if ($query_siswa) {
    while ($siswa = mysqli_fetch_assoc($query_siswa)) {
         $foto_path_siswa = 'uploads/' . $siswa['foto_siswa'];
        $testimonials[] = [
            'nama' => $siswa['nama_lengkap'],
            'foto' => (file_exists($foto_path_siswa)) ? $foto_path_siswa : $default_avatar_siswa,
            'fallback' => $default_avatar_siswa,
            'role' => 'Siswa',
            'quote' => 'Saya jadi lebih mudah berkomunikasi dengan Guru Wali saya. Belajar jadi lebih terarah.'
        ];
    }
}

// Jika data minim, tambahkan fallback manual (agar desain tidak rusak)
if (count($testimonials) < 2) {
     $testimonials[] = [
            'nama' => 'Budi, S.Pd.',
            'foto' => $default_avatar_guru,
            'fallback' => $default_avatar_guru,
            'role' => 'Guru',
            'quote' => 'Aplikasi ini adalah terobosan untuk pendidikan modern. Sangat membantu rekapitulasi nilai.'
        ];
     $testimonials[] = [
            'nama' => 'Siti Aisyah',
            'foto' => $default_avatar_siswa,
            'fallback' => $default_avatar_siswa,
            'role' => 'Siswa',
            'quote' => 'Melihat nilai dan catatan guru jadi lebih transparan. Saya suka!'
        ];
}
// Acak testimoni agar guru/siswa tidak selalu berurutan
shuffle($testimonials);


// 5. CEK JIKA PENGGUNA SUDAH LOGIN
if (isset($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang di Radig - <?php echo htmlspecialchars($nama_sekolah); ?></title>

    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Perbaikan Path: "assets://" diubah menjadi "assets/" -->
    <link href="assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/poppins-font.css" rel="stylesheet">

    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/sweetalert2.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>

    <style>
        /* SKEMA WARNA BARU (Berwarna-warni) */
        :root {
            --primary-color: #00796B;   
            --secondary-color: #14c4b0; 
            --accent-1: #00796B;        /* Teal */
            --accent-2: #FFAB00;        /* Amber */
            --accent-3: #E64A19;        /* Deep Orange */
            --background-light: #F8F9FA;
            --text-dark: #212529;
            --text-muted: #6c757d;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --card-shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* --- NAVBAR --- */
        .navbar {
            background-color: #fff;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
        }

        /* --- HERO SECTION --- */
        .hero-section {
            background-color: #ffffff;
            padding: 140px 0 100px 0;
            min-height: 90vh;
            display: flex;
            align-items: center;
            text-align: center;
        }
        .hero-logo {
            max-height: 120px;
            height: auto;
            width: auto;
            margin-bottom: 2rem;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            /* Animasi Float */
            animation: float-anim 4s ease-in-out infinite;
        }
        .hero-section h1 {
            font-size: 2.8rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        .hero-section h2 {
            font-size: 2rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        .hero-section .lead {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-primary.btn-lg {
            padding: 14px 40px;
            font-size: 1.1rem;
        }
        /* Animasi Pulse untuk Tombol */
        .pulse-anim {
            animation: pulse-anim 2s infinite;
        }

        /* --- SECTION UMUM --- */
        .content-section {
            padding: 80px 0;
        }
        .section-title {
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 2.5rem;
            color: var(--text-dark);
        }
        .section-subtitle {
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            color: var(--text-muted);
            margin-bottom: 4rem;
            font-size: 1.1rem;
        }

        /* --- FITUR (WARNA-WARNI) --- */
        .feature-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            border-top: 5px solid transparent; /* Border atas untuk warna */
            border-bottom: 1px solid #eee;
            border-left: 1px solid #eee;
            border-right: 1px solid #eee;
        }
        .feature-card:hover {
             transform: translateY(-10px);
            box-shadow: var(--card-shadow-hover);
        }
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        .feature-card:hover .feature-icon {
            transform: rotate(-10deg) scale(1.1);
        }
        .feature-card h3 {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        /* Warna-warni untuk Fitur */
        .feature-card-1 { border-top-color: var(--accent-1); }
        .feature-card-1 h3 { color: var(--accent-1); }
        .feature-icon-1 { color: var(--accent-1); }

        .feature-card-2 { border-top-color: var(--accent-2); }
        .feature-card-2 h3 { color: var(--accent-2); }
        .feature-icon-2 { color: var(--accent-2); }
        
        .feature-card-3 { border-top-color: var(--accent-3); }
        .feature-card-3 h3 { color: var(--accent-3); }
        .feature-icon-3 { color: var(--accent-3); }

        /* --- WORKFLOW (WARNA-WARNI) --- */
        .workflow-card {
            background-color: #fff;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .workflow-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--card-shadow-hover);
        }
        .workflow-icon-bg {
            width: 80px;
            height: 80px;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            transition: all 0.3s ease;
        }
        .workflow-card:hover .workflow-icon-bg {
            transform: scale(1.1) rotate(10deg);
        }
        .workflow-icon {
            font-size: 2.5rem;
        }
        /* Warna-warni untuk Workflow */
        .workflow-card-1 .workflow-icon-bg { background-color: var(--accent-1); }
        .workflow-card-2 .workflow-icon-bg { background-color: var(--accent-2); }
        .workflow-card-3 .workflow-icon-bg { background-color: var(--accent-3); }
        .workflow-card-4 .workflow-icon-bg { background-color: var(--primary-color); }

        /* --- TESTIMONI --- */
        .testimonial-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .testimonial-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1.5rem;
            border: 4px solid var(--primary-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .testimonial-card .quote {
            font-style: italic;
            color: var(--text-muted);
            margin-bottom: 1rem;
            flex-grow: 1;
        }
        .testimonial-card .name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0;
        }
         .testimonial-card .role {
            font-size: 0.9rem;
            color: var(--secondary-color); /* Ganti warna role */
            font-weight: 500;
        }
        
        /* --- FOOTER --- */
        .footer {
            background-color: #343a40;
            color: #fff;
            padding: 40px 0 20px 0;
        }
        .footer p {
            color: rgba(255, 255, 255, 0.7);
        }

        /* --- MODAL --- */
        #loginModal .modal-content {
            border-radius: 1rem;
            border: none;
            box-shadow: var(--card-shadow-hover);
        }
        #loginModalLabel {
            font-weight: 600;
            color: var(--primary-color);
        }
        #loginModal .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(74, 20, 140, 0.25);
        }

        /* --- KEYFRAMES ANIMASI --- */
        @keyframes float-anim {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        @keyframes pulse-anim {
            0% { box-shadow: 0 0 0 0 rgba(74, 20, 140, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(74, 20, 140, 0); }
            100% { box-shadow: 0 0 0 0 rgba(74, 20, 140, 0); }
        }

        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo Sekolah" height="35" class="me-2">
                <span>Rapor Digital (Radig)</span>
            </a>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#loginModal">
                <i class="bi bi-box-arrow-in-right"></i> Masuk
            </button>
        </div>
    </nav>

    <!-- HERO SECTION BARU - Personal ke Sekolah -->
    <header class="hero-section d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Logo Sekolah DIBESARKAN dan di TENGAH -->
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo <?php echo htmlspecialchars($nama_sekolah); ?>" class="hero-logo reveal">
                    
                    <!-- H1 Baru -->
                    <h1 class="reveal" style="transition-delay: 0.1s;">
                        Selamat Datang di Portal Rapor Digital
                    </h1>
                    
                    <!-- H2 Baru - Nama Sekolah Dinamis -->
                    <h2 class="fw-normal mb-4 reveal" style="transition-delay: 0.2s;">
                        <?php echo htmlspecialchars($nama_sekolah); ?>
                    </h2>
                    
                    <p class="lead mb-4 reveal" style="transition-delay: 0.3s;">
                        Akses mudah dan terintegrasi untuk Guru, Siswa, dan Wali Murid.
                    </p>
                    <!-- Tombol dengan Animasi Pulse -->
                    <a href="#" class="btn btn-primary btn-lg shadow-sm reveal pulse-anim" data-bs-toggle="modal" data-bs-target="#loginModal" style="transition-delay: 0.4s;">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke Akun Anda
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main>
        <!-- SECTION FITUR BARU - 3 Kartu Berwarna -->
        <section id="keunggulan" class="content-section">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="section-title">Mengapa Menggunakan Radig?</h2>
                    <p class="section-subtitle">Platform kami dirancang untuk merevolusi cara sekolah Anda mengelola data akademik dan pendampingan siswa.</p>
                </div>
                <div class="row g-4">
                    <!-- Kartu 1 -->
                    <div class="col-lg-4 d-flex align-items-stretch reveal">
                        <div class="feature-card feature-card-1">
                            <!-- Ikon Baru -->
                            <i class="bi bi-heart-pulse-fill feature-icon feature-icon-1"></i>
                            <h3>Pendampingan Holistik</h3>
                            <p class="text-muted">Fitur Guru Wali memungkinkan pendampingan jangka panjang, membangun portofolio digital komprehensif untuk setiap siswa dari masuk hingga lulus.</p>
                        </div>
                    </div>
                    <!-- Kartu 2 -->
                    <div class="col-lg-4 d-flex align-items-stretch reveal" style="transition-delay: 0.1s;">
                         <div class="feature-card feature-card-2">
                            <!-- Ikon Baru -->
                            <i class="bi bi-chat-quote-fill feature-icon feature-icon-2"></i>
                            <h3>Komunikasi Terintegrasi</h3>
                            <p class="text-muted">Jembatani komunikasi Guru Wali dan siswa melalui chat privat. Memudahkan konsultasi, bimbingan, dan pemantauan kapanpun, di manapun.</p>
                        </div>
                    </div>
                    <!-- Kartu 3 -->
                    <div class="col-lg-4 d-flex align-items-stretch reveal" style="transition-delay: 0.2s;">
                         <div class="feature-card feature-card-3">
                            <!-- Ikon Baru -->
                            <i class="bi bi-clipboard-check-fill feature-icon feature-icon-3"></i>
                            <h3>Penilaian Komprehensif</h3>
                            <p class="text-muted">Kelola nilai intra, kokurikuler , dan ekstrakurikuler dalam satu platform terpadu. Memberikan gambaran utuh perkembangan siswa.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION WORKFLOW - Ikon Berwarna -->
        <section id="workflow" class="content-section bg-white">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-title">Alur Kerja Kolaboratif</h2>
                    <p class="section-subtitle">Setiap peran memiliki tugas yang jelas dan terstruktur, menghasilkan rapor yang akurat dan komprehensif.</p>
                </div>
                <div class="row g-4">
                    <div class="col-lg-3 d-flex align-items-stretch reveal" style="transition-delay: 0.1s;">
                        <div class="workflow-card workflow-card-1">
                            <div class="workflow-icon-bg">
                                <i class="bi bi-person-gear workflow-icon"></i>
                            </div>
                            <h4 class="fw-bold">1. Admin</h4>
                            <p class="text-muted">Mengelola data master (siswa, guru, kelas) dan menetapkan Guru Wali untuk setiap siswa baru.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 d-flex align-items-stretch reveal" style="transition-delay: 0.2s;">
                        <div class="workflow-card workflow-card-2">
                             <div class="workflow-icon-bg">
                                <i class="bi bi-journal-text workflow-icon"></i>
                            </div>
                            <h4 class="fw-bold">2. Guru Mapel</h4>
                            <p class="text-muted">Fokus menginput nilai sumatif per Tujuan Pembelajaran (TP) dan asesmen kokurikuler (projek).</p>
                        </div>
                    </div>
                    <div class="col-lg-3 d-flex align-items-stretch reveal" style="transition-delay: 0.3s;">
                        <div class="workflow-card workflow-card-3">
                             <div class="workflow-icon-bg">
                                <i class="bi bi-person-rolodex workflow-icon"></i>
                            </div>
                            <h4 class="fw-bold">3. Guru Wali</h4>
                            <p class="text-muted">Mendampingi siswa, mengisi portofolio digital, dan berkomunikasi melalui chat secara berkelanjutan.</p>
                        </div>
                    </div>
                     <div class="col-lg-3 d-flex align-items-stretch reveal" style="transition-delay: 0.4s;">
                        <div class="workflow-card workflow-card-4">
                             <div class="workflow-icon-bg">
                                <i class="bi bi-person-check-fill workflow-icon"></i>
                            </div>
                            <h4 class="fw-bold">4. Wali Kelas</h4>
                            <p class="text-muted">Melengkapi data absensi, catatan akhir semester, dan melakukan finalisasi sebelum rapor dicetak.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION TESTIMONI BARU - Data Dinamis -->
        <section id="testimoni" class="content-section">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="section-title">Apa Kata Pengguna?</h2>
                    <!-- Judul personalisasi -->
                    <p class="section-subtitle">Testimoni nyata dari guru dan siswa di <?php echo htmlspecialchars($nama_sekolah); ?>.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php 
                    $delay = 0;
                    // Tampilkan maksimal 4 testimoni
                    foreach (array_slice($testimonials, 0, 4) as $testi): 
                        // Script fallback jika foto error
                        $onerror_script = "this.onerror=null;this.src='" . $testi['fallback'] . "';";
                    ?>
                    <div class="col-md-6 col-lg-5 d-flex align-items-stretch reveal" style="transition-delay: <?php echo $delay; ?>s;">
                        <div class="testimonial-card">
                            <img src="<?php echo htmlspecialchars($testi['foto']); ?>" alt="<?php echo htmlspecialchars($testi['nama']); ?>" class="testimonial-img" onerror="<?php echo $onerror_script; ?>">
                            <p class="quote">"<?php echo htmlspecialchars($testi['quote']); ?>"</p>
                            <h5 class="name mb-0"><?php echo htmlspecialchars($testi['nama']); ?></h5>
                            <span class="role"><?php echo htmlspecialchars($testi['role']); ?></span>
                        </div>
                    </div>
                    <?php 
                    $delay += 0.1;
                    endforeach; 
                    ?>
                </div>
            </div>
        </section>
    </main>
    
    <footer class="footer">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Aplikasi Rapor Digital (Radig) | <?php echo htmlspecialchars($nama_sekolah); ?>
                <!-- [BARU] Menampilkan Versi Aplikasi dari koneksi.php -->
                <?php if(isset($APP_VERSION)) echo '<span class="badge bg-light text-dark ms-1">' . htmlspecialchars($APP_VERSION) . '</span>'; ?>
            </p>
        </div>
    </footer>

    <!-- Modal Login -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel"><i class="bi bi-person-circle"></i> Login Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="proses_login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Script JS untuk Animasi dan Notifikasi -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animasi untuk elemen saat di-scroll ke viewport
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.reveal').forEach(el => {
                observer.observe(el);
            });

            // Logika SweetAlert untuk notifikasi login
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('pesan')) {
                const pesan = urlParams.get('pesan');
                if (pesan === 'gagal') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Gagal',
                        text: 'Username atau password yang Anda masukkan salah.',
                    });
                } else if (pesan === 'logout') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Logout Berhasil',
                        text: 'Anda telah berhasil keluar dari sistem.',
                    });
                } else if (pesan === 'belum_login') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Akses Ditolak',
                        text: 'Anda harus login terlebih dahulu.',
                    });
                }
                
                // Membersihkan URL dari parameter 'pesan' agar tidak muncul lagi saat di-refresh
                window.history.replaceState(null, null, window.location.pathname);
            }
        });
    </script>
</body>
</html>