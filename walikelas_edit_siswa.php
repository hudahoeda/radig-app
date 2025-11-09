<?php
include 'header.php';
include 'koneksi.php';

// Validasi role & input
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Anda harus login sebagai Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}
if (!isset($_GET['id_siswa']) || empty($_GET['id_siswa'])) {
    echo "<script>Swal.fire('Error','ID Siswa tidak valid.','error').then(() => window.location = 'walikelas_identitas_siswa.php');</script>";
    exit;
}

$id_siswa_edit = (int)$_GET['id_siswa'];
$id_wali_kelas = $_SESSION['id_guru'];

// Validasi Keamanan & ambil data siswa
$stmt_check = mysqli_prepare($koneksi, "SELECT s.* FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas WHERE s.id_siswa = ? AND k.id_wali_kelas = ?");
mysqli_stmt_bind_param($stmt_check, "ii", $id_siswa_edit, $id_wali_kelas);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
if (mysqli_num_rows($result_check) === 0) {
    echo "<script>Swal.fire('Akses Ditolak','Anda bukan wali kelas dari siswa ini.','error').then(() => window.location = 'walikelas_identitas_siswa.php');</script>";
    exit;
}
$siswa = mysqli_fetch_assoc($result_check);
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }

    .nav-tabs-form .nav-link {
        font-weight: 500;
        color: var(--text-muted);
        border: none;
        border-bottom: 3px solid transparent;
    }
    .nav-tabs-form .nav-link.active {
        color: var(--primary-color);
        border-color: var(--primary-color);
        font-weight: 600;
        background-color: #e0f2f1;
    }
    .tab-content {
        border: 1px solid var(--border-color);
        border-top: none;
        padding: 2rem;
        border-radius: 0 0 0.5rem 0.5rem;
    }

    /* Perbaikan CSS untuk Foto Profil */
    .profile-picture-container { /* Kontainer baru */
        width: 150px;
        height: 150px;
        margin: 0 auto 1.5rem auto; /* Tengah & margin bawah */
        border-radius: 50%;
        overflow: hidden; /* Pastikan gambar tidak keluar dari lingkaran */
        border: 4px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        display: flex; /* Untuk ikon di tengah */
        align-items: center; /* Untuk ikon di tengah */
        justify-content: center; /* Untuk ikon di tengah */
    }
    .profile-picture-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-picture-container .icon-placeholder {
        font-size: 100px; /* Ukuran ikon yang lebih pas */
        color: #ced4da;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Identitas Siswa</h1>
                <p class="lead mb-0 opacity-75"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></p>
            </div>
            <a href="walikelas_identitas_siswa.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali ke Daftar Siswa</a>
        </div>
    </div>

    <form action="walikelas_aksi.php?aksi=update_siswa" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_siswa" value="<?php echo $siswa['id_siswa']; ?>">
        
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <div class="profile-picture-container"> 
                            <?php if (!empty($siswa['foto_siswa']) && file_exists('uploads/foto_siswa/' . $siswa['foto_siswa'])): ?>
                                <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto saat ini">
                            <?php else: ?>
                                <i class="bi bi-person-circle icon-placeholder"></i>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></h5>
                        <p class="text-muted">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?></p>
                        <hr>
                        <label for="foto_siswa" class="form-label fw-bold">Ganti Foto (Opsional)</label>
                        <input class="form-control" type="file" id="foto_siswa" name="foto_siswa" accept="image/jpeg, image/png">
                        <small class="text-muted d-block mt-1">Format: JPG/PNG. Ukuran maks: 1MB.</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-light p-0">
                        <ul class="nav nav-tabs nav-tabs-form nav-fill" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#data-pribadi" type="button"><i class="bi bi-person-fill me-2"></i>Data Pribadi</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#data-ortu" type="button"><i class="bi bi-people-fill me-2"></i>Data Ortu & Wali</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#data-pendidikan" type="button"><i class="bi bi-building-fill me-2"></i>Riwayat Pendidikan</button></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="data-pribadi" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12"><label for="nama_lengkap" class="form-label">Nama Lengkap</label><input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($siswa['nama_lengkap'] ?? ''); ?>" required></div>
                                    <div class="col-md-6"><label for="nis" class="form-label">NIS</label><input type="text" class="form-control" id="nis" name="nis" value="<?php echo htmlspecialchars($siswa['nis'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="nisn" class="form-label">NISN</label><input type="text" class="form-control" id="nisn" name="nisn" value="<?php echo htmlspecialchars($siswa['nisn'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="tempat_lahir" class="form-label">Tempat Lahir</label><input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" value="<?php echo htmlspecialchars($siswa['tempat_lahir'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="tanggal_lahir" class="form-label">Tanggal Lahir</label><input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($siswa['tanggal_lahir'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="jenis_kelamin" class="form-label">Jenis Kelamin</label><select class="form-select" id="jenis_kelamin" name="jenis_kelamin"><option value="L" <?php echo (($siswa['jenis_kelamin'] ?? '') == 'L') ? 'selected' : ''; ?>>Laki-laki</option><option value="P" <?php echo (($siswa['jenis_kelamin'] ?? '') == 'P') ? 'selected' : ''; ?>>Perempuan</option></select></div>
                                    <div class="col-md-6"><label for="agama" class="form-label">Agama</label><input type="text" class="form-control" id="agama" name="agama" value="<?php echo htmlspecialchars($siswa['agama'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="status_dalam_keluarga" class="form-label">Status dalam Keluarga</label><input type="text" class="form-control" id="status_dalam_keluarga" name="status_dalam_keluarga" value="<?php echo htmlspecialchars($siswa['status_dalam_keluarga'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="anak_ke" class="form-label">Anak ke</label><input type="number" class="form-control" id="anak_ke" name="anak_ke" value="<?php echo htmlspecialchars($siswa['anak_ke'] ?? ''); ?>"></div>
                                    <div class="col-12"><label for="alamat" class="form-label">Alamat Peserta Didik</label><textarea class="form-control" id="alamat" name="alamat" rows="2"><?php echo htmlspecialchars($siswa['alamat'] ?? ''); ?></textarea></div>
                                    <div class="col-12"><label for="telepon_siswa" class="form-label">Nomor WA Siswa/Orang Tua</label><input type="text" class="form-control" id="telepon_siswa" name="telepon_siswa" value="<?php echo htmlspecialchars($siswa['telepon_siswa'] ?? ''); ?>"></div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="data-ortu" role="tabpanel">
                                <h5>Data Orang Tua</h5><hr>
                                <div class="row g-3">
                                    <div class="col-md-6"><label for="nama_ayah" class="form-label">Nama Ayah</label><input type="text" class="form-control" id="nama_ayah" name="nama_ayah" value="<?php echo htmlspecialchars($siswa['nama_ayah'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="pekerjaan_ayah" class="form-label">Pekerjaan Ayah</label><input type="text" class="form-control" id="pekerjaan_ayah" name="pekerjaan_ayah" value="<?php echo htmlspecialchars($siswa['pekerjaan_ayah'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="nama_ibu" class="form-label">Nama Ibu</label><input type="text" class="form-control" id="nama_ibu" name="nama_ibu" value="<?php echo htmlspecialchars($siswa['nama_ibu'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="pekerjaan_ibu" class="form-label">Pekerjaan Ibu</label><input type="text" class="form-control" id="pekerjaan_ibu" name="pekerjaan_ibu" value="<?php echo htmlspecialchars($siswa['pekerjaan_ibu'] ?? ''); ?>"></div>
                                </div>
                                <h5 class="mt-4">Data Wali <span class="text-muted fw-normal small">(Jika ada)</span></h5><hr>
                                <div class="row g-3">
                                    <div class="col-md-6"><label for="nama_wali" class="form-label">Nama Wali</label><input type="text" class="form-control" id="nama_wali" name="nama_wali" value="<?php echo htmlspecialchars($siswa['nama_wali'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="pekerjaan_wali" class="form-label">Pekerjaan Wali</label><input type="text" class="form-control" id="pekerjaan_wali" name="pekerjaan_wali" value="<?php echo htmlspecialchars($siswa['pekerjaan_wali'] ?? ''); ?>"></div>
                                    <div class="col-12"><label for="alamat_wali" class="form-label">Alamat Wali</label><textarea class="form-control" id="alamat_wali" name="alamat_wali" rows="2"><?php echo htmlspecialchars($siswa['alamat_wali'] ?? ''); ?></textarea></div>
                                    <div class="col-12"><label for="telepon_wali" class="form-label">Telepon Wali</label><input type="text" class="form-control" id="telepon_wali" name="telepon_wali" value="<?php echo htmlspecialchars($siswa['telepon_wali'] ?? ''); ?>"></div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="data-pendidikan" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6"><label for="sekolah_asal" class="form-label">Sekolah Asal</label><input type="text" class="form-control" id="sekolah_asal" name="sekolah_asal" value="<?php echo htmlspecialchars($siswa['sekolah_asal'] ?? ''); ?>"></div>
                                    <div class="col-md-6"><label for="diterima_tanggal" class="form-label">Diterima pada Tanggal</label><input type="date" class="form-control" id="diterima_tanggal" name="diterima_tanggal" value="<?php echo htmlspecialchars($siswa['diterima_tanggal'] ?? ''); ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
             <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire('Berhasil!','" . addslashes($_SESSION['pesan']) . "','success');</script>";
    unset($_SESSION['pesan']);
}
if (isset($_SESSION['pesan_error'])) {
    echo "<script>Swal.fire('Gagal!','" . addslashes($_SESSION['pesan_error']) . "','error');</script>";
    unset($_SESSION['pesan_error']);
}
include 'footer.php';
?>