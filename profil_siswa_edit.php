<?php
// FILE INI KHUSUS UNTUK SISWA
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'koneksi.php';

// Cek sesi dulu - KHUSUS SISWA
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'siswa') {
    echo "<div class='alert alert-danger'>Hanya siswa yang bisa mengakses halaman ini. (Sesi role tidak terdeteksi)</div>";
    exit();
}

if (!isset($_SESSION['id_siswa']) || empty($_SESSION['id_siswa'])) {
    echo "<div class='alert alert-danger'>Sesi Anda tidak valid atau telah berakhir. Silakan login kembali. (Error: ID SISWA HILANG DARI SESI)</div>";
    exit();
}

$id_siswa_login = (int) $_SESSION['id_siswa'];

// --- LOGIKA UPDATE DATA SISWA (Saat form disubmit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form, sesuaikan dengan field di tabel siswa
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $nis = mysqli_real_escape_string($koneksi, $_POST['nis']);
    // $nisn = mysqli_real_escape_string($koneksi, $_POST['nisn']); // Asumsi NISN readonly
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    $update_fields = [];
    $update_fields[] = "nama_lengkap = '$nama_lengkap'";
    $update_fields[] = "nis = " . (!empty($nis) ? "'$nis'" : "NULL");
    // $update_fields[] = "nisn = " . (!empty($nisn) ? "'$nisn'" : "NULL");

    // Handle Perubahan Password
    if (!empty($password_baru)) {
        if ($password_baru === $konfirmasi_password) {
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            $update_fields[] = "password = '$hashed_password'";
        } else {
            $_SESSION['update_status'] = 'error_password';
            header("Location: profil_siswa_edit.php");
            exit();
        }
    }

    // --- Handle Upload Foto Siswa ---
    if (isset($_FILES['foto_siswa']) && $_FILES['foto_siswa']['error'] == 0) {
        $q_old_photo = mysqli_query($koneksi, "SELECT foto_siswa FROM siswa WHERE id_siswa = $id_siswa_login");
        $d_old_photo = mysqli_fetch_assoc($q_old_photo);
        $old_photo_path = 'uploads/foto_siswa/' . $d_old_photo['foto_siswa'];

        $target_dir = "uploads/foto_siswa/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $imageFileType = strtolower(pathinfo($_FILES["foto_siswa"]["name"], PATHINFO_EXTENSION));
        $new_filename = "siswa_" . $id_siswa_login . "_" . time() . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;

        $check = getimagesize($_FILES["foto_siswa"]["tmp_name"]);
        if ($check !== false && in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            if (move_uploaded_file($_FILES["foto_siswa"]["tmp_name"], $target_file)) {
                $update_fields[] = "foto_siswa = '$new_filename'";
                if (!empty($d_old_photo['foto_siswa']) && file_exists($old_photo_path)) {
                    @unlink($old_photo_path);
                }
            }
        }
    }

    $query_update = "UPDATE siswa SET " . implode(", ", $update_fields) . " WHERE id_siswa = $id_siswa_login";
    
    if (mysqli_query($koneksi, $query_update)) {
        $_SESSION['update_status'] = 'success';
        $_SESSION['nama_siswa'] = $nama_lengkap; // Update session nama siswa
    } else {
        $_SESSION['update_status'] = 'error_db';
    }

    header("Location: profil_siswa_edit.php");
    exit();
}


// --- MULAI OUTPUT HTML ---
include 'header.php'; 

// --- PENGAMBILAN DATA SISWA UNTUK DITAMPILKAN ---
$siswa_result = mysqli_query($koneksi, "SELECT * FROM siswa WHERE id_siswa = $id_siswa_login");

