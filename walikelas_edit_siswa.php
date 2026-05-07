<?php
// ==============================================================================
// FILE: walikelas_edit_siswa.php
// DESAIN: Layout Klasik + Notifikasi SweetAlert Modern
// ==============================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'header.php';
include 'koneksi.php';

// LOAD LIBRARY: SweetAlert2 (Wajib ditaruh di atas)
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// Validasi Login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Akses Ditolak',
                text: 'Anda harus login sebagai Guru.',
                confirmButtonColor: '#3085d6'
            }).then(() => window.location = 'dashboard.php');
        });
    </script>";
    exit;
}
if (!isset($_GET['id_siswa']) || empty($_GET['id_siswa'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'ID Siswa tidak valid.',
                confirmButtonColor: '#3085d6'
            }).then(() => window.location = 'walikelas_identitas_siswa.php');
        });
    </script>";
    exit;
}

$id_siswa_edit = (int)$_GET['id_siswa'];
$id_wali_kelas = $_SESSION['id_guru'];

// Ambil Data Siswa
$stmt_check = mysqli_prepare($koneksi, "
    SELECT s.*, k.nama_kelas 
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.id_siswa = ? 
    AND k.id_wali_kelas = ?
");

mysqli_stmt_bind_param($stmt_check, "ii", $id_siswa_edit, $id_wali_kelas);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$siswa = mysqli_fetch_assoc($result_check);

if (!$siswa) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Data Tidak Ditemukan',
                text: 'Data siswa tidak ditemukan atau bukan bagian dari kelas Anda.',
                confirmButtonColor: '#3085d6'
            }).then(() => window.location = 'walikelas_identitas_siswa.php');
        });
    </script>";
    exit;
}
?>

