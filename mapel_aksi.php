<?php
session_start();
include 'koneksi.php';

// Validasi peran admin di awal
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    // Jika header belum terkirim (misal request AJAX), kirim sebagai JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    // Jika request biasa, bisa juga redirect, tapi JSON lebih aman untuk AJAX
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit();
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

// --- 1. LOGIKA UNTUK MENGURUTKAN MAPEL ---
if ($aksi == 'simpan_urutan') {
    // Set header ke JSON khusus untuk aksi ini
    header('Content-Type: application/json');

    $urutan_json = $_POST['urutan'];
    $urutan_ids = json_decode($urutan_json);

    if (is_array($urutan_ids)) {
        $query = "UPDATE mata_pelajaran SET urutan = ? WHERE id_mapel = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        
        foreach ($urutan_ids as $index => $id_mapel) {
            $urutan_baru = $index + 1;
            mysqli_stmt_bind_param($stmt, "ii", $urutan_baru, $id_mapel);
            mysqli_stmt_execute($stmt);
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak valid']);
    }
    exit();
}

// --- 2. LOGIKA UNTUK TAMBAH MAPEL ---
elseif ($aksi == 'tambah') {
    $kode_mapel = $_POST['kode_mapel'];
    $nama_mapel = $_POST['nama_mapel'];
    // [MODIFIKASI] Ambil input kelompok jika ada di form, default 'Umum'
    $kelompok = isset($_POST['kelompok']) ? $_POST['kelompok'] : 'Umum';

    // 1. Validasi input
    if (empty($kode_mapel) || empty($nama_mapel)) {
        $_SESSION['pesan_error'] = "Kode mapel dan Nama mapel wajib diisi.";
        header("Location: mapel_tambah.php");
        exit();
    }

    // 2. Cek duplikasi kode mapel
    $cek_query = mysqli_prepare($koneksi, "SELECT id_mapel FROM mata_pelajaran WHERE kode_mapel = ?");
    mysqli_stmt_bind_param($cek_query, "s", $kode_mapel);
    mysqli_stmt_execute($cek_query);
    mysqli_stmt_store_result($cek_query);
    
    if (mysqli_stmt_num_rows($cek_query) > 0) {
        $_SESSION['pesan_error'] = "Kode mapel '$kode_mapel' sudah digunakan. Harap gunakan kode unik.";
        header("Location: mapel_tambah.php");
        exit();
    }
    mysqli_stmt_close($cek_query);

    $parent_mapel_id = isset($_POST['parent_mapel_id']) && $_POST['parent_mapel_id'] !== '' ? (int)$_POST['parent_mapel_id'] : null;
    $is_tambahan = isset($_POST['is_tambahan']) ? 1 : 0;

    // [MODIFIKASI] Cegah Maksimal Kedalaman (Depth = 1)
    if ($parent_mapel_id !== null) {
        // Cek apakah parent yang dipilih juga sudah memiliki parent (Depth > 1)
        $q_cek_parent = mysqli_prepare($koneksi, "SELECT parent_mapel_id FROM mata_pelajaran WHERE id_mapel = ?");
        mysqli_stmt_bind_param($q_cek_parent, "i", $parent_mapel_id);
        mysqli_stmt_execute($q_cek_parent);
        $res_parent = mysqli_stmt_get_result($q_cek_parent);
        $row_parent = mysqli_fetch_assoc($res_parent);

        if ($row_parent && $row_parent['parent_mapel_id'] !== null) {
            $_SESSION['pesan_error'] = "Gagal! Mapel induk yang dipilih sudah tergabung ke mapel lain. Penggabungan bertingkat tidak diizinkan.";
            header("Location: mapel_tambah.php");
            exit();
        }
    }

    // 3. Masukkan data baru (set urutan ke 99 sesuai default di SQL)
    // [MODIFIKASI] Insert juga kolom kelompok, parent_mapel_id, is_tambahan, agama_khusus
    $agama_khusus = isset($_POST['agama_khusus']) && $_POST['agama_khusus'] !== '' ? $_POST['agama_khusus'] : null;
    $query = mysqli_prepare($koneksi, "INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, kelompok, urutan, parent_mapel_id, is_tambahan, agama_khusus) VALUES (?, ?, ?, 99, ?, ?, ?)");
    mysqli_stmt_bind_param($query, "sssiis", $kode_mapel, $nama_mapel, $kelompok, $parent_mapel_id, $is_tambahan, $agama_khusus);
    
    if (mysqli_stmt_execute($query)) {
        $_SESSION['pesan'] = "Mata pelajaran baru berhasil ditambahkan.";
        header("Location: mapel_tampil.php");
    } else {
        $_SESSION['pesan_error'] = "Gagal menambahkan data. Terjadi kesalahan database.";
        header("Location: mapel_tambah.php");
    }
    exit();
}

// --- 3. LOGIKA UNTUK EDIT MAPEL ---
elseif ($aksi == 'update') {
    $id_mapel = (int)$_POST['id_mapel'];
    $kode_mapel = $_POST['kode_mapel'];
    $nama_mapel = $_POST['nama_mapel'];
    // [MODIFIKASI] Ambil input kelompok jika ada, default pertahankan yang lama atau 'Umum'
    $kelompok = isset($_POST['kelompok']) ? $_POST['kelompok'] : 'Umum';

    if ($id_mapel <= 0 || empty($kode_mapel) || empty($nama_mapel)) {
        $_SESSION['pesan_error'] = "Semua kolom harus diisi.";
        header("Location: mapel_edit.php?id=" . $id_mapel);
        exit();
    }

    // Cek duplikasi kode mapel, KECUALI untuk dirinya sendiri
    $cek_query = mysqli_prepare($koneksi, "SELECT id_mapel FROM mata_pelajaran WHERE kode_mapel = ? AND id_mapel != ?");
    mysqli_stmt_bind_param($cek_query, "si", $kode_mapel, $id_mapel);
    mysqli_stmt_execute($cek_query);
    mysqli_stmt_store_result($cek_query);
    
    if (mysqli_stmt_num_rows($cek_query) > 0) {
        $_SESSION['pesan_error'] = "Kode mapel '$kode_mapel' sudah digunakan oleh mapel lain.";
        header("Location: mapel_edit.php?id=" . $id_mapel);
        exit();
    }
    mysqli_stmt_close($cek_query);

    $parent_mapel_id = isset($_POST['parent_mapel_id']) && $_POST['parent_mapel_id'] !== '' ? (int)$_POST['parent_mapel_id'] : null;
    $is_tambahan = isset($_POST['is_tambahan']) ? 1 : 0;

    // [MODIFIKASI] Cegah Loop Tak Terbatas & Maksimal Kedalaman (Depth = 1)
    if ($parent_mapel_id !== null) {
        // Cek agar mapel tidak di set sebagai parent untuk dirinya sendiri
        if ($parent_mapel_id === $id_mapel) {
            $_SESSION['pesan_error'] = "Gagal! Mapel tidak bisa digabung ke dirinya sendiri.";
            header("Location: mapel_edit.php?id=" . $id_mapel);
            exit();
        }

        // Cek apakah parent yang dipilih juga sudah memiliki parent (Depth > 1)
        $q_cek_parent = mysqli_prepare($koneksi, "SELECT parent_mapel_id FROM mata_pelajaran WHERE id_mapel = ?");
        mysqli_stmt_bind_param($q_cek_parent, "i", $parent_mapel_id);
        mysqli_stmt_execute($q_cek_parent);
        $res_parent = mysqli_stmt_get_result($q_cek_parent);
        $row_parent = mysqli_fetch_assoc($res_parent);

        if ($row_parent && $row_parent['parent_mapel_id'] !== null) {
            $_SESSION['pesan_error'] = "Gagal! Mapel induk yang dipilih sudah tergabung ke mapel lain. Penggabungan bertingkat tidak diizinkan.";
            header("Location: mapel_edit.php?id=" . $id_mapel);
            exit();
        }
    }

    // [MODIFIKASI] Update juga kolom kelompok, parent_mapel_id, is_tambahan, agama_khusus
    $agama_khusus = isset($_POST['agama_khusus']) && $_POST['agama_khusus'] !== '' ? $_POST['agama_khusus'] : null;
    $query = mysqli_prepare($koneksi, "UPDATE mata_pelajaran SET kode_mapel = ?, nama_mapel = ?, kelompok = ?, parent_mapel_id = ?, is_tambahan = ?, agama_khusus = ? WHERE id_mapel = ?");
    mysqli_stmt_bind_param($query, "sssiisi", $kode_mapel, $nama_mapel, $kelompok, $parent_mapel_id, $is_tambahan, $agama_khusus, $id_mapel);
    
    if (mysqli_stmt_execute($query)) {
        $_SESSION['pesan'] = "Data mata pelajaran berhasil diperbarui.";
        header("Location: mapel_tampil.php");
    } else {
        $_SESSION['pesan_error'] = "Gagal memperbarui data.";
        header("Location: mapel_edit.php?id=" . $id_mapel);
    }
    exit();
}

// --- 4. [BARU] LOGIKA UBAH KELOMPOK (TOGGLE AGAMA) ---
elseif ($aksi == 'set_kelompok') {
    $id_mapel = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $kelompok = isset($_GET['kelompok']) ? $_GET['kelompok'] : '';

    if ($id_mapel > 0 && !empty($kelompok)) {
        // Gunakan Prepared Statement untuk keamanan
        $query = mysqli_prepare($koneksi, "UPDATE mata_pelajaran SET kelompok = ? WHERE id_mapel = ?");
        mysqli_stmt_bind_param($query, "si", $kelompok, $id_mapel);
        
        if (mysqli_stmt_execute($query)) {
            $_SESSION['pesan'] = "Kelompok mata pelajaran berhasil diubah menjadi " . htmlspecialchars($kelompok) . ".";
        } else {
            $_SESSION['pesan_error'] = "Gagal mengubah kelompok mapel.";
        }
        mysqli_stmt_close($query);
    }
    header("Location: mapel_tampil.php");
    exit();
}

// --- 4.1 LOGIKA SET LAMPIRAN ---
elseif ($aksi == 'set_lampiran') {
    $id_mapel = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = isset($_GET['status']) ? (int)$_GET['status'] : 0;

    if ($id_mapel > 0) {
        $query = mysqli_prepare($koneksi, "UPDATE mata_pelajaran SET is_tambahan = ? WHERE id_mapel = ?");
        mysqli_stmt_bind_param($query, "ii", $status, $id_mapel);
        if (mysqli_stmt_execute($query)) {
            $_SESSION['pesan'] = $status ? "Mata pelajaran diatur sebagai Lampiran." : "Mata pelajaran dikembalikan menjadi Mapel Utama.";
        } else {
            $_SESSION['pesan_error'] = "Gagal mengubah status lampiran.";
        }
        mysqli_stmt_close($query);
    }
    header("Location: mapel_tampil.php");
    exit();
}

// --- 4.2 LOGIKA SET PARENT (GABUNG INDUK) ---
elseif ($aksi == 'set_parent') {
    $id_mapel = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $parent = (isset($_GET['parent']) && $_GET['parent'] !== '') ? (int)$_GET['parent'] : null;

    if ($id_mapel > 0) {
        if ($parent !== null && $parent == $id_mapel) {
            $_SESSION['pesan_error'] = "Gagal! Tidak bisa digabung ke dirinya sendiri.";
        } else {
            // Cek kedalaman (mencegah nested parent)
            $boleh = true;
            if ($parent !== null) {
                $q = mysqli_prepare($koneksi, "SELECT parent_mapel_id FROM mata_pelajaran WHERE id_mapel = ?");
                mysqli_stmt_bind_param($q, "i", $parent);
                mysqli_stmt_execute($q);
                $res = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
                if ($res && $res['parent_mapel_id'] !== null) {
                    $boleh = false;
                    $_SESSION['pesan_error'] = "Gagal! Penggabungan bertingkat tidak diizinkan.";
                }
                mysqli_stmt_close($q);
            }

            if ($boleh) {
                if ($parent === null || $parent === 0) {
                    $query = mysqli_prepare($koneksi, "UPDATE mata_pelajaran SET parent_mapel_id = NULL WHERE id_mapel = ?");
                    mysqli_stmt_bind_param($query, "i", $id_mapel);
                } else {
                    $query = mysqli_prepare($koneksi, "UPDATE mata_pelajaran SET parent_mapel_id = ? WHERE id_mapel = ?");
                    mysqli_stmt_bind_param($query, "ii", $parent, $id_mapel);
                }

                if (mysqli_stmt_execute($query)) {
                    $_SESSION['pesan'] = "Status gabung mapel induk berhasil diperbarui.";
                } else {
                    $_SESSION['pesan_error'] = "Gagal memperbarui mapel induk.";
                }
                mysqli_stmt_close($query);
            }
        }
    }
    header("Location: mapel_tampil.php");
    exit();
}

// --- 5. LOGIKA UNTUK HAPUS MAPEL ---
elseif ($aksi == 'hapus') {
    $id_mapel = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id_mapel <= 0) {
        $_SESSION['pesan_error'] = "ID Mata Pelajaran tidak valid.";
        header("Location: mapel_tampil.php");
        exit();
    }

    // PENTING: Cek dependensi data agar tidak error
    // 1. Cek di tabel tujuan_pembelajaran
    $cek_tp_query = mysqli_prepare($koneksi, "SELECT id_tp FROM tujuan_pembelajaran WHERE id_mapel = ? LIMIT 1");
    mysqli_stmt_bind_param($cek_tp_query, "i", $id_mapel);
    mysqli_stmt_execute($cek_tp_query);
    mysqli_stmt_store_result($cek_tp_query);

    if (mysqli_stmt_num_rows($cek_tp_query) > 0) {
        $_SESSION['pesan_error'] = "Gagal! Mata pelajaran tidak bisa dihapus karena sudah memiliki data Tujuan Pembelajaran.";
        header("Location: mapel_tampil.php");
        exit();
    }
    mysqli_stmt_close($cek_tp_query);

    // 2. Cek di tabel penilaian
    $cek_p_query = mysqli_prepare($koneksi, "SELECT id_penilaian FROM penilaian WHERE id_mapel = ? LIMIT 1");
    mysqli_stmt_bind_param($cek_p_query, "i", $id_mapel);
    mysqli_stmt_execute($cek_p_query);
    mysqli_stmt_store_result($cek_p_query);

    if (mysqli_stmt_num_rows($cek_p_query) > 0) {
        $_SESSION['pesan_error'] = "Gagal! Mata pelajaran tidak bisa dihapus karena sudah memiliki data Penilaian.";
        header("Location: mapel_tampil.php");
        exit();
    }
    mysqli_stmt_close($cek_p_query);
    
    // 3. Cek di tabel guru_mengajar
    $cek_gm_query = mysqli_prepare($koneksi, "SELECT id_guru_mengajar FROM guru_mengajar WHERE id_mapel = ? LIMIT 1");
    mysqli_stmt_bind_param($cek_gm_query, "i", $id_mapel);
    mysqli_stmt_execute($cek_gm_query);
    mysqli_stmt_store_result($cek_gm_query);

    if (mysqli_stmt_num_rows($cek_gm_query) > 0) {
        $_SESSION['pesan_error'] = "Gagal! Mata pelajaran tidak bisa dihapus karena masih ada guru yang mengajar mapel ini.";
        header("Location: mapel_tampil.php");
        exit();
    }
    mysqli_stmt_close($cek_gm_query);


    // 4. Jika aman, lakukan penghapusan
    $query = mysqli_prepare($koneksi, "DELETE FROM mata_pelajaran WHERE id_mapel = ?");
    mysqli_stmt_bind_param($query, "i", $id_mapel);

    if (mysqli_stmt_execute($query)) {
        $_SESSION['pesan'] = "Mata pelajaran berhasil dihapus.";
    } else {
        // Menangkap error jika masih ada constraint lain yang terlewat
        $_SESSION['pesan_error'] = "Gagal menghapus data. Kemungkinan data ini masih terhubung dengan data rapor atau data lainnya.";
    }
    header("Location: mapel_tampil.php");
    exit();
}


// Jika aksi tidak cocok, kembalikan ke halaman utama
else {
    header("Location: mapel_tampil.php");
    exit();
}
?>