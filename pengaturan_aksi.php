<?php
/**
 * ==================================================================================
 * FILE: pengaturan_aksi.php
 * DESKRIPSI: Backend Engine Pusat untuk Manajemen Sistem dan Database.
 * FUNGSI: Menangani Identitas Sekolah, Pejabat, Pengaturan Rapor, Tahun Ajaran,
 * Upload Logo/KOP/Watermark, Backup, Restore, Migrasi, dan Sinkronisasi.
 * ==================================================================================
 */

session_start();
include 'koneksi.php';

// ----------------------------------------------------------------------------------
// [PART 1] VALIDASI KEAMANAN & KONFIGURASI SERVER
// ----------------------------------------------------------------------------------

// Pastikan hanya admin yang bisa memproses aksi sensitif ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak. Anda harus login sebagai administrator untuk menjalankan skrip ini.");
}

// Konfigurasi performa untuk menangani file SQL berukuran besar dan migrasi berat
ini_set('memory_limit', '1024M');     // Alokasi memori hingga 1GB
set_time_limit(900);                  // Batas waktu eksekusi 15 menit
ini_set('upload_max_filesize', '50M'); // Izin upload file hingga 50MB
ini_set('post_max_size', '50M');

// Pengaturan Target Redirect
$ui_database = 'pengaturan_backup_tampil.php';
$ui_pengaturan = 'pengaturan_tampil.php';

// ----------------------------------------------------------------------------------
// [PART 2] FUNGSI-FUNGSI PEMBANTU (HELPER FUNCTIONS)
// ----------------------------------------------------------------------------------

/**
 * Format Pesan JSON untuk SweetAlert
 */
function set_json_pesan($tipe, $judul, $teks) {
    return json_encode([
        'icon'  => $tipe,
        'title' => $judul,
        'html'  => $teks
    ]);
}

/**
 * Simpan atau Update Data ke Tabel Pengaturan secara Dinamis
 */
function simpanPengaturan($koneksi, $nama, $nilai) {
    $sql = "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $nama, $nilai);
    mysqli_stmt_execute($stmt);
}

/**
 * Handler Upload File Profesional (Logo, KOP, Watermark)
 */
function handleFileUpload($file_key, $upload_dir, $allowed_types, $field_name) {
    // Cek keberadaan file di array global $_FILES
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] == UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$file_key]['error'] == 0) {
        $file = $_FILES[$file_key];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi Ekstensi
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', "Format file untuk $field_name tidak diizinkan. Gunakan: " . implode(', ', $allowed_types));
            return null;
        }

        // [SECURITY HARDENING] Validasi MIME Type Asli dengan finfo atau mime_content_type
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file['tmp_name']);
        } else {
            // Fallback ke pengecekan ekstensi jika ekstensi fileinfo sama sekali tidak aktif
            $mime_type = 'image/' . ($file_ext == 'jpg' ? 'jpeg' : $file_ext);
        }

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($mime_type, $allowed_mimes)) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', "File terdeteksi berbahaya atau bukan gambar valid (MIME: $mime_type).");
            return null;
        }
        
        // Validasi Ukuran (KOP 2MB, Lainnya 1MB)
        $is_kop = (strpos($field_name, 'kop') !== false);
        $limit = $is_kop ? 2097152 : 1048576; 
        
        if ($file['size'] > $limit) { 
             $limit_text = $is_kop ? '2MB' : '1MB';
             $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', "Ukuran file $field_name terlalu besar. Maksimal $limit_text.");
            return null;
        }
        
        // Generate Nama File Unik
        $new_filename = $field_name . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        // [MODIFIKASI] Resize & Compress Image menggunakan GD Library
        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $max_dim = 800;
            $image_info = getimagesize($file['tmp_name']);
            if ($image_info !== false) {
                list($width_orig, $height_orig, $image_type) = $image_info;

                if ($width_orig > $max_dim || $height_orig > $max_dim) {
                    $ratio_orig = $width_orig / $height_orig;
                    if (1 > $ratio_orig) { // height is bigger
                       $max_width = $max_dim * $ratio_orig;
                       $max_height = $max_dim;
                    } else { // width is bigger or equal
                       $max_height = $max_dim / $ratio_orig;
                       $max_width = $max_dim;
                    }

                    $image_p = imagecreatetruecolor((int)$max_width, (int)$max_height);

                    if ($image_type == IMAGETYPE_PNG) {
                        imagealphablending($image_p, false);
                        imagesavealpha($image_p, true);
                        $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
                        imagefilledrectangle($image_p, 0, 0, (int)$max_width, (int)$max_height, $transparent);
                        $image = imagecreatefrompng($file['tmp_name']);
                    } else {
                        $image = imagecreatefromjpeg($file['tmp_name']);
                    }

                    if ($image) {
                        imagecopyresampled($image_p, $image, 0, 0, 0, 0, (int)$max_width, (int)$max_height, $width_orig, $height_orig);

                        $success = false;
                        if ($image_type == IMAGETYPE_PNG) {
                            $success = imagepng($image_p, $destination, 8); // 0-9 compression level
                        } else {
                            $success = imagejpeg($image_p, $destination, 80); // 0-100 quality
                        }

                        imagedestroy($image_p);
                        imagedestroy($image);

                        if ($success) return $new_filename;
                    }
                }
            }
        }

        // Fallback untuk file normal atau jika proses resize gagal
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $new_filename;
        } else {
            $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', "Gagal memindahkan file ke direktori tujuan.");
            return null;
        }
    }
    return null; 
}

