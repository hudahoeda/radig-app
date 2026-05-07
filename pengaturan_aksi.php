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
                WHERE id_sekolah = 1";
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
        $sql = "UPDATE sekolah SET nama_kepsek=?, jabatan_kepsek=?, nip_kepsek=? WHERE id_sekolah = 1";
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
            $q = mysqli_query($koneksi, "SELECT logo_sekolah FROM sekolah WHERE id_sekolah = 1");
            $d = mysqli_fetch_assoc($q);
            if ($d && !empty($d['logo_sekolah']) && file_exists($upload_dir . $d['logo_sekolah'])) {
                unlink($upload_dir . $d['logo_sekolah']);
            }
            $sql = "UPDATE sekolah SET logo_sekolah = ? WHERE id_sekolah = 1";
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

        simpanPengaturan($koneksi, 'cetak_tanpa_kop', $cetak_tanpa_kop);
        simpanPengaturan($koneksi, 'margin_atas_tanpa_kop', $margin_atas);
        simpanPengaturan($koneksi, 'rapor_tampil_kop', $rapor_tampil_kop);

        // Handle Upload Gambar KOP
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
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Gambar KOP sekolah berhasil diperbarui.');
        } else {
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Pengaturan konfigurasi KOP disimpan.');
        }
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
            
            $sql_content = file_get_contents($_FILES['sql_file_migrasi']['tmp_name']);
            if (mysqli_multi_query($koneksi, $sql_content)) {
                do { 
                    if ($result = mysqli_store_result($koneksi)) mysqli_free_result($result);
                } while (mysqli_next_result($koneksi));
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
            mysqli_query($koneksi, "SET foreign_key_checks = 1");
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