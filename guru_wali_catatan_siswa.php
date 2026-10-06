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
    body {
        background-color: #f4f7fe;
    }

    /* Center Aligned Profile Header */
    .profile-header-wrapper {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 3rem 1.5rem 5rem 1.5rem;
        border-bottom-left-radius: 2rem;
        border-bottom-right-radius: 2rem;
        color: white;
        text-align: center;
        position: relative;
        margin-bottom: 2rem;
    }

    .profile-header-wrapper .back-btn {
        position: absolute;
        top: 1.5rem;
        left: 1.5rem;
        color: white;
        font-size: 1.2rem;
        text-decoration: none;
        background: rgba(255,255,255,0.2);
        padding: 0.4rem 0.8rem;
        border-radius: 2rem;
        backdrop-filter: blur(5px);
        transition: all 0.2s;
    }
    .profile-header-wrapper .back-btn:hover {
        background: rgba(255,255,255,0.3);
    }

    .profile-avatar-container {
        position: relative;
        display: inline-block;
        margin-bottom: 1rem;
    }
    .profile-avatar {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.9);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        background: #e9ecef;
    }
    .profile-avatar-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.9);
        background: linear-gradient(135deg, #e9ecef, #dee2e6);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: #6c757d;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.2rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .profile-meta {
        font-size: 0.9rem;
        opacity: 0.9;
        font-weight: 500;
    }

    /* Timeline Modern */
    .timeline-container {
        padding: 0 1rem;
        max-width: 800px;
        margin: 0 auto;
        position: relative;
    }
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
        background: linear-gradient(to bottom, var(--primary-color) 0%, rgba(var(--bs-primary-rgb),0.1) 100%);
        border-radius: 3px;
    }
    .timeline-item {
        margin-bottom: 1.5rem;
        position: relative;
        padding-left: 55px;
    }
    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        border: 4px solid #f4f7fe; /* Warna background body */
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        z-index: 2;
    }
    .timeline-content {
        background-color: #fff;
        border-radius: 1rem;
        padding: 1.25rem;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.03);
        position: relative;
        transition: transform 0.2s;
    }
    .timeline-content:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }
    /* Panah kecil ke kiri */
    .timeline-content:before {
        content: '';
        position: absolute;
        top: 18px;
        right: 100%;
        border-top: 8px solid transparent;
        border-bottom: 8px solid transparent;
        border-right: 8px solid #fff;
    }

    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }
    .timeline-category {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.3em 0.8em;
        border-radius: 2rem;
        display: inline-block;
        background: rgba(0,0,0,0.04);
    }
    .timeline-date {
        font-size: 0.8rem;
        font-weight: 500;
        color: #adb5bd;
    }

    .timeline-body {
        color: #495057;
        line-height: 1.5;
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    /* Floating Action Button (FAB) */
    .fab-btn {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 65px;
        height: 65px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        box-shadow: 0 10px 25px rgba(var(--bs-primary-rgb), 0.5);
        border: none;
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .fab-btn:hover, .fab-btn:active {
        transform: scale(1.1) rotate(90deg);
        box-shadow: 0 15px 35px rgba(var(--bs-primary-rgb), 0.6);
        color: white;
    }

    /* Glass Modal styling */
    .modal-content {
        border-radius: 1.5rem;
        border: none;
        box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    }
    .modal-header {
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1.5rem 1.5rem 1rem;
    }
    .modal-body {
        padding: 1.5rem;
    }
</style>

<div class="profile-header-wrapper">
    <a href="guru_wali_dashboard.php" class="back-btn"><i class="bi bi-chevron-left me-1"></i> Kembali</a>
    
    <div class="profile-avatar-container">
        <?php
        $foto_path = 'uploads/foto_siswa/' . htmlspecialchars($data_siswa['foto_siswa']);
        if (!empty($data_siswa['foto_siswa']) && file_exists($foto_path)) {
            echo '<img src="' . $foto_path . '" alt="Foto" class="profile-avatar">';
        } else {
            echo '<div class="profile-avatar-placeholder"><i class="bi bi-person-fill"></i></div>';
        }
        ?>
    </div>

    <h2 class="profile-name"><?php echo htmlspecialchars($data_siswa['nama_lengkap']); ?></h2>
    <div class="profile-meta">
        NIS: <?php echo htmlspecialchars($data_siswa['nis']); ?> &nbsp;&bull;&nbsp; Kelas <?php echo htmlspecialchars($data_siswa['nama_kelas']); ?>
    </div>
</div>

<div class="container-fluid">
    <div class="timeline-container pb-5">

        <?php
        // Notifikasi status hapus/tambah
        if(isset($_GET['status'])){
            if($_GET['status'] == 'hapus_sukses' || $_GET['status'] == 'sukses'){
                $msg = $_GET['status'] == 'sukses' ? 'Catatan berhasil ditambahkan.' : 'Catatan berhasil dihapus.';
                echo '<div class="alert alert-success border-0 shadow-sm rounded-4 mb-4" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> '.$msg.'
                      </div>';
            } else {
                echo '<div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Proses gagal dilakukan.
                      </div>';
            }
        }
        ?>

        <?php if (mysqli_num_rows($query_catatan) > 0): ?>
            <ul class="timeline">
                <?php
                $category_map = [
                    'Akademik' => ['icon' => 'bi-book-half', 'color' => 'bg-info', 'text' => 'text-info'],
                    'Karakter' => ['icon' => 'bi-heart-fill', 'color' => 'bg-success', 'text' => 'text-success'],
                    'Keterampilan' => ['icon' => 'bi-tools', 'color' => 'bg-warning', 'text' => 'text-warning'],
                    'Komunikasi Ortu' => ['icon' => 'bi-telephone-fill', 'color' => 'bg-danger', 'text' => 'text-danger'],
                    'Lainnya' => ['icon' => 'bi-three-dots', 'color' => 'bg-secondary', 'text' => 'text-secondary']
                ];
                while ($catatan = mysqli_fetch_assoc($query_catatan)):
                    $cat_info = $category_map[$catatan['kategori_catatan']] ?? $category_map['Lainnya'];
                ?>
                    <li class="timeline-item">
                        <div class="timeline-icon <?php echo $cat_info['color']; ?>">
                            <i class="bi <?php echo $cat_info['icon']; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-category <?php echo $cat_info['text']; ?>" style="background: rgba(var(--bs-<?php echo str_replace('bg-', '', $cat_info['color']); ?>-rgb), 0.1);">
                                    <?php echo htmlspecialchars($catatan['kategori_catatan']); ?>
                                </span>

                                <div class="d-flex align-items-center gap-2">
                                    <span class="timeline-date"><i class="bi bi-calendar2-event me-1"></i> <?php echo date('d M Y, H:i', strtotime($catatan['tanggal_catatan'])); ?></span>

                                    <!-- Delete Button -->
                                    <form action="proses_hapus_catatan_wali.php" method="POST" onsubmit="return confirm('Hapus catatan ini secara permanen?');" class="m-0 p-0">
                                        <input type="hidden" name="id_catatan" value="<?php echo $catatan['id_catatan']; ?>">
                                        <input type="hidden" name="id_siswa" value="<?php echo $id_siswa; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0 ms-1 opacity-75" title="Hapus catatan" style="line-height:1;">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <p class="timeline-body mt-2"><?php echo nl2br(htmlspecialchars($catatan['isi_catatan'])); ?></p>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="d-inline-block p-4 rounded-circle bg-white shadow-sm mb-3">
                    <i class="bi bi-journal-x fs-1 text-muted"></i>
                </div>
                <h5 class="fw-bold text-dark">Belum Ada Catatan</h5>
                <p class="text-muted">Tekan tombol + di sudut bawah untuk menambahkan catatan pertama.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Floating Action Button -->
<button class="fab-btn" data-bs-toggle="modal" data-bs-target="#modalTambahCatatan" title="Tambah Catatan">
    <i class="bi bi-plus"></i>
</button>

<!-- Modal Tambah Catatan -->
<div class="modal fade" id="modalTambahCatatan" tabindex="-1" aria-labelledby="modalTambahCatatanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTambahCatatanLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Tulis Catatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="proses_tambah_catatan_wali.php" method="POST">
                    <input type="hidden" name="id_siswa" value="<?php echo $id_siswa; ?>">
                    <input type="hidden" name="id_guru_wali" value="<?php echo $id_guru_login; ?>">

                    <div class="mb-3">
                        <label for="tanggal_catatan" class="form-label text-muted fw-bold small">TANGGAL & WAKTU</label>
                        <input type="datetime-local" class="form-control form-control-lg bg-light border-0" id="tanggal_catatan" name="tanggal_catatan" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="kategori_catatan" class="form-label text-muted fw-bold small">KATEGORI</label>
                        <select class="form-select form-select-lg bg-light border-0" id="kategori_catatan" name="kategori_catatan" required>
                            <option value="Akademik">Akademik</option>
                            <option value="Karakter">Karakter</option>
                            <option value="Keterampilan">Keterampilan</option>
                            <option value="Komunikasi Ortu">Komunikasi dengan Orang Tua</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="isi_catatan" class="form-label text-muted fw-bold small">ISI CATATAN</label>
                        <textarea class="form-control bg-light border-0" id="isi_catatan" name="isi_catatan" rows="5" placeholder="Tuliskan observasi perkembangan anak..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill"><i class="bi bi-send-fill me-2"></i>Simpan Catatan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>