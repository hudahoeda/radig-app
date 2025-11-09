<?php
session_start();
include 'koneksi.php';

// Validasi role Guru dan kelengkapan sesi
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru' || !isset($_SESSION['id_guru'])) {
    // Menggunakan json_encode untuk pesan error yang lebih aman saat di-redirect
    $_SESSION['error'] = json_encode(['title' => 'Akses Ditolak', 'text' => 'Anda harus login sebagai guru.']);
    header("Location: login.php"); // Arahkan ke halaman login jika sesi tidak valid
    exit;
}

$aksi = $_GET['aksi'] ?? '';
$id_pembina = $_SESSION['id_guru'];

if ($aksi == 'simpan_penilaian') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Hanya izinkan metode POST
        header("Location: pembina_penilaian_ekskul.php");
        exit();
    }

    $id_ekskul = (int)$_POST['id_ekskul'];
    $semester = (int)$_POST['semester'];
    $data_kehadiran = $_POST['kehadiran'] ?? [];
    $data_penilaian = $_POST['penilaian'] ?? [];

    // KEAMANAN: Pastikan guru ini adalah pembina sah dari ekskul yang dinilai
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_ekskul FROM ekstrakurikuler WHERE id_ekskul = ? AND id_pembina = ?");
    mysqli_stmt_bind_param($stmt_cek, "ii", $id_ekskul, $id_pembina);
    mysqli_stmt_execute($stmt_cek);
    if (mysqli_stmt_get_result($stmt_cek)->num_rows == 0) {
        $_SESSION['error'] = "Anda tidak memiliki wewenang untuk menilai ekskul ini.";
        header("Location: pembina_penilaian_ekskul.php");
        exit();
    }

    mysqli_begin_transaction($koneksi);
    try {
        // ==========================================================
        // ### BAGIAN YANG DIPERBAIKI ###
        // ==========================================================
        
        // 1. Ambil nilai total pertemuan yang sekarang terpusat dari form
        $total_pertemuan_umum = (int)($_POST['total_pertemuan_umum'] ?? 0);

        // 2. Siapkan query UPSERT (tidak berubah, query sudah bagus)
        $stmt_kehadiran = mysqli_prepare($koneksi, "
            INSERT INTO ekskul_kehadiran (id_peserta_ekskul, semester, jumlah_hadir, total_pertemuan) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE jumlah_hadir = VALUES(jumlah_hadir), total_pertemuan = VALUES(total_pertemuan)
        ");

        // 3. Loop melalui data kehadiran yang strukturnya sudah baru
        foreach ($data_kehadiran as $id_peserta => $jumlah_hadir) {
            // Konversi ke integer untuk keamanan
            $id_peserta_int = (int)$id_peserta;
            $jumlah_hadir_int = (int)$jumlah_hadir;
            
            // Gunakan nilai total pertemuan yang sama untuk semua siswa
            mysqli_stmt_bind_param($stmt_kehadiran, "iiii", $id_peserta_int, $semester, $jumlah_hadir_int, $total_pertemuan_umum);
            mysqli_stmt_execute($stmt_kehadiran);
        }
        
        // ==========================================================
        // ### AKHIR BAGIAN YANG DIPERBAIKI ###
        // ==========================================================

        // Proses Penilaian (Bagian ini tidak perlu diubah karena struktur datanya sama)
        $stmt_penilaian = mysqli_prepare($koneksi, "
            INSERT INTO ekskul_penilaian (id_peserta_ekskul, id_tujuan_ekskul, nilai)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
        ");

        foreach ($data_penilaian as $id_peserta => $penilaian) {
            foreach ($penilaian as $id_tujuan => $nilai) {
                // Hanya proses jika ada nilai yang dipilih
                if (!empty($nilai)) {
                    mysqli_stmt_bind_param($stmt_penilaian, "iis", $id_peserta, $id_tujuan, $nilai);
                    mysqli_stmt_execute($stmt_penilaian);
                }
            }
        }

        mysqli_commit($koneksi);
        $_SESSION['pesan'] = "Semua data penilaian berhasil disimpan.";

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        // Berikan pesan error yang lebih informatif
        error_log("Database Error: " . $exception->getMessage()); // Log error untuk admin
        $_SESSION['error'] = "Terjadi kesalahan pada database. Silakan coba lagi.";
    }

    header("Location: pembina_penilaian_ekskul.php?ekskul_id=" . $id_ekskul);
    exit();
} else {
    // Jika tidak ada aksi yang valid, kembalikan ke halaman utama
    header("Location: dashboard.php");
    exit();
}
?>