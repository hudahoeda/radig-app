<?php
session_start();
include 'koneksi.php';

// Validasi role Guru dan kelengkapan sesi
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru' || !isset($_SESSION['id_guru'])) {
    $_SESSION['error'] = json_encode(['title' => 'Akses Ditolak', 'text' => 'Anda harus login sebagai guru.']);
    header("Location: login.php");
    exit;
}

$aksi = $_GET['aksi'] ?? '';
$id_pembina = $_SESSION['id_guru'];

if ($aksi == 'simpan_penilaian') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: pembina_penilaian_ekskul.php");
        exit();
    }

    $id_ekskul = (int)$_POST['id_ekskul'];
    $semester = (int)$_POST['semester'];
    $data_kehadiran = $_POST['kehadiran'] ?? [];
    $data_penilaian = $_POST['penilaian'] ?? []; // Array [id_peserta][id_tujuan] = nilai
    $total_pertemuan_umum = (int)($_POST['total_pertemuan_umum'] ?? 0);

    // 1. KEAMANAN: Cek Wewenang
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_ekskul FROM ekstrakurikuler WHERE id_ekskul = ? AND id_pembina = ?");
    mysqli_stmt_bind_param($stmt_cek, "ii", $id_ekskul, $id_pembina);
    mysqli_stmt_execute($stmt_cek);
    if (mysqli_stmt_get_result($stmt_cek)->num_rows == 0) {
        $_SESSION['error'] = "Anda tidak memiliki wewenang untuk menilai ekskul ini.";
        header("Location: pembina_penilaian_ekskul.php");
        exit();
    }

    // 2. HITUNG JUMLAH TUJUAN PEMBELAJARAN (Untuk Validasi Kelengkapan)
    // Kita harus tahu ada berapa TP untuk ekskul ini di semester ini
    $q_count_tp = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul AND semester = $semester");
    $total_tp_wajib = mysqli_fetch_assoc($q_count_tp)['total'];

    mysqli_begin_transaction($koneksi);
    try {
        $stmt_kehadiran = mysqli_prepare($koneksi, "
            INSERT INTO ekskul_kehadiran (id_peserta_ekskul, semester, jumlah_hadir, total_pertemuan) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE jumlah_hadir = VALUES(jumlah_hadir), total_pertemuan = VALUES(total_pertemuan)
        ");

        $stmt_penilaian = mysqli_prepare($koneksi, "
            INSERT INTO ekskul_penilaian (id_peserta_ekskul, id_tujuan_ekskul, nilai)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
        ");

        $jumlah_tersimpan = 0;

        // Loop per Siswa berdasarkan Data Kehadiran (karena ini input dasar)
        foreach ($data_kehadiran as $id_peserta => $jumlah_hadir_val) {
            $id_peserta = (int)$id_peserta;
            
            // --- VALIDASI KELENGKAPAN DATA (SERVER SIDE) ---
            
            // 1. Cek Kehadiran
            $is_hadir_filled = ($jumlah_hadir_val !== ''); 
            
            // 2. Cek Nilai TP
            $nilai_siswa_ini = $data_penilaian[$id_peserta] ?? [];
            $filled_tp_count = 0;
            foreach ($nilai_siswa_ini as $val_nilai) {
                if (!empty($val_nilai)) {
                    $filled_tp_count++;
                }
            }
            $is_nilai_complete = ($filled_tp_count >= $total_tp_wajib); // Harus >= jumlah TP wajib

            // LOGIKA PENYIMPANAN KETAT:
            // Simpan HANYA JIKA (Kehadiran Terisi DAN Semua Nilai Terisi)
            
            if ($is_hadir_filled && $is_nilai_complete) {
                // A. Simpan Kehadiran
                $jumlah_hadir_int = (int)$jumlah_hadir_val;
                mysqli_stmt_bind_param($stmt_kehadiran, "iiii", $id_peserta, $semester, $jumlah_hadir_int, $total_pertemuan_umum);
                mysqli_stmt_execute($stmt_kehadiran);

                // B. Simpan Penilaian
                foreach ($nilai_siswa_ini as $id_tujuan => $nilai) {
                    if (!empty($nilai)) {
                        mysqli_stmt_bind_param($stmt_penilaian, "iis", $id_peserta, $id_tujuan, $nilai);
                        mysqli_stmt_execute($stmt_penilaian);
                    }
                }
                $jumlah_tersimpan++;
            } 
            // ELSE: Abaikan (Skip). Data parsial tidak disimpan ke DB.
        }

        mysqli_commit($koneksi);
        
        if ($jumlah_tersimpan > 0) {
            $_SESSION['pesan'] = "Berhasil menyimpan data lengkap untuk $jumlah_tersimpan siswa.";
        } else {
            // Jika tidak ada yang disimpan (mungkin karena user hanya mengisi parsial lalu memaksa submit via inspect element)
            $_SESSION['pesan'] = "Tidak ada perubahan data yang disimpan. Pastikan data lengkap (Kehadiran & Semua Nilai) sebelum simpan.";
        }

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        error_log("Database Error: " . $exception->getMessage());
        $_SESSION['error'] = "Terjadi kesalahan pada database.";
    }

    header("Location: pembina_penilaian_ekskul.php?ekskul_id=" . $id_ekskul);
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>