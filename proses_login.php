<?php
// proses_login.php
session_start();
include 'koneksi.php';

// Menangkap data yang dikirim dari form login
$username = $_POST['username'];
$password = $_POST['password'];

// --- Langkah 1: Coba login sebagai Guru/Admin ---
$stmt_guru = mysqli_prepare($koneksi, "SELECT id_guru, nama_guru, password, role FROM guru WHERE username = ?");
mysqli_stmt_bind_param($stmt_guru, "s", $username);
mysqli_stmt_execute($stmt_guru);
$result_guru = mysqli_stmt_get_result($stmt_guru);
$cek_guru = mysqli_num_rows($result_guru);

if ($cek_guru > 0) {
    // Username ditemukan di tabel guru
    $data_guru = mysqli_fetch_assoc($result_guru);
    // Verifikasi password guru
    if (password_verify($password, $data_guru['password'])) {
        // Password guru cocok
        // Update waktu login terakhir guru
        $update_stmt = mysqli_prepare($koneksi, "UPDATE guru SET terakhir_login = NOW() WHERE id_guru = ?");
        mysqli_stmt_bind_param($update_stmt, "i", $data_guru['id_guru']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        // Buat session untuk guru/admin
        $_SESSION['id_guru'] = $data_guru['id_guru'];
        $_SESSION['nama_guru'] = $data_guru['nama_guru']; // Tetap gunakan nama_guru untuk display name
        $_SESSION['role'] = $data_guru['role'];

        // Alihkan ke dashboard
        header("location:dashboard.php");
        exit(); // Hentikan script setelah redirect

    } else {
        // Password guru tidak cocok
        header("location:index.php?pesan=gagal");
        exit();
    }
} else {
    // --- Langkah 2: Jika tidak ditemukan di guru, coba login sebagai Siswa ---
    $stmt_siswa = mysqli_prepare($koneksi, "SELECT id_siswa, nama_lengkap, password FROM siswa WHERE username = ? AND status_siswa = 'Aktif'"); // Hanya siswa aktif yang bisa login
    mysqli_stmt_bind_param($stmt_siswa, "s", $username);
    mysqli_stmt_execute($stmt_siswa);
    $result_siswa = mysqli_stmt_get_result($stmt_siswa);
    $cek_siswa = mysqli_num_rows($result_siswa);

    if ($cek_siswa > 0) {
        // Username ditemukan di tabel siswa
        $data_siswa = mysqli_fetch_assoc($result_siswa);
        // Verifikasi password siswa
        // ASUMSI: Password siswa juga di-hash dengan cara yang sama (password_hash)
        if (password_verify($password, $data_siswa['password'])) {
            // Password siswa cocok

            // Buat session untuk siswa
            $_SESSION['id_siswa'] = $data_siswa['id_siswa'];
            $_SESSION['nama_siswa'] = $data_siswa['nama_lengkap']; // Gunakan nama_siswa
            $_SESSION['role'] = 'siswa'; // Set role secara eksplisit

            // Alihkan ke dashboard
            header("location:dashboard.php");
            exit(); // Hentikan script setelah redirect

        } else {
            // Password siswa tidak cocok
            header("location:index.php?pesan=gagal");
            exit();
        }
        mysqli_stmt_close($stmt_siswa);

    } else {
        // Username tidak ditemukan di guru maupun siswa
        header("location:index.php?pesan=gagal");
        exit();
    }
}

// Tutup statement guru jika belum ditutup
if (isset($stmt_guru)) {
    mysqli_stmt_close($stmt_guru);
}
mysqli_close($koneksi);
?>
