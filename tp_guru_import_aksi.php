<?php
session_start();
include 'koneksi.php';
require 'vendor/autoload.php'; // Path ke autoload.php dari Composer

use PhpOffice\PhpSpreadsheet\IOFactory;

// Validasi peran
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Akses Ditolak']);
    header("location: dashboard.php");
    exit();
}

// Validasi file upload
if (!isset($_FILES['file_import']) || $_FILES['file_import']['error'] != UPLOAD_ERR_OK) {
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Upload Gagal', 'text' => 'Tidak ada file yang diunggah atau terjadi kesalahan.']);
    header("location: tp_guru_import.php");
    exit();
}

$file_tmp = $_FILES['file_import']['tmp_name'];
$file_ext = strtolower(pathinfo($_FILES['file_import']['name'], PATHINFO_EXTENSION));

if ($file_ext != 'xlsx') {
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Format Salah', 'text' => 'Hanya file dengan format .xlsx yang diizinkan.']);
    header("location: tp_guru_import.php");
    exit();
}

// --- Persiapan Data ---
$id_guru = (int)$_SESSION['id_guru'];
$fase = 'D';
$id_tahun_ajaran_aktif = 0;

// Ambil ID tahun ajaran aktif
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
if ($d_ta_aktif = mysqli_fetch_assoc($q_ta_aktif)) {
    $id_tahun_ajaran_aktif = (int)$d_ta_aktif['id_tahun_ajaran'];
}

// Buat peta (map) dari nama mapel ke id_mapel untuk efisiensi
$mapel_map = [];
$query_mapel = "SELECT id_mapel, nama_mapel FROM mata_pelajaran";
$result_mapel = mysqli_query($koneksi, $query_mapel);
while ($row = mysqli_fetch_assoc($result_mapel)) {
    $mapel_map[$row['nama_mapel']] = (int)$row['id_mapel'];
}

// --- Mulai Baca dan Proses Excel ---
try {
    $spreadsheet = IOFactory::load($file_tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $berhasil_disimpan = 0;
    $gagal_disimpan = 0;
    $pesan_error = [];

    // Mulai transaksi database
    mysqli_begin_transaction($koneksi);

    // Siapkan statement insert
    $query_insert = "INSERT INTO tujuan_pembelajaran (id_mapel, id_guru_pembuat, fase, kode_tp, deskripsi_tp, semester, id_tahun_ajaran) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($koneksi, $query_insert);
    
    // Loop mulai dari baris 2 (baris 1 adalah header)
    for ($row = 2; $row <= $highestRow; $row++) {
        $kode_tp = $sheet->getCell('A' . $row)->getValue();
        $deskripsi_tp = trim($sheet->getCell('B' . $row)->getValue());
        $semester = trim($sheet->getCell('C' . $row)->getValue());
        $nama_mapel = trim($sheet->getCell('D' . $row)->getValue());

        // Validasi: Lewati baris kosong
        if (empty($deskripsi_tp) && empty($nama_mapel)) {
            continue;
        }

        // Validasi data per baris
        if (empty($deskripsi_tp) || empty($semester) || empty($nama_mapel)) {
            $gagal_disimpan++;
            $pesan_error[] = "Baris {$row}: Data tidak lengkap (Deskripsi, Semester, dan Mata Pelajaran wajib diisi).";
            continue;
        }

        if (!in_array($semester, [1, 2])) {
            $gagal_disimpan++;
            $pesan_error[] = "Baris {$row}: Semester harus diisi dengan angka 1 atau 2.";
            continue;
        }

        if (!isset($mapel_map[$nama_mapel])) {
            $gagal_disimpan++;
            $pesan_error[] = "Baris {$row}: Nama Mata Pelajaran '{$nama_mapel}' tidak ditemukan di database.";
            continue;
        }

        $id_mapel = $mapel_map[$nama_mapel];
        
        // Bind parameter dan eksekusi
        mysqli_stmt_bind_param($stmt_insert, "iisssii", $id_mapel, $id_guru, $fase, $kode_tp, $deskripsi_tp, $semester, $id_tahun_ajaran_aktif);
        
        if (mysqli_stmt_execute($stmt_insert)) {
            $berhasil_disimpan++;
        } else {
            $gagal_disimpan++;
            $pesan_error[] = "Baris {$row}: Gagal menyimpan ke database karena kesalahan teknis.";
        }
    }
    
    // Finalisasi transaksi
    if ($gagal_disimpan > 0) {
        // Jika ada satu saja yang gagal, batalkan semua yang sudah masuk
        mysqli_rollback($koneksi);
        $text_error = "Proses import dibatalkan karena ditemukan {$gagal_disimpan} data tidak valid. <br><br>Detail Kesalahan:<br>" . implode("<br>", array_slice($pesan_error, 0, 5));
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Import Gagal', 'html' => $text_error]);
    } else {
        // Jika semua berhasil, simpan permanen
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = "Import Selesai. Berhasil menyimpan {$berhasil_disimpan} data TP baru.";
    }

} catch (Exception $e) {
    mysqli_rollback($koneksi); // Pastikan rollback jika terjadi exception
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Terjadi Kesalahan', 'text' => 'Gagal membaca file Excel. Pastikan format file benar. Pesan: ' . $e->getMessage()]);
}

header("location: tp_guru_tampil.php");
exit();