/**
 * SKRIP MIGRASI DATA SPESIFIK (Logika Bisnis Sangat Detail)
 */
function jalankan_skrip_migrasi($koneksi, &$errors) {
    // 1. Sinkronisasi KKM (Ubah ke standar baru 50)
    $sql1 = "UPDATE `pengaturan` SET `nilai_pengaturan` = '50' WHERE `nama_pengaturan` = 'kkm'";
    if (!mysqli_query($koneksi, $sql1)) $errors[] = "Gagal sinkronisasi KKM: " . mysqli_error($koneksi);

    // 2. Inisialisasi Pengaturan Rapor Default (Hanya jika belum ada)
    $sql2 = "INSERT INTO `pengaturan` (nama_pengaturan, nilai_pengaturan) VALUES 
             ('rapor_ukuran_kertas', 'F4'),
             ('rapor_skema_warna', 'light_green'),
             ('tanggal_rapor_pts', '2025-09-10'),
             ('kop_sekolah', ''),
             ('cetak_tanpa_kop', '0'),
             ('margin_atas_tanpa_kop', '0'),
             ('rapor_tampil_kop', '0')
             ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)";
    if (!mysqli_query($koneksi, $sql2)) $errors[] = "Gagal inisialisasi pengaturan default: " . mysqli_error($koneksi);

    // 3. Transformasi Nama Mata Pelajaran (Seni Musik -> Seni Rupa)
    $sql3 = "UPDATE `mata_pelajaran` SET `nama_mapel` = 'Seni Rupa', `urutan` = 14 WHERE `id_mapel` = 8";
    if (!mysqli_query($koneksi, $sql3)) $errors[] = "Gagal transformasi mapel Seni Rupa: " . mysqli_error($koneksi);

    // 4. Rekonstruksi Urutan Mata Pelajaran (Struktur Kurikulum Terbaru)
    $sql4 = "UPDATE `mata_pelajaran` SET `urutan` = CASE `id_mapel`
                WHEN 1 THEN 11 WHEN 2 THEN 1 WHEN 3 THEN 6 WHEN 4 THEN 7 WHEN 5 THEN 8
                WHEN 6 THEN 9 WHEN 7 THEN 10 WHEN 9 THEN 15 WHEN 10 THEN 13 WHEN 11 THEN 16
                WHEN 12 THEN 12 WHEN 13 THEN 2 ELSE `urutan`
             END WHERE `id_mapel` IN (1,2,3,4,5,6,7,9,10,11,12,13)";
    if (!mysqli_query($koneksi, $sql4)) $errors[] = "Gagal rekonstruksi urutan mapel: " . mysqli_error($koneksi);

    // 5. Injeksi Mata Pelajaran Agama Tambahan (Lengkap)
    $sql5 = "INSERT IGNORE INTO `mata_pelajaran` (`id_mapel`, `nama_mapel`, `kode_mapel`, `urutan`) VALUES
             (17, 'Pendidikan Agama Hindu dan Budi Pekerti', 'PAH', 4),
             (15, 'Pendidikan Agama Budha dan Budi Pekerti', 'PAB', 3),
             (16, 'Pendidikan Agama Katolik dan Budi Pekerti', 'PAKK', 5)";
    if (!mysqli_query($koneksi, $sql5)) $errors[] = "Gagal injeksi mapel agama baru: " . mysqli_error($koneksi);
}

// ----------------------------------------------------------------------------------
// [PART 3] ENGINE AKSI (SWITCH CASE)
// ----------------------------------------------------------------------------------

$aksi = $_GET['aksi'] ?? $_POST['aksi'] ?? ''; 