<style>
    /* Styling Klasik yang Anda sukai */
    .page-header {
        background: linear-gradient(135deg, var(--primary-color, #0d6efd), var(--secondary-color, #0dcaf0));
        padding: 2rem;
        border-radius: 0.75rem;
        color: white;
        margin-bottom: 2rem;
    }
    .profile-picture-container {
        width: 150px;
        height: 150px;
        margin: 0 auto 1.5rem auto;
        border-radius: 50%;
        overflow: hidden;
        border: 4px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
    }
    .profile-picture-container img { width: 100%; height: 100%; object-fit: cover; }
    .nav-tabs .nav-link.active { font-weight: bold; border-top: 3px solid var(--primary-color, #0d6efd); }
    
    /* Sedikit sentuhan modern pada tombol simpan */
    .btn-simpan-keren {
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2);
    }
    .btn-simpan-keren:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 8px rgba(13, 110, 253, 0.3);
    }
</style>

<div class="container-fluid">
    <div class="page-header shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 fw-bold">Edit Identitas Siswa</h2>
                <p class="mb-0 opacity-75">
                    <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> 
                    (Kelas: <?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Masuk Kelas'); ?>)
                </p>
            </div>
            <a href="walikelas_identitas_siswa.php" class="btn btn-light text-primary fw-bold">
                <i class="bi bi-arrow-left me-2"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Form Data Siswa -->
    <form action="walikelas_aksi.php?aksi=update_siswa" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_siswa" value="<?php echo $siswa['id_siswa']; ?>">
        <!-- Input hidden foto lama untuk logic fallback di aksi -->
        <input type="hidden" name="foto_siswa_lama" value="<?php echo htmlspecialchars($siswa['foto_siswa'] ?? ''); ?>">
        
        <div class="row g-4">
            <!-- Kolom Kiri: Foto -->
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="profile-picture-container">
                            <?php
                            $foto_path = 'uploads/foto_siswa/' . ($siswa['foto_siswa'] ?? '');
                            if (!empty($siswa['foto_siswa']) && file_exists($foto_path)):
                            ?>
                                <img src="<?php echo htmlspecialchars($foto_path); ?>" alt="Foto Siswa">
                            <?php else: ?>
                                <i class="bi bi-person-circle" style="font-size: 80px; color: #ccc;"></i>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></h5>
                        <p class="text-muted">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?></p>
                        <hr>
                        <div class="mb-3 text-start">
                            <label for="foto_siswa" class="form-label fw-bold small">Ganti Foto (Opsional)</label>
                            <input class="form-control form-control-sm" type="file" id="foto_siswa" name="foto_siswa" accept="image/jpeg, image/png">
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Format: JPG/PNG. Maks: 1MB.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Form Tabs -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white p-0">
                        <ul class="nav nav-tabs nav-fill" id="myTab" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#data-pribadi" type="button">Data Pribadi</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#data-ortu" type="button">Orang Tua</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#data-pendidikan" type="button">Pendidikan</button></li>
                        </ul>
                    </div>
                    <div class="card-body p-4">
                        <div class="tab-content" id="myTabContent">
                            
                            <!-- TAB 1: Data Pribadi -->
                            <div class="tab-pane fade show active" id="data-pribadi">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($siswa['nama_lengkap'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">NIS</label>
                                        <input type="text" class="form-control" name="nis" value="<?php echo htmlspecialchars($siswa['nis'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">NISN</label>
                                        <input type="text" class="form-control" name="nisn" value="<?php echo htmlspecialchars($siswa['nisn'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">NIK</label>
                                        <input type="text" class="form-control" name="nik" value="<?php echo htmlspecialchars($siswa['nik'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tempat Lahir</label>
                                        <input type="text" class="form-control" name="tempat_lahir" value="<?php echo htmlspecialchars($siswa['tempat_lahir'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tanggal Lahir</label>
                                        <input type="date" class="form-control" name="tanggal_lahir" value="<?php echo htmlspecialchars($siswa['tanggal_lahir'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Jenis Kelamin</label>
                                        <select class="form-select" name="jenis_kelamin">
                                            <option value="L" <?php echo (($siswa['jenis_kelamin'] ?? '') == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                            <option value="P" <?php echo (($siswa['jenis_kelamin'] ?? '') == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Agama</label>
                                        <input type="text" class="form-control" name="agama" value="<?php echo htmlspecialchars($siswa['agama'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status Keluarga</label>
                                        <input type="text" class="form-control" name="status_dalam_keluarga" value="<?php echo htmlspecialchars($siswa['status_dalam_keluarga'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Anak ke</label>
                                        <input type="number" class="form-control" name="anak_ke" value="<?php echo htmlspecialchars($siswa['anak_ke'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Alamat</label>
                                        <textarea class="form-control" name="alamat" rows="2"><?php echo htmlspecialchars($siswa['alamat'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Telepon (WA)</label>
                                        <input type="text" class="form-control" name="telepon_siswa" value="<?php echo htmlspecialchars($siswa['telepon_siswa'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- TAB 2: Data Ortu -->
                            <div class="tab-pane fade" id="data-ortu">
                                <h6 class="text-primary fw-bold mb-3">Data Orang Tua</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6"><label class="form-label">Nama Ayah</label><input type="text" class="form-control" name="nama_ayah" value="<?php echo htmlspecialchars($siswa['nama_ayah'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Pekerjaan Ayah</label><input type="text" class="form-control" name="pekerjaan_ayah" value="<?php echo htmlspecialchars($siswa['pekerjaan_ayah'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Nama Ibu</label><input type="text" class="form-control" name="nama_ibu" value="<?php echo htmlspecialchars($siswa['nama_ibu'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Pekerjaan Ibu</label><input type="text" class="form-control" name="pekerjaan_ibu" value="<?php echo htmlspecialchars($siswa['pekerjaan_ibu'] ?? ''); ?>"></div>
                                </div>
                                
                                <h6 class="text-primary fw-bold mb-3">Data Wali (Opsional)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Nama Wali</label><input type="text" class="form-control" name="nama_wali" value="<?php echo htmlspecialchars($siswa['nama_wali'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Pekerjaan Wali</label><input type="text" class="form-control" name="pekerjaan_wali" value="<?php echo htmlspecialchars($siswa['pekerjaan_wali'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Telepon Wali</label><input type="text" class="form-control" name="telepon_wali" value="<?php echo htmlspecialchars($siswa['telepon_wali'] ?? ''); ?>"></div>
                                    <div class="col-12"><label class="form-label">Alamat Wali</label><textarea class="form-control" name="alamat_wali" rows="2"><?php echo htmlspecialchars($siswa['alamat_wali'] ?? ''); ?></textarea></div>
                                </div>
                            </div>
                            
                            <!-- TAB 3: Riwayat Pendidikan -->
                            <div class="tab-pane fade" id="data-pendidikan">
                                <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i> Sesuaikan dengan ijazah sebelumnya.</div>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Sekolah Asal (SD/Mi/SMP Lain)</label>
                                        <input type="text" class="form-control" name="sekolah_asal" 
                                               value="<?php echo htmlspecialchars($siswa['sekolah_asal'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Diterima di Kelas</label>
                                        <select class="form-select" name="diterima_di_kelas">
                                            <option value="">- Pilih Kelas -</option>
                                            <option value="7" <?php echo (($siswa['diterima_di_kelas'] ?? '') == '7') ? 'selected' : ''; ?>>Kelas 7</option>
                                            <option value="8" <?php echo (($siswa['diterima_di_kelas'] ?? '') == '8') ? 'selected' : ''; ?>>Kelas 8</option>
                                            <option value="9" <?php echo (($siswa['diterima_di_kelas'] ?? '') == '9') ? 'selected' : ''; ?>>Kelas 9</option>
                                           
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Tanggal Diterima</label>
                                        <input type="date" class="form-control" name="diterima_tanggal" 
                                               value="<?php echo htmlspecialchars($siswa['diterima_tanggal'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="card-footer bg-light text-end p-3">
                        <button type="submit" class="btn btn-primary btn-simpan-keren px-4"><i class="bi bi-save me-2"></i>Simpan Perubahan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
// HANDLING SESSION SWEETALERT (JSON)
// Logic: Menggunakan desain SweetAlert yang lebih "Modern" dan Interaktif
if (isset($_SESSION['pesan'])) {
    $data_pesan = json_decode($_SESSION['pesan'], true); 

    if ($data_pesan) {
        $icon = json_encode($data_pesan['icon']);
        $title = json_encode($data_pesan['title']);
        $text = json_encode($data_pesan['text']);
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: $icon,
                    title: $title,
                    text: $text,
                    confirmButtonText: '<i class=\"bi bi-hand-thumbs-up\"></i> Oke, Siap!',
                    confirmButtonColor: '#0d6efd',
                    background: '#ffffff',
                    padding: '2rem',
                    backdrop: `
                        rgba(0,0,0,0.4)
                        left top
                        no-repeat
                    `,
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    },
                    customClass: {
                        title: 'fw-bold text-primary',
                        popup: 'rounded-4 shadow-lg border-0'
                    },
                    timer: 4000,
                    timerProgressBar: true
                });
            });
        </script>";
    }
    unset($_SESSION['pesan']);
}
?>

<!-- Tambahan: Animate.css untuk animasi popup SweetAlert agar lebih mulus -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<?php include 'footer.php'; ?>