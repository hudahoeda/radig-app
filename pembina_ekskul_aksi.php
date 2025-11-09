<?php
session_start();
include 'koneksi.php';

// Validasi role Guru
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    die("Akses ditolak. Anda bukan guru.");
}

$id_pembina = $_SESSION['id_guru'];
$aksi = $_GET['aksi'] ?? '';

// Fungsi untuk mengecek apakah guru ini adalah pembina sah dari ekskul terkait
function cek_kewenangan_pembina($koneksi, $id_pembina, $id_ekskul) {
    $stmt = mysqli_prepare($koneksi, "SELECT id_ekskul FROM ekstrakurikuler WHERE id_ekskul = ? AND id_pembina = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id_ekskul, $id_pembina);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

switch ($aksi) {
    case 'tambah_tujuan':
        $id_ekskul = (int)$_POST['id_ekskul'];
        $semester = (int)$_POST['semester'];
        $deskripsi = trim($_POST['deskripsi_tujuan']);

        // KEAMANAN: Cek apakah guru ini berhak mengedit ekskul ini
        if (!cek_kewenangan_pembina($koneksi, $id_pembina, $id_ekskul)) {
            $_SESSION['pesan'] = "Error! Anda tidak memiliki wewenang untuk ekskul ini.";
            break;
        }

        if (!empty($deskripsi)) {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO ekskul_tujuan (id_ekskul, semester, deskripsi_tujuan) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iis", $id_ekskul, $semester, $deskripsi);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['pesan'] = "Tujuan pembelajaran berhasil ditambahkan.";
            } else {
                $_SESSION['pesan'] = "Gagal menambahkan tujuan.";
            }
        } else {
            $_SESSION['pesan'] = "Deskripsi tujuan tidak boleh kosong.";
        }
        break;

    case 'hapus_tujuan':
        $id_tujuan = (int)$_GET['id'];
        
        // KEAMANAN: Cek kewenangan sebelum menghapus
        $q_cek = mysqli_prepare($koneksi, "
            SELECT e.id_ekskul 
            FROM ekskul_tujuan et 
            JOIN ekstrakurikuler e ON et.id_ekskul = e.id_ekskul 
            WHERE et.id_tujuan_ekskul = ? AND e.id_pembina = ?
        ");
        mysqli_stmt_bind_param($q_cek, "ii", $id_tujuan, $id_pembina);
        mysqli_stmt_execute($q_cek);
        $result_cek = mysqli_stmt_get_result($q_cek);

        if (mysqli_num_rows($result_cek) > 0) {
            $stmt = mysqli_prepare($koneksi, "DELETE FROM ekskul_tujuan WHERE id_tujuan_ekskul = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_tujuan);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['pesan'] = "Tujuan pembelajaran berhasil dihapus.";
            } else {
                $_SESSION['pesan'] = "Gagal menghapus tujuan.";
            }
        } else {
            $_SESSION['pesan'] = "Error! Anda tidak memiliki wewenang untuk menghapus tujuan ini.";
        }
        break;

    default:
        $_SESSION['pesan'] = "Aksi tidak valid.";
        break;
}

header("Location: pembina_ekskul.php");
exit();
?>