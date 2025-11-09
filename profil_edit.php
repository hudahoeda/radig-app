<?php
// --- [PERBAIKAN 6] ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- [AKHIR PERBAIKAN 6] ---

// --- [PERBAIKAN 2] ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- [AKHIR PERBAIKAN 2] ---

include 'koneksi.php';

// --- [PERBAIKAN 5 - STRUKTUR] ---

// Cek sesi dulu
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    echo "<div class='alert alert-danger'>Hanya admin dan guru yang bisa mengakses halaman ini. (Sesi role tidak terdeteksi)</div>";
    exit();
}

if (!isset($_SESSION['id_guru']) || empty($_SESSION['id_guru'])) {
    echo "<div class='alert alert-danger'>Sesi Anda tidak valid atau telah berakhir. Silakan login kembali. (Error: ID GURU HILANG DARI SESI)</div>";
    exit();
}

$id_guru_login = (int) $_SESSION['id_guru'];

// --- LOGIKA UPDATE DATA (Saat form disubmit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_guru = mysqli_real_escape_string($koneksi, $_POST['nama_guru']);
    $nip = mysqli_real_escape_string($koneksi, $_POST['nip']);
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    $update_fields = [];
    $update_fields[] = "nama_guru = '$nama_guru'";
    // --- [PERBAIKAN 7] ---
    // Handle NIP kosong agar disimpan sebagai NULL, bukan string kosong
    $update_fields[] = "nip = " . (!empty($nip) ? "'$nip'" : "NULL");
    // --- [AKHIR PERBAIKAN 7] ---

    // Handle Perubahan Password
    if (!empty($password_baru)) {
        if ($password_baru === $konfirmasi_password) {
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            $update_fields[] = "password = '$hashed_password'";
        } else {
            $_SESSION['update_status'] = 'error_password';
            header("Location: profil_edit.php");
            exit();
        }
    }

    // --- Handle Upload Foto ---
    if (isset($_FILES['foto_guru']) && $_FILES['foto_guru']['error'] == 0) {
        $q_old_photo = mysqli_query($koneksi, "SELECT foto_guru FROM guru WHERE id_guru = $id_guru_login");
        $d_old_photo = mysqli_fetch_assoc($q_old_photo);
        $old_photo_path = 'uploads/guru_photos/' . $d_old_photo['foto_guru'];

        $target_dir = "uploads/guru_photos/";
        $imageFileType = strtolower(pathinfo($_FILES["foto_guru"]["name"], PATHINFO_EXTENSION));
        $new_filename = "guru_" . $id_guru_login . "_" . time() . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;

        $check = getimagesize($_FILES["foto_guru"]["tmp_name"]);
        if ($check !== false && in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            if (move_uploaded_file($_FILES["foto_guru"]["tmp_name"], $target_file)) {
                $update_fields[] = "foto_guru = '$new_filename'";
                if (!empty($d_old_photo['foto_guru']) && file_exists($old_photo_path)) {
                    @unlink($old_photo_path);
                }
            }
        }
    }

    $query_update = "UPDATE guru SET " . implode(", ", $update_fields) . " WHERE id_guru = $id_guru_login";
    
    if (mysqli_query($koneksi, $query_update)) {
        $_SESSION['update_status'] = 'success';
        $_SESSION['nama_guru'] = $nama_guru;
    } else {
        $_SESSION['update_status'] = 'error_db';
    }

    header("Location: profil_edit.php");
    exit();
}
// --- [AKHIR PERBAIKAN 5] ---


// --- MULAI OUTPUT HTML ---
include 'header.php'; 

// --- Ambil ID Tahun Ajaran Aktif ---
$id_ta_aktif = 0;
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
if ($q_ta_aktif && $d_ta_aktif = mysqli_fetch_assoc($q_ta_aktif)) {
    $id_ta_aktif = (int) $d_ta_aktif['id_tahun_ajaran'];
}

// --- PENGAMBILAN SEMUA DATA UNTUK DITAMPILKAN ---

// --- [PERBAIKAN 4] ---
$guru_result = mysqli_query($koneksi, "SELECT * FROM guru WHERE id_guru = $id_guru_login");

if (!$guru_result) {
     echo "<div class='alert alert-danger'>Terjadi error SQL saat mengambil data guru: " . mysqli_error($koneksi) . "</div>";
     include 'footer.php';
     exit();
}
$guru = mysqli_fetch_assoc($guru_result);
if ($guru === null) {
    echo "<div class='alert alert-danger'>Data guru dengan ID $id_guru_login tidak ditemukan di database. Sesi mungkin tidak sinkron.</div>";
    include 'footer.php';
    exit();
}
// --- [AKHIR PERBAIKAN 4] ---

$walas_result = mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_wali_kelas = $id_guru_login AND id_tahun_ajaran = $id_ta_aktif");
$walas = mysqli_fetch_assoc($walas_result);

$pembina_result = mysqli_query($koneksi, "SELECT nama_ekskul FROM ekstrakurikuler WHERE id_pembina = $id_guru_login AND id_tahun_ajaran = $id_ta_aktif");

