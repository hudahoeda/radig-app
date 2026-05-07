<?php
session_start();
include 'koneksi.php';

// Menambah batas eksekusi untuk proses berat seperti import/salin
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
// Ini penting agar 'execute' melempar error (exception) jika gagal
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { die("Akses ditolak."); }

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

//======================================================================
// --- AKSI TAMBAH KELAS ---
//======================================================================
if ($aksi == 'tambah') {
    $nama_kelas = $_POST['nama_kelas'];
    $fase = $_POST['fase'];
    $id_wali_kelas = !empty($_POST['id_wali_kelas']) ? $_POST['id_wali_kelas'] : NULL;
    $id_tahun_ajaran = $_POST['id_tahun_ajaran'];

    try {
        $query = "INSERT INTO kelas (nama_kelas, fase, id_wali_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ssii", $nama_kelas, $fase, $id_wali_kelas, $id_tahun_ajaran);
        
        if(mysqli_stmt_execute($stmt)){
            $_SESSION['pesan'] = "Kelas baru berhasil ditambahkan.";
        }
        header("location:kelas_tampil.php?id_ta=" . $id_tahun_ajaran);

    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $_SESSION['pesan_error'] = "Gagal! Kelas dengan nama '" . htmlspecialchars($nama_kelas) . "' sudah ada untuk tahun ajaran ini.";
        } else {
            $_SESSION['pesan_error'] = "Terjadi error database: " . $e->getMessage();
        }
        header("location:kelas_tambah.php"); 
    }
    exit();

//======================================================================
// --- AKSI UPDATE KELAS ---
//======================================================================
} elseif ($aksi == 'update') {
    $id_kelas = $_POST['id_kelas'];
    $nama_kelas = $_POST['nama_kelas'];
    $fase = $_POST['fase'];
    $id_wali_kelas = !empty($_POST['id_wali_kelas']) ? $_POST['id_wali_kelas'] : NULL;
    $id_tahun_ajaran = $_POST['id_tahun_ajaran'];

    try {
        $query = "UPDATE kelas SET nama_kelas=?, fase=?, id_wali_kelas=?, id_tahun_ajaran=? WHERE id_kelas=?";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ssiii", $nama_kelas, $fase, $id_wali_kelas, $id_tahun_ajaran, $id_kelas);

        if(mysqli_stmt_execute($stmt)){
            $_SESSION['pesan'] = "Data kelas berhasil diperbarui.";
        }
        header("location:kelas_tampil.php?id_ta=" . $id_tahun_ajaran);

    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $_SESSION['pesan_error'] = "Gagal! Nama kelas '" . htmlspecialchars($nama_kelas) . "' sudah digunakan oleh kelas lain di tahun ajaran ini.";
        } else {
            $_SESSION['pesan_error'] = "Terjadi error database: " . $e->getMessage();
        }
        header("location:kelas_edit.php?id=" . $id_kelas); 
    }
    exit();

