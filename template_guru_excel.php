<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Pengguna');

// Menulis header kolom
$sheet->setCellValue('A1', 'NIP (Opsional)');
$sheet->setCellValue('B1', 'Nama Lengkap');
$sheet->setCellValue('C1', 'Username');
$sheet->setCellValue('D1', 'Role');
$sheet->setCellValue('E1', 'Password (Opsional)'); // KOLOM BARU

// Memberi instruksi
$sheet->setCellValue('F1', 'CATATAN: Isi kolom "Role" hanya dengan "admin" atau "guru". Jika Password kosong, akan diatur default sama dengan Username.');

// Memberi contoh pengisian
$sheet->setCellValue('A2', '198508102010011001');
$sheet->setCellValue('B2', 'Guru Contoh');
$sheet->setCellValue('C2', 'guru.contoh');
$sheet->setCellValue('D2', 'guru');
$sheet->setCellValue('E2', 'rahasiaguru'); // CONTOH PASSWORD BARU

// Mengatur lebar kolom
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(20); // LEBAR KOLOM BARU
$sheet->getColumnDimension('F')->setWidth(70);

// Mengatur header HTTP untuk mengunduh file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_pengguna.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>