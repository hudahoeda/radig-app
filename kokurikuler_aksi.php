<?php
session_start();
include 'koneksi.php';

// Cek apakah pengguna sudah login
if (!isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

//======================================================================
// --- AKSI TAMBAH KEGIATAN BARU ---
//======================================================================
if ($aksi == 'tambah') {
    if ($_SESSION['role'] != 'admin') die("Akses ditolak.");
    
    $tema = $_POST['tema_kegiatan'];
    $semester = $_POST['semester'];
    $bentuk = $_POST['bentuk_kegiatan'];
    $id_koordinator = (int)$_POST['id_koordinator'];
    $dimensi_terpilih = isset($_POST['dimensi']) ? $_POST['dimensi'] : [];
    $mapel_terpilih = isset($_POST['mapel_terlibat']) ? $_POST['mapel_terlibat'] : [];
    $kelas_terpilih = isset($_POST['kelas_sasaran']) ? $_POST['kelas_sasaran'] : [];
    
    $query_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $ta_aktif = mysqli_fetch_assoc($query_ta_aktif);
    $id_tahun_ajaran = $ta_aktif['id_tahun_ajaran'];

    mysqli_begin_transaction($koneksi);
    try {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_kegiatan (id_tahun_ajaran, semester, tema_kegiatan, bentuk_kegiatan, id_koordinator) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssi", $id_tahun_ajaran, $semester, $tema, $bentuk, $id_koordinator);
        mysqli_stmt_execute($stmt);
        $id_kegiatan_baru = mysqli_insert_id($koneksi);

        if (!empty($dimensi_terpilih)) {
            $stmt_d = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_target_dimensi (id_kegiatan, nama_dimensi) VALUES (?, ?)");
            foreach ($dimensi_terpilih as $dim) { mysqli_stmt_bind_param($stmt_d, "is", $id_kegiatan_baru, $dim); mysqli_stmt_execute($stmt_d); }
        }
        if (!empty($mapel_terpilih)) {
            $stmt_m = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_mapel_terlibat (id_kegiatan, id_mapel) VALUES (?, ?)");
            foreach ($mapel_terpilih as $m) { mysqli_stmt_bind_param($stmt_m, "ii", $id_kegiatan_baru, $m); mysqli_stmt_execute($stmt_m); }
        }
        if (!empty($kelas_terpilih)) {
            $stmt_k = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_kelas_terlibat (id_kegiatan, id_kelas) VALUES (?, ?)");
            foreach ($kelas_terpilih as $k) { mysqli_stmt_bind_param($stmt_k, "ii", $id_kegiatan_baru, $k); mysqli_stmt_execute($stmt_k); }
        }

        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Kegiatan berhasil ditambahkan.']);
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()]);
    }
    header("location: kokurikuler_tampil.php");
    exit();
}

