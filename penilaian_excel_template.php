<?php
// Memanggil autoloader dari Composer
require 'vendor/autoload.php';
include 'koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Validasi ID Penilaian
$id_penilaian = isset($_GET['id_penilaian']) ? (int)$_GET['id_penilaian'] : 0;
if ($id_penilaian == 0) die("Error: Penilaian tidak valid.");

// Ambil data penilaian untuk nama file
$q_penilaian = mysqli_query($koneksi, "SELECT nama_penilaian, id_kelas FROM penilaian WHERE id_penilaian = $id_penilaian");
$penilaian = mysqli_fetch_assoc($q_penilaian);
if (!$penilaian) die("Error: Penilaian tidak ditemukan.");

// Ambil daftar siswa dari kelas terkait
$q_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = {$penilaian['id_kelas']} AND status_siswa = 'Aktif' ORDER BY nama_lengkap ASC");

// Membuat objek spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Menulis header kolom
$sheet->setCellValue('A1', 'ID Siswa (JANGAN DIUBAH)');
$sheet->setCellValue('B1', 'Nama Siswa');
$sheet->setCellValue('C1', 'Nilai');

// Mengisi data siswa
$row = 2;
while ($siswa = mysqli_fetch_assoc($q_siswa)) {
    $sheet->setCellValue('A' . $row, $siswa['id_siswa']);
    $sheet->setCellValue('B' . $row, $siswa['nama_lengkap']);
    // Kolom C (Nilai) sengaja dikosongkan
    $row++;
}

// Styling (opsional tapi direkomendasikan)
$sheet->getStyle('A1:C1')->getFont()->setBold(true);
$sheet->getColumnDimension('A')->setVisible(false); // Sembunyikan kolom ID Siswa dari guru
$sheet->getColumnDimension('B')->setWidth(40);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->freezePane('A2'); // Bekukan baris header

// Membersihkan nama file dari karakter yang tidak valid
$safe_filename = preg_replace('/[^A-Za-z0-9-_\s]/', '', $penilaian['nama_penilaian']);
$filename = "template-nilai-" . str_replace(' ', '-', $safe_filename) . ".xlsx";

// Mengatur header untuk download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheet.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Menyimpan file ke output PHP
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;