//======================================================================
// --- AKSI HAPUS KELAS ---
//======================================================================
} elseif ($aksi == 'hapus') {
    $id_kelas = (int)$_GET['id'];
    
    // Nonaktifkan mysqli_report agar 'UPDATE' tidak error jika tidak ada siswa
    mysqli_report(MYSQLI_REPORT_OFF);
    
    try {
        // Set NULL id_kelas pada siswa terlebih dahulu
        mysqli_query($koneksi, "UPDATE siswa SET id_kelas = NULL WHERE id_kelas = $id_kelas");
        
        // Aktifkan lagi report error untuk DELETE
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        $stmt = mysqli_prepare($koneksi, "DELETE FROM kelas WHERE id_kelas = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_kelas);
        mysqli_stmt_execute($stmt);
        $_SESSION['pesan'] = "Data kelas berhasil dihapus.";

    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1451) { 
            $_SESSION['pesan_error'] = "Gagal Hapus! Kelas ini tidak dapat dihapus karena masih memiliki data terkait (seperti data penilaian).";
        } else {
            $_SESSION['pesan_error'] = "Gagal menghapus kelas. Error: " . $e->getMessage();
        }
    }
    
    header("location:kelas_tampil.php");
    exit();

//======================================================================
// --- [BARU] AKSI SALIN KELAS ---
//======================================================================
} elseif ($aksi == 'salin_kelas') {
    $id_ta_sumber = $_POST['id_ta_sumber'];
    $id_ta_tujuan = $_POST['id_ta_tujuan'];
    $copy_wali = isset($_POST['copy_wali']) ? true : false;

    // Validasi input
    if (empty($id_ta_sumber) || empty($id_ta_tujuan)) {
        $_SESSION['pesan_error'] = "Tahun ajaran sumber dan tujuan harus dipilih.";
        header("location:kelas_tampil.php");
        exit();
    }

    if ($id_ta_sumber == $id_ta_tujuan) {
        $_SESSION['pesan_error'] = "Tahun ajaran sumber dan tujuan tidak boleh sama.";
        header("location:kelas_tampil.php?id_ta=" . $id_ta_tujuan);
        exit();
    }

    try {
        // Ambil semua kelas dari tahun ajaran sumber
        $query_sumber = mysqli_query($koneksi, "SELECT nama_kelas, fase, id_wali_kelas FROM kelas WHERE id_tahun_ajaran = '$id_ta_sumber'");
        
        // Siapkan variabel counter
        $berhasil = 0;
        $dilewati = 0; // Jika kelas sudah ada
        $gagal = 0;

        // Siapkan Prepared Statement (Agar efisien dalam loop)
        // 1. Cek duplikat di tujuan
        $stmt_cek = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE nama_kelas = ? AND id_tahun_ajaran = ? LIMIT 1");
        // 2. Insert kelas baru
        $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO kelas (nama_kelas, fase, id_wali_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");

        // Variabel binding
        $bind_nama = "";
        $bind_ta = $id_ta_tujuan;
        $bind_fase = "";
        $bind_wali = null;

        // Binding parameter (dilakukan sekali di luar loop)
        mysqli_stmt_bind_param($stmt_cek, "si", $bind_nama, $bind_ta);
        mysqli_stmt_bind_param($stmt_insert, "ssii", $bind_nama, $bind_fase, $bind_wali, $bind_ta);

        while ($kelas = mysqli_fetch_assoc($query_sumber)) {
            $bind_nama = $kelas['nama_kelas'];
            $bind_fase = $kelas['fase'];
            
            // Logika Wali Kelas: Jika dicentang DAN di sumber ada walinya, maka disalin. Jika tidak, NULL.
            $bind_wali = ($copy_wali && !empty($kelas['id_wali_kelas'])) ? $kelas['id_wali_kelas'] : null;

            // Eksekusi Cek Duplikat
            mysqli_stmt_execute($stmt_cek);
            mysqli_stmt_store_result($stmt_cek);

            if (mysqli_stmt_num_rows($stmt_cek) > 0) {
                // Kelas sudah ada di tujuan, lewati
                $dilewati++;
            } else {
                // Insert kelas baru
                if(mysqli_stmt_execute($stmt_insert)){
                    $berhasil++;
                } else {
                    $gagal++;
                }
            }
        }

        // Buat Laporan untuk SweetAlert
        $pesan_html = "<div class='text-start'>Proses Salin Kelas Selesai.<br>
                       <span class='text-success'>&#10004; Berhasil Disalin: <b>$berhasil</b></span><br>
                       <span class='text-warning'>&#9888; Dilewati (Sudah Ada): <b>$dilewati</b></span>";
        
        if ($gagal > 0) {
            $pesan_html .= "<br><span class='text-danger'>&#10060; Gagal: <b>$gagal</b></span>";
        }
        $pesan_html .= "</div>";

        // Kirim response JSON untuk SweetAlert
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Salin Selesai', 'html' => $pesan_html]);

    } catch (Exception $e) {
        $_SESSION['pesan_error'] = "Terjadi kesalahan saat menyalin data: " . $e->getMessage();
    }

    header("location:kelas_tampil.php?id_ta=" . $id_ta_tujuan);
    exit();

//======================================================================
// --- AKSI IMPORT KELAS ---
//======================================================================
} elseif ($aksi == 'import_kelas') {
    
    require 'vendor/autoload.php'; // Butuh PhpSpreadsheet

    if (isset($_FILES['file_kelas']['name']) && $_FILES['file_kelas']['error'] == 0) {
        
        $file_name = $_FILES['file_kelas']['name'];
        $file_tmp = $_FILES['file_kelas']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext == 'xlsx') {
            
            $stmt_check_guru = null;
            $stmt_check_kelas = null;
            $stmt_insert = null;
            $stmt_update = null;

            try {
                // Ambil ID Tahun Ajaran Aktif
                $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
                $d_ta = mysqli_fetch_assoc($q_ta);
                $id_ta_aktif = $d_ta['id_tahun_ajaran'];

                if(empty($id_ta_aktif)) {
                    throw new Exception("Tidak ada Tahun Ajaran Aktif. Silakan aktifkan satu di Pengaturan.");
                }

                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $spreadsheet = $reader->load($file_tmp);
                // Target sheet "Import"
                $sheetData = $spreadsheet->getSheetByName('Import')->toArray(null, true, true, true);
                
                mysqli_autocommit($koneksi, FALSE);

                // Siapkan statement SQL
                $stmt_check_guru = mysqli_prepare($koneksi, "SELECT id_guru FROM guru WHERE username = ? LIMIT 1");
                $stmt_check_kelas = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE nama_kelas = ? AND id_tahun_ajaran = ? LIMIT 1");
                $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO kelas (nama_kelas, fase, id_wali_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");
                $stmt_update = mysqli_prepare($koneksi, "UPDATE kelas SET fase = ?, id_wali_kelas = ? WHERE id_kelas = ?");
                
                // Variabel binding
                $b_nama_kelas = "";
                $b_fase = "";
                $b_username = "";
                $b_id_wali = null;
                $b_id_kelas_ex = 0;

                // Bind parameter sekali di luar loop
                mysqli_stmt_bind_param($stmt_check_guru, "s", $b_username);
                mysqli_stmt_bind_param($stmt_check_kelas, "si", $b_nama_kelas, $id_ta_aktif);
                mysqli_stmt_bind_param($stmt_insert, "ssii", $b_nama_kelas, $b_fase, $b_id_wali, $id_ta_aktif);
                mysqli_stmt_bind_param($stmt_update, "sii", $b_fase, $b_id_wali, $b_id_kelas_ex);

                $berhasil_tambah = 0;
                $berhasil_update = 0;
                $gagal_format = 0;
                $gagal_guru = 0;

                $baris_pertama = true;
                foreach ($sheetData as $row) {
                    if ($baris_pertama) { $baris_pertama = false; continue; } // Lewati header

                    // Assign nilai ke variabel yang di-bind
                    $b_nama_kelas = trim($row['A'] ?? '');
                    $b_fase = trim($row['B'] ?? '');
                    $b_username = trim($row['C'] ?? '');

                    // Validasi data baris
                    if (empty($b_nama_kelas) || empty($b_fase) || empty($b_username)) {
                        $gagal_format++;
                        continue;
                    }

                    // 1. Cari ID Wali Kelas berdasarkan username
                    mysqli_stmt_execute($stmt_check_guru);
                    $result_guru = mysqli_stmt_get_result($stmt_check_guru);
                    if ($data_guru = mysqli_fetch_assoc($result_guru)) {
                        $b_id_wali = $data_guru['id_guru'];
                    } else {
                        $gagal_guru++; // Guru tidak ditemukan
                        continue;
                    }

                    // 2. Cek apakah kelas sudah ada di TA Aktif
                    mysqli_stmt_execute($stmt_check_kelas);
                    $result_kelas = mysqli_stmt_get_result($stmt_check_kelas);
                    
                    if ($data_kelas = mysqli_fetch_assoc($result_kelas)) {
                        // KELAS SUDAH ADA -> UPDATE WALI KELAS & FASE
                        $b_id_kelas_ex = $data_kelas['id_kelas'];
                        mysqli_stmt_execute($stmt_update);
                        $berhasil_update++;
                    } else {
                        // KELAS BELUM ADA -> INSERT BARU
                        mysqli_stmt_execute($stmt_insert);
                        $berhasil_tambah++;
                    }
                }
                
                mysqli_commit($koneksi);
                $pesan = "<b>Import Selesai (T.A Aktif)!</b><br>Kelas Baru: <b>$berhasil_tambah</b><br>Kelas Diperbarui: <b>$berhasil_update</b><br>Gagal (Format): <b>$gagal_format</b><br>Gagal (Wali Kelas Tdk Ditemukan): <b>$gagal_guru</b>";
                $_SESSION['pesan'] = json_encode(['icon' => 'info', 'title' => 'Hasil Import Kelas', 'html' => $pesan]);
                
            } catch(Exception $e) {
                mysqli_rollback($koneksi);
                // Cek kode error 1062 (Duplicate Entry) saat import
                if ($e instanceof mysqli_sql_exception && $e->getCode() == 1062) {
                     $_SESSION['pesan_error'] = "Gagal! Terdeteksi nama kelas duplikat di dalam file Excel Anda.";
                } else {
                     $_SESSION['pesan_error'] = "Gagal memproses file. Error: " . $e->getMessage();
                }
            } finally {
                // Tutup semua statement
                if(isset($stmt_check_guru)) mysqli_stmt_close($stmt_check_guru);
                if(isset($stmt_check_kelas)) mysqli_stmt_close($stmt_check_kelas);
                if(isset($stmt_insert)) mysqli_stmt_close($stmt_insert);
                if(isset($stmt_update)) mysqli_stmt_close($stmt_update);
                mysqli_autocommit($koneksi, TRUE);
            }

        } else {
            $_SESSION['pesan_error'] = "Gagal! Format file harus .xlsx.";
        }
    } else {
        $upload_error = $_FILES['file_kelas']['error'] ?? 'Tidak ada file';
        $_SESSION['pesan_error'] = "Gagal! Tidak ada file yang diunggah. Kode Error: $upload_error";
    }
    
    header("location: kelas_tampil.php");
    exit();
}

//======================================================================
else {
    header("location:kelas_tampil.php");
    exit();
}
?>