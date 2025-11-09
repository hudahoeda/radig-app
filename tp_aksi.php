<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

//======================================================================
// --- AKSI TAMBAH OLEH GURU ---
//======================================================================
if ($aksi == 'tambah') {
    if ($_SESSION['role'] != 'guru') {
        die("Akses ditolak. Anda bukan Guru.");
    }
    
    $id_guru_pembuat = $_SESSION['id_guru'];
    $id_mapel = $_POST['id_mapel'];
    $kode_tp = $_POST['kode_tp'];
    $deskripsi = $_POST['deskripsi_tp'];
    $semester = $_POST['semester'];
    $id_tahun_ajaran = $_POST['id_tahun_ajaran'];
    $kelas_berlaku = isset($_POST['kelas_berlaku']) ? $_POST['kelas_berlaku'] : [];
    $fase = 'D';

    mysqli_begin_transaction($koneksi);
    try {
        $query_tp = "INSERT INTO tujuan_pembelajaran (id_mapel, id_guru_pembuat, fase, kode_tp, deskripsi_tp, semester, id_tahun_ajaran) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_tp = mysqli_prepare($koneksi, $query_tp);
        mysqli_stmt_bind_param($stmt_tp, "iisssii", $id_mapel, $id_guru_pembuat, $fase, $kode_tp, $deskripsi, $semester, $id_tahun_ajaran);
        mysqli_stmt_execute($stmt_tp);

        $id_tp_baru = mysqli_insert_id($koneksi);

        if (!empty($kelas_berlaku)) {
            $query_kelas = "INSERT INTO tp_kelas (id_tp, id_kelas) VALUES (?, ?)";
            $stmt_kelas = mysqli_prepare($koneksi, $query_kelas);
            foreach ($kelas_berlaku as $id_kelas) {
                mysqli_stmt_bind_param($stmt_kelas, "ii", $id_tp_baru, $id_kelas);
                mysqli_stmt_execute($stmt_kelas);
            }
        }
        
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = "Tujuan Pembelajaran baru berhasil ditambahkan.";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = "Gagal menyimpan TP: " . $e->getMessage();
    }

    header("location: tp_guru_tampil.php");
    exit();
}

//======================================================================
// --- AKSI UPDATE OLEH GURU ---
//======================================================================
elseif ($aksi == 'update') {
    if ($_SESSION['role'] != 'guru') {
        die("Akses ditolak.");
    }
    
    $id_tp = (int)$_POST['id_tp'];
    $id_guru_login = $_SESSION['id_guru'];
    $id_mapel = $_POST['id_mapel'];
    $kode_tp = $_POST['kode_tp'];
    $deskripsi = $_POST['deskripsi_tp'];
    $semester = $_POST['semester'];
    $kelas_berlaku = isset($_POST['kelas_berlaku']) ? $_POST['kelas_berlaku'] : [];

    mysqli_begin_transaction($koneksi);
    try {
        // Update data utama TP (pastikan guru hanya bisa update TP miliknya)
        $query_update_tp = "UPDATE tujuan_pembelajaran SET id_mapel=?, kode_tp=?, deskripsi_tp=?, semester=? WHERE id_tp=? AND id_guru_pembuat=?";
        $stmt_update_tp = mysqli_prepare($koneksi, $query_update_tp);
        mysqli_stmt_bind_param($stmt_update_tp, "isssii", $id_mapel, $kode_tp, $deskripsi, $semester, $id_tp, $id_guru_login);
        mysqli_stmt_execute($stmt_update_tp);

        // Hapus penugasan kelas yang lama
        $stmt_delete_kelas = mysqli_prepare($koneksi, "DELETE FROM tp_kelas WHERE id_tp = ?");
        mysqli_stmt_bind_param($stmt_delete_kelas, "i", $id_tp);
        mysqli_stmt_execute($stmt_delete_kelas);

        // Masukkan penugasan kelas yang baru
        if (!empty($kelas_berlaku)) {
            $query_kelas_baru = "INSERT INTO tp_kelas (id_tp, id_kelas) VALUES (?, ?)";
            $stmt_kelas_baru = mysqli_prepare($koneksi, $query_kelas_baru);
            foreach ($kelas_berlaku as $id_kelas) {
                mysqli_stmt_bind_param($stmt_kelas_baru, "ii", $id_tp, $id_kelas);
                mysqli_stmt_execute($stmt_kelas_baru);
            }
        }
        
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = "Tujuan Pembelajaran berhasil diperbarui.";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = "Gagal memperbarui TP: " . $e->getMessage();
    }

    header("location: tp_guru_tampil.php");
    exit();
}

//======================================================================
// --- AKSI HAPUS (Bisa oleh Admin atau Guru) ---
//======================================================================
elseif ($aksi == 'hapus') {
    $id_tp = (int)$_GET['id'];
    $id_guru_login = $_SESSION['id_guru'];

    // Ambil data TP untuk validasi kepemilikan
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_guru_pembuat FROM tujuan_pembelajaran WHERE id_tp=?");
    mysqli_stmt_bind_param($stmt_cek, "i", $id_tp);
    mysqli_stmt_execute($stmt_cek);
    $data_tp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));

    // Validasi: Admin bisa hapus semua, Guru hanya bisa hapus miliknya
    if ($_SESSION['role'] == 'admin' || ($data_tp && $data_tp['id_guru_pembuat'] == $id_guru_login)) {
        // Karena ada ON DELETE CASCADE di database, data di `tp_kelas` akan ikut terhapus
        $stmt_hapus = mysqli_prepare($koneksi, "DELETE FROM tujuan_pembelajaran WHERE id_tp=?");
        mysqli_stmt_bind_param($stmt_hapus, "i", $id_tp);
        mysqli_stmt_execute($stmt_hapus);
        $_SESSION['pesan'] = "Tujuan Pembelajaran berhasil dihapus.";
    } else {
        $_SESSION['pesan_error'] = "Gagal: Anda tidak berhak menghapus TP ini.";
    }
    
    if ($_SESSION['role'] == 'admin') {
        $id_mapel_redirect = isset($_GET['id_mapel']) ? $_GET['id_mapel'] : '';
        if ($id_mapel_redirect) {
            header("location: tp_tampil.php?id_mapel=" . $id_mapel_redirect);
        } else {
            header("location: mapel_tampil.php");
        }
    } else {
        header("location: tp_guru_tampil.php");
    }
    exit();
}

// Jika tidak ada aksi yang cocok
else {
    header("location: dashboard.php");
    exit();
}
?>