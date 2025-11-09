<?php
session_start();
include 'koneksi.php';

// Ambil jenis aksi dari URL
$aksi = $_GET['aksi'] ?? '';

// --- PERUBAHAN KEAMANAN ---
// Izinkan aksi 'kembali' jika sedang impersonate,
// selain itu, WAJIB role admin.
if ($aksi == 'kembali' && isset($_SESSION['admin_asal_id'])) {
    // Izinkan aksi 'kembali' untuk dieksekusi
} elseif (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    // Jika bukan admin DAN bukan aksi 'kembali', tolak
    $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Akses Ditolak', 'text' => 'Anda harus login sebagai Admin.']);
    header("Location: dashboard.php");
    exit();
}
// --- AKHIR PERUBAHAN KEAMANAN ---


switch ($aksi) {

    // Aksi untuk memproses kenaikan kelas dan kelulusan
    case 'proses_kenaikan_siswa':
        // Ganti nama aksi ini agar tidak bentrok dengan yang lama jika ada
        if (!isset($_POST['id_siswa']) || !isset($_POST['tindakan'])) {
            $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Silakan pilih minimal satu siswa dan satu tindakan.']);
            header('Location: ' . $_SERVER['HTTP_REFERER']); // Kembali ke halaman sebelumnya
            exit;
        }

        $daftar_id_siswa = $_POST['id_siswa']; // Ini adalah array
        $tindakan = $_POST['tindakan'];
        $id_kelas_baru = isset($_POST['id_kelas_baru']) ? (int)$_POST['id_kelas_baru'] : 0;
        $id_kelas_lama = (int)$_POST['id_kelas_lama'];

        if ($tindakan == 'naik' && $id_kelas_baru == 0) {
            $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Untuk tindakan "Naik Kelas", Anda wajib memilih kelas tujuan.']);
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

        mysqli_begin_transaction($koneksi);
        try {
            $stmt_lulus = mysqli_prepare($koneksi, "UPDATE siswa SET status_siswa = 'Lulus', id_kelas = NULL WHERE id_siswa = ?");
            $stmt_naik = mysqli_prepare($koneksi, "UPDATE siswa SET id_kelas = ? WHERE id_siswa = ?");
            
            foreach ($daftar_id_siswa as $id_siswa) {
                if ($tindakan == 'luluskan') {
                    mysqli_stmt_bind_param($stmt_lulus, "i", $id_siswa);
                    mysqli_stmt_execute($stmt_lulus);
                } elseif ($tindakan == 'naik') {
                    mysqli_stmt_bind_param($stmt_naik, "ii", $id_kelas_baru, $id_siswa);
                    mysqli_stmt_execute($stmt_naik);
                }
            }

            mysqli_commit($koneksi);
            $jumlah_siswa = count($daftar_id_siswa);
            $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Proses Selesai', 'text' => "$jumlah_siswa siswa telah berhasil diproses."]);
        
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Proses Gagal', 'text' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }

        header('Location: admin_kenaikan_kelas.php?id_kelas=' . $id_kelas_lama); // Kembali ke halaman kelas yang sama
        exit;
        break;

    // --- FITUR IMPERSONATE ---
    
    case 'login_sebagai_guru':
        // Keamanan ganda, pastikan admin asli
        if (!isset($_SESSION['admin_asal_id']) && $_SESSION['role'] != 'admin') {
            die('Akses ditolak.');
        }
        $id_target = (int)$_GET['id_target'];
        
        // Simpan ID admin asli
        $_SESSION['admin_asal_id'] = $_SESSION['id_guru'];
        
        // Ambil data guru target
        $q_guru = mysqli_prepare($koneksi, "SELECT * FROM guru WHERE id_guru = ?");
        mysqli_stmt_bind_param($q_guru, "i", $id_target);
        mysqli_stmt_execute($q_guru);
        $guru_target = mysqli_fetch_assoc(mysqli_stmt_get_result($q_guru));
        
        if ($guru_target) {
            // Hapus session siswa jika ada (jika sebelumnya menyamar sbg siswa)
            unset($_SESSION['id_siswa']);
            unset($_SESSION['nama_siswa']); // [FIX] Hapus nama_siswa
            unset($_SESSION['id_kelas']);   // [FIX] Hapus id_kelas
            
            // Timpa session dengan data guru target
            $_SESSION['id_guru'] = $guru_target['id_guru'];
            $_SESSION['username'] = $guru_target['username'];
            $_SESSION['nama_guru'] = $guru_target['nama_guru']; // [FIX] Sesuai header.php
            $_SESSION['role'] = $guru_target['role'];
            
            header('Location: dashboard.php');
            exit;
        } else {
            // Gagal menemukan guru, kembali ke halaman pengguna
            $_SESSION['error'] = "Gagal login sebagai pengguna: Target tidak ditemukan.";
            header('Location: pengguna_tampil.php?tab=guru');
            exit;
        }
        break;

    // --- [BARU] LOGIKA UNTUK LOGIN SEBAGAI SISWA ---
    case 'login_sebagai_siswa':
        // Keamanan ganda, pastikan admin asli
        if (!isset($_SESSION['admin_asal_id']) && $_SESSION['role'] != 'admin') {
            die('Akses ditolak.');
        }
        $id_target = (int)$_GET['id_target'];

        // Simpan ID admin asli
        $_SESSION['admin_asal_id'] = $_SESSION['id_guru'];

        // Ambil data siswa target
        $q_siswa = mysqli_prepare($koneksi, "SELECT * FROM siswa WHERE id_siswa = ?");
        mysqli_stmt_bind_param($q_siswa, "i", $id_target);
        mysqli_stmt_execute($q_siswa);
        $siswa_target = mysqli_fetch_assoc(mysqli_stmt_get_result($q_siswa));

        if ($siswa_target) {
            // Hapus session guru/admin
            unset($_SESSION['id_guru']);
            unset($_SESSION['nama_guru']); // [FIX] Hapus nama_guru
            
            // Atur session baru sebagai siswa
            $_SESSION['id_siswa'] = $siswa_target['id_siswa'];
            $_SESSION['username'] = $siswa_target['username'];
            $_SESSION['nama_siswa'] = $siswa_target['nama_lengkap']; // [FIX] Sesuai header.php
            $_SESSION['role'] = 'siswa';
            $_SESSION['id_kelas'] = $siswa_target['id_kelas'];

            header('Location: dashboard.php');
            exit;
        } else {
            // Gagal menemukan siswa, kembali ke halaman pengguna
            $_SESSION['error'] = "Gagal login sebagai siswa: Target tidak ditemukan.";
            header('Location: pengguna_tampil.php?tab=siswa');
            exit;
        }
        break;
    // --- [AKHIR BARU] ---

    case 'kembali':
        // Hanya bisa kembali jika ada session admin_asal_id
        if (isset($_SESSION['admin_asal_id'])) {
            $id_admin_asli = $_SESSION['admin_asal_id'];
            
            // Ambil data admin asli
            $q_admin = mysqli_prepare($koneksi, "SELECT * FROM guru WHERE id_guru = ?");
            mysqli_stmt_bind_param($q_admin, "i", $id_admin_asli);
            mysqli_stmt_execute($q_admin);
            $admin_asli = mysqli_fetch_assoc(mysqli_stmt_get_result($q_admin));
            
            if ($admin_asli) {
                // Hapus session impersonate
                unset($_SESSION['id_siswa']);
                unset($_SESSION['nama_siswa']); // [FIX] Hapus session siswa
                unset($_SESSION['id_kelas']);   // [FIX] Hapus session siswa
                unset($_SESSION['admin_asal_id']);
                
                // Kembalikan session admin
                $_SESSION['id_guru'] = $admin_asli['id_guru'];
                $_SESSION['username'] = $admin_asli['username'];
                $_SESSION['nama_guru'] = $admin_asli['nama_guru']; // [FIX] Sesuai header.php
                $_SESSION['role'] = $admin_asli['role'];
                
                header('Location: dashboard.php'); // Kembali ke dashboard admin
                exit;
            }
        }
        // Jika tidak ada session admin_asal_id, kembalikan ke dashboard normal
        header('Location: dashboard.php');
        exit;
        break;
    
    // --- AKHIR FITUR IMPERSONATE ---

    default:
        // Jika aksi tidak dikenali, kembalikan ke dashboard
        $_SESSION['pesan'] = json_encode(['icon' => 'warning', 'title' => 'Aksi Tidak Dikenali']);
        header('Location: dashboard.php');
        exit;
        break;
}

?>