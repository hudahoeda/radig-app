<?php
session_start();
include 'koneksi.php';

if ($_SESSION['role'] != 'guru' || $_SERVER['REQUEST_METHOD'] != 'POST') {
    die("Akses tidak diizinkan.");
}

// Ambil data dari form
$id_siswa = (int)$_POST['id_siswa'];
$id_guru_wali = (int)$_POST['id_guru_wali'];
$tanggal_catatan = mysqli_real_escape_string($koneksi, $_POST['tanggal_catatan']);
$kategori_catatan = mysqli_real_escape_string($koneksi, $_POST['kategori_catatan']);
$isi_catatan = mysqli_real_escape_string($koneksi, $_POST['isi_catatan']);

// Pastikan guru yang menyimpan adalah guru wali yang sah
if ($id_guru_wali != $_SESSION['id_guru']) {
     die("Operasi tidak valid.");
}

// Validasi data tidak boleh kosong
if (empty($id_siswa) || empty($tanggal_catatan) || empty($kategori_catatan) || empty($isi_catatan)) {
    $_SESSION['pesan'] = "{icon: 'warning', title: 'Gagal', text: 'Semua kolom harus diisi.'}";
    header("Location: guru_wali_catatan_siswa.php?id_siswa=$id_siswa");
    exit();
}

// Query untuk insert data
$query = "INSERT INTO catatan_guru_wali (id_siswa, id_guru_wali, tanggal_catatan, kategori_catatan, isi_catatan) 
          VALUES ('$id_siswa', '$id_guru_wali', '$tanggal_catatan', '$kategori_catatan', '$isi_catatan')";

if (mysqli_query($koneksi, $query)) {
    $_SESSION['pesan'] = "{icon: 'success', title: 'Berhasil', text: 'Catatan baru telah disimpan.'}";
} else {
    $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan: " . mysqli_error($koneksi) . "'}";
}

header("Location: guru_wali_catatan_siswa.php?id_siswa=$id_siswa");
exit();
?>