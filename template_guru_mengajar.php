<?php
session_start();
include 'koneksi.php'; 
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Keamanan
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak. Hanya admin yang boleh mengunduh template ini.");
}

try {
    // Ambil tahun ajaran aktif
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

    $spreadsheet = new Spreadsheet();
    
    // --- SHEET 1: PETUNJUK & IMPORT ---
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Import Guru & Mengajar');

    // Judul Kolom
    $headers = [
        'A1' => 'NIP (Opsional)',
        'B1' => 'Nama Lengkap (Wajib)',
        'C1' => 'Username (Wajib & Unik)',
        'D1' => 'Role (Wajib: guru/admin)',
        'E1' => 'Password (Opsional)',
        'F1' => 'Kode Mapel (Wajib jika role=guru)',
        'G1' => 'Nama Kelas (Wajib jika role=guru)'
    ];

    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }

    // Beri gaya pada Header
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00796b']]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    // Set lebar kolom
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(20);

    // Tambahkan contoh data
    $sheet->setCellValue('A2', '198508102010011001');
    $sheet->setCellValue('B2', 'Guru Contoh 1');
    $sheet->setCellValue('C2', 'guru.contoh1');
    $sheet->setCellValue('D2', 'guru');
    $sheet->setCellValue('E2', 'rahasiaguru1');
    $sheet->setCellValue('F2', 'MTK');
    $sheet->setCellValue('G2', 'VII A');
    
    $sheet->setCellValue('A3', '198508102010011001'); // NIP sama
    $sheet->setCellValue('B3', 'Guru Contoh 1'); // Nama sama
    $sheet->setCellValue('C3', 'guru.contoh1'); // Username SAMA
    $sheet->setCellValue('D3', 'guru');
    $sheet->setCellValue('E3', ''); // Password bisa dikosongi
    $sheet->setCellValue('F3', 'MTK'); // Mapel sama
    $sheet->setCellValue('G3', 'VII B'); // Kelas BERBEDA
    
    $sheet->setCellValue('A4', '198508102010011001');
    $sheet->setCellValue('B4', 'Guru Contoh 1');
    $sheet->setCellValue('C4', 'guru.contoh1'); // Username SAMA
    $sheet->setCellValue('D4', 'guru');
    $sheet->setCellValue('E4', '');
    $sheet->setCellValue('F4', 'IPA'); // Mapel BERBEDA
    $sheet->setCellValue('G4', 'VII A'); // Kelas berbeda

    $sheet->setCellValue('A5', '199011122015022002');
    $sheet->setCellValue('B5', 'Admin Contoh');
    $sheet->setCellValue('C5', 'admin.baru');
    $sheet->setCellValue('D5', 'admin');
    $sheet->setCellValue('E5', 'rahasiadmin');
    $sheet->setCellValue('F5', ''); // Role admin, mapel/kelas bisa kosong
    $sheet->setCellValue('G5', '');

    // Menambahkan Petunjuk di kolom terpisah
    $sheet->setCellValue('I1', 'PETUNJUK PENTING!');
    $sheet->getStyle('I1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF8F00'));
    
    $instructions = [
        'I3' => 'Username (Wajib)',
        'J3' => 'Username adalah KUNCI. Jika guru mengajar banyak kelas/mapel, buat beberapa baris dengan USERNAME SAMA.',
        'I4' => 'Data Guru',
        'J4' => 'Data guru (NIP, Nama, Role, Pass) hanya akan dibaca pada baris PERTAMA untuk setiap username unik. Baris selanjutnya dengan username sama akan diabaikan datanya.',
        'I5' => 'Guru Baru',
        'J5' => 'Jika Username belum ada di sistem, guru baru akan dibuatkan.',
        'I6' => 'Penugasan (Mapel/Kelas)',
        'J6' => 'Jika Role="guru", Kode Mapel dan Nama Kelas WAJIB diisi. Gunakan data dari sheet "Daftar Mapel" dan "Daftar Kelas Aktif".',
        'I7' => 'Admin',
        'J7' => 'Jika Role="admin", kolom Kode Mapel dan Nama Kelas bisa dikosongkan.',
        'I8' => 'Tahun Ajaran',
        'J8' => 'Semua penugasan mengajar akan otomatis dimasukkan ke TAHUN AJARAN AKTIF saat ini.',
    ];

    foreach ($instructions as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    $sheet->getStyle('I3:I8')->getFont()->setBold(true);
    $sheet->getColumnDimension('I')->setWidth(20);
    $sheet->getColumnDimension('J')->setWidth(70);
    // Wrap text untuk instruksi
    $sheet->getStyle('J3:J8')->getAlignment()->setWrapText(true);


    // --- SHEET 2: DAFTAR MAPEL ---
    $mapelSheet = new Worksheet($spreadsheet, 'Daftar Mapel');
    $spreadsheet->addSheet($mapelSheet, 1);
    
    $mapelSheet->setCellValue('A1', 'KODE MAPEL (Untuk di-copy)');
    $mapelSheet->setCellValue('B1', 'NAMA MATA PELAJARAN');
    $mapelSheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    $mapelSheet->getColumnDimension('A')->setWidth(30);
    $mapelSheet->getColumnDimension('B')->setWidth(40);

    // Ambil data mapel dari DB
    $query_mapel = mysqli_query($koneksi, "SELECT kode_mapel, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
    $row = 2;
    while ($mapel = mysqli_fetch_assoc($query_mapel)) {
        $mapelSheet->setCellValue('A' . $row, $mapel['kode_mapel']);
        $mapelSheet->setCellValue('B' . $row, $mapel['nama_mapel']);
        $row++;
    }

    // --- SHEET 3: DAFTAR KELAS AKTIF ---
    $kelasSheet = new Worksheet($spreadsheet, 'Daftar Kelas Aktif');
    $spreadsheet->addSheet($kelasSheet, 2);
    
    $kelasSheet->setCellValue('A1', 'NAMA KELAS (Untuk di-copy)');
    $kelasSheet->setCellValue('B1', 'TAHUN AJARAN AKTIF');
    $kelasSheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    $kelasSheet->getColumnDimension('A')->setWidth(30);
    $kelasSheet->getColumnDimension('B')->setWidth(30);

    if ($id_tahun_ajaran_aktif > 0) {
        // Ambil data kelas dari DB
        $query_kelas = mysqli_query($koneksi, "SELECT k.nama_kelas, ta.tahun_ajaran FROM kelas k JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran WHERE k.id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY k.nama_kelas ASC");
        $row = 2;
        while ($kelas = mysqli_fetch_assoc($query_kelas)) {
            $kelasSheet->setCellValue('A' . $row, $kelas['nama_kelas']);
            $kelasSheet->setCellValue('B' . $row, $kelas['tahun_ajaran']);
            $row++;
        }
    } else {
        $kelasSheet->setCellValue('A2', 'Tidak ada tahun ajaran aktif yang ditemukan.');
        $kelasSheet->mergeCells('A2:B2');
    }
    
    // Set sheet aktif kembali ke sheet pertama
    $spreadsheet->setActiveSheetIndex(0);

    // --- PROSES DOWNLOAD ---
    $filename = 'template_import_guru_dan_mengajar.xlsx';
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