<?php
session_start();
include 'koneksi.php';

// Cek apakah pengguna sudah login
if (!isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

//======================================================================
// --- AKSI TAMBAH KEGIATAN BARU (OLEH ADMIN) ---
//======================================================================
if ($aksi == 'tambah') {
    // Hanya admin yang bisa melakukan aksi ini
    if ($_SESSION['role'] != 'admin') {
        die("Akses ditolak. Anda bukan Admin.");
    }
    
    // Ambil data dari form
    $tema = $_POST['tema_kegiatan'];
    $semester = $_POST['semester'];
    $bentuk = $_POST['bentuk_kegiatan'];
    $dimensi_terpilih = isset($_POST['dimensi']) ? $_POST['dimensi'] : [];
    $id_koordinator = (int)$_POST['id_koordinator']; // <-- TAMBAHAN
    $mapel_terpilih = isset($_POST['mapel_terlibat']) ? $_POST['mapel_terlibat'] : []; // <-- TAMBAHAN
    
    // Ambil ID tahun ajaran yang aktif
    $query_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $ta_aktif = mysqli_fetch_assoc($query_ta_aktif);
    $id_tahun_ajaran = $ta_aktif['id_tahun_ajaran'];

    // Mulai transaksi database
    mysqli_begin_transaction($koneksi);

    try {
        // 1. Masukkan data ke tabel kokurikuler_kegiatan (dengan id_koordinator)
        // <-- DIMODIFIKASI: Menambah id_koordinator
        $query_kegiatan = "INSERT INTO kokurikuler_kegiatan (id_tahun_ajaran, semester, tema_kegiatan, bentuk_kegiatan, id_koordinator) VALUES (?, ?, ?, ?, ?)";
        $stmt_kegiatan = mysqli_prepare($koneksi, $query_kegiatan);
        mysqli_stmt_bind_param($stmt_kegiatan, "isssi", $id_tahun_ajaran, $semester, $tema, $bentuk, $id_koordinator); // <-- DIMODIFIKASI: "isssi"
        mysqli_stmt_execute($stmt_kegiatan);
        
        // Ambil ID kegiatan yang baru saja dibuat
        $id_kegiatan_baru = mysqli_insert_id($koneksi);

        // 2. Masukkan data dimensi yang dipilih ke tabel kokurikuler_target_dimensi (Tidak berubah)
        if (!empty($dimensi_terpilih)) {
            $query_dimensi = "INSERT INTO kokurikuler_target_dimensi (id_kegiatan, nama_dimensi) VALUES (?, ?)";
            $stmt_dimensi = mysqli_prepare($koneksi, $query_dimensi);
            
            foreach ($dimensi_terpilih as $dimensi) {
                mysqli_stmt_bind_param($stmt_dimensi, "is", $id_kegiatan_baru, $dimensi);
                mysqli_stmt_execute($stmt_dimensi);
            }
        }
        
        // 3. Masukkan data mapel yang dipilih ke tabel kokurikuler_mapel_terlibat // <-- TAMBAHAN
        if (!empty($mapel_terpilih)) {
            $query_mapel = "INSERT INTO kokurikuler_mapel_terlibat (id_kegiatan, id_mapel) VALUES (?, ?)";
            $stmt_mapel = mysqli_prepare($koneksi, $query_mapel);
            
            foreach ($mapel_terpilih as $id_mapel) {
                mysqli_stmt_bind_param($stmt_mapel, "ii", $id_kegiatan_baru, $id_mapel);
                mysqli_stmt_execute($stmt_mapel);
            }
        }

        // Jika semua berhasil, commit transaksi
        mysqli_commit($koneksi);
        // <-- DIMODIFIKASI: Menggunakan format JSON untuk notifikasi
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Rencana kegiatan kokurikuler berhasil ditambahkan.']);

    } catch (mysqli_sql_exception $exception) {
        // Jika ada error, batalkan semua perubahan
        mysqli_rollback($koneksi);
        // <-- DIMODIFIKASI: Menggunakan format JSON untuk notifikasi
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan: ' . $exception->getMessage()]);
    }

    header("location: kokurikuler_tampil.php");
    exit();
}

//======================================================================
// --- AKSI UPDATE KEGIATAN (OLEH ADMIN) ---
//======================================================================
// <-- BLOK BARU -->
elseif ($aksi == 'update') {
    // Hanya admin yang bisa melakukan aksi ini
    if ($_SESSION['role'] != 'admin') {
        die("Akses ditolak. Anda bukan Admin.");
    }
    
    // Ambil data dari form
    $id_kegiatan = (int)$_POST['id_kegiatan'];
    $tema = $_POST['tema_kegiatan'];
    $semester = $_POST['semester'];
    $bentuk = $_POST['bentuk_kegiatan'];
    $id_koordinator = (int)$_POST['id_koordinator'];
    $dimensi_terpilih = isset($_POST['dimensi']) ? $_POST['dimensi'] : [];
    $mapel_terpilih = isset($_POST['mapel_terlibat']) ? $_POST['mapel_terlibat'] : [];
    
    if ($id_kegiatan == 0) {
        $_SESSION['pesan_error'] = "ID Kegiatan tidak valid.";
        header("location: kokurikuler_tampil.php");
        exit();
    }

    // Mulai transaksi database
    mysqli_begin_transaction($koneksi);

    try {
        // 1. Update data utama di kokurikuler_kegiatan
        $query_kegiatan = "UPDATE kokurikuler_kegiatan SET semester = ?, tema_kegiatan = ?, bentuk_kegiatan = ?, id_koordinator = ? WHERE id_kegiatan = ?";
        $stmt_kegiatan = mysqli_prepare($koneksi, $query_kegiatan);
        mysqli_stmt_bind_param($stmt_kegiatan, "ssssi", $semester, $tema, $bentuk, $id_koordinator, $id_kegiatan);
        mysqli_stmt_execute($stmt_kegiatan);
        
        // 2. Update dimensi (Hapus yang lama, masukkan yang baru)
        $query_hapus_dimensi = "DELETE FROM kokurikuler_target_dimensi WHERE id_kegiatan = ?";
        $stmt_hapus_dimensi = mysqli_prepare($koneksi, $query_hapus_dimensi);
        mysqli_stmt_bind_param($stmt_hapus_dimensi, "i", $id_kegiatan);
        mysqli_stmt_execute($stmt_hapus_dimensi);
        
        if (!empty($dimensi_terpilih)) {
            $query_dimensi = "INSERT INTO kokurikuler_target_dimensi (id_kegiatan, nama_dimensi) VALUES (?, ?)";
            $stmt_dimensi = mysqli_prepare($koneksi, $query_dimensi);
            foreach ($dimensi_terpilih as $dimensi) {
                mysqli_stmt_bind_param($stmt_dimensi, "is", $id_kegiatan, $dimensi);
                mysqli_stmt_execute($stmt_dimensi);
            }
        }
        
        // 3. Update mapel terlibat (Hapus yang lama, masukkan yang baru)
        $query_hapus_mapel = "DELETE FROM kokurikuler_mapel_terlibat WHERE id_kegiatan = ?";
        $stmt_hapus_mapel = mysqli_prepare($koneksi, $query_hapus_mapel);
        mysqli_stmt_bind_param($stmt_hapus_mapel, "i", $id_kegiatan);
        mysqli_stmt_execute($stmt_hapus_mapel);

        if (!empty($mapel_terpilih)) {
            $query_mapel = "INSERT INTO kokurikuler_mapel_terlibat (id_kegiatan, id_mapel) VALUES (?, ?)";
            $stmt_mapel = mysqli_prepare($koneksi, $query_mapel);
            foreach ($mapel_terpilih as $id_mapel) {
                mysqli_stmt_bind_param($stmt_mapel, "ii", $id_kegiatan, $id_mapel);
                mysqli_stmt_execute($stmt_mapel);
            }
        }

        // Jika semua berhasil, commit transaksi
        mysqli_commit($koneksi);
        // <-- DIMODIFIKASI: Menggunakan format JSON untuk notifikasi
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Kegiatan kokurikuler berhasil diperbarui.']);

    } catch (mysqli_sql_exception $exception) {
        // Jika ada error, batalkan semua perubahan
        mysqli_rollback($koneksi);
        // <-- DIMODIFIKASI: Menggunakan format JSON untuk notifikasi
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan saat update: ' . $exception->getMessage()]);
    }

    header("location: kokurikuler_tampil.php");
    exit();
}

//======================================================================
// --- AKSI HAPUS KEGIATAN (OLEH ADMIN) ---
//======================================================================
// <-- BLOK BARU -->
elseif ($aksi == 'hapus') {
    // Hanya admin yang bisa melakukan aksi ini
    if ($_SESSION['role'] != 'admin') {
        die("Akses ditolak. Anda bukan Admin.");
    }
    
    $id_kegiatan = (int)$_GET['id'];
    
    if ($id_kegiatan == 0) {
        $_SESSION['pesan_error'] = "ID Kegiatan tidak valid.";
        header("location: kokurikuler_tampil.php");
        exit();
    }

    // Mulai transaksi database
    mysqli_begin_transaction($koneksi);
    try {
        // Hapus data terkait secara manual untuk FK yang tidak 'ON DELETE CASCADE'
        
        // 1. Hapus Asesmen (Anak dari Target Dimensi)
        // Kita perlu subquery berlapis karena MySQL tidak mengizinkan DELETE dari tabel yang sama yang di-SELECT
        $query_hapus_asesmen = "DELETE FROM kokurikuler_asesmen 
                               WHERE id_target IN (
                                   SELECT id_target FROM (
                                       SELECT id_target FROM kokurikuler_target_dimensi WHERE id_kegiatan = ?
                                   ) as tmp
                               )";
        $stmt_hapus_asesmen = mysqli_prepare($koneksi, $query_hapus_asesmen);
        mysqli_stmt_bind_param($stmt_hapus_asesmen, "i", $id_kegiatan);
        mysqli_stmt_execute($stmt_hapus_asesmen);

        // 2. Hapus Target Dimensi (Anak dari Kegiatan)
        $query_hapus_dimensi = "DELETE FROM kokurikuler_target_dimensi WHERE id_kegiatan = ?";
        $stmt_hapus_dimensi = mysqli_prepare($koneksi, $query_hapus_dimensi);
        mysqli_stmt_bind_param($stmt_hapus_dimensi, "i", $id_kegiatan);
        mysqli_stmt_execute($stmt_hapus_dimensi);
        
        // 3. Hapus Kegiatan Utama (Ini akan otomatis menghapus mapel_terlibat dan tim_penilai via ON DELETE CASCADE)
        $query_hapus_kegiatan = "DELETE FROM kokurikuler_kegiatan WHERE id_kegiatan = ?";
        $stmt_hapus_kegiatan = mysqli_prepare($koneksi, $query_hapus_kegiatan);
        mysqli_stmt_bind_param($stmt_hapus_kegiatan, "i", $id_kegiatan);
        mysqli_stmt_execute($stmt_hapus_kegiatan);
        
        // Commit
        mysqli_commit($koneksi);
        // <-- DIMODIFIKASI: Menggunakan format JSON untuk notifikasi
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Kegiatan kokurikuler dan semua data terkait berhasil dihapus.']);

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        // <-- DIMODIFIKASI: Menggunakan format JSON untuk notifikasi
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus: ' . $exception->getMessage()]);
    }
    
    header("location: kokurikuler_tampil.php");
    exit();
}

//======================================================================
// --- AKSI SIMPAN TIM PENILAI (OLEH KOORDINATOR/ADMIN) ---
//======================================================================
// <-- BLOK BARU -->
elseif ($aksi == 'simpan_tim') {
    $id_kegiatan = (int)$_POST['id_kegiatan'];
    $tim_guru_ids = isset($_POST['tim_guru']) ? $_POST['tim_guru'] : [];
    $id_guru_login = (int)$_SESSION['id_guru'];
    $role_login = $_SESSION['role'];

    if ($id_kegiatan == 0) {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'ID Kegiatan tidak valid.']);
        header("location: kokurikuler_tampil.php");
        exit();
    }

    // Keamanan: Cek apakah user adalah admin atau koordinator projek
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_koordinator FROM kokurikuler_kegiatan WHERE id_kegiatan = ?");
    mysqli_stmt_bind_param($stmt_cek, "i", $id_kegiatan);
    mysqli_stmt_execute($stmt_cek);
    $keg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
    
    if ($role_login != 'admin' && $keg['id_koordinator'] != $id_guru_login) {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Akses Ditolak', 'text' => 'Anda bukan koordinator untuk projek ini.']);
        header("location: kokurikuler_pilih.php");
        exit();
    }

    // Mulai transaksi
    mysqli_begin_transaction($koneksi);
    try {
        // 1. Hapus tim lama (kecuali koordinator, meskipun koordinator seharusnya tidak ada di tabel ini, tapi untuk keamanan)
        $query_hapus = "DELETE FROM kokurikuler_tim_penilai WHERE id_kegiatan = ?";
        $stmt_hapus = mysqli_prepare($koneksi, $query_hapus);
        mysqli_stmt_bind_param($stmt_hapus, "i", $id_kegiatan);
        mysqli_stmt_execute($stmt_hapus);
        
        // 2. Masukkan tim baru
        if (!empty($tim_guru_ids)) {
            $query_tambah = "INSERT INTO kokurikuler_tim_penilai (id_kegiatan, id_guru) VALUES (?, ?)";
            $stmt_tambah = mysqli_prepare($koneksi, $query_tambah);
            
            foreach ($tim_guru_ids as $id_guru_tim) {
                // Pastikan tidak memasukkan koordinator ke dalam tabel tim (karena dia sudah pasti punya akses)
                if ($id_guru_tim != $keg['id_koordinator']) {
                    mysqli_stmt_bind_param($stmt_tambah, "ii", $id_kegiatan, $id_guru_tim);
                    mysqli_stmt_execute($stmt_tambah);
                }
            }
        }
        
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Tim penilai berhasil diperbarui.']);

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan database: ' . $exception->getMessage()]);
    }
    
    header("location: kokurikuler_kelola_tim.php?id=" . $id_kegiatan);
    exit();
}


