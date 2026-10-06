<?php
session_start();
include 'koneksi.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Validasi role
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Akses Ditolak']);
    header("location: dashboard.php");
    exit();
}

if (!isset($_FILES['file_import']) || $_FILES['file_import']['error'] != UPLOAD_ERR_OK) {
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Upload Gagal', 'text' => 'File tidak valid.']);
    header("location: walikelas_data_rapor.php");
    exit();
}

$file_tmp = $_FILES['file_import']['tmp_name'];
$file_ext = strtolower(pathinfo($_FILES['file_import']['name'], PATHINFO_EXTENSION));

if ($file_ext != 'xlsx') {
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Format Salah', 'text' => 'Gunakan file .xlsx hasil download dari sistem.']);
    header("location: walikelas_data_rapor.php");
    exit();
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

// Ambil data kelas
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);
$id_kelas = $kelas['id_kelas'] ?? 0;

if (!$id_kelas) {
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Anda bukan wali kelas aktif saat ini.']);
    header("location: walikelas_data_rapor.php");
    exit();
}

try {
    $spreadsheet = IOFactory::load($file_tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $berhasil = 0;
    $gagal = 0;

    mysqli_begin_transaction($koneksi);

    for ($row = 5; $row <= $highestRow; $row++) { // Data mulai baris 5
        $id_siswa = (int)$sheet->getCell('A' . $row)->getValue();

        // Lewati jika ID Siswa kosong
        if ($id_siswa == 0) continue;

        $sakit = (int)$sheet->getCell('D' . $row)->getValue();
        $izin = (int)$sheet->getCell('E' . $row)->getValue();
        $alpha = (int)$sheet->getCell('F' . $row)->getValue();
        $catatan = trim($sheet->getCell('G' . $row)->getValue() ?? '');

        // Pastikan tidak negatif
        $sakit = max(0, $sakit);
        $izin = max(0, $izin);
        $alpha = max(0, $alpha);

        // Validasi kepemilikan siswa
        $cek_siswa = mysqli_query($koneksi, "SELECT DISTINCT s.id_siswa FROM siswa s LEFT JOIN rapor r ON s.id_siswa = r.id_siswa WHERE s.id_siswa = $id_siswa AND (s.id_kelas = $id_kelas OR r.id_kelas = $id_kelas)");
        if (mysqli_num_rows($cek_siswa) == 0) {
            $gagal++;
            continue; // Siswa bukan milik kelas ini, lewat
        }

        // Cek data rapor eksisting
        $cek_rapor = mysqli_query($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = $id_siswa AND id_kelas = $id_kelas AND semester = $semester_aktif AND id_tahun_ajaran = $id_tahun_ajaran");

        if (mysqli_num_rows($cek_rapor) > 0) {
            // Update
            $q_update = mysqli_prepare($koneksi, "UPDATE rapor SET sakit = ?, izin = ?, tanpa_keterangan = ?, catatan_wali_kelas = ? WHERE id_siswa = ? AND id_kelas = ? AND semester = ? AND id_tahun_ajaran = ?");
            mysqli_stmt_bind_param($q_update, "iiisiiii", $sakit, $izin, $alpha, $catatan, $id_siswa, $id_kelas, $semester_aktif, $id_tahun_ajaran);
            if(mysqli_stmt_execute($q_update)) $berhasil++; else $gagal++;
        } else {
            // Insert
            $q_insert = mysqli_prepare($koneksi, "INSERT INTO rapor (id_siswa, id_kelas, semester, id_tahun_ajaran, sakit, izin, tanpa_keterangan, catatan_wali_kelas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($q_insert, "iiiiiiis", $id_siswa, $id_kelas, $semester_aktif, $id_tahun_ajaran, $sakit, $izin, $alpha, $catatan);
            if(mysqli_stmt_execute($q_insert)) $berhasil++; else $gagal++;
        }
    }

    if ($gagal > 0 && $berhasil == 0) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal Import', 'text' => 'Semua data gagal diimport. Pastikan Anda tidak merubah ID Siswa di template.']);
    } else {
        mysqli_commit($koneksi);
        $pesan_teks = "Berhasil memproses {$berhasil} data siswa.";
        if ($gagal > 0) $pesan_teks .= " ({$gagal} data dilewati)";
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Import Sukses', 'text' => $pesan_teks]);
    }

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal Membaca File', 'text' => 'File tidak valid atau rusak. Pesan: ' . $e->getMessage()]);
}

header("location: walikelas_data_rapor.php");
exit();
?>