switch ($aksi) {

    // --- AKSI 1: UPDATE IDENTITAS SEKOLAH ---
    case 'update_sekolah':
        $sql = "UPDATE sekolah SET 
                nama_sekolah=?, jenjang=?, npsn=?, nss=?, jalan=?, desa_kelurahan=?, 
                kecamatan=?, kabupaten_kota=?, provinsi=?, telepon=?, email=?, website=? 
                LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssssss", 
            $_POST['nama_sekolah'], $_POST['jenjang'], $_POST['npsn'], $_POST['nss'], 
            $_POST['jalan'], $_POST['desa_kelurahan'], $_POST['kecamatan'], 
            $_POST['kabupaten_kota'], $_POST['provinsi'], $_POST['telepon'], 
            $_POST['email'], $_POST['website']
        );
        if(mysqli_stmt_execute($stmt)){
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Identitas sekolah telah berhasil diperbarui.');
        } else {
            $_SESSION['pesan'] = set_json_pesan('error', 'Gagal!', 'Terjadi kesalahan sistem: ' . mysqli_error($koneksi));
        }
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 2: UPDATE DATA PEJABAT (KEPSEK) ---
    case 'update_pejabat':
        $sql = "UPDATE sekolah SET nama_kepsek=?, jabatan_kepsek=?, nip_kepsek=? LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $_POST['nama_kepsek'], $_POST['jabatan_kepsek'], $_POST['nip_kepsek']);
        if(mysqli_stmt_execute($stmt)){
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Data pimpinan/pejabat sekolah telah diperbarui.');
        }
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 3: UPDATE PENGATURAN UMUM RAPOR ---
    case 'update_pengaturan':
        if (isset($_POST['pengaturan']) && is_array($_POST['pengaturan'])) {
            foreach ($_POST['pengaturan'] as $nama => $nilai) {
                simpanPengaturan($koneksi, $nama, $nilai);
            }
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Seluruh pengaturan rapor telah disimpan.');
        }
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 4: TAMBAH TAHUN AJARAN BARU ---
    case 'tambah_ta':
        $tahun_ajaran = $_POST['tahun_ajaran'] ?? '';
        if (!empty($tahun_ajaran)) {
            $sql = "INSERT INTO tahun_ajaran (tahun_ajaran, status) VALUES (?, 'Tidak Aktif')";
            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, "s", $tahun_ajaran);
            if(mysqli_stmt_execute($stmt)){
                $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Tahun ajaran baru telah ditambahkan ke sistem.');
            }
        }
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 5: AKTIFKAN TAHUN AJARAN TERTENTU ---
    case 'aktifkan_ta':
        $id_ta = $_GET['id'] ?? 0;
        if ($id_ta > 0) {
            mysqli_query($koneksi, "UPDATE tahun_ajaran SET status = 'Tidak Aktif'");
            $sql = "UPDATE tahun_ajaran SET status = 'Aktif' WHERE id_tahun_ajaran = ?";
            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id_ta);
            if(mysqli_stmt_execute($stmt)){
                $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Tahun ajaran terpilih kini telah aktif.');
            }
        }
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 6: UPDATE LOGO SEKOLAH ---
    case 'update_logo':
        $upload_dir = 'uploads/';
        $new_logo = handleFileUpload('logo_sekolah', $upload_dir, ['jpg', 'jpeg', 'png'], 'logo');
        if ($new_logo) {
            $q = mysqli_query($koneksi, "SELECT logo_sekolah FROM sekolah LIMIT 1");
            $d = mysqli_fetch_assoc($q);
            if ($d && !empty($d['logo_sekolah']) && file_exists($upload_dir . $d['logo_sekolah'])) {
                unlink($upload_dir . $d['logo_sekolah']);
            }
            $sql = "UPDATE sekolah SET logo_sekolah = ? LIMIT 1";
            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, "s", $new_logo);
            mysqli_stmt_execute($stmt);
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Logo resmi sekolah telah diperbarui.');
        }
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 7: MANAJEMEN KOP SURAT (DENGAN KOMPATIBILITAS FILE LAMA) ---
    case 'simpan_kop':
    case 'update_kop_gambar': 
        $upload_dir = 'uploads/';
        
        // Simpan metadata kop (Warna, Kertas, dll)
        if (isset($_POST['pengaturan']) && is_array($_POST['pengaturan'])) {
            foreach ($_POST['pengaturan'] as $nama => $nilai) simpanPengaturan($koneksi, $nama, $nilai);
        }
        
        // Handle Toggle Fitur
        $cetak_tanpa_kop = (isset($_POST['cetak_tanpa_kop']) && $_POST['cetak_tanpa_kop'] == '1') ? '1' : '0';
        $margin_atas = $_POST['margin_atas_tanpa_kop'] ?? '0';
        $rapor_tampil_kop = (isset($_POST['rapor_tampil_kop']) && $_POST['rapor_tampil_kop'] == '1') ? '1' : '0';

        // [KOP DESIGNER] Text & Toggle
        $kop_baris_1 = $_POST['kop_baris_1'] ?? '';
        $kop_baris_2 = $_POST['kop_baris_2'] ?? '';
        $kop_baris_3 = $_POST['kop_baris_3'] ?? '';
        $kop_baris_4 = $_POST['kop_baris_4'] ?? '';
        $kop_logo_kiri_tampil = (isset($_POST['kop_logo_kiri_tampil']) && $_POST['kop_logo_kiri_tampil'] == '1') ? '1' : '0';
        $kop_logo_kanan_tampil = (isset($_POST['kop_logo_kanan_tampil']) && $_POST['kop_logo_kanan_tampil'] == '1') ? '1' : '0';

        simpanPengaturan($koneksi, 'cetak_tanpa_kop', $cetak_tanpa_kop);
        simpanPengaturan($koneksi, 'margin_atas_tanpa_kop', $margin_atas);
        simpanPengaturan($koneksi, 'rapor_tampil_kop', $rapor_tampil_kop);
        simpanPengaturan($koneksi, 'kop_baris_1', $kop_baris_1);
        simpanPengaturan($koneksi, 'kop_baris_2', $kop_baris_2);
        simpanPengaturan($koneksi, 'kop_baris_3', $kop_baris_3);
        simpanPengaturan($koneksi, 'kop_baris_4', $kop_baris_4);
        simpanPengaturan($koneksi, 'kop_logo_kiri_tampil', $kop_logo_kiri_tampil);
        simpanPengaturan($koneksi, 'kop_logo_kanan_tampil', $kop_logo_kanan_tampil);

        // Handle Upload Gambar KOP Kustom (Full)
        $input_name = isset($_FILES['file_kop']) ? 'file_kop' : (isset($_FILES['file_kop_sekolah']) ? 'file_kop_sekolah' : 'file_kop');
        $new_kop = handleFileUpload($input_name, $upload_dir, ['jpg', 'jpeg', 'png'], 'kop_sekolah');
        
        if ($new_kop) {
            // Hapus file lama jika ada
            $keys = ['kop_sekolah', 'file_kop_sekolah'];
            foreach ($keys as $key) {
                $q = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = '$key'");
                $d = mysqli_fetch_assoc($q);
                if ($d && !empty($d['nilai_pengaturan']) && file_exists($upload_dir . $d['nilai_pengaturan'])) {
                    unlink($upload_dir . $d['nilai_pengaturan']);
                }
            }
            // Simpan ke dua key untuk kompatibilitas
            simpanPengaturan($koneksi, 'kop_sekolah', $new_kop);
            simpanPengaturan($koneksi, 'file_kop_sekolah', $new_kop);
        }

        // [KOP DESIGNER] Handle Upload Logo Kiri
        $new_logo_kiri = handleFileUpload('file_logo_kiri', $upload_dir, ['jpg', 'jpeg', 'png'], 'logo_kiri');
        if ($new_logo_kiri) {
            $q = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'logo_kiri'");
            $d = mysqli_fetch_assoc($q);
            if ($d && !empty($d['nilai_pengaturan']) && file_exists($upload_dir . $d['nilai_pengaturan'])) {
                unlink($upload_dir . $d['nilai_pengaturan']);
            }
            simpanPengaturan($koneksi, 'logo_kiri', $new_logo_kiri);
        }

        $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Pengaturan konfigurasi KOP disimpan.');
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 8: MANAJEMEN WATERMARK ---
    case 'simpan_watermark':
    case 'update_watermark':
        $upload_dir = 'uploads/';
        if (isset($_POST['hapus_watermark']) && $_POST['hapus_watermark'] == '1') {
            $q = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'watermark_file'");
            $d = mysqli_fetch_assoc($q);
            if ($d && !empty($d['nilai_pengaturan']) && file_exists($upload_dir . $d['nilai_pengaturan'])) {
                unlink($upload_dir . $d['nilai_pengaturan']);
            }
            simpanPengaturan($koneksi, 'watermark_file', '');
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Watermark telah dihapus.');
        } else {
            $input_wm = isset($_FILES['file_watermark']) ? 'file_watermark' : (isset($_FILES['watermark_baru']) ? 'watermark_baru' : 'file_watermark');
            $new_wm = handleFileUpload($input_wm, $upload_dir, ['png'], 'watermark');
            if ($new_wm) {
                $q = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'watermark_file'");
                $d = mysqli_fetch_assoc($q);
                if ($d && !empty($d['nilai_pengaturan']) && file_exists($upload_dir . $d['nilai_pengaturan'])) {
                    unlink($upload_dir . $d['nilai_pengaturan']);
                }
                simpanPengaturan($koneksi, 'watermark_file', $new_wm);
                $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Watermark sistem diperbarui.');
            }
        }
        header("Location: $ui_pengaturan");
        exit();

    // --- AKSI 9: PEMBUATAN BACKUP DATABASE (PURE PHP METHOD) ---
    case 'buat_backup':
        global $host, $user, $pass, $db;
        $db_host = $host ?? 'localhost'; 
        $db_user = $user ?? 'u1444233_admin'; 
        $db_pass = $pass ?? 'Rashengan123456'; 
        $db_name = $db ?? 'u1444233_rapor';

        try {
            $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if ($mysqli->connect_error) throw new Exception('Gagal melakukan koneksi database untuk proses backup.');
            $mysqli->set_charset("utf8mb4");
            
            $backup_content = "-- Rapor Digital Backup System\n-- Host: {$db_host}\n-- Waktu: " . date('Y-m-d H:i:s') . "\n-- Database: `{$db_name}`\n\n";
            $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            $res = $mysqli->query("SHOW TABLES");
            while ($row = $res->fetch_row()) {
                $table = $row[0];
                // Struktur
                $res_s = $mysqli->query("SHOW CREATE TABLE `{$table}`"); 
                $row_s = $res_s->fetch_row();
                $backup_content .= "DROP TABLE IF EXISTS `{$table}`;\n{$row_s[1]};\n\n";
                
                // Isi Data
                $res_d = $mysqli->query("SELECT * FROM `{$table}`");
                while ($row_d = $res_d->fetch_row()) {
                    $vals = array_map(function($v) use ($mysqli) {
                        return is_null($v) ? "NULL" : "'" . $mysqli->real_escape_string($v) . "'";
                    }, $row_d);
                    $backup_content .= "INSERT INTO `{$table}` VALUES (" . implode(',', $vals) . ");\n";
                }
                $backup_content .= "\n";
            }
            $backup_content .= "SET FOREIGN_KEY_CHECKS=1;";
            $mysqli->close();
            
            $backup_dir = 'backups/';
            if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
            $nama_file = 'backup_' . $db_name . '_' . date('Ymd_His') . '.sql';
            
            if (file_put_contents($backup_dir . $nama_file, $backup_content) === false) {
                throw new Exception("Gagal menulis file backup ke folder server.");
            }
            
            $_SESSION['pesan'] = set_json_pesan('success', 'Backup Berhasil', "File cadangan <b>$nama_file</b> telah berhasil dibuat.");
        } catch (Exception $e) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Backup Gagal', $e->getMessage());
        }
        header("Location: $ui_database");
        exit();

    // --- AKSI 10: RESTORE TOTAL DATABASE (PENGGANTIAN TOTAL) ---
    case 'lakukan_restore_total':
        if (!isset($_FILES['sql_file_restore']) || $_FILES['sql_file_restore']['error'] != 0) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Restore Gagal', 'File SQL tidak valid atau tidak terdeteksi.');
            header("Location: $ui_database"); exit;
        }

        try {
            mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 0");
            
            // Hapus semua tabel yang ada saat ini
            $res = mysqli_query($koneksi, "SHOW TABLES");
            while($row = mysqli_fetch_row($res)) {
                mysqli_query($koneksi, "DROP TABLE IF EXISTS `{$row[0]}`");
            }
            
            // Eksekusi skrip SQL pemulihan
            $sql_content = file_get_contents($_FILES['sql_file_restore']['tmp_name']);
            if (mysqli_multi_query($koneksi, $sql_content)) {
                do { 
                    if ($result = mysqli_store_result($koneksi)) mysqli_free_result($result);
                } while (mysqli_next_result($koneksi));
            }
            
            mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 1");
            $_SESSION['pesan'] = set_json_pesan('success', 'Restore Berhasil', 'Database sistem telah dipulihkan sepenuhnya.');
        } catch (Exception $e) {
            mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 1");
            $_SESSION['pesan'] = set_json_pesan('error', 'Restore Gagal', 'Error fatal: ' . $e->getMessage());
        }
        header("Location: $ui_database");
        exit();

    // --- AKSI 11: HAPUS FILE CADANGAN DI SERVER ---
    case 'hapus_backup':
        $file = urldecode($_GET['file'] ?? '');
        $path = 'backups/' . basename($file);
        if (!empty($file) && file_exists($path)) {
            unlink($path);
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil', 'File cadangan telah dihapus dari penyimpanan server.');
        } else {
            $_SESSION['pesan'] = set_json_pesan('error', 'Gagal', 'File tidak ditemukan atau akses ditolak.');
        }
        header("Location: $ui_database");
        exit();

    // --- AKSI 11.5: REGENERATE MASSAL RAPOR (PEMELIHARAAN SISTEM) ---
    case 'regenerate_massal_rapor':
        set_time_limit(300); // 5 menit

        // 1. Dapatkan Tahun Ajaran dan Semester Aktif
        $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
        $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

        $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
        $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 0;

        $q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
        $kkm = mysqli_fetch_assoc($q_kkm)['nilai_pengaturan'] ?? 75;

        // 2. Ambil Semua Rapor yang sudah FINAL di TA dan Semester aktif
        $q_rapor = mysqli_query($koneksi, "
            SELECT r.id_rapor, r.id_siswa, r.id_kelas
            FROM rapor r
            WHERE r.id_tahun_ajaran = $id_tahun_ajaran AND r.semester = $semester_aktif
        ");

        $count_updated = 0;

        if(mysqli_num_rows($q_rapor) > 0) {
            if (!function_exists('hitungDataRaporSiswa_massal')) {
                function hitungDataRaporSiswa_massal($koneksi, $id_siswa, $id_kelas, $semester_aktif, $kkm, $daftar_mapel) {
                    $data_rapor_siswa = [];
                    $stmt_sumatif_tp = mysqli_prepare($koneksi, "
                        SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian,
                                GROUP_CONCAT(tp.deskripsi_tp SEPARATOR '|||') as deskripsi_tps,
                                GROUP_CONCAT(tp.kktp SEPARATOR '|||') as kktp_tps
                        FROM penilaian_detail_nilai pdn
                        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
                        JOIN penilaian_tp ptp ON p.id_penilaian = ptp.id_penilaian
                        JOIN tujuan_pembelajaran tp ON ptp.id_tp = tp.id_tp
                        WHERE p.subjenis_penilaian = 'Sumatif TP' AND pdn.id_siswa = ? AND p.id_mapel = ?
                        AND p.id_kelas = ? AND p.semester = ?
                        GROUP BY p.id_penilaian, pdn.nilai, p.bobot_penilaian
                    ");
                    $stmt_sumatif_akhir = mysqli_prepare($koneksi, "
                        SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian
                        FROM penilaian_detail_nilai pdn
                        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
                        WHERE p.subjenis_penilaian IN ('Sumatif Akhir Semester', 'Sumatif Akhir Tahun')
                        AND p.jenis_penilaian = 'Sumatif' AND pdn.id_siswa = ? AND p.id_mapel = ?
                        AND p.id_kelas = ? AND p.semester = ?
                    ");

                    foreach ($daftar_mapel as $mapel) {
                        $id_mapel = $mapel['id_mapel'];
                        $skor_per_tp = [];
                        $komponen_nilai = [];
                        $total_nilai_x_bobot = 0;
                        $total_bobot = 0;

                        mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
                        if (mysqli_stmt_execute($stmt_sumatif_tp)) {
                            $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
                            while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
                                $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
                                $kktps_individu = explode('|||', $d_nilai['kktp_tps'] ?? '');
                                foreach ($tps_individu as $idx => $desc_tp) {
                                    $kktp_val = isset($kktps_individu[$idx]) && is_numeric($kktps_individu[$idx]) ? (int)$kktps_individu[$idx] : 75;
                                    if (!isset($skor_per_tp[$desc_tp])) $skor_per_tp[$desc_tp] = ['skor' => [], 'kktp' => $kktp_val];
                                    $skor_per_tp[$desc_tp]['skor'][] = $d_nilai['nilai'];
                                }
                                $komponen_nilai[] = [
                                    'nama' => $d_nilai['nama_penilaian'], 'jenis' => $d_nilai['subjenis_penilaian'],
                                    'nilai' => $d_nilai['nilai'], 'bobot' => $d_nilai['bobot_penilaian'],
                                    'deskripsi_tp' => str_replace('|||', '<br>- ', $d_nilai['deskripsi_tps'])
                                ];
                                $total_nilai_x_bobot += (int)$d_nilai['nilai'] * (int)$d_nilai['bobot_penilaian'];
                                $total_bobot += (int)$d_nilai['bobot_penilaian'];
                            }
                        }

                        mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
                        if (mysqli_stmt_execute($stmt_sumatif_akhir)) {
                            $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
                            while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
                                $komponen_nilai[] = [
                                    'nama' => $d_nilai_akhir['nama_penilaian'], 'jenis' => $d_nilai_akhir['subjenis_penilaian'],
                                    'nilai' => $d_nilai_akhir['nilai'], 'bobot' => $d_nilai_akhir['bobot_penilaian'],
                                    'deskripsi_tp' => 'Mencakup keseluruhan materi semester.'
                                ];
                                $total_nilai_x_bobot += (int)$d_nilai_akhir['nilai'] * (int)$d_nilai_akhir['bobot_penilaian'];
                                $total_bobot += (int)$d_nilai_akhir['bobot_penilaian'];
                            }
                        }

                        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;

                        $deskripsi_final = '';
                        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
                            $rekap_tp = [];
                            $kata_hapus = ['Peserta didik dapat', 'Peserta didik mampu', 'peserta didik mampu', 'siswa dapat', 'siswa mampu', 'mampu', 'memahami', 'menguasai', 'menjelaskan', 'menganalisis', 'mengidentifikasi', 'menentukan', 'menunjukkan'];

                            foreach ($skor_per_tp as $deskripsi => $data_tp) {
                                $avg = array_sum($data_tp['skor']) / count($data_tp['skor']);
                                $kktp_tp = $data_tp['kktp'];
                                $desc_clean = trim(str_ireplace($kata_hapus, '', $deskripsi));
                                $desc_clean = preg_replace('/\s+/', ' ', $desc_clean);
                                $desc_clean = lcfirst($desc_clean);

                                if (!isset($rekap_tp[$desc_clean]) || $rekap_tp[$desc_clean]['avg'] < $avg) {
                                    $rekap_tp[$desc_clean] = ['avg' => $avg, 'original_desc' => $deskripsi, 'kktp' => $kktp_tp];
                                }
                            }

                            $tp_lulus = [];
                            $tp_remedi = [];
                            $total_kktp_mapel = 0;
                            $count_kktp_mapel = 0;

                            foreach ($rekap_tp as $clean_desc => $data) {
                                $total_kktp_mapel += $data['kktp'];
                                $count_kktp_mapel++;
                                $selisih = $data['avg'] - $data['kktp'];

                                if ($data['avg'] >= $data['kktp']) {
                                    $tp_lulus[$clean_desc] = $selisih;
                                } else {
                                    $tp_remedi[$clean_desc] = $selisih;
                                }
                            }
                            $rata_rata_kktp = $count_kktp_mapel > 0 ? round($total_kktp_mapel / $count_kktp_mapel) : $kkm;

                            arsort($tp_lulus);
                            asort($tp_remedi);

                            // AMBIL 1 TP TERTINGGI DAN TERENDAH
                            $top_tp = array_slice(array_keys($tp_lulus), 0, 1);
                            $bottom_tp = array_slice(array_keys($tp_remedi), 0, 1);

                            $deskripsi_draf = "";
                            if (!empty($top_tp)) {
                                $top_score = reset($tp_lulus);
                                if ($top_score >= 90) {
                                    $predikat_teks = "sangat baik";
                                } elseif ($top_score >= 80) {
                                    $predikat_teks = "baik";
                                } else {
                                    $predikat_teks = "cukup baik";
                                }
                                $deskripsi_draf .= "Menunjukkan penguasaan yang $predikat_teks dalam " . implode(', ', $top_tp) . ". ";
                            } elseif ($nilai_akhir >= $rata_rata_kktp && empty($top_tp)) {
                                $deskripsi_draf .= "Secara keseluruhan, capaian kompetensi sudah tuntas. ";
                            }

                            if (!empty($bottom_tp)) {
                                $deskripsi_draf .= "Namun, perlu penguatan lebih lanjut dalam " . implode(', ', $bottom_tp) . ".";
                            } else {
                                $deskripsi_draf .= "Semua tujuan pembelajaran telah tercapai dengan baik.";
                            }

                            $deskripsi_final = ucfirst(trim($deskripsi_draf));

                        } elseif ($nilai_akhir !== null && $nilai_akhir >= $rata_rata_kktp) {
                            $deskripsi_final = 'Capaian kompetensi secara umum sudah menunjukkan ketuntasan yang baik.';
                        } elseif ($nilai_akhir !== null && $nilai_akhir < $rata_rata_kktp) {
                            $deskripsi_final = 'Perlu ditingkatkan lagi pada beberapa tujuan pembelajaran untuk mencapai ketuntasan minimum.';
                        } else {
                            $deskripsi_final = 'Data penilaian belum lengkap atau belum ada penilaian sumatif yang diinput.';
                        }

                        $data_rapor_siswa[$id_mapel] = [
                            'nilai_akhir' => $nilai_akhir,
                            'deskripsi' => $deskripsi_final
                        ];
                    }
                    if ($stmt_sumatif_tp) mysqli_stmt_close($stmt_sumatif_tp);
                    if ($stmt_sumatif_akhir) mysqli_stmt_close($stmt_sumatif_akhir);

                    return $data_rapor_siswa;
                }
            }

            $stmt_rapor_detail_upsert = mysqli_prepare($koneksi, "INSERT INTO rapor_detail_akademik (id_rapor, id_mapel, nilai_akhir, capaian_kompetensi) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nilai_akhir = VALUES(nilai_akhir), capaian_kompetensi = VALUES(capaian_kompetensi)");

            mysqli_begin_transaction($koneksi);
            try {
                $mapel_per_kelas = [];

                while ($row = mysqli_fetch_assoc($q_rapor)) {
                    $id_rapor = $row['id_rapor'];
                    $id_siswa = $row['id_siswa'];
                    $id_kelas = $row['id_kelas'];

                    if (!isset($mapel_per_kelas[$id_kelas])) {
                        $q_mapel = mysqli_query($koneksi, "SELECT DISTINCT m.id_mapel, m.nama_mapel, m.urutan FROM mata_pelajaran m JOIN penilaian p ON m.id_mapel = p.id_mapel WHERE p.id_kelas = $id_kelas AND p.semester = $semester_aktif ORDER BY m.urutan");
                        $mapel_per_kelas[$id_kelas] = mysqli_fetch_all($q_mapel, MYSQLI_ASSOC);
                    }
                    $daftar_mapel = $mapel_per_kelas[$id_kelas];

                    if (empty($daftar_mapel)) continue;

                    $data_akademik = hitungDataRaporSiswa_massal($koneksi, $id_siswa, $id_kelas, $semester_aktif, $kkm, $daftar_mapel);

                    foreach ($data_akademik as $id_mapel => $detail) {
                        if ($detail['nilai_akhir'] !== null) {
                            mysqli_stmt_bind_param($stmt_rapor_detail_upsert, "iiis", $id_rapor, $id_mapel, $detail['nilai_akhir'], $detail['deskripsi']);
                            mysqli_stmt_execute($stmt_rapor_detail_upsert);
                        }
                    }
                    $count_updated++;
                }

                mysqli_commit($koneksi);
                $_SESSION['pesan'] = set_json_pesan('success', 'Regenerate Selesai!', "Berhasil menghitung ulang dan memperbarui narasi capaian kompetensi untuk {$count_updated} siswa.");
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $_SESSION['pesan'] = set_json_pesan('error', 'Gagal Total', 'Terjadi kesalahan saat memproses data: ' . $e->getMessage());
            }
            if ($stmt_rapor_detail_upsert) mysqli_stmt_close($stmt_rapor_detail_upsert);
        } else {
            $_SESSION['pesan'] = set_json_pesan('info', 'Tidak Ada Data', 'Belum ada data rapor yang difinalisasi pada semester ini untuk diregenerate.');
        }

        header("Location: pengaturan_tampil.php");
        exit();

    // --- AKSI 12: MIGRASI DATA KOMPLEKS (STRUKTUR & ISI) ---
    case 'migrasi_via_file':
        if (!isset($_FILES['sql_file_migrasi']) || $_FILES['sql_file_migrasi']['error'] != 0) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Migrasi Gagal', 'File migrasi tidak ditemukan.');
            header("Location: $ui_database"); exit;
        }
        
        $errors = [];
        try {
            mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 0");
            
            // Drop & Re-import
            $res = mysqli_query($koneksi, "SHOW TABLES");
            while($row = mysqli_fetch_row($res)) {
                mysqli_query($koneksi, "DROP TABLE IF EXISTS `{$row[0]}`");
            }
            
            // Eksekusi SQL secara baris per baris untuk menghindari "MySQL server has gone away" (max_allowed_packet limit)
            $templine = '';
            $lines = file($_FILES['sql_file_migrasi']['tmp_name']);
            foreach ($lines as $line) {
                // Abaikan komentar dan baris kosong
                if (substr($line, 0, 2) == '--' || substr($line, 0, 2) == '/*' || trim($line) == '') {
                    continue;
                }
                $templine .= $line;
                // Jika baris diakhiri dengan titik koma, eksekusi query
                if (substr(trim($line), -1, 1) == ';') {
                    try {
                        mysqli_query($koneksi, $templine);
                    } catch (Exception $e) {
                        $errors[] = "Error eksekusi query: " . $e->getMessage();
                    }
                    $templine = '';
                }
            }
            
            // Perbaikan Struktur khusus (Kokurikuler)
            $cek_k = mysqli_query($koneksi, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kokurikuler_kegiatan' AND COLUMN_NAME = 'id_koordinator'");
            if (mysqli_num_rows($cek_k) == 0) {
                $sql_alt = "ALTER TABLE `kokurikuler_kegiatan` 
                           ADD COLUMN `id_koordinator` INT DEFAULT NULL AFTER `bentuk_kegiatan`, 
                           ADD KEY `fk_kegiatan_koordinator` (`id_koordinator`), 
                           ADD CONSTRAINT `fk_kegiatan_koordinator` FOREIGN KEY (`id_koordinator`) REFERENCES `guru` (`id_guru`) ON DELETE SET NULL ON UPDATE CASCADE";
                mysqli_query($koneksi, $sql_alt);
            }

            // Pembuatan tabel relasi kokurikuler jika belum ada
            mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS `kokurikuler_mapel_terlibat` ( `id_kegiatan` int NOT NULL, `id_mapel` int NOT NULL, PRIMARY KEY (`id_kegiatan`,`id_mapel`), KEY `fk_mapel_mapel` (`id_mapel`), CONSTRAINT `fk_mapel_kegiatan` FOREIGN KEY (`id_kegiatan`) REFERENCES `kokurikuler_kegiatan` (`id_kegiatan`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `fk_mapel_mapel` FOREIGN KEY (`id_mapel`) REFERENCES `mata_pelajaran` (`id_mapel`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
            
            mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS `kokurikuler_tim_penilai` ( `id_kegiatan` int NOT NULL, `id_guru` int NOT NULL, PRIMARY KEY (`id_kegiatan`,`id_guru`), KEY `fk_tim_guru` (`id_guru`), CONSTRAINT `fk_tim_guru` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE ON UPDATE CASCADE, CONSTRAINT `fk_tim_kegiatan` FOREIGN KEY (`id_kegiatan`) REFERENCES `kokurikuler_kegiatan` (`id_kegiatan`) ON DELETE CASCADE ON UPDATE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

            // Jalankan seluruh skrip migrasi bisnis
            jalankan_skrip_migrasi($koneksi, $errors);
            
            // Pembersihan data akhir
            mysqli_query($koneksi, "UPDATE `siswa` SET `jenis_kelamin` = NULL WHERE `jenis_kelamin` = ''");
            mysqli_query($koneksi, "SET foreign_key_checks = 1");

            if (empty($errors)) {
                $_SESSION['pesan'] = set_json_pesan('success', 'Migrasi Berhasil!', 'Database telah diimpor dan disesuaikan dengan struktur aplikasi terbaru.');
            } else {
                $_SESSION['pesan'] = set_json_pesan('warning', 'Migrasi Selesai (dengan catatan)', 'Proses selesai, namun terdapat beberapa peringatan minor: ' . implode('; ', array_slice($errors, 0, 2)) . '...');
            }
            
        } catch (Exception $e) {
            try {
                mysqli_query($koneksi, "SET foreign_key_checks = 1");
            } catch (Exception $ex) {
                // Abaikan jika koneksi sudah terputus
            }
            $_SESSION['pesan'] = set_json_pesan('error', 'Migrasi Gagal Total', $e->getMessage());
        }
        header("Location: $ui_database");
        exit();

    // --- AKSI DEFAULT: PROTEKSI REDIRECT ---
    default:
        header("Location: $ui_pengaturan");
        exit();
}
?>