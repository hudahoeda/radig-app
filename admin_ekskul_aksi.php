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
        
        // [BUG FIX] Hapus data secara cascading dari bawah ke atas
        // 1. Ambil semua id_peserta_ekskul yang terhubung dengan ekskul ini
        $q_peserta = mysqli_query($koneksi, "SELECT id_peserta_ekskul FROM ekskul_peserta WHERE id_ekskul = $id_ekskul");
        if ($q_peserta && mysqli_num_rows($q_peserta) > 0) {
            $list_peserta = [];
            while ($row = mysqli_fetch_assoc($q_peserta)) {
                $list_peserta[] = $row['id_peserta_ekskul'];
            }
            $in_clause = implode(',', $list_peserta);

            // 2. Hapus nilai dan kehadiran peserta tersebut
            mysqli_query($koneksi, "DELETE FROM ekskul_penilaian WHERE id_peserta_ekskul IN ($in_clause)");
            mysqli_query($koneksi, "DELETE FROM ekskul_kehadiran WHERE id_peserta_ekskul IN ($in_clause)");

            // 3. Hapus peserta
            mysqli_query($koneksi, "DELETE FROM ekskul_peserta WHERE id_ekskul = $id_ekskul");
        }

        // 4. Hapus tujuan ekskul (TP)
        mysqli_query($koneksi, "DELETE FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul");

        // 5. Terakhir, hapus ekstrakurikulernya
        $stmt = mysqli_prepare($koneksi, "DELETE FROM ekstrakurikuler WHERE id_ekskul = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_ekskul);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['pesan'] = "Ekstrakurikuler beserta seluruh datanya berhasil dihapus.";
        } else {
            $_SESSION['pesan'] = "Gagal menghapus data. Error: " . mysqli_error($koneksi);
        }
        break;
    
    case 'update':
        $id_ekskul = (int)$_POST['id_ekskul'];
        $nama_ekskul = $_POST['nama_ekskul'];
        $id_pembina = (int)$_POST['id_pembina'];

        if (empty($nama_ekskul) || empty($id_pembina) || $id_ekskul <= 0) {
            $_SESSION['pesan'] = "Gagal! Data tidak lengkap.";
        } else {
            $stmt = mysqli_prepare($koneksi, "UPDATE ekstrakurikuler SET nama_ekskul = ?, id_pembina = ? WHERE id_ekskul = ?");
            mysqli_stmt_bind_param($stmt, "sii", $nama_ekskul, $id_pembina, $id_ekskul);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['pesan'] = "Ekstrakurikuler berhasil diperbarui.";
            } else {
                $_SESSION['pesan'] = "Gagal memperbarui data. Error: " . mysqli_error($koneksi);
            }
        }
        break;
    default:
        $_SESSION['pesan'] = "Aksi tidak valid.";
        break;
}

// Redirect kembali ke halaman utama manajemen ekskul
header("Location: admin_ekskul.php");
exit();
?>