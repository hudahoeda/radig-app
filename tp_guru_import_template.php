<?php
session_start();
include 'koneksi.php';
require 'vendor/autoload.php'; // Path ke autoload.php dari Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

// Validasi peran
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    die("Akses ditolak. Halaman ini khusus untuk Guru.");
}

$id_guru_login = (int)$_SESSION['id_guru'];
$id_tahun_ajaran_aktif = 0;

// Ambil ID tahun ajaran yang aktif
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
if ($d_ta_aktif = mysqli_fetch_assoc($q_ta_aktif)) {
    $id_tahun_ajaran_aktif = (int)$d_ta_aktif['id_tahun_ajaran'];
}

// Ambil daftar mapel yang diampu guru
$mapel_list = [];
$query_mapel = "SELECT DISTINCT m.nama_mapel FROM guru_mengajar gm 
                JOIN mata_pelajaran m ON gm.id_mapel = m.id_mapel
                WHERE gm.id_guru = ? AND gm.id_tahun_ajaran = ? ORDER BY m.nama_mapel";
$stmt_mapel = mysqli_prepare($koneksi, $query_mapel);
mysqli_stmt_bind_param($stmt_mapel, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($stmt_mapel);
$result_mapel = mysqli_stmt_get_result($stmt_mapel);
while ($row = mysqli_fetch_assoc($result_mapel)) {
    $mapel_list[] = $row['nama_mapel'];
}

// === Buat File Excel ===
$spreadsheet = new Spreadsheet();

// --- Sheet 1: Template Isian ---
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template TP');

// Header
$sheet->setCellValue('A1', 'KODE_TP (Opsional)');
$sheet->setCellValue('B1', 'DESKRIPSI_TUJUAN_PEMBELAJARAN (Wajib)');
$sheet->setCellValue('C1', 'SEMESTER (Wajib: 1 atau 2)');
$sheet->setCellValue('D1', 'NAMA_MATA_PELAJARAN (Wajib)');

// Atur lebar kolom
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(80);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(40);

// Buat dropdown untuk Mata Pelajaran
if (!empty($mapel_list)) {
    $validation = $sheet->getCell('D2')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setFormula1('"' . implode(',', $mapel_list) . '"');
    // Terapkan validasi ke 100 baris ke bawah
    for ($i = 3; $i <= 102; $i++) {
        $sheet->getCell('D' . $i)->setDataValidation(clone $validation);
    }
}

// Buat dropdown untuk Semester
$validationSemester = $sheet->getCell('C2')->getDataValidation();
$validationSemester->setType(DataValidation::TYPE_LIST);
$validationSemester->setAllowBlank(false);
$validationSemester->setShowDropDown(true);
$validationSemester->setFormula1('"1,2"');
for ($i = 3; $i <= 102; $i++) {
    $sheet->getCell('C' . $i)->setDataValidation(clone $validationSemester);
}


// --- Sheet 2: Petunjuk ---
$instructionSheet = $spreadsheet->createSheet();
$instructionSheet->setTitle('Petunjuk Pengisian');
$instructionSheet->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE IMPORT TUJUAN PEMBELAJARAN');
$instructionSheet->mergeCells('A1:B1');
$instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$instructionSheet->setCellValue('A3', 'KODE_TP');
$instructionSheet->setCellValue('B3', 'Diisi dengan kode unik untuk TP jika ada. Kolom ini bersifat opsional, boleh dikosongkan.');
$instructionSheet->setCellValue('A4', 'DESKRIPSI_TUJUAN_PEMBELAJARAN');
$instructionSheet->setCellValue('B4', 'Diisi dengan deskripsi lengkap dari TP. Kolom ini wajib diisi.');
$instructionSheet->setCellValue('A5', 'SEMESTER');
$instructionSheet->setCellValue('B5', 'Pilih semester dari dropdown, yaitu 1 untuk Ganjil atau 2 untuk Genap. Kolom ini wajib diisi.');
$instructionSheet->setCellValue('A6', 'NAMA_MATA_PELAJARAN');
$instructionSheet->setCellValue('B6', 'Pilih mata pelajaran yang sesuai dari dropdown. Daftar ini berisi mapel yang Anda ampu di tahun ajaran aktif. Kolom ini wajib diisi.');

$instructionSheet->getColumnDimension('A')->setWidth(30);
$instructionSheet->getColumnDimension('B')->setWidth(80);
$instructionSheet->getStyle('A3:A6')->getFont()->setBold(true);

// Set sheet aktif ke sheet pertama
$spreadsheet->setActiveSheetIndex(0);

// Redirect output ke browser client
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_tp.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;