<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Siswa');

// Menulis header kolom
$sheet->setCellValue('A1', 'NISN');
$sheet->setCellValue('B1', 'NIS');
$sheet->setCellValue('C1', 'Nama Lengkap');
$sheet->setCellValue('D1', 'Username');
$sheet->setCellValue('E1', 'Password (Opsional)');
$sheet->setCellValue('F1', 'Nama Kelas (Wajib, harus sama persis dengan di sistem)'); // KOLOM BARU

// Menambahkan contoh data
$sheet->setCellValue('A2', '0012345678');
$sheet->setCellValue('B2', '12345');
$sheet->setCellValue('C2', 'Siswa Kelas Tujuh A');
$sheet->setCellValue('D2', 'siswa.tujuh.a');
$sheet->setCellValue('E2', 'rahasiaku123');
$sheet->setCellValue('F2', 'VII-A'); // CONTOH NAMA KELAS

// Mengatur lebar kolom
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(35);
$sheet->getColumnDimension('D')->setWidth(25);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setWidth(20);

// Mengatur header HTTP
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_semua_siswa.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>