if (!$siswa_result) {
     echo "<div class='alert alert-danger'>Terjadi error SQL saat mengambil data siswa: " . mysqli_error($koneksi) . "</div>";
     include 'footer.php';
     exit();
}
$siswa = mysqli_fetch_assoc($siswa_result);
if ($siswa === null) {
    echo "<div class='alert alert-danger'>Data siswa dengan ID $id_siswa_login tidak ditemukan.</div>";
    include 'footer.php';
    exit();
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Profil Saya</h1>

    <div class="row">
        <!-- Buat form edit di tengah -->
        <div class="col-xl-8 col-lg-10 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold" style="color: var(--primary-color);"><i class="bi bi-pencil-square me-2"></i>Edit Informasi Profil & Akun</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img id="profile-preview" class="rounded-circle mb-3" 
                             src="<?php echo (!empty($siswa['foto_siswa']) && file_exists('uploads/foto_siswa/'.$siswa['foto_siswa'])) ? 'uploads/foto_siswa/'.$siswa['foto_siswa'] : 'uploads/default-avatar.png'; ?>" 
                             alt="Foto Profil" style="width: 150px; height: 150px; object-fit: cover;">
                        <br>
                        <button id="btn-ganti-foto" class="btn btn-sm btn-outline-primary"><i class="bi bi-camera-fill me-1"></i> Ganti Foto</button>
                    </div>

                    <form id="profilForm" method="POST" action="profil_siswa_edit.php" enctype="multipart/form-data">
                        <input type="file" name="foto_siswa" id="foto_upload" class="d-none" accept="image/*">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($siswa['username'] ?? ''); ?>" readonly>
                                <small class="form-text text-muted">Username tidak dapat diubah.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($siswa['nama_lengkap'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <label for="nisn" class="form-label">NISN</label>
                                <input type="text" class="form-control" id="nisn" name="nisn" value="<?php echo htmlspecialchars($siswa['nisn'] ?? ''); ?>" readonly>
                                <small class="form-text text-muted">NISN tidak dapat diubah.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nis" class="form-label">NIS</label>
                                <input type="text" class="form-control" id="nis" name="nis" value="<?php echo htmlspecialchars($siswa['nis'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="mb-3 text-secondary">Ubah Password (Opsional)</h6>
                        <p class="text-muted small">Kosongkan jika tidak ingin mengubah password.</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password_baru" class="form-label">Password Baru</label>
                                <input type="password" class="form-control" id="password_baru" name="password_baru">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="konfirmasi_password" class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password">
                                <div id="passwordHelp" class="form-text text-danger d-none">Password tidak cocok!</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="background-color: var(--primary-color); border-color: var(--primary-color);">
                            <i class="bi bi-save-fill me-2"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // --- Notifikasi SweetAlert2 ---
    <?php
    if (isset($_SESSION['update_status'])) {
        $status = $_SESSION['update_status'];
        if ($status == 'success') {
            echo "Swal.fire({ title: 'Berhasil!', text: 'Profil Anda berhasil diperbarui.', icon: 'success', timer: 2000, showConfirmButton: false });";
        } elseif ($status == 'error_password') {
            echo "Swal.fire({ title: 'Gagal!', text: 'Konfirmasi password tidak cocok. Silakan coba lagi.', icon: 'error' });";
        } elseif ($status == 'error_db') {
             echo "Swal.fire({ title: 'Gagal!', text: 'Terjadi kesalahan saat menyimpan data.', icon: 'error' });";
        }
        unset($_SESSION['update_status']);
    }
    ?>

    // --- Logika Ganti Foto ---
    $('#btn-ganti-foto').on('click', function() {
        $('#foto_upload').click();
    });

    $('#foto_upload').on('change', function(event) {
        if (event.target.files && event.target.files[0]) {
            var reader = new FileReader();
            reader.onload = function(){
                $('#profile-preview').attr('src', reader.result);
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    });

    // --- Validasi Password ---
    $('#konfirmasi_password').on('keyup', function() {
        if ($('#password_baru').val() != $(this).val()) {
            $('#passwordHelp').removeClass('d-none');
        } else {
            $('#passwordHelp').addClass('d-none');
        }
    });

    $('#profilForm').on('submit', function(e) {
        var pwd = $('#password_baru').val();
        if (pwd && pwd !== $('#konfirmasi_password').val()) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Oops...', text: 'Password baru dan konfirmasi tidak cocok!' });
        }
    });
});
</script>

<?php
include 'footer.php';
?>