//======================================================================
// --- AKSI SIMPAN ASESMEN (OLEH GURU/WALI KELAS/ADMIN) ---
//======================================================================
elseif ($aksi == 'simpan_asesmen') {
    // Validasi peran, yang boleh input adalah guru, wali kelas, atau admin
    if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
        die("Akses ditolak.");
    }
    
    // <!-- TAMBAHAN: Ambil ID Guru yang sedang login -->
    $id_guru_penilai = (int)$_SESSION['id_guru']; 

    $id_kegiatan = (int)$_POST['id_kegiatan'];
    $nilai_data = $_POST['nilai'];
    // Kita juga akan menangani catatan jika ada
    $catatan_data = isset($_POST['catatan']) ? $_POST['catatan'] : [];

    // Logika UPSERT (UPDATE jika ada, INSERT jika belum ada)
    // <-- DIMODIFIKASI: Menambah id_guru_penilai
    $query = "INSERT INTO kokurikuler_asesmen (id_target, id_siswa, id_guru_penilai, nilai_kualitatif, catatan_guru) VALUES (?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE nilai_kualitatif = VALUES(nilai_kualitatif), catatan_guru = VALUES(catatan_guru)";
    $stmt = mysqli_prepare($koneksi, $query);

    foreach ($nilai_data as $id_siswa => $dimensi_penilaian) {
        foreach ($dimensi_penilaian as $id_target => $nilai_kualitatif) {
            if ($nilai_kualitatif !== '') {
                // Ambil catatan yang sesuai, jika ada
                $catatan = isset($catatan_data[$id_siswa][$id_target]) ? $catatan_data[$id_siswa][$id_target] : '';
                // <-- DIMODIFIKASI: Bind 5 parameter "iiiss"
                mysqli_stmt_bind_param($stmt, "iiiss", $id_target, $id_siswa, $id_guru_penilai, $nilai_kualitatif, $catatan);
                mysqli_stmt_execute($stmt);
            }
        }
    }

    // <-- DIMODIFIKASI: Menggunakan format JSON untuk notifikasi
    $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil Disimpan', 'text' => 'Perubahan asesmen telah disimpan.']);
    header("location: kokurikuler_input.php?kegiatan=" . $id_kegiatan . "&kelas=" . $_POST['id_kelas']); // <-- DIMODIFIKASI: Menambah id_kelas di redirect
    exit();
}

// Jika tidak ada aksi yang cocok
else {
    header("location: dashboard.php");
    exit();
}
?>