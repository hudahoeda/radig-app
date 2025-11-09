<?php
session_start();
include 'koneksi.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    // Untuk AJAX, kembalikan JSON. Untuk form biasa, die()
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Sesi Anda mungkin telah berakhir.']);
        exit();
    } else {
        die("Akses ditolak. Anda harus login sebagai Admin.");
    }
}

// Ambil aksi dari URL
$aksi = $_GET['aksi'] ?? '';

// ===================================================
// --- AKSI BARU UNTUK "QUICK KELUAR" ---
// ===================================================
if ($aksi == 'quick_keluar') {
    // Set header untuk merespon sebagai JSON
    header('Content-Type: application/json');
    
    // Ambil data dari POST
    $id_siswa = (int)$_POST['id_siswa'];
    $alasan = $_POST['alasan'] ?? 'Alasan tidak diisi (Aksi Cepat)';
    $tanggal_keluar = date('Y-m-d'); // Set tanggal keluar hari ini

    // Validasi dasar
    if (empty($id_siswa) || empty(trim($alasan))) {
        echo json_encode(['status' => 'error', 'message' => 'ID Siswa atau Alasan tidak boleh kosong.']);
        exit();
    }

    // Ambil info kelas siswa saat ini sebelum di-null-kan
    $q_kelas = mysqli_query($koneksi, "SELECT k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.id_kelas = k.id_kelas WHERE s.id_siswa = $id_siswa");
    $d_kelas = mysqli_fetch_assoc($q_kelas);
    $kelas_ditinggalkan_raw = $d_kelas['nama_kelas'] ?? 'Tidak ada kelas';

    // ===================================================================
    // --- PERBAIKAN: Potong string agar tidak error "Data too long" ---
    // Asumsi kolom database 'kelas_ditinggalkan' punya batas, kita potong di 50 karakter
    $kelas_ditinggalkan = substr($kelas_ditinggalkan_raw, 0, 5);
    // ===================================================================

    mysqli_begin_transaction($koneksi);
    try {
        // 1. Update status siswa & lepaskan dari kelas (set id_kelas = NULL)
        $stmt_update = mysqli_prepare($koneksi, "UPDATE siswa SET status_siswa = 'Keluar', id_kelas = NULL WHERE id_siswa = ?");
        mysqli_stmt_bind_param($stmt_update, "i", $id_siswa);
        mysqli_stmt_execute($stmt_update);

        // 2. Hapus data mutasi keluar yang lama (jika ada) untuk diganti yang baru
        $stmt_delete = mysqli_prepare($koneksi, "DELETE FROM mutasi_keluar WHERE id_siswa = ?");
        mysqli_stmt_bind_param($stmt_delete, "i", $id_siswa);
        mysqli_stmt_execute($stmt_delete);
        
        // 3. Masukkan data mutasi keluar yang baru
        $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO mutasi_keluar (id_siswa, tanggal_keluar, kelas_ditinggalkan, alasan) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_insert, "isss", $id_siswa, $tanggal_keluar, $kelas_ditinggalkan, $alasan);
        mysqli_stmt_execute($stmt_insert);

        mysqli_commit($koneksi);
        echo json_encode(['status' => 'success', 'message' => 'Siswa berhasil ditandai sebagai "Keluar".']);

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan database: ' . $exception->getMessage()]);
    }
    
    // Penting: Hentikan eksekusi setelah mengirim JSON
    exit();
}


