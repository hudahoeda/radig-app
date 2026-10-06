<?php
session_start();
include 'koneksi.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    die("Akses Ditolak");
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

// Ambil data kelas
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);
$id_kelas = $kelas['id_kelas'] ?? 0;

if (!$id_kelas) {
    die("Anda bukan wali kelas di tahun ajaran ini.");
}

$nama_kelas = $kelas['nama_kelas'];

// Ambil data siswa
$query_siswa = "SELECT s.id_siswa, s.nisn, s.nama_lengkap, r.sakit, r.izin, r.tanpa_keterangan, r.catatan_wali_kelas
                FROM siswa s
                LEFT JOIN rapor r ON s.id_siswa = r.id_siswa AND r.semester = $semester_aktif AND r.id_tahun_ajaran = $id_tahun_ajaran
                WHERE s.id_kelas = $id_kelas ORDER BY s.nama_lengkap ASC";
$result_siswa = mysqli_query($koneksi, $query_siswa);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Absensi');

// Header Style
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF198754']], // Bootstrap success color
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

// Set Header Row 1 (Info)
$sheet->setCellValue('A1', 'TEMPLATE IMPORT ABSENSI & CATATAN WALI KELAS');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', 'Kelas: ' . $nama_kelas);
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Set Headers Row 4
$headers = ['ID Siswa (JANGAN DIUBAH)', 'NISN', 'Nama Lengkap', 'Sakit', 'Izin', 'Alpha', 'Catatan Wali Kelas'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '4', $h);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}
$sheet->getColumnDimension('G')->setAutoSize(false);
$sheet->getColumnDimension('G')->setWidth(50);
$sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

// Populate Data
$row = 5;
while ($s = mysqli_fetch_assoc($result_siswa)) {
    $sheet->setCellValue('A' . $row, $s['id_siswa']);
    $sheet->setCellValueExplicit('B' . $row, $s['nisn'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $row, $s['nama_lengkap']);
    $sheet->setCellValue('D' . $row, $s['sakit'] ?? 0);
    $sheet->setCellValue('E' . $row, $s['izin'] ?? 0);
    $sheet->setCellValue('F' . $row, $s['tanpa_keterangan'] ?? 0);
    $sheet->setCellValue('G' . $row, $s['catatan_wali_kelas'] ?? '');

    // Protect ID, NISN, Nama (UI indication)
    $sheet->getStyle('A'.$row.':C'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

    $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray($dataStyle);
    $row++;
}

$filename = 'Template_Absensi_' . str_replace(' ', '_', $nama_kelas) . '_' . date('Ymd') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
