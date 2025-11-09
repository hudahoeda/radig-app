<?php
include 'header.php';
include 'koneksi.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak!', text: 'Anda tidak diizinkan mengakses halaman ini.'}).then(() => window.location.href = 'dashboard.php');</script>";
    include 'footer.php';
    exit();
}

// Tentukan aksi berdasarkan parameter URL
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
$aksi = 'kelola'; // Default aksi adalah mengelola siswa yang ada
if (isset($_GET['aksi']) && $_GET['aksi'] == 'masuk') {
    $aksi = 'masuk';
    $id_siswa = 0; // Pastikan id_siswa 0 untuk aksi masuk
}

// Jika aksinya 'kelola', ID siswa wajib ada
if ($aksi == 'kelola' && $id_siswa == 0) {
    echo "<script>Swal.fire({icon: 'error', title: 'ID Tidak Valid', text: 'ID Siswa tidak ditemukan.'}).then(() => window.location.href = 'mutasi_siswa_tampil.php');</script>";
    include 'footer.php';
    exit();
}

$siswa = null;
$mutasi_masuk = null;
$mutasi_keluar = null;

if ($aksi == 'kelola') {
    // Ambil data siswa yang akan dikelola
    $stmt = mysqli_prepare($koneksi, "SELECT s.nama_lengkap, s.nis, s.nisn, s.foto_siswa, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.id_kelas = k.id_kelas WHERE s.id_siswa = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_siswa);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $siswa = mysqli_fetch_assoc($result);

    if (!$siswa) {
        echo "<script>Swal.fire({icon: 'error', title: 'Data Gagal Dimuat', text: 'Data siswa tidak ditemukan di database.'}).then(() => window.location.href = 'mutasi_siswa_tampil.php');</script>";
        include 'footer.php';
        exit();
    }
    // Ambil data mutasi yang sudah ada
    $q_mutasi_masuk = mysqli_query($koneksi, "SELECT * FROM mutasi_masuk WHERE id_siswa = $id_siswa");
    $mutasi_masuk = mysqli_fetch_assoc($q_mutasi_masuk);
    $q_mutasi_keluar = mysqli_query($koneksi, "SELECT * FROM mutasi_keluar WHERE id_siswa = $id_siswa");
    $mutasi_keluar = mysqli_fetch_assoc($q_mutasi_keluar);
}

// Ambil semua kelas untuk dropdown
$query_kelas_list = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif') ORDER BY nama_kelas ASC");

?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }

    /* --- [PERBAIKAN] Atur ukuran foto siswa di kartu profil --- */
    .student-profile-card .me-3 img {
        width: 80px; 
        height: 80px; 
        object-fit: cover; 
        border-radius: 50%;
        border: 3px solid #dee2e6; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .nav-tabs-form .nav-link.active {
        color: var(--primary-color); border-color: var(--primary-color); font-weight: 600;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1"><?php echo $aksi == 'masuk' ? 'Catat Mutasi Masuk' : 'Kelola Mutasi Siswa'; ?></h1>
                <p class="lead mb-0 opacity-75"><?php echo $aksi == 'masuk' ? 'Tambahkan data siswa pindahan baru ke sistem.' : 'Ubah status atau catat data pindah untuk siswa.'; ?></p>
            </div>
            <a href="mutasi_siswa_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali</a>
        </div>
    </div>

    <?php if ($aksi == 'kelola' && $siswa): ?>
    <div class="card shadow-sm mb-4 student-profile-card">
        <div class="card-body d-flex align-items-center">
            <div class="me-3">
                <?php if (!empty($siswa['foto_siswa'])): ?>
                    <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto Siswa">
                <?php else: ?>
                    <i class="bi bi-person-circle" style="font-size: 80px; color: #ced4da;"></i>
                <?php endif; ?>
            </div>
            <div>
                <h4 class="mb-0"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></h4>
                <p class="text-muted mb-0"><strong>NISN:</strong> <?php echo htmlspecialchars($siswa['nisn']); ?> | <strong>Kelas Terakhir:</strong> <?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><h5 class="mb-0"><i class="bi bi-printer-fill me-2 text-primary"></i>Riwayat & Cetak Dokumen Mutasi</h5></div>
        <div class="card-body">
            <p class="text-muted small">Gunakan tombol di bawah untuk mencetak formulir mutasi. Formulir akan terisi otomatis jika data mutasi sudah disimpan.</p>
            <div class="row">
                <div class="col-md-6 border-end">
                    <h6 class="fw-bold"><i class="bi bi-box-arrow-in-right text-success"></i> Dokumen Mutasi Masuk</h6>
                    <a href="cetak_mutasi.php?tipe=masuk&id_siswa=<?php echo $id_siswa; ?>" class="btn btn-sm btn-outline-success" target="_blank"><i class="bi bi-printer"></i> Cetak Dokumen Masuk</a><hr>
                    <p class="fw-bold small">Data Tercatat:</p>
                    <?php if ($mutasi_masuk): ?>
                        <p class="mb-1"><small class="text-muted">Sekolah Asal:</small><br><strong><?php echo htmlspecialchars($mutasi_masuk['sekolah_asal']); ?></strong></p>
                        <p class="mb-1"><small class="text-muted">Tanggal Masuk:</small><br><strong><?php echo date('d F Y', strtotime($mutasi_masuk['tanggal_masuk'])); ?></strong></p>
                        <p class="mb-0"><small class="text-muted">Diterima di Kelas:</small><br><strong><?php echo htmlspecialchars($mutasi_masuk['diterima_di_kelas']); ?></strong></p>
                    <?php else: ?>
                        <p class="text-muted fst-italic small">Belum ada data mutasi masuk yang tercatat.</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold"><i class="bi bi-box-arrow-left text-warning"></i> Dokumen Mutasi Keluar/Lulus</h6>
                    <a href="cetak_mutasi.php?tipe=keluar&id_siswa=<?php echo $id_siswa; ?>" class="btn btn-sm btn-outline-warning" target="_blank"><i class="bi bi-printer"></i> Cetak Dokumen Keluar</a><hr>
                    <p class="fw-bold small">Data Tercatat:</p>
                    <?php if ($mutasi_keluar): ?>
                        <p class="mb-1"><small class="text-muted">Tanggal Keluar:</small><br><strong><?php echo date('d F Y', strtotime($mutasi_keluar['tanggal_keluar'])); ?></strong></p>
                        <p class="mb-1"><small class="text-muted">Kelas yang Ditinggalkan:</small><br><strong><?php echo htmlspecialchars($mutasi_keluar['kelas_ditinggalkan']); ?></strong></p>
                        <p class="mb-0"><small class="text-muted">Alasan:</small><br><strong><?php echo htmlspecialchars($mutasi_keluar['alasan']); ?></strong></p>
                    <?php else: ?>
                        <p class="text-muted fst-italic small">Belum ada data mutasi keluar yang tercatat.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-light p-0">
            <ul class="nav nav-tabs nav-tabs-form nav-fill">
                <li class="nav-item"><button class="nav-link <?php if($aksi=='kelola') echo 'active';?>" data-bs-toggle="tab" data-bs-target="#mutasi-keluar"><i class="bi bi-box-arrow-up me-2"></i>Catat Mutasi Keluar / Lulus</button></li>
                <li class="nav-item"><button class="nav-link <?php if($aksi=='masuk') echo 'active';?>" data-bs-toggle="tab" data-bs-target="#mutasi-masuk"><i class="bi bi-box-arrow-down me-2"></i>Catat Mutasi Masuk</button></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content p-3">
                <div class="tab-pane fade <?php if($aksi=='kelola') echo 'show active';?>" id="mutasi-keluar">
                    <?php if ($aksi == 'kelola'): ?>
                    <form action="mutasi_aksi.php?aksi=proses_keluar" method="POST">
                        <input type="hidden" name="id_siswa" value="<?php echo $id_siswa; ?>">
                        <p>Gunakan form ini untuk mengubah status siswa dan mencatat informasi kepindahannya.</p>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="status_siswa" class="form-label fw-bold">Ubah Status Menjadi</label><select name="status_siswa" id="status_siswa" class="form-select" required><option value="Pindah">Pindah ke Sekolah Lain</option><option value="Lulus">Lulus</option><option value="Keluar">Keluar (Drop Out)</option></select></div>
                            <div class="col-md-6"><label for="tanggal_keluar" class="form-label fw-bold">Tanggal Efektif</label><input type="date" class="form-control" id="tanggal_keluar" name="tanggal_keluar" value="<?php echo $mutasi_keluar['tanggal_keluar'] ?? date('Y-m-d'); ?>" required></div>
                            <div class="col-md-12"><label for="kelas_ditinggalkan" class="form-label fw-bold">Kelas yang Ditinggalkan</label><input type="text" class="form-control" id="kelas_ditinggalkan" name="kelas_ditinggalkan" placeholder="Contoh: VIII-A" value="<?php echo $mutasi_keluar['kelas_ditinggalkan'] ?? ($siswa['nama_kelas'] ?? ''); ?>" required></div>
                            <div class="col-12"><label for="alasan" class="form-label fw-bold">Keterangan / Alasan</label><textarea class="form-control" id="alasan" name="alasan" rows="3" placeholder="Contoh: Mengikuti orang tua pindah tugas..." required><?php echo $mutasi_keluar['alasan'] ?? ''; ?></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-warning mt-3"><i class="bi bi-save-fill me-2"></i>Simpan Data Mutasi Keluar</button>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-light text-center">Form ini hanya untuk siswa yang sudah terdaftar di sistem. Untuk mencatat mutasi masuk siswa baru, silakan pilih tab "Catat Mutasi Masuk".</div>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade <?php if($aksi=='masuk') echo 'show active';?>" id="mutasi-masuk">
                    <form action="mutasi_aksi.php?aksi=proses_masuk" method="POST">
                        <p>Gunakan form ini untuk mendaftarkan siswa <strong>pindahan baru</strong> ke sekolah. Data siswa akan dibuat dan ditandai sebagai <strong>Aktif</strong>.</p>
                        <div class="row g-3">
                            <div class="col-12"><hr><h6 class="text-muted">DATA PRIBADI</h6></div>
                            <div class="col-md-8"><label class="form-label fw-bold">Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Jenis Kelamin</label><select name="jenis_kelamin" class="form-select" required><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Tempat Lahir</label><input type="text" name="tempat_lahir" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="form-control"></div>
                            <div class="col-12"><hr><h6 class="text-muted">DATA AKADEMIK</h6></div>
                            <div class="col-md-6"><label class="form-label fw-bold">NIS</label><input type="text" name="nis" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label fw-bold">NISN</label><input type="text" name="nisn" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Sekolah Asal</label><input type="text" name="sekolah_asal" class="form-control" placeholder="Nama sekolah sebelumnya" required></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Diterima di Kelas</label>
                                <select name="id_kelas" class="form-select" required>
                                    <option value="">-- Pilih Kelas Penempatan --</option>
                                    <?php mysqli_data_seek($query_kelas_list, 0); while ($kelas = mysqli_fetch_assoc($query_kelas_list)): ?>
                                    <option value="<?php echo $kelas['id_kelas']; ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label fw-bold">Tanggal Diterima</label><input type="date" name="diterima_tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4"><i class="bi bi-person-plus-fill me-2"></i>Tambahkan Siswa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>