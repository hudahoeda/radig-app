<?php
session_start();
include 'koneksi.php';

// Validasi peran, hanya guru yang bisa melakukan aksi di file ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    $_SESSION['pesan'] = "'Akses Ditolak', 'Anda tidak memiliki izin untuk melakukan aksi ini.', 'error'";
    header("location: dashboard.php");
    exit();
}

$aksi = $_REQUEST['aksi'] ?? '';
$id_guru = (int)$_SESSION['id_guru'];

switch ($aksi) {
    //======================================================================
    // AKSI UNTUK MENAMBAH TP BARU
    //======================================================================
    case 'tambah':
        $id_mapel = (int)$_POST['id_mapel'];
        $kode_tp = $_POST['kode_tp'];
        $deskripsi = $_POST['deskripsi_tp'];
        $semester = (int)$_POST['semester'];
        $id_tahun_ajaran = (int)$_POST['id_tahun_ajaran'];
        $fase = 'D'; // Asumsi Fase D

        if (empty($id_mapel) || empty($deskripsi) || empty($semester) || empty($id_tahun_ajaran)) {
            $_SESSION['pesan'] = "'Gagal', 'Semua kolom wajib diisi.', 'error'";
            header("location: tp_guru_tambah.php");
            exit();
        }

        $query_tp = "INSERT INTO tujuan_pembelajaran (id_mapel, id_guru_pembuat, fase, kode_tp, deskripsi_tp, semester, id_tahun_ajaran) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_tp = mysqli_prepare($koneksi, $query_tp);
        mysqli_stmt_bind_param($stmt_tp, "iisssii", $id_mapel, $id_guru, $fase, $kode_tp, $deskripsi, $semester, $id_tahun_ajaran);
        
        if (mysqli_stmt_execute($stmt_tp)) {
            $_SESSION['pesan'] = "'Berhasil', 'Tujuan Pembelajaran baru berhasil ditambahkan.', 'success'";
        } else {
            $_SESSION['pesan'] = "'Error', 'Gagal menyimpan data TP.', 'error'";
        }
        header("location: tp_guru_tampil.php");
        exit();

    //======================================================================
    // AKSI UNTUK MENUGASKAN TP KE KELAS (INDIVIDU DARI MODAL)
    //======================================================================
    case 'tugaskan_tp':
        $id_tp = (int)$_POST['id_tp'];
        $kelas_dipilih = $_POST['id_kelas'] ?? [];

        // Hapus penugasan lama dengan validasi kepemilikan guru
        $query_delete = "DELETE tk FROM tp_kelas tk
                         JOIN tujuan_pembelajaran tp ON tk.id_tp = tp.id_tp
                         WHERE tk.id_tp = ? AND tp.id_guru_pembuat = ?";
        $stmt_delete = mysqli_prepare($koneksi, $query_delete);
        mysqli_stmt_bind_param($stmt_delete, "ii", $id_tp, $id_guru);
        mysqli_stmt_execute($stmt_delete);

        // Masukkan penugasan baru
        if (!empty($kelas_dipilih)) {
            $query_insert = "INSERT INTO tp_kelas (id_tp, id_kelas) VALUES (?, ?)";
            $stmt_insert = mysqli_prepare($koneksi, $query_insert);
            foreach ($kelas_dipilih as $id_kelas) {
                $id_kelas_int = (int)$id_kelas; 
                mysqli_stmt_bind_param($stmt_insert, "ii", $id_tp, $id_kelas_int);
                mysqli_stmt_execute($stmt_insert);
            }
        }
        
        $_SESSION['pesan'] = "'Berhasil', 'Penugasan TP ke kelas berhasil diperbarui.', 'success'";
        header("location: tp_guru_tampil.php");
        exit();

    //======================================================================
    // [BARU] AKSI UNTUK MENUGASKAN BANYAK TP KE BANYAK KELAS
    //======================================================================
    case 'tugaskan_massal':
        $tp_ids = $_POST['tp_ids'] ?? [];
        $kelas_ids = $_POST['id_kelas_tujuan'] ?? [];

        if (empty($tp_ids) || empty($kelas_ids)) {
             $_SESSION['pesan'] = "'Peringatan', 'Tidak ada TP atau Kelas yang dipilih.', 'warning'";
            header('location: tp_guru_tampil.php');
            exit();
        }

        // Sanitasi data
        $tp_ids_sanitized = array_map('intval', $tp_ids);
        $kelas_ids_sanitized = array_map('intval', $kelas_ids);

        // Siapkan query
        // INSERT IGNORE akan melewati data yang sudah ada (mencegah error duplikat)
        $query_insert_massal = "INSERT IGNORE INTO tp_kelas (id_tp, id_kelas) VALUES (?, ?)";
        $stmt_insert_massal = mysqli_prepare($koneksi, $query_insert_massal);

        $berhasil_ditugaskan = 0;
        
        // Loop untuk setiap TP yang dipilih
        foreach ($tp_ids_sanitized as $id_tp) {
            // Loop untuk setiap KELAS yang dipilih
            foreach ($kelas_ids_sanitized as $id_kelas) {
                // Kita tidak perlu validasi kepemilikan guru di sini
                // karena TP ID sudah didapat dari tp_guru_tampil.php
                // yang HANYA menampilkan TP milik guru tsb.
                mysqli_stmt_bind_param($stmt_insert_massal, "ii", $id_tp, $id_kelas);
                if (mysqli_stmt_execute($stmt_insert_massal)) {
                    $berhasil_ditugaskan += mysqli_stmt_affected_rows($stmt_insert_massal);
                }
            }
        }
        
        $total_tp = count($tp_ids_sanitized);
        $total_kelas = count($kelas_ids_sanitized);
        $_SESSION['pesan'] = "'Berhasil', '{$total_tp} TP berhasil ditugaskan ke {$total_kelas} kelas. ({$berhasil_ditugaskan} relasi baru dibuat)', 'success'";
        header('location: tp_guru_tampil.php');
        exit();


    //======================================================================
    // AKSI UNTUK MENGHAPUS SATU TP
    //======================================================================
    case 'hapus':
        $id_tp = (int)$_GET['id_tp'];
        $query = "DELETE FROM tujuan_pembelajaran WHERE id_tp = ? AND id_guru_pembuat = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ii", $id_tp, $id_guru);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION['pesan'] = "'Berhasil', 'Tujuan Pembelajaran telah dihapus.', 'success'";
            } else {
                $_SESSION['pesan'] = "'Gagal', 'TP tidak ditemukan atau Anda tidak punya hak akses.', 'error'";
            }
        } else {
            $_SESSION['pesan'] = "'Error', 'Terjadi kesalahan saat menghapus data.', 'error'";
        }
        header("location: tp_guru_tampil.php");
        exit();

    //======================================================================
    // AKSI UNTUK MENGHAPUS BANYAK TP (MASSAL)
    //======================================================================
    case 'hapus_massal':
        if (empty($_POST['tp_ids']) || !is_array($_POST['tp_ids'])) {
            $_SESSION['pesan'] = "'Peringatan', 'Tidak ada TP yang dipilih untuk dihapus.', 'warning'";
            header('location: tp_guru_tampil.php');
            exit();
        }

        $ids_sanitized = array_map('intval', $_POST['tp_ids']);
        $placeholders = implode(',', array_fill(0, count($ids_sanitized), '?'));
        
        $query = "DELETE FROM tujuan_pembelajaran WHERE id_tp IN ($placeholders) AND id_guru_pembuat = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        
        $types = str_repeat('i', count($ids_sanitized)) . 'i';
        $params_to_bind = array_merge([$types], $ids_sanitized, [$id_guru]);
        $refs = [];
        foreach($params_to_bind as $key => $value) {
            $refs[$key] = &$params_to_bind[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));

        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            $_SESSION['pesan'] = "'Berhasil', '$affected_rows Tujuan Pembelajaran telah dihapus.', 'success'";
        } else {
            $_SESSION['pesan'] = "'Error', 'Terjadi kesalahan saat menghapus data massal.', 'error'";
        }
        header('location: tp_guru_tampil.php');
        exit();

    //======================================================================
    // [PERBAIKAN] AKSI UNTUK MENGUPDATE TP
    //======================================================================
    case 'update':
        $id_tp = (int)$_POST['id_tp'];
        $id_mapel = (int)$_POST['id_mapel'];
        $kode_tp = $_POST['kode_tp'];
        $deskripsi = $_POST['deskripsi_tp'];
        $semester = (int)$_POST['semester'];

        // Validasi dasar
        if (empty($id_tp) || empty($id_mapel) || empty($deskripsi) || empty($semester)) {
            $_SESSION['pesan'] = "'Gagal', 'Semua kolom wajib diisi.', 'error'";
            // Kembalikan ke halaman edit jika gagal
            header("location: tp_guru_edit.php?id_tp=" . $id_tp);
            exit();
        }

        // Query update dengan validasi kepemilikan (id_guru_pembuat)
        $query_update = "UPDATE tujuan_pembelajaran 
                         SET id_mapel = ?, kode_tp = ?, deskripsi_tp = ?, semester = ? 
                         WHERE id_tp = ? AND id_guru_pembuat = ?";
        $stmt_update = mysqli_prepare($koneksi, $query_update);
        // Tipe data: i (id_mapel), s (kode_tp), s (deskripsi_tp), i (semester), i (id_tp), i (id_guru)
        mysqli_stmt_bind_param($stmt_update, "issiii", $id_mapel, $kode_tp, $deskripsi, $semester, $id_tp, $id_guru);
        
        if (mysqli_stmt_execute($stmt_update)) {
            // Cek apakah ada baris yang benar-benar berubah
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                $_SESSION['pesan'] = "'Berhasil', 'Tujuan Pembelajaran berhasil diperbarui.', 'success'";
            } else {
                // Query berhasil tapi tidak ada data berubah (atau TP tidak dimiliki guru ini)
                $_SESSION['pesan'] = "'Info', 'Tidak ada perubahan data yang disimpan.', 'info'";
            }
        } else {
            $_SESSION['pesan'] = "'Error', 'Gagal memperbarui data TP.', 'error'";
        }
        // Kembalikan ke halaman daftar TP setelah sukses atau gagal
        header("location: tp_guru_tampil.php");
        exit();

    //======================================================================
    // AKSI DEFAULT JIKA TIDAK ADA YANG COCOK
    //======================================================================
    default:
        header("location: tp_guru_tampil.php");
        exit();
}
?>
