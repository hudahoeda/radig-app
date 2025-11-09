<?php
session_start();
include 'koneksi.php';

// Memanggil autoloader Composer di awal agar bisa digunakan di semua aksi
require 'vendor/autoload.php';

// Validasi role, guru atau admin bisa melakukan aksi
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    $_SESSION['pesan'] = "{icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak memiliki wewenang.'}";
    header("location: dashboard.php");
    exit();
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id_guru = (int)($_SESSION['id_guru'] ?? 0); // Pastikan id_guru ada

switch ($aksi) {
    //======================================================================
    // AKSI TAMBAH PENILAIAN (BATCH)
    //======================================================================
    case 'tambah_penilaian':
        $id_kelas = (int)$_POST['id_kelas'];
        $id_mapel = (int)$_POST['id_mapel'];
        $daftar_penilaian = $_POST['penilaian'] ?? [];

        if (empty($daftar_penilaian)) {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal', text: 'Tidak ada data penilaian yang dikirim.'}";
            header("location: penilaian_tambah.php?id_kelas=$id_kelas&id_mapel=$id_mapel");
            exit();
        }

        $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
        $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

        $berhasil_disimpan = 0;
        $gagal_disimpan = 0;
        $pesan_error = [];

        foreach ($daftar_penilaian as $data) {
            $nama_penilaian = strip_tags($data['nama_penilaian']);
            $jenis_penilaian = $data['jenis_penilaian'];
            $tanggal_penilaian = $data['tanggal_penilaian'];
            $id_tp_array = $data['id_tp'] ?? [];
            $subjenis_penilaian = NULL;
            $bobot_penilaian = 1;

            // Validasi input per baris
            if (empty($nama_penilaian) || empty($jenis_penilaian) || empty($tanggal_penilaian)) {
                $gagal_disimpan++;
                $pesan_error[] = "Data tidak lengkap untuk: " . ($nama_penilaian ?: 'Penilaian Tanpa Nama');
                continue;
            }

            if ($jenis_penilaian == 'Sumatif') {
                if (empty($data['subjenis_penilaian'])) {
                    $gagal_disimpan++;
                    $pesan_error[] = "Sub-Jenis Sumatif wajib diisi untuk: $nama_penilaian";
                    continue;
                }
                $subjenis_penilaian = $data['subjenis_penilaian'];
                $bobot_penilaian = (int)$data['bobot_penilaian'];
                if ($bobot_penilaian < 1) { $bobot_penilaian = 1; }
                
                if ($subjenis_penilaian == 'Sumatif TP' && empty($id_tp_array)) {
                    $gagal_disimpan++;
                    $pesan_error[] = "Sumatif TP wajib memilih TP untuk: $nama_penilaian";
                    continue;
                }
            } else { // Jika Formatif
                if (empty($id_tp_array)) {
                    $gagal_disimpan++;
                    $pesan_error[] = "Formatif wajib memilih TP untuk: $nama_penilaian";
                    continue;
                }
            }
            
            // Jika validasi lolos, mulai transaksi DB
            mysqli_begin_transaction($koneksi);
            try {
                // Insert ke tabel penilaian
                $stmt_penilaian = mysqli_prepare($koneksi, "INSERT INTO penilaian (id_kelas, id_mapel, id_guru, nama_penilaian, jenis_penilaian, subjenis_penilaian, bobot_penilaian, semester, tanggal_penilaian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_penilaian, "iiissssis", $id_kelas, $id_mapel, $id_guru, $nama_penilaian, $jenis_penilaian, $subjenis_penilaian, $bobot_penilaian, $semester_aktif, $tanggal_penilaian);
                
                if (!mysqli_stmt_execute($stmt_penilaian)) {
                    throw new Exception("Gagal menyimpan data penilaian utama ($nama_penilaian).");
                }
                $id_penilaian_baru = mysqli_insert_id($koneksi);

                // Insert ke tabel relasi penilaian_tp (jika bukan sumatif akhir)
                if (!empty($id_tp_array) && $subjenis_penilaian != 'Sumatif Akhir Semester' && $subjenis_penilaian != 'Sumatif Akhir Tahun') {
                    $stmt_penilaian_tp = mysqli_prepare($koneksi, "INSERT INTO penilaian_tp (id_penilaian, id_tp) VALUES (?, ?)");
                    foreach ($id_tp_array as $id_tp) {
                        mysqli_stmt_bind_param($stmt_penilaian_tp, "ii", $id_penilaian_baru, $id_tp);
                        if (!mysqli_stmt_execute($stmt_penilaian_tp)) {
                            throw new Exception("Gagal menyimpan relasi TP untuk: $nama_penilaian");
                        }
                    }
                }

                mysqli_commit($koneksi);
                $berhasil_disimpan++;

            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $gagal_disimpan++;
                $pesan_error[] = $e->getMessage();
            }
        } // end foreach

        // Siapkan pesan feedback
        if ($berhasil_disimpan > 0 && $gagal_disimpan == 0) {
            $_SESSION['pesan'] = "{icon: 'success', title: 'Berhasil', text: 'Sebanyak $berhasil_disimpan penilaian baru telah dibuat.'}";
        } elseif ($berhasil_disimpan > 0 && $gagal_disimpan > 0) {
            $_SESSION['pesan'] = "{icon: 'warning', title: 'Sebagian Berhasil', text: '$berhasil_disimpan penilaian dibuat, $gagal_disimpan gagal. Error: " . implode(', ', $pesan_error) . "'}";
        } else {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal Total', text: 'Semua $gagal_disimpan penilaian gagal dibuat. Error: " . implode(', ', $pesan_error) . "'}";
        }
        
        header("location: penilaian_tampil.php?id_kelas=$id_kelas&id_mapel=$id_mapel");
        exit();
        break;

    //======================================================================
    // AKSI SIMPAN NILAI (SINGLE) - Tidak diubah
    //======================================================================
    case 'simpan_nilai':
        // ... (logika simpan_nilai yang ada sebelumnya tetap di sini)
        $id_penilaian = (int)$_POST['id_penilaian'];
        $nilai_data = $_POST['nilai'];

        $q_penilaian = mysqli_query($koneksi, "SELECT id_kelas, id_mapel FROM penilaian WHERE id_penilaian=$id_penilaian");
        $d_penilaian = mysqli_fetch_assoc($q_penilaian);
        $id_kelas = $d_penilaian['id_kelas'];
        $id_mapel = $d_penilaian['id_mapel'];

        $stmt = mysqli_prepare($koneksi, "INSERT INTO penilaian_detail_nilai (id_penilaian, id_siswa, nilai) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        
        $berhasil_disimpan = 0;
        foreach ($nilai_data as $id_siswa => $nilai) {
            if ($nilai !== '' && is_numeric($nilai)) {
                $nilai_valid = (float)$nilai;
                if ($nilai_valid < 0) $nilai_valid = 0;
                if ($nilai_valid > 100) $nilai_valid = 100;

                mysqli_stmt_bind_param($stmt, "iid", $id_penilaian, $id_siswa, $nilai_valid);
                if(mysqli_stmt_execute($stmt)) {
                    $berhasil_disimpan++;
                }
            }
        }

        $_SESSION['pesan'] = "{icon: 'success', title: 'Berhasil', text: 'Sebanyak $berhasil_disimpan data nilai siswa berhasil disimpan.'}";
        header("location: penilaian_tampil.php?id_kelas=$id_kelas&id_mapel=$id_mapel");
        exit();
        break;

    //======================================================================
    // AKSI IMPORT NILAI (SINGLE) - Tidak diubah
    //======================================================================
    case 'import_nilai':
        // ... (logika import_nilai yang ada sebelumnya tetap di sini)
        $id_penilaian = (int)$_POST['id_penilaian'];
        $redirect_url = "penilaian_input_nilai.php?id_penilaian=" . $id_penilaian;

        if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal', text: 'Tidak ada file yang diunggah atau terjadi error.'}";
            header("Location: " . $redirect_url);
            exit;
        }
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file_excel']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            if ($highestRow <= 1) throw new Exception("File Excel tidak berisi data nilai.");
            $stmt = mysqli_prepare($koneksi, "INSERT INTO penilaian_detail_nilai (id_penilaian, id_siswa, nilai) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            $berhasil = 0;
            for ($row = 2; $row <= $highestRow; $row++) {
                $id_siswa = $sheet->getCell('A' . $row)->getValue();
                $nilai = $sheet->getCell('C' . $row)->getValue();
                if (!empty($id_siswa) && is_numeric($nilai)) {
                    $nilai_valid = (float)$nilai;
                    if ($nilai_valid < 0) $nilai_valid = 0;
                    if ($nilai_valid > 100) $nilai_valid = 100;
                    mysqli_stmt_bind_param($stmt, "iid", $id_penilaian, $id_siswa, $nilai_valid);
                    if (mysqli_stmt_execute($stmt)) $berhasil++;
                }
            }
            $_SESSION['pesan'] = "{icon: 'success', title: 'Berhasil', text: 'Sebanyak $berhasil data nilai berhasil diimpor.'}";
        } catch (Exception $e) {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal Memproses File', text: '" . $e->getMessage() . "'}";
        }
        header("Location: " . $redirect_url);
        exit;
        break;

    //======================================================================
    // AKSI BARU: IMPORT NILAI BATCH (DARI TEMPLATE KELAS)
    //======================================================================
    case 'import_nilai_batch':
        $id_kelas = (int)$_POST['id_kelas'];
        $id_mapel = (int)$_POST['id_mapel'];
        
        $redirect_url = "penilaian_tampil.php?id_kelas=$id_kelas&id_mapel=$id_mapel";

        if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal', text: 'Tidak ada file yang diunggah atau terjadi error.'}";
            header("Location: " . $redirect_url);
            exit;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file_excel']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColStr = $sheet->getHighestColumn();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColStr);

            if ($highestRow <= 2) { // Baris 1: Nama, Baris 2: ID
                throw new Exception("File Excel tidak berisi data nilai.");
            }

            // 1. Bangun peta kolom -> id_penilaian
            $col_map = [];
            for ($col = 3; $col <= $highestColIndex; $col++) { // Mulai dari C (indeks 3)
                $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $id_penilaian_cell = $sheet->getCell($colStr . '2')->getValue();
                if (is_numeric($id_penilaian_cell)) {
                    $col_map[$colStr] = (int)$id_penilaian_cell;
                }
            }

            if (empty($col_map)) {
                throw new Exception("Format template tidak valid. Tidak ditemukan ID Penilaian di baris kedua.");
            }

            // 2. Siapkan statement
            $stmt = mysqli_prepare($koneksi, "INSERT INTO penilaian_detail_nilai (id_penilaian, id_siswa, nilai) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");

            $berhasil = 0;
            // 3. Loop data siswa (mulai dari baris 3)
            for ($row = 3; $row <= $highestRow; $row++) {
                $id_siswa = $sheet->getCell('A' . $row)->getValue();
                if (empty($id_siswa) || !is_numeric($id_siswa)) continue; // Skip jika id siswa tidak valid

                // 4. Loop per penilaian (kolom)
                foreach ($col_map as $colStr => $id_penilaian) {
                    $nilai = $sheet->getCell($colStr . $row)->getValue();
                    
                    // Simpan hanya jika nilai diisi dan numerik
                    if ($nilai !== null && $nilai !== '' && is_numeric($nilai)) {
                        $nilai_valid = (float)$nilai;
                        if ($nilai_valid < 0) $nilai_valid = 0;
                        if ($nilai_valid > 100) $nilai_valid = 100;
                        
                        mysqli_stmt_bind_param($stmt, "iid", $id_penilaian, $id_siswa, $nilai_valid);
                        if (mysqli_stmt_execute($stmt)) {
                            $berhasil++;
                        }
                    }
                }
            }
            $_SESSION['pesan'] = "{icon: 'success', title: 'Berhasil', text: 'Sebanyak $berhasil data nilai berhasil diimpor/diperbarui.'}";

        } catch (Exception $e) {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal Memproses File', text: '" . htmlspecialchars($e->getMessage()) . "'}";
        }

        header("Location: " . $redirect_url);
        exit;
        break;


    //======================================================================
    // AKSI HAPUS PENILAIAN - Tidak diubah
    //======================================================================
    case 'hapus_penilaian':
        // ... (logika hapus_penilaian yang ada sebelumnya tetap di sini)
        $id_penilaian = isset($_GET['id_penilaian']) ? (int)$_GET['id_penilaian'] : 0;
        $stmt_cek = mysqli_prepare($koneksi, "SELECT id_kelas, id_mapel, id_guru FROM penilaian WHERE id_penilaian = ?");
        mysqli_stmt_bind_param($stmt_cek, "i", $id_penilaian);
        mysqli_stmt_execute($stmt_cek);
        $result_cek = mysqli_stmt_get_result($stmt_cek);
        if($result_cek->num_rows > 0) {
            $d_penilaian = mysqli_fetch_assoc($result_cek);
            if ($d_penilaian['id_guru'] != $id_guru && $_SESSION['role'] != 'admin') {
                $_SESSION['pesan'] = "{icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak berhak menghapus penilaian ini.'}";
                header("location: penilaian_tampil.php?id_kelas={$d_penilaian['id_kelas']}&id_mapel={$d_penilaian['id_mapel']}");
                exit();
            }
            mysqli_begin_transaction($koneksi);
            try {
                $stmt_del_tp = mysqli_prepare($koneksi, "DELETE FROM penilaian_tp WHERE id_penilaian = ?");
                mysqli_stmt_bind_param($stmt_del_tp, "i", $id_penilaian);
                mysqli_stmt_execute($stmt_del_tp);
                $stmt_del_nilai = mysqli_prepare($koneksi, "DELETE FROM penilaian_detail_nilai WHERE id_penilaian = ?");
                mysqli_stmt_bind_param($stmt_del_nilai, "i", $id_penilaian);
                mysqli_stmt_execute($stmt_del_nilai);
                $stmt_del_penilaian = mysqli_prepare($koneksi, "DELETE FROM penilaian WHERE id_penilaian = ?");
                mysqli_stmt_bind_param($stmt_del_penilaian, "i", $id_penilaian);
                if (!mysqli_stmt_execute($stmt_del_penilaian)) throw new Exception("Gagal menghapus data penilaian utama.");
                mysqli_commit($koneksi);
                $_SESSION['pesan'] = "{icon: 'success', title: 'Berhasil', text: 'Penilaian dan semua data nilai terkait telah dihapus.'}";
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan saat menghapus data.'}";
            }
            header("location: penilaian_tampil.php?id_kelas={$d_penilaian['id_kelas']}&id_mapel={$d_penilaian['id_mapel']}");
            exit();
        } else {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Tidak Ditemukan', text: 'Data penilaian tidak ditemukan.'}";
            header("location: dashboard.php");
            exit();
        }
        break;

    //======================================================================
    // DEFAULT
    //======================================================================
    default:
        header("location: dashboard.php");
        exit();
        break;
}
?>
