<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Data Siswa Lengkap');

// Menulis header kolom sesuai kebutuhan rapor
$headers = [
    'A1' => 'Nama Lengkap (Wajib)',
    'B1' => 'Jenis Kelamin (L/P)',
    'C1' => 'NISN (Wajib & Unik)',
    'D1' => 'NIS',
    'E1' => 'Tempat Lahir',
    'F1' => 'Tanggal Lahir (YYYY-MM-DD)',
    'G1' => 'NIK',
    'H1' => 'Agama',
    'I1' => 'Alamat Siswa',
    'J1' => 'Sekolah Asal',
    'K1' => 'Diterima Tanggal (YYYY-MM-DD)',
    'L1' => 'Anak ke',
    'M1' => 'Status dalam Keluarga',
    'N1' => 'Telepon Siswa',
    'O1' => 'Nama Ayah',
    'P1' => 'Pekerjaan Ayah',
    'Q1' => 'Nama Ibu',
    'R1' => 'Pekerjaan Ibu',
    'S1' => 'Nama Wali',
    'T1' => 'Alamat Wali',
    'U1' => 'Telepon Wali',
    'V1' => 'Pekerjaan Wali',
    'W1' => 'Username (Wajib & Unik)',
    'X1' => 'Password (Opsional, jika kosong = NISN)',
    'Y1' => 'Nama Kelas (Wajib, harus sama persis dengan di sistem)'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
    $style = $sheet->getStyle($cell);
    $style->getFont()->setBold(true);
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');
}

// Mengatur lebar kolom agar rapi
foreach (range('A', 'Y') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Mengatur header HTTP untuk mengunduh file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_siswa_lengkap.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>