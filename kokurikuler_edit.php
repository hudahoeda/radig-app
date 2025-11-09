<?php
include 'header.php';
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID dari URL untuk tahu kegiatan mana yang akan diedit
$id_kegiatan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_kegiatan == 0) {
    echo "<script>Swal.fire('Error','ID Kegiatan tidak valid.','error').then(() => window.location = 'kokurikuler_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data utama kegiatan yang akan diedit (Termasuk id_koordinator baru)
$stmt_keg = mysqli_prepare($koneksi, "SELECT * FROM kokurikuler_kegiatan WHERE id_kegiatan = ?");
mysqli_stmt_bind_param($stmt_keg, "i", $id_kegiatan);
mysqli_stmt_execute($stmt_keg);
$kegiatan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_keg));

if (!$kegiatan) {
    echo "<script>Swal.fire('Error','Kegiatan tidak ditemukan.','error').then(() => window.location = 'kokurikuler_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil semua dimensi yang sudah dipilih untuk kegiatan ini
$stmt_dim = mysqli_prepare($koneksi, "SELECT nama_dimensi FROM kokurikuler_target_dimensi WHERE id_kegiatan = ?");
mysqli_stmt_bind_param($stmt_dim, "i", $id_kegiatan);
mysqli_stmt_execute($stmt_dim);
$result_dim = mysqli_stmt_get_result($stmt_dim);
$dimensi_terpilih = [];
while($row = mysqli_fetch_assoc($result_dim)){
    $dimensi_terpilih[] = $row['nama_dimensi'];
}

// Data master dimensi profil (Sama seperti di halaman tambah)
$dimensi_lulusan = [
    'Keimanan dan Ketakwaan terhadap Tuhan YME' => ['icon' => 'bi-brightness-high-fill', 'desc' => 'Akhlak mulia dalam hubungannya dengan Tuhan Yang Maha Esa.'],
    'Kewargaan' => ['icon' => 'bi-globe-asia-australia', 'desc' => 'Berpartisipasi aktif dalam menjaga lingkungan dan masyarakat.'],
    'Penalaran Kritis' => ['icon' => 'bi-lightbulb-fill', 'desc' => 'Mampu memproses informasi, menganalisis, dan mengambil keputusan.'],
    'Kreativitas' => ['icon' => 'bi-palette-fill', 'desc' => 'Menghasilkan gagasan atau karya yang orisinal dan bermakna.'],
    'Kolaborasi' => ['icon' => 'bi-people-fill', 'desc' => 'Bekerja sama secara proaktif untuk mencapai tujuan bersama.'],
    'Kemandirian' => ['icon' => 'bi-person-walking', 'desc' => 'Memiliki inisiatif dan bertanggung jawab atas proses & hasil belajar.'],
    'Kesehatan' => ['icon' => 'bi-heart-pulse-fill', 'desc' => 'Menjaga kesehatan jasmani dan rohani diri sendiri serta lingkungan.'],
    'Komunikasi' => ['icon' => 'bi-chat-quote-fill', 'desc' => 'Menyampaikan dan menerima gagasan secara efektif dan santun.']
];

// <!-- TAMBAHAN: Ambil data guru untuk dropdown koordinator -->
$query_guru = "SELECT id_guru, nama_guru FROM guru WHERE role IN ('guru', 'admin') ORDER BY nama_guru ASC";
$result_guru = mysqli_query($koneksi, $query_guru);
$daftar_guru = mysqli_fetch_all($result_guru, MYSQLI_ASSOC);

// <!-- TAMBAHAN: Ambil data mapel untuk dropdown multiselect -->
$query_mapel = "SELECT id_mapel, nama_mapel FROM mata_pelajaran ORDER BY urutan, nama_mapel ASC";
$result_mapel = mysqli_query($koneksi, $query_mapel);
$daftar_mapel = mysqli_fetch_all($result_mapel, MYSQLI_ASSOC);

// <!-- TAMBAHAN: Ambil data mapel yang SUDAH TERPILIH untuk kegiatan ini -->
$query_mapel_terpilih = "SELECT id_mapel FROM kokurikuler_mapel_terlibat WHERE id_kegiatan = ?";
$stmt_mapel_terpilih = mysqli_prepare($koneksi, $query_mapel_terpilih);
mysqli_stmt_bind_param($stmt_mapel_terpilih, "i", $id_kegiatan);
mysqli_stmt_execute($stmt_mapel_terpilih);
$result_mapel_terpilih = mysqli_stmt_get_result($stmt_mapel_terpilih);
$mapel_terpilih_ids = [];
while($row = mysqli_fetch_assoc($result_mapel_terpilih)) {
    $mapel_terpilih_ids[] = $row['id_mapel'];
}

?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .dimension-card { border: 2px solid var(--border-color); border-radius: 0.5rem; padding: 1.25rem; cursor: pointer; transition: all 0.2s ease-in-out; height: 100%; }
    .dimension-card:hover { border-color: var(--primary-color); background-color: #e0f2f1; }
    .dimension-card.selected { border-color: var(--secondary-color); background-color: var(--primary-color); color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .dimension-card.selected .dimension-icon, .dimension-card.selected .text-muted { color: white !important; }
    .dimension-card .dimension-icon { font-size: 2rem; color: var(--primary-color); transition: color 0.2s ease-in-out; }
    .dimension-card .form-check-input { display: none; }
    
    /* <!-- TAMBAHAN: Style untuk select multiple agar lebih jelas --> */
    select[multiple] {
        height: auto;
        min-height: 150px; /* Atur tinggi minimal agar terlihat seperti box */
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Kegiatan Kokurikuler</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk: <strong><?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?></strong></p>
            </div>
            <a href="kokurikuler_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <form action="kokurikuler_aksi.php?aksi=update" method="POST">
            <input type="hidden" name="id_kegiatan" value="<?php echo $id_kegiatan; ?>">
            <div class="card-body p-4 p-md-5">
                <div class="row g-5">
                    <div class="col-lg-5">
                        <h4 class="mb-4"><span class="badge bg-primary me-2">1</span>Detail Kegiatan</h4>
                        <div class="mb-4">
                            <label for="tema_kegiatan" class="form-label fs-5 fw-bold">Tema Kegiatan</label>
                            <input type="text" class="form-control form-control-lg" id="tema_kegiatan" name="tema_kegiatan" value="<?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="semester" class="form-label fw-bold">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="1" <?php if($kegiatan['semester'] == 1) echo 'selected'; ?>>Ganjil</option>
                                <option value="2" <?php if($kegiatan['semester'] == 2) echo 'selected'; ?>>Genap</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="bentuk_kegiatan" class="form-label fw-bold">Bentuk Kegiatan (Model)</label>
                            <select class="form-select" id="bentuk_kegiatan" name="bentuk_kegiatan" required>
                                <option value="Lintas Disiplin" <?php if($kegiatan['bentuk_kegiatan'] == 'Lintas Disiplin') echo 'selected'; ?>>Pembelajaran Lintas Disiplin</option>
                                <option value="G7KAIH" <?php if($kegiatan['bentuk_kegiatan'] == 'G7KAIH') echo 'selected'; ?>>Gerakan 7KAIH</option>
                                <option value="Cara Lainnya" <?php if($kegiatan['bentuk_kegiatan'] == 'Cara Lainnya') echo 'selected'; ?>>Cara Lainnya (Khas Sekolah)</option>
                            </select>
                        </div>

                        <!-- ==================================================== -->
                        <!-- TAMBAHAN: Form Koordinator (dengan data terpilih) -->
                        <!-- ==================================================== -->
                        <div class="mb-3">
                            <label for="id_koordinator" class="form-label fw-bold">Pilih Koordinator Projek</label>
                            <select class="form-select" id="id_koordinator" name="id_koordinator" required>
                                <option value="">-- Pilih Guru Koordinator --</option>
                                <?php foreach($daftar_guru as $guru): ?>
                                    <?php $selected = ($guru['id_guru'] == $kegiatan['id_koordinator']) ? 'selected' : ''; ?>
                                    <option value="<?php echo $guru['id_guru']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ==================================================== -->
                        <!-- TAMBAHAN: Form Mapel Terlibat (dengan data terpilih) -->
                        <!-- ==================================================== -->
                        <div class="mb-3">
                            <label for="mapel_terlibat" class="form-label fw-bold">Pilih Mapel yang Berperan</label>
                            <select class="form-select" id="mapel_terlibat" name="mapel_terlibat[]" multiple required>
                                <?php foreach($daftar_mapel as $mapel): ?>
                                    <?php $selected = (in_array($mapel['id_mapel'], $mapel_terpilih_ids)) ? 'selected' : ''; ?>
                                    <option value="<?php echo $mapel['id_mapel']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Tahan Ctrl (atau Cmd di Mac) untuk memilih lebih dari satu mapel.</div>
                        </div>

                    </div>

                    <div class="col-lg-7">
                        <h4 class="mb-4"><span class="badge bg-primary me-2">2</span>Pilih Ulang Dimensi yang Dikuatkan</h4>
                        <div class="row g-3">
                            <?php foreach($dimensi_lulusan as $nama_dimensi => $detail): 
                                $id_dimensi = 'dimensi_' . str_replace(' ', '', $nama_dimensi);
                                $is_selected = in_array($nama_dimensi, $dimensi_terpilih);
                            ?>
                            <div class="col-md-6">
                                <div class="dimension-card <?php if($is_selected) echo 'selected'; ?>" onclick="toggleDimension(this, '<?php echo $id_dimensi; ?>')">
                                    <input class="form-check-input" type="checkbox" name="dimensi[]" value="<?php echo $nama_dimensi; ?>" id="<?php echo $id_dimensi; ?>" <?php if($is_selected) echo 'checked'; ?>>
                                    <div class="d-flex">
                                        <div class="dimension-icon me-3"><i class="bi <?php echo $detail['icon']; ?>"></i></div>
                                        <div>
                                            <label class="form-check-label fw-bold" for="<?php echo $id_dimensi; ?>"><?php echo $nama_dimensi; ?></label>
                                            <p class="small text-muted mb-0"><?php echo $detail['desc']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end bg-light p-3">
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDimension(cardElement, checkboxId) {
    const checkbox = document.getElementById(checkboxId);
    checkbox.checked = !checkbox.checked;
    cardElement.classList.toggle('selected', checkbox.checked);
}
</script>

<?php include 'footer.php'; ?>