<?php
include 'koneksi.php';
include 'header.php';

if ($_SESSION['role'] != 'guru') {
    die("Akses tidak diizinkan.");
}

$id_guru_login = $_SESSION['id_guru'];
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;

// Ambil data siswa untuk verifikasi dan ditampilkan, termasuk foto
$query_siswa = mysqli_query($koneksi, "
    SELECT 
        s.id_siswa, s.nama_lengkap, s.nis, s.foto_siswa,
        k.nama_kelas, 
        g.nama_guru as nama_wali_kelas
    FROM siswa s 
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
    LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru
    WHERE s.id_siswa = $id_siswa AND s.id_guru_wali = $id_guru_login
");

if (mysqli_num_rows($query_siswa) == 0) {
    die("Data siswa tidak ditemukan atau Anda bukan Guru Wali untuk siswa ini.");
}
$data_siswa = mysqli_fetch_assoc($query_siswa);

// Ambil semua catatan untuk siswa ini
$query_catatan = mysqli_query($koneksi, "
    SELECT * FROM catatan_guru_wali 
    WHERE id_siswa = $id_siswa 
    ORDER BY tanggal_catatan DESC, id_catatan DESC
");
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }

    .student-profile-panel {
        display: flex;
        align-items: center;
        background-color: #fff;
        border-radius: 0.75rem;
        padding: 1rem;
        border: 1px solid var(--border-color);
    }
    .student-profile-panel img {
        width: 70px;
        height: 70px;
        object-fit: cover;
        border-radius: 50%;
        margin-right: 1.5rem;
    }
    .student-profile-panel .student-name {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0;
    }

    /* Timeline Style */
    .timeline {
        list-style: none;
        padding: 0;
        position: relative;
    }
    .timeline:before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 20px;
        width: 3px;
        background-color: #e9ecef;
    }
    .timeline-item {
        margin-bottom: 2rem;
        position: relative;
        padding-left: 50px;
    }
    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        border: 3px solid #e9ecef;
    }
    .timeline-content {
        background-color: #fff;
        border-radius: 0.5rem;
        padding: 1rem;
        border: 1px solid var(--border-color);
        position: relative;
    }
    .timeline-content:before {
        content: '';
        position: absolute;
        top: 15px;
        right: 100%;
        border-top: 8px solid transparent;
        border-bottom: 8px solid transparent;
        border-right: 8px solid var(--border-color);
    }
    .timeline-content .timeline-date {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
    }
    .timeline-content .timeline-category {
        font-size: 0.9rem;
        font-weight: 600;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Portofolio Siswa</h1>
                <p class="lead mb-0 opacity-75">Catatan perkembangan untuk <?php echo htmlspecialchars($data_siswa['nama_lengkap']); ?></p>
            </div>
            <a href="guru_wali_dashboard.php" class="btn btn-outline-light mt-3 mt-sm-0"><i class="bi bi-arrow-left me-2"></i>Daftar Siswa</a>
        </div>
    </div>
    
    <div class="student-profile-panel mb-4">
        <?php
        $foto_path = 'uploads/foto_siswa/' . htmlspecialchars($data_siswa['foto_siswa']);
        if (!empty($data_siswa['foto_siswa']) && file_exists($foto_path)) {
            echo '<img src="' . $foto_path . '" alt="Foto Siswa">';
        } else {
            echo '<div style="width:70px; height:70px; margin-right: 1.5rem; flex-shrink: 0;"><i class="bi bi-person-circle" style="font-size: 70px; color: #adb5bd;"></i></div>';
        }
        ?>
        <div>
            <h3 class="student-name"><?php echo htmlspecialchars($data_siswa['nama_lengkap']); ?></h3>
            <span class="text-muted">NIS: <?php echo htmlspecialchars($data_siswa['nis']); ?> | Kelas Saat Ini: <?php echo htmlspecialchars($data_siswa['nama_kelas']); ?> | Wali Kelas: <?php echo htmlspecialchars($data_siswa['nama_wali_kelas'] ?? '-'); ?></span>
        </div>
    </div>

    <?php
    // Tampilkan notifikasi status hapus
    if(isset($_GET['status'])){
        if($_GET['status'] == 'hapus_sukses'){
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> Catatan berhasil dihapus.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else if($_GET['status'] == 'hapus_gagal'){
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Gagal menghapus catatan. Anda mungkin tidak memiliki izin.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
    }
    ?>

    <div class="row">
        <!-- Kolom Tambah Catatan -->
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Tambah Catatan Baru</h5>
                </div>
                <div class="card-body">
                    <form action="proses_tambah_catatan_wali.php" method="POST">
                        <input type="hidden" name="id_siswa" value="<?php echo $id_siswa; ?>">
                        <input type="hidden" name="id_guru_wali" value="<?php echo $id_guru_login; ?>">
                        <div class="mb-3">
                            <label for="tanggal_catatan" class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal_catatan" name="tanggal_catatan" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="kategori_catatan" class="form-label">Kategori</label>
                            <select class="form-select" id="kategori_catatan" name="kategori_catatan" required>
                                <option value="Akademik">Akademik</option>
                                <option value="Karakter">Karakter</option>
                                <option value="Keterampilan">Keterampilan</option>
                                <option value="Komunikasi Ortu">Komunikasi dengan Orang Tua</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="isi_catatan" class="form-label">Isi Catatan</label>
                            <textarea class="form-control" id="isi_catatan" name="isi_catatan" rows="6" placeholder="Tuliskan observasi atau kejadian penting di sini..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save-fill me-2"></i>Simpan Catatan</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Kolom Riwayat Catatan -->
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                 <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Catatan</h5>
                </div>
                <div class="card-body" style="max-height: 650px; overflow-y: auto;">
                    <?php if (mysqli_num_rows($query_catatan) > 0): ?>
                        <ul class="timeline">
                            <?php 
                            $category_map = [
                                'Akademik' => ['icon' => 'bi-book-half', 'color' => 'bg-info'],
                                'Karakter' => ['icon' => 'bi-heart-fill', 'color' => 'bg-success'],
                                'Keterampilan' => ['icon' => 'bi-tools', 'color' => 'bg-warning'],
                                'Komunikasi Ortu' => ['icon' => 'bi-telephone-fill', 'color' => 'bg-danger'],
                                'Lainnya' => ['icon' => 'bi-three-dots', 'color' => 'bg-secondary']
                            ];
                            while ($catatan = mysqli_fetch_assoc($query_catatan)): 
                                $cat_info = $category_map[$catatan['kategori_catatan']] ?? $category_map['Lainnya'];
                            ?>
                                <li class="timeline-item">
                                    <div class="timeline-icon <?php echo $cat_info['color']; ?>">
                                        <i class="bi <?php echo $cat_info['icon']; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="timeline-category" style="color: var(--<?php echo str_replace('bg-', '', $cat_info['color']);?>);"><?php echo htmlspecialchars($catatan['kategori_catatan']); ?></span>
                                                <span class="timeline-date ms-2">| <?php echo date('d F Y', strtotime($catatan['tanggal_catatan'])); ?></span>
                                            </div>
                                            
                                            <!-- TOMBOL HAPUS -->
                                            <form action="proses_hapus_catatan_wali.php" method="POST" onsubmit="return confirm('Anda yakin ingin menghapus catatan ini?');" style="margin: 0;">
                                                <input type="hidden" name="id_catatan" value="<?php echo $catatan['id_catatan']; ?>">
                                                <input type="hidden" name="id_siswa" value="<?php echo $id_siswa; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Hapus catatan">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <!-- AKHIR TOMBOL HAPUS -->

                                        </div>
                                        <hr class="my-2">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($catatan['isi_catatan'])); ?></p>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <i class="bi bi-journal-x fs-1 text-muted"></i>
                            <h5 class="mt-3">Belum Ada Catatan</h5>
                            <p class="text-muted">Gunakan formulir di samping untuk menambahkan catatan perkembangan pertama bagi siswa ini.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>