<?php
session_start();
include 'koneksi.php';
require 'vendor/autoload.php'; // Membutuhkan PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Keamanan
if ($_SESSION['role'] != 'admin') { die("Akses ditolak."); }

try {
    $spreadsheet = new Spreadsheet();
    
    // --- SHEET 1: PETUNJUK & IMPORT ---
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Import');

    // Judul Kolom
    $sheet->setCellValue('A1', 'nama_kelas');
    $sheet->setCellValue('B1', 'fase');
    $sheet->setCellValue('C1', 'username_walikelas');

    // Beri gaya pada Header
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00796b']]
    ];
    $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

    // Set lebar kolom
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(10);
    $sheet->getColumnDimension('C')->setWidth(30);

    // Tambahkan contoh data
    $sheet->setCellValue('A2', 'VII A');
    $sheet->setCellValue('B2', 'D');
    $sheet->setCellValue('C2', 'guru.budi');

    // Menambahkan Petunjuk di samping
    $sheet->setCellValue('E1', 'PETUNJUK PENGISIAN');
    $sheet->getStyle('E1')->getFont()->setBold(true)->setSize(14);
    $sheet->mergeCells('E1:G1');
    $sheet->setCellValue('E3', 'nama_kelas');
    $sheet->setCellValue('F3', 'Isi nama kelas. (Contoh: VII A, VIII B, IX C)');
    $sheet->setCellValue('E4', 'fase');
    $sheet->setCellValue('F4', 'Isi Fase. (Contoh: D untuk SMP)');
    $sheet->setCellValue('E5', 'username_walikelas');
    $sheet->setCellValue('F5', 'Isi USERNAME guru. Buka sheet "Daftar Guru" di sebelah untuk copy-paste username.');
    $sheet->getStyle('E3:E5')->getFont()->setBold(true);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(60);


    // --- SHEET 2: DAFTAR GURU ---
    $guruSheet = new Worksheet($spreadsheet, 'Daftar Guru');
    $spreadsheet->addSheet($guruSheet, 1);
    
    $guruSheet->setCellValue('A1', 'NAMA LENGKAP GURU');
    $guruSheet->setCellValue('B1', 'USERNAME (Untuk di-copy)');
    $guruSheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    $guruSheet->getColumnDimension('A')->setWidth(35);
    $guruSheet->getColumnDimension('B')->setWidth(30);

    // Ambil data guru dari DB
    $query_guru = mysqli_query($koneksi, "SELECT nama_guru, username FROM guru WHERE role = 'guru' ORDER BY nama_guru ASC");
    $row = 2;
    while ($guru = mysqli_fetch_assoc($query_guru)) {
        $guruSheet->setCellValue('A' . $row, $guru['nama_guru']);
        $guruSheet->setCellValue('B' . $row, $guru['username']);
        $row++;
    }
    
    // Set sheet aktif kembali ke sheet pertama
    $spreadsheet->setActiveSheetIndex(0);

    // --- PROSES DOWNLOAD ---
    $filename = 'template_import_kelas.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} catch(Exception $e) {
    die("Gagal membuat template: " . $e->getMessage());
}
?>
