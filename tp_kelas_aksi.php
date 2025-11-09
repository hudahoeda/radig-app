<?php
session_start();
include 'koneksi.php';

if (!isset($_POST['simpan']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$id_kelas = (int)$_POST['id_kelas'];
$id_mapel = (int)$_POST['id_mapel'];
$id_guru = (int)$_POST['id_guru'];
$tp_ids = $_POST['tp_ids'] ?? [];

// Mulai transaksi database untuk keamanan data
mysqli_begin_transaction($koneksi);

try {
    // 1. Atur Guru Mengajar
    $stmt_check = mysqli_prepare($koneksi, "SELECT id_guru_mengajar FROM guru_mengajar WHERE id_kelas=? AND id_mapel=?");
    mysqli_stmt_bind_param($stmt_check, "ii", $id_kelas, $id_mapel);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);

    if (mysqli_num_rows($result_check) > 0) {
        // Jika sudah ada, UPDATE
        $stmt_update_guru = mysqli_prepare($koneksi, "UPDATE guru_mengajar SET id_guru=? WHERE id_kelas=? AND id_mapel=?");
        mysqli_stmt_bind_param($stmt_update_guru, "iii", $id_guru, $id_kelas, $id_mapel);
        mysqli_stmt_execute($stmt_update_guru);
    } else {
        // Jika belum ada, INSERT
        $stmt_insert_guru = mysqli_prepare($koneksi, "INSERT INTO guru_mengajar (id_guru, id_mapel, id_kelas, id_tahun_ajaran) SELECT ?, ?, ?, id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif' LIMIT 1");
        mysqli_stmt_bind_param($stmt_insert_guru, "iii", $id_guru, $id_mapel, $id_kelas);
        mysqli_stmt_execute($stmt_insert_guru);
    }

    // 2. Atur Tujuan Pembelajaran
    // Hapus dulu semua TP untuk mapel dan kelas ini
    $stmt_delete_tp = mysqli_prepare($koneksi, "DELETE tk FROM tp_kelas tk JOIN tujuan_pembelajaran tp ON tk.id_tp = tp.id_tp WHERE tk.id_kelas=? AND tp.id_mapel=?");
    mysqli_stmt_bind_param($stmt_delete_tp, "ii", $id_kelas, $id_mapel);
    mysqli_stmt_execute($stmt_delete_tp);

    // Masukkan kembali TP yang dipilih
    if (!empty($tp_ids)) {
        $stmt_insert_tp = mysqli_prepare($koneksi, "INSERT INTO tp_kelas (id_tp, id_kelas) VALUES (?, ?)");
        foreach ($tp_ids as $id_tp) {
            $id_tp_int = (int)$id_tp;
            mysqli_stmt_bind_param($stmt_insert_tp, "ii", $id_tp_int, $id_kelas);
            mysqli_stmt_execute($stmt_insert_tp);
        }
    }

    // Jika semua berhasil, commit transaksi
    mysqli_commit($koneksi);
    $_SESSION['pesan'] = "Perubahan pembelajaran untuk kelas berhasil disimpan.";

} catch (mysqli_sql_exception $exception) {
    // Jika ada error, batalkan semua perubahan
    mysqli_rollback($koneksi);
    $_SESSION['error'] = "Terjadi kesalahan: " . $exception->getMessage();
}

header("Location: tp_kelas_tampil.php?id_kelas=" . $id_kelas);
exit();
?>