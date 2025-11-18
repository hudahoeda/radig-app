<?php
session_start();
include 'koneksi.php';

// Memanggil autoloader Composer di awal agar bisa digunakan di semua aksi
// (Penting untuk PHPSpreadsheet jika nanti dipakai di bagian import)
require 'vendor/autoload.php';

// ===========================================================
// VALIDASI UMUM
// ===========================================================
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    $_SESSION['pesan'] = "{icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak memiliki wewenang.'}";
    header("location: dashboard.php");
    exit();
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id_guru = (int)($_SESSION['id_guru'] ?? 0);

switch ($aksi) {
    //======================================================================
    // AKSI 1: TAMBAH PENILAIAN (BATCH & MULTI-KELAS)
    //======================================================================
    case 'tambah_penilaian':
        $id_kelas_utama = (int)$_POST['id_kelas'];
        $id_mapel = (int)$_POST['id_mapel'];
        $daftar_penilaian = $_POST['penilaian'] ?? [];

        // --- FITUR BARU: TANGKAP DATA KELAS PARALEL ---
        $target_kelas_array = $_POST['target_kelas'] ?? [];
        
        // Masukkan kelas utama ke dalam array agar ikut diproses
        array_push($target_kelas_array, $id_kelas_utama);
        
        // Pastikan ID kelas unik (menghindari duplikasi jika user iseng)
        $target_kelas_array = array_unique($target_kelas_array); 

        // Validasi Data Kosong
        if (empty($daftar_penilaian)) {
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal', text: 'Tidak ada data penilaian yang dikirim.'}";
            header("location: penilaian_tambah.php?id_kelas=$id_kelas_utama&id_mapel=$id_mapel");
            exit();
        }

        // Ambil semester aktif
        $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
        $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

        $berhasil_disimpan = 0;
        $total_input_berhasil = 0;
        
        mysqli_begin_transaction($koneksi);

        try {
            // LOOP 1: ITERASI UNTUK SETIAP KELAS (Kelas Utama + Paralel)
            foreach ($target_kelas_array as $id_kelas_proses) {
                $id_kelas_proses = (int)$id_kelas_proses;

                // LOOP 2: ITERASI UNTUK SETIAP BARIS PENILAIAN YANG DIINPUT GURU
                foreach ($daftar_penilaian as $data) {
                    $nama_penilaian = strip_tags($data['nama_penilaian']);
                    $jenis_penilaian = $data['jenis_penilaian'];
                    $tanggal_penilaian = $data['tanggal_penilaian'];
                    $id_tp_array = $data['id_tp'] ?? [];
                    $subjenis_penilaian = NULL;
                    $bobot_penilaian = 1;

                    // Validasi input dasar per baris
                    if (empty($nama_penilaian) || empty($jenis_penilaian) || empty($tanggal_penilaian)) {
                        continue; // Skip baris rusak
                    }

                    // Validasi Logika Sumatif
                    if ($jenis_penilaian == 'Sumatif') {
                        if (empty($data['subjenis_penilaian'])) {
                            throw new Exception("Sub-Jenis Sumatif wajib diisi untuk: $nama_penilaian");
                        }
                        $subjenis_penilaian = $data['subjenis_penilaian'];
                        $bobot_penilaian = (int)$data['bobot_penilaian'];
                        if ($bobot_penilaian < 1) { $bobot_penilaian = 1; }
                        
                        // Sumatif Lingkup Materi wajib ada TP
                        if ($subjenis_penilaian == 'Sumatif TP' && empty($id_tp_array)) {
                             throw new Exception("Sumatif TP wajib memilih TP untuk: $nama_penilaian");
                        }
                    } else { 
                        // Formatif wajib ada TP
                        if (empty($id_tp_array)) {
                            throw new Exception("Formatif wajib memilih TP untuk: $nama_penilaian");
                        }
                    }
                    
                    // 1. Insert ke tabel penilaian
                    // PERHATIKAN: Menggunakan $id_kelas_proses (bukan $id_kelas_utama)
                    $stmt_penilaian = mysqli_prepare($koneksi, "INSERT INTO penilaian (id_kelas, id_mapel, id_guru, nama_penilaian, jenis_penilaian, subjenis_penilaian, bobot_penilaian, semester, tanggal_penilaian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt_penilaian, "iiissssis", $id_kelas_proses, $id_mapel, $id_guru, $nama_penilaian, $jenis_penilaian, $subjenis_penilaian, $bobot_penilaian, $semester_aktif, $tanggal_penilaian);
                    
                    if (!mysqli_stmt_execute($stmt_penilaian)) {
                        throw new Exception("Gagal menyimpan data penilaian ($nama_penilaian) untuk Kelas ID: $id_kelas_proses.");
                    }
                    
                    // Ambil ID Penilaian yang baru saja dibuat
                    $id_penilaian_baru = mysqli_insert_id($koneksi);

                    // 2. Insert ke tabel relasi penilaian_tp (jika bukan sumatif akhir)
                    // Relasi TP ini akan sama untuk semua kelas karena TP biasanya melekat pada Mapel/Guru
                    if (!empty($id_tp_array) && $subjenis_penilaian != 'Sumatif Akhir Semester' && $subjenis_penilaian != 'Sumatif Akhir Tahun') {
                        $stmt_penilaian_tp = mysqli_prepare($koneksi, "INSERT INTO penilaian_tp (id_penilaian, id_tp) VALUES (?, ?)");
                        foreach ($id_tp_array as $id_tp) {
                            mysqli_stmt_bind_param($stmt_penilaian_tp, "ii", $id_penilaian_baru, $id_tp);
                            if (!mysqli_stmt_execute($stmt_penilaian_tp)) {
                                throw new Exception("Gagal menyimpan relasi TP untuk: $nama_penilaian");
                            }
                        }
                    }

                    $total_input_berhasil++;
                } // End Loop Penilaian
            } // End Loop Kelas

            // Jika semua lancar, Commit database
            mysqli_commit($koneksi);
            
            $jumlah_kelas = count($target_kelas_array);
            $jumlah_item = count($daftar_penilaian);
            
            $_SESSION['pesan'] = "{icon: 'success', title: 'Berhasil', text: '$jumlah_item item penilaian berhasil dibuat dan diduplikasi ke $jumlah_kelas kelas.'}";

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['pesan'] = "{icon: 'error', title: 'Gagal', text: '" . $e->getMessage() . "'}";
        }
        
        // Redirect kembali ke halaman kelas utama
        header("location: penilaian_tampil.php?id_kelas=$id_kelas_utama&id_mapel=$id_mapel");
        exit();
        break;

    //======================================================================
    // AKSI 2: SIMPAN NILAI SISWA (INPUT MANUAL)
    //======================================================================
    case 'simpan_nilai':
        $id_penilaian = (int)$_POST['id_penilaian'];
        $nilai_data = $_POST['nilai'];

        // Ambil info untuk redirect
        $q_penilaian = mysqli_query($koneksi, "SELECT id_kelas, id_mapel FROM penilaian WHERE id_penilaian=$id_penilaian");
        $d_penilaian = mysqli_fetch_assoc($q_penilaian);
        $id_kelas = $d_penilaian['id_kelas'];
        $id_mapel = $d_penilaian['id_mapel'];

        $stmt = mysqli_prepare($koneksi, "INSERT INTO penilaian_detail_nilai (id_penilaian, id_siswa, nilai) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        
        $berhasil_disimpan = 0;
        foreach ($nilai_data as $id_siswa => $nilai) {
            // Hanya simpan jika nilai tidak kosong dan angka
            if ($nilai !== '' && is_numeric($nilai)) {
                $nilai_valid = (float)$nilai;
                // Normalisasi range 0-100
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
    // AKSI 3: IMPORT NILAI (SINGLE - MODAL KECIL)
    //======================================================================
    case 'import_nilai':
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
    // AKSI 4: IMPORT NILAI BATCH (DARI TEMPLATE KELAS BESAR)
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

            if ($highestRow <= 2) { // Baris 1: Nama Header, Baris 2: ID Penilaian (Hidden)
                throw new Exception("File Excel tidak berisi data nilai.");
            }

            // 1. Mapping Kolom -> ID Penilaian (Membaca Baris ke-2 yang disembunyikan)
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

                // 4. Loop per penilaian (kolom horizontal)
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
    // AKSI 5: HAPUS PENILAIAN
    //======================================================================
    case 'hapus_penilaian':
        $id_penilaian = isset($_GET['id_penilaian']) ? (int)$_GET['id_penilaian'] : 0;
        
        // Cek kepemilikan
        $stmt_cek = mysqli_prepare($koneksi, "SELECT id_kelas, id_mapel, id_guru FROM penilaian WHERE id_penilaian = ?");
        mysqli_stmt_bind_param($stmt_cek, "i", $id_penilaian);
        mysqli_stmt_execute($stmt_cek);
        $result_cek = mysqli_stmt_get_result($stmt_cek);
        
        if($result_cek->num_rows > 0) {
            $d_penilaian = mysqli_fetch_assoc($result_cek);
            
            // Validasi hak akses
            if ($d_penilaian['id_guru'] != $id_guru && $_SESSION['role'] != 'admin') {
                $_SESSION['pesan'] = "{icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak berhak menghapus penilaian ini.'}";
                header("location: penilaian_tampil.php?id_kelas={$d_penilaian['id_kelas']}&id_mapel={$d_penilaian['id_mapel']}");
                exit();
            }

            mysqli_begin_transaction($koneksi);
            try {
                // 1. Hapus relasi TP
                $stmt_del_tp = mysqli_prepare($koneksi, "DELETE FROM penilaian_tp WHERE id_penilaian = ?");
                mysqli_stmt_bind_param($stmt_del_tp, "i", $id_penilaian);
                mysqli_stmt_execute($stmt_del_tp);
                
                // 2. Hapus nilai siswa
                $stmt_del_nilai = mysqli_prepare($koneksi, "DELETE FROM penilaian_detail_nilai WHERE id_penilaian = ?");
                mysqli_stmt_bind_param($stmt_del_nilai, "i", $id_penilaian);
                mysqli_stmt_execute($stmt_del_nilai);
                
                // 3. Hapus penilaian utama
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