//======================================================================
// --- AKSI UPDATE KEGIATAN ---
//======================================================================
elseif ($aksi == 'update') {
    if ($_SESSION['role'] != 'admin') die("Akses ditolak.");
    
    $id_kegiatan = (int)$_POST['id_kegiatan'];
    $tema = $_POST['tema_kegiatan'];
    $semester = $_POST['semester'];
    $bentuk = $_POST['bentuk_kegiatan'];
    $id_koordinator = (int)$_POST['id_koordinator'];
    $dimensi_terpilih = isset($_POST['dimensi']) ? $_POST['dimensi'] : [];
    $mapel_terpilih = isset($_POST['mapel_terlibat']) ? $_POST['mapel_terlibat'] : [];
    $kelas_terpilih = isset($_POST['kelas_sasaran']) ? $_POST['kelas_sasaran'] : [];

    mysqli_begin_transaction($koneksi);
    try {
        $stmt = mysqli_prepare($koneksi, "UPDATE kokurikuler_kegiatan SET semester=?, tema_kegiatan=?, bentuk_kegiatan=?, id_koordinator=? WHERE id_kegiatan=?");
        mysqli_stmt_bind_param($stmt, "ssssi", $semester, $tema, $bentuk, $id_koordinator, $id_kegiatan);
        mysqli_stmt_execute($stmt);
        
        // Update anak tabel: Hapus dulu, lalu insert ulang
        mysqli_query($koneksi, "DELETE FROM kokurikuler_target_dimensi WHERE id_kegiatan=$id_kegiatan");
        if (!empty($dimensi_terpilih)) {
            $stmt_d = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_target_dimensi (id_kegiatan, nama_dimensi) VALUES (?, ?)");
            foreach ($dimensi_terpilih as $dim) { mysqli_stmt_bind_param($stmt_d, "is", $id_kegiatan, $dim); mysqli_stmt_execute($stmt_d); }
        }

        mysqli_query($koneksi, "DELETE FROM kokurikuler_mapel_terlibat WHERE id_kegiatan=$id_kegiatan");
        if (!empty($mapel_terpilih)) {
            $stmt_m = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_mapel_terlibat (id_kegiatan, id_mapel) VALUES (?, ?)");
            foreach ($mapel_terpilih as $m) { mysqli_stmt_bind_param($stmt_m, "ii", $id_kegiatan, $m); mysqli_stmt_execute($stmt_m); }
        }

        mysqli_query($koneksi, "DELETE FROM kokurikuler_kelas_terlibat WHERE id_kegiatan=$id_kegiatan");
        if (!empty($kelas_terpilih)) {
            $stmt_k = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_kelas_terlibat (id_kegiatan, id_kelas) VALUES (?, ?)");
            foreach ($kelas_terpilih as $k) { mysqli_stmt_bind_param($stmt_k, "ii", $id_kegiatan, $k); mysqli_stmt_execute($stmt_k); }
        }

        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Kegiatan diperbarui.']);
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()]);
    }
    header("location: kokurikuler_tampil.php");
    exit();
}

//======================================================================
// --- AKSI HAPUS KEGIATAN ---
//======================================================================
elseif ($aksi == 'hapus') {
    if ($_SESSION['role'] != 'admin') die("Akses ditolak.");
    $id = (int)$_GET['id'];
    
    // ON DELETE CASCADE di database seharusnya menangani anak-anaknya, 
    // tapi menghapus manual lebih aman jika constraint tidak sempurna.
    mysqli_begin_transaction($koneksi);
    try {
        mysqli_query($koneksi, "DELETE FROM kokurikuler_asesmen WHERE id_target IN (SELECT id_target FROM kokurikuler_target_dimensi WHERE id_kegiatan=$id)");
        mysqli_query($koneksi, "DELETE FROM kokurikuler_target_dimensi WHERE id_kegiatan=$id");
        mysqli_query($koneksi, "DELETE FROM kokurikuler_mapel_terlibat WHERE id_kegiatan=$id");
        mysqli_query($koneksi, "DELETE FROM kokurikuler_kelas_terlibat WHERE id_kegiatan=$id");
        mysqli_query($koneksi, "DELETE FROM kokurikuler_tim_penilai WHERE id_kegiatan=$id");
        mysqli_query($koneksi, "DELETE FROM kokurikuler_kegiatan WHERE id_kegiatan=$id");
        
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Terhapus', 'text' => 'Data kegiatan dihapus.']);
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => $e->getMessage()]);
    }
    header("location: kokurikuler_tampil.php");
    exit();
}