$mengajar_query = "
    SELECT
        mp.nama_mapel,
        GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') AS kelas_diajar
    FROM
        guru_mengajar gm
    JOIN
        mata_pelajaran mp ON gm.id_mapel = mp.id_mapel
    JOIN
        kelas k ON gm.id_kelas = k.id_kelas
    WHERE
        gm.id_guru = $id_guru_login 
        AND gm.id_tahun_ajaran = $id_ta_aktif
        AND k.id_tahun_ajaran = $id_ta_aktif
    GROUP BY
        mp.nama_mapel
    ORDER BY
        mp.nama_mapel
";
$mengajar_result = mysqli_query($koneksi, $mengajar_query);

?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Profil Saya</h1>

    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                    <img id="profile-preview" class="rounded-circle mb-3" 
                         src="<?php echo (!empty($guru['foto_guru']) && file_exists('uploads/guru_photos/'.$guru['foto_guru'])) ? 'uploads/guru_photos/'.$guru['foto_guru'] : 'uploads/default-avatar.png'; ?>" 
                         alt="Foto Profil" style="width: 150px; height: 150px; object-fit: cover;">
                    
                    <!-- [PERBAIKAN 7] Terapkan ?? di sini -->
                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($guru['nama_guru'] ?? 'Nama Guru'); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($guru['nip'] ?? '-'); ?></p>
                    <!-- [AKHIR PERBAIKAN 7] -->

                    <button id="btn-ganti-foto" class="btn btn-sm btn-outline-primary"><i class="bi bi-camera-fill me-1"></i> Ganti Foto</button>
                </div>
                <hr class="my-0">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted"><strong><i class="bi bi-briefcase-fill me-2"></i>Peran Anda</strong> (TA Aktif)</h6>
                    <ul class="list-group list-group-flush">
                        <?php if ($walas): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">Wali Kelas</div>
                                    <!-- [PERBAIKAN 7] Terapkan ?? di sini -->
                                    <?php echo htmlspecialchars($walas['nama_kelas'] ?? ''); ?>
                                </div>
                            </li>
                        <?php endif; ?>

                        <?php if ($pembina_result && mysqli_num_rows($pembina_result) > 0): ?>
                             <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">Pembina Ekstrakurikuler</div>
                                    <?php while($pembina = mysqli_fetch_assoc($pembina_result)) {
                                        // [PERBAIKAN 7] Terapkan ?? di sini
                                        echo htmlspecialchars($pembina['nama_ekskul'] ?? '') . "<br>";
                                    } ?>
                                </div>
                            </li>
                        <?php endif; ?>

                         <?php if ($mengajar_result && mysqli_num_rows($mengajar_result) > 0): ?>
                             <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">Guru Mata Pelajaran</div>
                                    <?php while($mengajar = mysqli_fetch_assoc($mengajar_result)) {
                                        // [PERBAIKAN 7] Terapkan ?? di sini
                                        echo "<strong>" . htmlspecialchars($mengajar['nama_mapel'] ?? '') . "</strong>";
                                        if(!empty($mengajar['kelas_diajar'])){
                                            echo "<br><small class='text-muted'> di " . htmlspecialchars($mengajar['kelas_diajar'] ?? '') . "</small><br>";
                                        } else {
                                            echo "<br><small class='text-muted'> (Belum ada data kelas ajar)</small><br>";
                                        }
                                    } ?>
                                </div>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!$walas && (!$pembina_result || mysqli_num_rows($pembina_result) == 0) && (!$mengajar_result || mysqli_num_rows($mengajar_result) == 0)): ?>
                             <li class="list-group-item">
                                <small class="text-muted">Tidak ada peran khusus yang terdaftar untuk tahun ajaran ini.</small>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold" style="color: var(--primary-color);"><i class="bi bi-pencil-square me-2"></i>Edit Informasi Profil & Akun</h6>
                </div>
                <div class="card-body">
                    <form id="profilForm" method="POST" action="profil_edit.php" enctype="multipart/form-data">
                        <input type="file" name="foto_guru" id="foto_upload" class="d-none" accept="image/*">
                        
                        <!-- [PERBAIKAN 7] Terapkan ?? di sini -->
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($guru['username'] ?? ''); ?>" readonly>
                            <small class="form-text text-muted">Username tidak dapat diubah.</small>
                        </div>
                        <div class="mb-3">
                            <label for="nama_guru" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_guru" name="nama_guru" value="<?php echo htmlspecialchars($guru['nama_guru'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="nip" class="form-label">NIP</label>
                            <input type="text" class="form-control" id="nip" name="nip" value="<?php echo htmlspecialchars($guru['nip'] ?? ''); ?>">
                        </div>
                        <!-- [AKHIR PERBAIKAN 7] -->
                        
                        <hr>
                        <h6 class="mb-3 text-secondary">Ubah Password (Opsional)</h6>
                        <p class="text-muted small">Kosongkan jika tidak ingin mengubah password.</p>
                        
                        <div class="mb-3">
                            <label for="password_baru" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="password_baru" name="password_baru">
                        </div>
                        <div class="mb-3">
                            <label for="konfirmasi_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password">
                            <div id="passwordHelp" class="form-text text-danger d-none">Password tidak cocok!</div>
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