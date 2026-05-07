<?php
// File: koneksi.php
// Konfigurasi koneksi MySQL. Nilai dapat diatur lewat environment untuk Docker/deploy.

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'rapor';
$pass = getenv('DB_PASSWORD') ?: 'rapor_secret';
$db   = getenv('DB_DATABASE') ?: 'raporsmp';

$koneksi = mysqli_connect($host, $user, $pass, $db, (int) $port);

if (!$koneksi) {
    die('Koneksi ke database gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($koneksi, 'utf8mb4');
date_default_timezone_set('Asia/Jakarta');

$APP_VERSION = 'v2.0.1';
