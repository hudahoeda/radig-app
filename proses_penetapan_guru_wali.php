<?php
session_start();
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    die("Akses tidak diizinkan.");
}

// Ambil id tahun ajaran untuk redirect kembali ke filter yang benar
$id_ta_redirect = isset($_POST['id_ta_redirect']) ? (int)$_POST['id_ta_redirect'] : null;
$redirect_url = "admin_penetapan_guru_wali.php" . ($id_ta_redirect ? "?id_ta=$id_ta_redirect" : "");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tetapkan_massal'])) {
    
    if (isset($_POST['id_guru_wali']) && isset($_POST['id_siswa']) && is_array($_POST['id_siswa'])) {
        
        $id_guru_wali_input = (int)$_POST['id_guru_wali'];
        $id_siswa_array = $_POST['id_siswa'];

        if (empty($id_siswa_array)) {
            $_SESSION['pesan'] = "{icon: 'warning', title: 'Gagal', text: 'Anda belum mencentang siswa manapun.'}";
            header("Location: $redirect_url");
            exit();
        }

        $id_siswa_clean = array_map('intval', $id_siswa_array);
        $id_siswa_list = implode(',', $id_siswa_clean);

        $id_guru_wali_db = ($id_guru_wali_input == 0) ? "NULL" : "'$id_guru_wali_input'";
        
        $query = "UPDATE siswa SET id_guru_wali = $id_guru_wali_db WHERE id_siswa IN ($id_siswa_list)";

        if (mysqli_query($koneksi, $query)) {
            $jumlah_siswa = count($id_siswa_clean);
             $_SESSION['pesan'] = "{
                icon: 'success',
                title: 'Berhasil!',
                text: 'Sebanyak $jumlah_siswa siswa telah berhasil diperbarui.'
            }";
        } else {
            $_SESSION['pesan'] = "{
                icon: 'error',
                title: 'Gagal',
                text: 'Terjadi kesalahan database: " . mysqli_error($koneksi) . "'
            }";
        }

    } else {
        $_SESSION['pesan'] = "{
            icon: 'warning',
            title: 'Gagal',
            text: 'Harap pilih Guru Wali dan centang setidaknya satu siswa.'
        }";
    }
} else {
    $_SESSION['pesan'] = "{
        icon: 'error',
        title: 'Akses Ditolak',
        text: 'Metode akses tidak valid.'
    }";
}

header("Location: $redirect_url");
exit();
?>

