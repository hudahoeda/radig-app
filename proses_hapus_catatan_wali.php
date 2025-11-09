<?php
// Mulai session
session_start(); 

include 'koneksi.php';

// Cek hak akses guru
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    die("Akses tidak diizinkan.");
}

$id_guru_login = $_SESSION['id_guru'];
$id_catatan_hapus = null;
$id_siswa_redirect = null;

// Cek apakah metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validasi input
    if (isset($_POST['id_catatan']) && is_numeric($_POST['id_catatan']) && isset($_POST['id_siswa']) && is_numeric($_POST['id_siswa'])) {
        
        $id_catatan_hapus = (int)$_POST['id_catatan'];
        $id_siswa_redirect = (int)$_POST['id_siswa'];

        // Security Check: Pastikan guru ini adalah pemilik catatan
        // Tabel catatan_guru_wali punya id_guru_wali, jadi kita cek itu.
        
        $stmt_check = mysqli_prepare($koneksi, "SELECT id_guru_wali FROM catatan_guru_wali WHERE id_catatan = ?");
        mysqli_stmt_bind_param($stmt_check, "i", $id_catatan_hapus);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) == 1) {
            $catatan = mysqli_fetch_assoc($result_check);
            
            // Verifikasi apakah guru yang login adalah guru yang membuat catatan
            if ($catatan['id_guru_wali'] == $id_guru_login) {
                
                // Lanjutkan proses hapus
                $stmt_hapus = mysqli_prepare($koneksi, "DELETE FROM catatan_guru_wali WHERE id_catatan = ? AND id_guru_wali = ?");
                mysqli_stmt_bind_param($stmt_hapus, "ii", $id_catatan_hapus, $id_guru_login);
                
                if (mysqli_stmt_execute($stmt_hapus)) {
                    // Berhasil hapus
                    header("Location: guru_wali_catatan_siswa.php?id_siswa=" . $id_siswa_redirect . "&status=hapus_sukses");
                    exit;
                } else {
                    // Gagal eksekusi query hapus
                    header("Location: guru_wali_catatan_siswa.php?id_siswa=" . $id_siswa_redirect . "&status=hapus_gagal");
                    exit;
                }
                
            } else {
                // Gagal: Mencoba menghapus catatan milik guru lain
                header("Location: guru_wali_catatan_siswa.php?id_siswa=" . $id_siswa_redirect . "&status=hapus_gagal");
                exit;
            }
            
        } else {
            // Gagal: Catatan tidak ditemukan
            header("Location: guru_wali_catatan_siswa.php?id_siswa=" . $id_siswa_redirect . "&status=hapus_gagal");
            exit;
        }
        
    } else {
        // Gagal: Input tidak valid
        // Jika id_siswa tidak ada, redirect ke dashboard
        if ($id_siswa_redirect) {
             header("Location: guru_wali_catatan_siswa.php?id_siswa=" . $id_siswa_redirect . "&status=hapus_gagal");
             exit;
        } else {
             header("Location: guru_wali_dashboard.php?status=error");
             exit;
        }
    }
    
} else {
    // Jika bukan POST, redirect
    header("Location: guru_wali_dashboard.php");
    exit;
}

?>