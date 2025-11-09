<?php
// dashboard.php - File ini bertindak sebagai router
include 'koneksi.php';
include 'header.php';


// Ambil peran dari session
$role = $_SESSION['role'] ?? 'tamu';

// Tampilkan konten dashboard sesuai peran
if ($role == 'admin') {
    include 'dashboard_admin.php';
} elseif ($role == 'guru') {
    include 'dashboard_guru.php';
} elseif ($role == 'siswa') {
    include 'dashboard_siswa.php';
} else {
    // Jika ada peran lain atau tidak dikenal
    echo "<div class='container mt-4'><h1>Selamat Datang!</h1><p>Peran Anda tidak dikenali.</p></div>";
}

include 'footer.php';
?>