//======================================================================
// --- [UPDATED] AKSI SIMPAN TIM PENILAI (PER KELAS) ---
//======================================================================
elseif ($aksi == 'simpan_tim') {
    $id_kegiatan = (int)$_POST['id_kegiatan'];
    
    // Data tim sekarang berbentuk array multi-dimensi: tim[id_kelas][] = id_guru
    $tim_data = isset($_POST['tim']) ? $_POST['tim'] : [];
    
    $id_guru_login = (int)$_SESSION['id_guru'];
    $role_login = $_SESSION['role'];

    // Cek Hak Akses
    $cek = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id_koordinator FROM kokurikuler_kegiatan WHERE id_kegiatan=$id_kegiatan"));
    if ($role_login != 'admin' && $cek['id_koordinator'] != $id_guru_login) {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Akses Ditolak', 'text' => 'Anda bukan koordinator.']);
        header("location: kokurikuler_pilih.php");
        exit();
    }

    mysqli_begin_transaction($koneksi);
    try {
        // 1. Hapus SEMUA tim penilai lama untuk kegiatan ini
        // (Kita reset ulang berdasarkan input baru agar sinkron)
        $query_hapus = "DELETE FROM kokurikuler_tim_penilai WHERE id_kegiatan = ?";
        $stmt_hapus = mysqli_prepare($koneksi, $query_hapus);
        mysqli_stmt_bind_param($stmt_hapus, "i", $id_kegiatan);
        mysqli_stmt_execute($stmt_hapus);
        
        // 2. Insert data baru
        // Struktur tabel baru: id_kegiatan, id_kelas, id_guru
        if (!empty($tim_data)) {
            $stmt_tambah = mysqli_prepare($koneksi, "INSERT INTO kokurikuler_tim_penilai (id_kegiatan, id_kelas, id_guru) VALUES (?, ?, ?)");
            
            foreach ($tim_data as $id_kelas_target => $guru_ids) {
                // $id_kelas_target adalah ID Kelas
                // $guru_ids adalah array ID Guru yang dicentang untuk kelas tsb
                if (is_array($guru_ids)) {
                    foreach ($guru_ids as $id_guru_penilai) {
                        // Jangan masukkan koordinator (opsional, tapi koordinator biasanya punya akses 'dewa' di kode lain)
                        if ($id_guru_penilai != $cek['id_koordinator']) {
                            mysqli_stmt_bind_param($stmt_tambah, "iii", $id_kegiatan, $id_kelas_target, $id_guru_penilai);
                            mysqli_stmt_execute($stmt_tambah);
                        }
                    }
                }
            }
        }
        
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Pembagian tugas tim penilai berhasil disimpan.']);

    } catch (Exception $exception) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan: ' . $exception->getMessage()]);
    }
    
    header("location: kokurikuler_kelola_tim.php?id=" . $id_kegiatan);
    exit();
}

//======================================================================
// --- AKSI SIMPAN ASESMEN ---
//======================================================================
elseif ($aksi == 'simpan_asesmen') {
    if (!in_array($_SESSION['role'], ['guru', 'admin'])) die("Akses ditolak.");
    
    $id_guru_penilai = (int)$_SESSION['id_guru']; 
    $id_kegiatan = (int)$_POST['id_kegiatan'];
    $nilai_data = $_POST['nilai'];
    $catatan_data = isset($_POST['catatan']) ? $_POST['catatan'] : [];

    $query = "INSERT INTO kokurikuler_asesmen (id_target, id_siswa, id_guru_penilai, nilai_kualitatif, catatan_guru) VALUES (?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE nilai_kualitatif = VALUES(nilai_kualitatif), catatan_guru = VALUES(catatan_guru)";
    $stmt = mysqli_prepare($koneksi, $query);

    foreach ($nilai_data as $id_siswa => $dimensi_penilaian) {
        foreach ($dimensi_penilaian as $id_target => $nilai_kualitatif) {
            if ($nilai_kualitatif !== '') {
                $catatan = isset($catatan_data[$id_siswa][$id_target]) ? $catatan_data[$id_siswa][$id_target] : '';
                mysqli_stmt_bind_param($stmt, "iiiss", $id_target, $id_siswa, $id_guru_penilai, $nilai_kualitatif, $catatan);
                mysqli_stmt_execute($stmt);
            }
        }
    }

    $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Tersimpan', 'text' => 'Penilaian berhasil disimpan.']);
    header("location: kokurikuler_input.php?kegiatan=" . $id_kegiatan . "&kelas=" . $_POST['id_kelas']);
    exit();
}

else {
    header("location: dashboard.php");
    exit();
}
?>