// =============================================
// --- AKSI UNTUK PROSES MUTASI KELUAR/LULUS --- (Logika Lama Anda)
// =============================================
if ($aksi == 'proses_keluar') {
    // Ambil data dari form
    $id_siswa = (int)$_POST['id_siswa'];
    $status_siswa = $_POST['status_siswa']; // Pindah, Lulus, atau Keluar
    $tanggal_keluar = $_POST['tanggal_keluar'];
    $kelas_ditinggalkan = $_POST['kelas_ditinggalkan'];
    $alasan = $_POST['alasan'];

    // Validasi dasar
    if (empty($id_siswa) || empty($status_siswa) || empty($tanggal_keluar) || empty($alasan)) {
        $_SESSION['pesan_error'] = "Semua kolom wajib diisi.";
        header("Location: kelola_mutasi.php?id_siswa=" . $id_siswa);
        exit();
    }

    mysqli_begin_transaction($koneksi);
    try {
        // 1. Update status siswa dan lepaskan dari kelas (set id_kelas = NULL)
        $stmt_update_siswa = mysqli_prepare($koneksi, "UPDATE siswa SET status_siswa = ?, id_kelas = NULL WHERE id_siswa = ?");
        mysqli_stmt_bind_param($stmt_update_siswa, "si", $status_siswa, $id_siswa);
        mysqli_stmt_execute($stmt_update_siswa);

        // 2. Hapus data mutasi keluar yang lama (jika ada) untuk diganti yang baru
        $stmt_delete_mutasi = mysqli_prepare($koneksi, "DELETE FROM mutasi_keluar WHERE id_siswa = ?");
        mysqli_stmt_bind_param($stmt_delete_mutasi, "i", $id_siswa);
        mysqli_stmt_execute($stmt_delete_mutasi);
        
        // 3. Masukkan data mutasi keluar yang baru
        $stmt_insert_mutasi = mysqli_prepare($koneksi, "INSERT INTO mutasi_keluar (id_siswa, tanggal_keluar, kelas_ditinggalkan, alasan) VALUES (?, ?, ?, ?)");
        // PERHATIAN: Pastikan $kelas_ditinggalkan dari form ini juga tidak terlalu panjang. 
        // Namun, error Anda terjadi di 'quick_keluar', jadi kita fokus di sana.
        mysqli_stmt_bind_param($stmt_insert_mutasi, "isss", $id_siswa, $tanggal_keluar, $kelas_ditinggalkan, $alasan);
        mysqli_stmt_execute($stmt_insert_mutasi);

        mysqli_commit($koneksi);
        $_SESSION['pesan'] = "Status siswa berhasil diubah dan data mutasi keluar telah dicatat.";

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan_error'] = "Terjadi kesalahan database: " . $exception->getMessage();
    }
    
    header("Location: mutasi_siswa_tampil.php");
    exit();
}

// =============================================
// --- AKSI UNTUK PROSES MUTASI MASUK --- (Logika Lama Anda)
// =============================================
if ($aksi == 'proses_masuk') {
    // Ambil data siswa baru dari form
    $nama_lengkap = $_POST['nama_lengkap'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $nis = $_POST['nis'];
    $nisn = $_POST['nisn'];
    $sekolah_asal = $_POST['sekolah_asal'];
    $id_kelas = (int)$_POST['id_kelas'];
    $diterima_tanggal = $_POST['diterima_tanggal'];

    // Validasi dasar
    if (empty($nama_lengkap) || empty($nisn) || empty($id_kelas)) {
        $_SESSION['pesan_error'] = "Nama, NISN, dan Kelas Penempatan wajib diisi.";
        header("Location: kelola_mutasi.php?aksi=masuk");
        exit();
    }
    
    // Set username & password default
    $username = $nisn;
    $password_default = password_hash($nisn, PASSWORD_BCRYPT);

    mysqli_begin_transaction($koneksi);
    try {
        // 1. Buat data siswa baru di tabel `siswa`
        $stmt_insert_siswa = mysqli_prepare($koneksi, 
            "INSERT INTO siswa (nisn, nis, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, id_kelas, username, password, status_siswa, sekolah_asal, diterima_tanggal) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', ?, ?)");
        mysqli_stmt_bind_param($stmt_insert_siswa, "ssssssissss", $nisn, $nis, $nama_lengkap, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $id_kelas, $username, $password_default, $sekolah_asal, $diterima_tanggal);
        mysqli_stmt_execute($stmt_insert_siswa);
        
        // Ambil ID siswa yang baru saja dibuat
        $id_siswa_baru = mysqli_insert_id($koneksi);

        // 2. Catat data di tabel `mutasi_masuk`
        $tahun_pelajaran = date('Y') . '/' . (date('Y') + 1); // Otomatisasi tahun pelajaran
        $nama_kelas_diterima = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas=$id_kelas"))['nama_kelas'];
        
        $stmt_insert_mutasi = mysqli_prepare($koneksi, 
            "INSERT INTO mutasi_masuk (id_siswa, sekolah_asal, tanggal_masuk, diterima_di_kelas, tahun_pelajaran) 
             VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_insert_mutasi, "issss", $id_siswa_baru, $sekolah_asal, $diterima_tanggal, $nama_kelas_diterima, $tahun_pelajaran);
        mysqli_stmt_execute($stmt_insert_mutasi);

        mysqli_commit($koneksi);
        $_SESSION['pesan'] = "Siswa pindahan baru bernama ".htmlspecialchars($nama_lengkap)." berhasil ditambahkan.";

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan_error'] = "Gagal menambahkan siswa baru. Error: " . $exception->getMessage();
    }

    header("Location: mutasi_siswa_tampil.php");
    exit();
}

// Jika tidak ada aksi yang cocok
header("Location: dashboard.php");
exit();

?>