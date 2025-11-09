<?php
session_start();
include 'koneksi.php';

// Validasi role Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak. Anda bukan admin.");
}

$aksi = $_GET['aksi'] ?? '';

switch ($aksi) {
    case 'tambah':
        $nama_ekskul = $_POST['nama_ekskul'];
        $id_pembina = (int)$_POST['id_pembina'];

        // Ambil tahun ajaran aktif
        $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
        $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

        // Validasi sederhana
        if (empty($nama_ekskul) || empty($id_pembina)) {
            $_SESSION['pesan'] = "Gagal! Nama ekskul dan pembina tidak boleh kosong.";
        } else {
            // Gunakan prepared statement untuk keamanan
            $stmt = mysqli_prepare($koneksi, "INSERT INTO ekstrakurikuler (nama_ekskul, id_pembina, id_tahun_ajaran) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sii", $nama_ekskul, $id_pembina, $id_tahun_ajaran);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['pesan'] = "Ekstrakurikuler baru berhasil ditambahkan.";
            } else {
                $_SESSION['pesan'] = "Gagal menambahkan data. Error: " . mysqli_error($koneksi);
            }
        }
        break;

    case 'hapus':
        $id_ekskul = (int)$_GET['id'];
        
        // Prepared statement untuk menghapus
        $stmt = mysqli_prepare($koneksi, "DELETE FROM ekstrakurikuler WHERE id_ekskul = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_ekskul);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['pesan'] = "Ekstrakurikuler berhasil dihapus.";
        } else {
            $_SESSION['pesan'] = "Gagal menghapus data. Error: " . mysqli_error($koneksi);
        }
        break;
    
    // Kita akan menambahkan case 'update' di sini nanti
    
    default:
        $_SESSION['pesan'] = "Aksi tidak valid.";
        break;
}

// Redirect kembali ke halaman utama manajemen ekskul
header("Location: admin_ekskul.php");
exit();
?>