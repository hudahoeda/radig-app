<?php
// Tahan output agar file biner Excel tidak rusak
ob_start(); 
session_start();

// Tampilkan error jika ada masalah sistem
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'koneksi.php';

// Cek Autoload PhpSpreadsheet
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
} else {
    while (ob_get_level()) { ob_end_clean(); }
    die("Kritis: Folder Vendor Tidak Ditemukan! Pastikan 'composer install' sudah dijalankan.");
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Validasi role Guru
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header("Location: dashboard.php");
    exit;
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id_pembina = $_SESSION['id_guru'];

/**
 * Fungsi Helper untuk Simpan Data per Siswa (Mendukung Database Tanpa Unique Key)
 */
function prosesSimpanSiswa($koneksi, $id_p, $semester, $hadir, $total, $nilai_array) {
    // 1. Simpan Kehadiran (Hanya jika diisi)
    if (trim($hadir) !== "") {
        $h_val = (int)$hadir;
        $q_cek_h = mysqli_query($koneksi, "SELECT id_peserta_ekskul FROM ekskul_kehadiran WHERE id_peserta_ekskul = $id_p AND semester = $semester");
        
        if ($q_cek_h && mysqli_num_rows($q_cek_h) > 0) {
            $stmt_h = mysqli_prepare($koneksi, "UPDATE ekskul_kehadiran SET jumlah_hadir = ?, total_pertemuan = ? WHERE id_peserta_ekskul = ? AND semester = ?");
            mysqli_stmt_bind_param($stmt_h, "iiii", $h_val, $total, $id_p, $semester);
            mysqli_stmt_execute($stmt_h);
        } else {
            $stmt_h = mysqli_prepare($koneksi, "INSERT INTO ekskul_kehadiran (id_peserta_ekskul, semester, jumlah_hadir, total_pertemuan) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_h, "iiii", $id_p, $semester, $h_val, $total);
            mysqli_stmt_execute($stmt_h);
        }
    }

    // 2. Simpan Nilai TP
    foreach ($nilai_array as $id_tujuan => $nilai) {
        $nilai = trim($nilai);
        if ($nilai !== "") {
            $q_cek_n = mysqli_query($koneksi, "SELECT id_peserta_ekskul FROM ekskul_penilaian WHERE id_peserta_ekskul = $id_p AND id_tujuan_ekskul = $id_tujuan");
            
            if ($q_cek_n && mysqli_num_rows($q_cek_n) > 0) {
                $stmt_n = mysqli_prepare($koneksi, "UPDATE ekskul_penilaian SET nilai = ? WHERE id_peserta_ekskul = ? AND id_tujuan_ekskul = ?");
                mysqli_stmt_bind_param($stmt_n, "sii", $nilai, $id_p, $id_tujuan);
                mysqli_stmt_execute($stmt_n);
            } else {
                $stmt_n = mysqli_prepare($koneksi, "INSERT INTO ekskul_penilaian (id_peserta_ekskul, id_tujuan_ekskul, nilai) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt_n, "iis", $id_p, $id_tujuan, $nilai);
                mysqli_stmt_execute($stmt_n);
            }
        }
    }
}

// ROUTING AKSI
switch ($aksi) {
    case 'simpan_penilaian':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        $id_ekskul = (int)$_POST['id_ekskul'];
        $semester = (int)$_POST['semester'];
        $data_hadir = isset($_POST['kehadiran']) ? $_POST['kehadiran'] : [];
        $data_nilai = isset($_POST['penilaian']) ? $_POST['penilaian'] : [];
        $total_umum = (int)$_POST['total_pertemuan_umum'];

        mysqli_begin_transaction($koneksi);
        try {
            foreach ($data_hadir as $id_p => $hadir) {
                $nilai_siswa = isset($data_nilai[$id_p]) ? $data_nilai[$id_p] : [];
                
                $ada_nilai = false;
                foreach($nilai_siswa as $ns) if(trim($ns) !== '') $ada_nilai = true;

                // Simpan jika kehadiran diisi ATAU ada nilai TP yang diisi
                if (trim($hadir) !== "" || $ada_nilai) {
                    prosesSimpanSiswa($koneksi, $id_p, $semester, $hadir, $total_umum, $nilai_siswa);
                }
            }
            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Berhasil menyimpan perubahan nilai.";
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['error'] = "Gagal menyimpan: " . $e->getMessage();
        }
        header("Location: pembina_penilaian_ekskul.php?ekskul_id=$id_ekskul");
        exit;

    case 'unduh_template':
        $id_ekskul = (int)($_GET['ekskul_id'] ?? 0);
        $semester = (int)($_GET['semester'] ?? 1);

        if ($id_ekskul == 0) {
            header("Location: pembina_penilaian_ekskul.php");
            exit;
        }

        try {
            $q_e = mysqli_query($koneksi, "SELECT nama_ekskul FROM ekstrakurikuler WHERE id_ekskul = $id_ekskul LIMIT 1");
            $row_e = $q_e ? mysqli_fetch_assoc($q_e) : null;
            $nama_ekskul = $row_e ? $row_e['nama_ekskul'] : 'Ekskul';

            $q_tujuan = mysqli_query($koneksi, "SELECT id_tujuan_ekskul FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul AND semester = $semester ORDER BY id_tujuan_ekskul");
            $tp_list = [];
            if ($q_tujuan) {
                while($t = mysqli_fetch_assoc($q_tujuan)) $tp_list[] = $t['id_tujuan_ekskul'];
            }

            if (empty($tp_list)) {
                throw new Exception("Tujuan Pembelajaran belum dibuat. Buat TP terlebih dahulu.");
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Penilaian');

            $headers = ['ID_PESERTA', 'NAMA_SISWA', 'NIS', 'KEHADIRAN'];
            foreach($tp_list as $id_tp) $headers[] = "TP_$id_tp";

            $col = 'A';
            foreach($headers as $h) {
                $sheet->setCellValue($col . '1', $h);
                $sheet->getStyle($col . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4E73DF');
                $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $col++;
            }

            $q_peserta = mysqli_query($koneksi, "
                SELECT p.id_peserta_ekskul, s.nama_lengkap, s.nis 
                FROM ekskul_peserta p 
                JOIN siswa s ON p.id_siswa = s.id_siswa 
                WHERE p.id_ekskul = $id_ekskul 
                ORDER BY s.nama_lengkap ASC
            ");

            $rowNum = 2;
            if ($q_peserta) {
                while($p = mysqli_fetch_assoc($q_peserta)) {
                    $id_p = $p['id_peserta_ekskul'];
                    $sheet->setCellValue('A' . $rowNum, $id_p);
                    $sheet->setCellValue('B' . $rowNum, $p['nama_lengkap']);
                    $sheet->setCellValueExplicit('C' . $rowNum, $p['nis'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    
                    $q_h = mysqli_query($koneksi, "SELECT jumlah_hadir FROM ekskul_kehadiran WHERE id_peserta_ekskul = $id_p AND semester = $semester");
                    $row_h = $q_h ? mysqli_fetch_assoc($q_h) : null;
                    $sheet->setCellValue('D' . $rowNum, $row_h ? $row_h['jumlah_hadir'] : '');

                    $colNilai = 'E';
                    foreach($tp_list as $id_tp) {
                        $q_n = mysqli_query($koneksi, "SELECT nilai FROM ekskul_penilaian WHERE id_peserta_ekskul = $id_p AND id_tujuan_ekskul = $id_tp");
                        $row_n = $q_n ? mysqli_fetch_assoc($q_n) : null;
                        $sheet->setCellValue($colNilai . $rowNum, $row_n ? $row_n['nilai'] : '');
                        $colNilai++;
                    }
                    $rowNum++;
                }
            }

            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="Template_Nilai_'.str_replace(' ', '_', $nama_ekskul).'_Smt'.$semester.'.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Throwable $th) {
            while (ob_get_level()) { ob_end_clean(); }
            die("<div style='background:#fff3f3; color:#880000; padding:20px; font-family:sans-serif;'>
                 <b>Gagal Download Excel:</b> " . $th->getMessage() . "
                 </div>");
        }

    case 'impor_excel':
        $id_ekskul = (int)($_POST['id_ekskul'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 1);

        if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "File tidak valid.";
            header("Location: pembina_penilaian_ekskul.php?ekskul_id=$id_ekskul");
            exit;
        }

        try {
            $spreadsheet = IOFactory::load($_FILES['file_excel']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();
            $header = array_shift($data);

            // Bersihkan Header dari spasi ekstra
            $tp_map = [];
            foreach($header as $idx => $label) {
                $label_clean = trim($label ?? '');
                if($label_clean && strpos($label_clean, 'TP_') === 0) {
                    $tp_map[$idx] = str_replace('TP_', '', $label_clean);
                }
            }

            $q_tot = mysqli_query($koneksi, "SELECT total_pertemuan FROM ekskul_kehadiran WHERE semester = $semester AND id_peserta_ekskul IN (SELECT id_peserta_ekskul FROM ekskul_peserta WHERE id_ekskul = $id_ekskul) LIMIT 1");
            $row_tot = $q_tot ? mysqli_fetch_assoc($q_tot) : null;
            $total_umum = $row_tot ? (int)$row_tot['total_pertemuan'] : 16;

            mysqli_begin_transaction($koneksi);
            $count = 0;
            
            foreach($data as $row) {
                if(empty($row[0])) continue;
                
                $id_p = (int)$row[0];
                $hadir = ($row[3] === "" || $row[3] === null) ? "" : trim($row[3]);
                
                $has_nilai = false;
                $nilai_siswa = [];
                
                foreach($tp_map as $idx => $id_tp) {
                    $val = trim($row[$idx] ?? '');
                    if ($val !== '') {
                        $has_nilai = true;
                        
                        // NORMALISASI: Jadikan "SB", "Baik", dll terbaca sempurna oleh UI
                        $val_upper = strtoupper($val);
                        if ($val_upper === 'SB' || stripos($val_upper, 'SANGAT') !== false) {
                            $val = 'Sangat Baik';
                        } elseif ($val_upper === 'B' || strtolower($val) === 'baik') {
                            $val = 'Baik';
                        } elseif ($val_upper === 'C' || stripos($val_upper, 'CUKUP') !== false) {
                            $val = 'Cukup';
                        } elseif ($val_upper === 'K' || stripos($val_upper, 'KURANG') !== false) {
                            $val = 'Kurang';
                        }
                    }
                    $nilai_siswa[$id_tp] = $val;
                }
                
                // Simpan jika ada kehadiran ATAU ada setidaknya 1 nilai yang diisi
                if($hadir !== "" || $has_nilai) {
                    prosesSimpanSiswa($koneksi, $id_p, $semester, $hadir, $total_umum, $nilai_siswa);
                    $count++;
                }
            }
            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Berhasil mengimpor data $count siswa.";
        } catch (\Throwable $th) {
            mysqli_rollback($koneksi);
            $_SESSION['error'] = "Gagal memproses Excel: " . $th->getMessage();
        }
        header("Location: pembina_penilaian_ekskul.php?ekskul_id=$id_ekskul");
        exit;

    default:
        header("Location: pembina_penilaian_ekskul.php");
        exit;
}