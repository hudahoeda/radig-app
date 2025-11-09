<?php
session_start();
require 'vendor/autoload.php';
include 'koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Validasi role
if ($_SESSION['role'] != 'guru') {
    die("Error: Akses ditolak.");
}

// Validasi ID
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
$id_mapel = isset($_GET['id_mapel']) ? (int)$_GET['id_mapel'] : 0;
$id_guru = (int)$_SESSION['id_guru'];

if ($id_kelas == 0 || $id_mapel == 0) {
    die("Error: Informasi Kelas atau Mata Pelajaran tidak lengkap.");
}

// Ambil data info kelas dan mapel
$q_info = mysqli_query($koneksi, "SELECT k.nama_kelas, m.nama_mapel FROM kelas k, mata_pelajaran m WHERE k.id_kelas = $id_kelas AND m.id_mapel = $id_mapel");
$info = mysqli_fetch_assoc($q_info);
$nama_kelas = $info['nama_kelas'] ?? 'N/A';
$nama_mapel = $info['nama_mapel'] ?? 'N/A';

// 1. Ambil daftar siswa
$q_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif' ORDER BY nama_lengkap ASC");
$daftar_siswa = [];
while ($s = mysqli_fetch_assoc($q_siswa)) {
    $daftar_siswa[] = $s;
}

// 2. Ambil daftar penilaian
$q_penilaian = mysqli_query($koneksi, "SELECT id_penilaian, nama_penilaian, jenis_penilaian FROM penilaian WHERE id_kelas = $id_kelas AND id_mapel = $id_mapel AND id_guru = $id_guru ORDER BY jenis_penilaian, tanggal_penilaian, id_penilaian");
$daftar_penilaian = [];
$daftar_id_penilaian = [];
while ($p = mysqli_fetch_assoc($q_penilaian)) {
    $daftar_penilaian[] = $p;
    $daftar_id_penilaian[] = $p['id_penilaian'];
}

// 3. Ambil nilai yang sudah ada
$nilai_tersimpan = [];
if (!empty($daftar_id_penilaian)) {
    $id_penilaian_str = implode(',', $daftar_id_penilaian);
    $q_nilai = mysqli_query($koneksi, "SELECT id_siswa, id_penilaian, nilai FROM penilaian_detail_nilai WHERE id_penilaian IN ($id_penilaian_str)");
    while ($n = mysqli_fetch_assoc($q_nilai)) {
        $nilai_tersimpan[$n['id_siswa']][$n['id_penilaian']] = $n['nilai'];
    }
}

// Membuat objek spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Menulis header kolom
$sheet->setCellValue('A1', 'ID Siswa');
$sheet->setCellValue('B1', 'Nama Siswa');
$sheet->setCellValue('A2', '(ID Siswa - JANGAN DIUBAH)');
$sheet->setCellValue('B2', '(Nama Siswa)');

// Menulis header penilaian
$col = 3; // Mulai dari kolom 'C'
foreach ($daftar_penilaian as $penilaian) {
    $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    
    // Baris 1: Nama Penilaian
    $sheet->setCellValue($colStr . '1', $penilaian['nama_penilaian']);
    // Baris 2: ID Penilaian (untuk import)
    $sheet->setCellValue($colStr . '2', $penilaian['id_penilaian']);

    $sheet->getColumnDimension($colStr)->setWidth(20);
    
    // Beri warna beda untuk Formatif / Sumatif
    if ($penilaian['jenis_penilaian'] == 'Formatif') {
        $sheet->getStyle($colStr . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCEE0'); // Kuning muda
    } else {
        $sheet->getStyle($colStr . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6F2F5'); // Biru muda
    }
    
    $col++;
}

// Mengisi data siswa dan nilai
$row = 3; // Mulai dari baris 3
foreach ($daftar_siswa as $siswa) {
    $sheet->setCellValue('A' . $row, $siswa['id_siswa']);
    $sheet->setCellValue('B' . $row, $siswa['nama_lengkap']);

    $col = 3; // Mulai dari kolom 'C'
    foreach ($daftar_penilaian as $penilaian) {
        $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $nilai = $nilai_tersimpan[$siswa['id_siswa']][$penilaian['id_penilaian']] ?? '';
        $sheet->setCellValue($colStr . $row, $nilai);
        $col++;
    }
    $row++;
}

// Styling
$highestColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
$highestRow = $row - 1;

// Header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005A8D']]
];
$sheet->getStyle('A1:' . $highestColStr . '1')->applyFromArray($headerStyle);

// ID Row (baris 2)
$idRowStyle = [
    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '555555']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']]
];
$sheet->getStyle('A2:' . $highestColStr . '2')->applyFromArray($idRowStyle);
// Sembunyikan baris 2
$sheet->getRowDimension('2')->setVisible(false);

// Sembunyikan kolom A (ID Siswa)
$sheet->getColumnDimension('A')->setVisible(false);
$sheet->getColumnDimension('B')->setWidth(40);

// Freeze pane
$sheet->freezePane('C3');

// Judul Sheet
$sheet->setTitle(substr("Nilai $nama_kelas", 0, 31));

// Membersihkan nama file
$safe_kelas = preg_replace('/[^A-Za-z0-9-]/', '', $nama_kelas);
$safe_mapel = preg_replace('/[^A-Za-z0-9-]/', '', $nama_mapel);
$filename = "template-batch-$safe_kelas-$safe_mapel.xlsx";

// Mengatur header untuk download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheet.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
