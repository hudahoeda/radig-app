<?php
// File: koneksi.php
// Konfigurasi untuk terhubung ke database MySQL

// --- SESUAIKAN DENGAN PENGATURAN HOSTING ANDA ---
// Nilai bisa dikonfigurasi lewat variabel environment (untuk Docker)
$host = getenv('DB_HOST') ?: "localhost"; // Biasanya 'localhost' jika web dan database di server yang sama
$port = getenv('DB_PORT') ?: "3306";
$user = getenv('DB_USERNAME') ?: "root"; // Ganti dengan username database yang Anda buat
$pass = getenv('DB_PASSWORD') ?: "11223344"; // Ganti dengan password database
$db   = getenv('DB_DATABASE') ?: "raporsmp"; // Ganti dengan nama database yang Anda buat
// --------------------------------------------------

// Membuat koneksi
$koneksi = mysqli_connect($host, $user, $pass, $db, (int) $port);

// Memeriksa koneksi
if (!$koneksi) {
    // Jika koneksi gagal, hentikan script dan tampilkan pesan error
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// Paksa koneksi menggunakan UTF-8 untuk karakter Indonesia
mysqli_set_charset($koneksi, 'utf8mb4');

// Mengatur zona waktu default ke Waktu Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// --- [BARU] Pengaturan Versi Aplikasi ---
// Definisikan versi aplikasi Anda di sini.
// Anda bisa mengubah "1.0.0" ini kapan saja saat ada pembaruan.
$APP_VERSION = "v2.0.1";
// ----------------------------------------

?>
