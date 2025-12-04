<?php
session_start();
// Wajib load library PhpSpreadsheet
require 'vendor/autoload.php'; 
include 'koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Validasi role Guru
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru' || !isset($_SESSION['id_guru'])) {
    $_SESSION['error'] = "Akses ditolak. Silakan login kembali.";
    header("Location: login.php");
    exit;
}

$aksi = $_GET['aksi'] ?? '';
$id_pembina = $_SESSION['id_guru'];

// Fungsi Helper: Konversi Huruf ke Predikat Lengkap
function konversi_nilai_ekskul($input) {
    $n = strtoupper(trim($input));
    if ($n == 'SB' || $n == 'SANGAT BAIK') return 'Sangat Baik';
    if ($n == 'B' || $n == 'BAIK') return 'Baik';
    if ($n == 'C' || $n == 'CUKUP') return 'Cukup';
    if ($n == 'K' || $n == 'KURANG') return 'Kurang';
    return null;
}

// Fungsi Cek Kewenangan
function cek_kewenangan($koneksi, $id_ekskul, $id_pembina) {
    $stmt = mysqli_prepare($koneksi, "SELECT id_ekskul FROM ekstrakurikuler WHERE id_ekskul = ? AND id_pembina = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id_ekskul, $id_pembina);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt)->num_rows > 0;
}

switch ($aksi) {
    // ==========================================================
    // 1. SIMPAN MANUAL (Logic tetap sama)
    // ==========================================================
    case 'simpan_penilaian':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: pembina_penilaian_ekskul.php");
            exit();
        }

        $id_ekskul = (int)$_POST['id_ekskul'];
        $semester = (int)$_POST['semester'];
        $data_kehadiran = $_POST['kehadiran'] ?? [];
        $data_penilaian = $_POST['penilaian'] ?? [];
        $total_pertemuan_umum = (int)($_POST['total_pertemuan_umum'] ?? 0);

        if (!cek_kewenangan($koneksi, $id_ekskul, $id_pembina)) {
            $_SESSION['error'] = "Anda tidak berhak menilai ekskul ini.";
            header("Location: pembina_penilaian_ekskul.php");
            exit;
        }

        mysqli_begin_transaction($koneksi);
        try {
            // Simpan Kehadiran
            $stmt_kehadiran = mysqli_prepare($koneksi, "
                INSERT INTO ekskul_kehadiran (id_peserta_ekskul, semester, jumlah_hadir, total_pertemuan) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE jumlah_hadir = VALUES(jumlah_hadir), total_pertemuan = VALUES(total_pertemuan)
            ");

            foreach ($data_kehadiran as $id_peserta => $jumlah_hadir) {
                $h = (int)$jumlah_hadir;
                mysqli_stmt_bind_param($stmt_kehadiran, "iiii", $id_peserta, $semester, $h, $total_pertemuan_umum);
                mysqli_stmt_execute($stmt_kehadiran);
            }

            // Simpan Nilai
            $stmt_penilaian = mysqli_prepare($koneksi, "
                INSERT INTO ekskul_penilaian (id_peserta_ekskul, id_tujuan_ekskul, nilai)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
            ");

            foreach ($data_penilaian as $id_peserta => $penilaian) {
                foreach ($penilaian as $id_tujuan => $nilai) {
                    if (!empty($nilai)) {
                        mysqli_stmt_bind_param($stmt_penilaian, "iis", $id_peserta, $id_tujuan, $nilai);
                        mysqli_stmt_execute($stmt_penilaian);
                    }
                }
            }

            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Data penilaian berhasil disimpan.";

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['error'] = "Gagal menyimpan data.";
        }
        header("Location: pembina_penilaian_ekskul.php?ekskul_id=" . $id_ekskul);
        exit();
        break;

    // ==========================================================
    // 2. DOWNLOAD TEMPLATE EXCEL (Menggunakan PhpSpreadsheet)
    // ==========================================================
    case 'download_template_excel':
        $id_ekskul = (int)$_GET['id_ekskul'];
        $semester = (int)$_GET['semester'];

        if (!cek_kewenangan($koneksi, $id_ekskul, $id_pembina)) die("Akses Ditolak");

        // Ambil Data Ekskul & Kelas
        $q_info = mysqli_query($koneksi, "SELECT nama_ekskul FROM ekstrakurikuler WHERE id_ekskul = $id_ekskul");
        $nama_ekskul = mysqli_fetch_assoc($q_info)['nama_ekskul'];

        // Ambil Data Peserta (Join Kelas)
        $q_peserta = mysqli_query($koneksi, "
            SELECT p.id_peserta_ekskul, s.nama_lengkap, k.nama_kelas 
            FROM ekskul_peserta p 
            JOIN siswa s ON p.id_siswa = s.id_siswa 
            JOIN kelas k ON s.id_kelas = k.id_kelas
            WHERE p.id_ekskul = $id_ekskul 
            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC
        ");

        // Ambil Tujuan Pembelajaran (Header Dinamis)
        $q_tujuan = mysqli_query($koneksi, "SELECT id_tujuan_ekskul, deskripsi_tujuan FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul AND semester = $semester ORDER BY id_tujuan_ekskul ASC");
        $list_tujuan = mysqli_fetch_all($q_tujuan, MYSQLI_ASSOC);

        // --- MULAI MEMBUAT SPREADSHEET ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header Visual (Baris 1)
        $sheet->setCellValue('A1', 'ID Siswa');
        $sheet->setCellValue('B1', 'Nama Siswa');
        $sheet->setCellValue('C1', 'Kelas');
        $sheet->setCellValue('D1', 'Jumlah Hadir');
        
        // Header System (Baris 2 - Hidden)
        $sheet->setCellValue('A2', 'id_peserta');
        $sheet->setCellValue('B2', 'nama');
        $sheet->setCellValue('C2', 'kelas');
        $sheet->setCellValue('D2', 'kehadiran');

        $col = 5; // Mulai dari Kolom E (Indeks 5)
        foreach ($list_tujuan as $t) {
            $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            
            // Baris 1: Deskripsi Tujuan (Agar guru tahu ini nilai apa)
            $sheet->setCellValue($colStr . '1', $t['deskripsi_tujuan']);
            
            // Baris 2: ID Tujuan (Untuk sistem mengenali)
            $sheet->setCellValue($colStr . '2', $t['id_tujuan_ekskul']); // PENTING: Ini ID Tujuan

            // Styling Header Tujuan
            $sheet->getColumnDimension($colStr)->setWidth(20);
            $sheet->getStyle($colStr . '1')->getAlignment()->setWrapText(true); // Wrap text jika panjang
            
            $col++;
        }

        // Isi Data Siswa (Mulai Baris 3)
        $row = 3;
        while ($siswa = mysqli_fetch_assoc($q_peserta)) {
            $sheet->setCellValue('A' . $row, $siswa['id_peserta_ekskul']);
            $sheet->setCellValue('B' . $row, $siswa['nama_lengkap']);
            $sheet->setCellValue('C' . $row, $siswa['nama_kelas']);
            // Kolom D (Kehadiran) & E dst (Nilai) dikosongkan untuk diisi guru
            $row++;
        }

        // --- STYLING ---
        $lastColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
        
        // Style Header Utama (Biru)
        $sheet->getStyle('A1:' . $lastColStr . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005A8D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getRowDimension('1')->setRowHeight(40); // Agak tinggi untuk deskripsi tujuan

        // Sembunyikan Baris 2 (Baris ID System)
        $sheet->getRowDimension('2')->setVisible(false);

        // Sembunyikan Kolom A (ID Peserta) agar tidak diubah guru
        $sheet->getColumnDimension('A')->setVisible(false);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);

        // Freeze Pane (Agar header dan nama tetap terlihat saat scroll)
        $sheet->freezePane('E3');

        $filename = "Nilai_Ekskul_" . preg_replace('/[^A-Za-z0-9]/', '_', $nama_ekskul) . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheet.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        break;

    // ==========================================================
    // 3. IMPORT NILAI EXCEL
    // ==========================================================
    case 'import_nilai_excel':
        $id_ekskul = (int)$_POST['id_ekskul'];
        $semester = (int)$_POST['semester'];
        $total_pertemuan = (int)$_POST['total_pertemuan_import'];

        if (!cek_kewenangan($koneksi, $id_ekskul, $id_pembina)) {
            $_SESSION['error'] = "Akses ditolak.";
            header("Location: pembina_penilaian_ekskul.php?ekskul_id=" . $id_ekskul);
            exit;
        }

        if (!isset($_FILES['file_nilai']) || $_FILES['file_nilai']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Gagal upload file atau file tidak ada.";
            header("Location: pembina_penilaian_ekskul.php?ekskul_id=" . $id_ekskul);
            exit;
        }

        try {
            // Load Excel
            $spreadsheet = IOFactory::load($_FILES['file_nilai']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColStr = $sheet->getHighestColumn();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColStr);

            if ($highestRow < 3) throw new Exception("File kosong atau format salah.");

            // 1. PETAKAN KOLOM (BACA BARIS 2 YANG DISEMBUNYIKAN)
            // Kita cari kolom mana yang kehadiran, kolom mana yang tujuan ID X
            $map_kolom = []; // [IndexCol => 'kehadiran' atau ID_Tujuan]
            
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $headerVal = trim($sheet->getCell($colStr . '2')->getValue()); // Baca baris 2
                
                if ($headerVal == 'id_peserta') $map_kolom['id_peserta'] = $colStr;
                elseif ($headerVal == 'kehadiran') $map_kolom['kehadiran'] = $colStr;
                elseif (is_numeric($headerVal)) {
                    // Jika angka, berarti ini ID Tujuan Ekskul
                    $map_kolom['tujuan'][$headerVal] = $colStr; // ID Tujuan => Kolom Excel (misal: 'E')
                }
            }

            if (!isset($map_kolom['id_peserta'])) throw new Exception("Format Template Salah: Kolom ID Peserta tidak ditemukan.");

            mysqli_begin_transaction($koneksi);
            
            $stmt_hadir = mysqli_prepare($koneksi, "INSERT INTO ekskul_kehadiran (id_peserta_ekskul, semester, jumlah_hadir, total_pertemuan) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE jumlah_hadir = VALUES(jumlah_hadir), total_pertemuan = VALUES(total_pertemuan)");
            
            $stmt_nilai = mysqli_prepare($koneksi, "INSERT INTO ekskul_penilaian (id_peserta_ekskul, id_tujuan_ekskul, nilai) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");

            $count_sukses = 0;

            // 2. LOOP DATA (MULAI BARIS 3)
            for ($row = 3; $row <= $highestRow; $row++) {
                $colId = $map_kolom['id_peserta'];
                $id_peserta = $sheet->getCell($colId . $row)->getValue();

                if (empty($id_peserta) || !is_numeric($id_peserta)) continue;

                // A. Proses Kehadiran
                if (isset($map_kolom['kehadiran'])) {
                    $jml_hadir = (int)$sheet->getCell($map_kolom['kehadiran'] . $row)->getValue();
                    mysqli_stmt_bind_param($stmt_hadir, "iiii", $id_peserta, $semester, $jml_hadir, $total_pertemuan);
                    mysqli_stmt_execute($stmt_hadir);
                }

                // B. Proses Nilai per Tujuan
                if (isset($map_kolom['tujuan'])) {
                    foreach ($map_kolom['tujuan'] as $id_tujuan => $colStr) {
                        $raw_nilai = $sheet->getCell($colStr . $row)->getValue();
                        $nilai_db = konversi_nilai_ekskul($raw_nilai); // Ubah SB jadi Sangat Baik

                        if ($nilai_db) {
                            mysqli_stmt_bind_param($stmt_nilai, "iis", $id_peserta, $id_tujuan, $nilai_db);
                            mysqli_stmt_execute($stmt_nilai);
                        }
                    }
                }
                $count_sukses++;
            }

            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Berhasil mengimport data untuk $count_sukses siswa.";

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['error'] = "Gagal Import: " . $e->getMessage();
        }

        header("Location: pembina_penilaian_ekskul.php?ekskul_id=" . $id_ekskul);
        exit;
        break;

    default:
        header("Location: dashboard.php");
        exit;
}
?>