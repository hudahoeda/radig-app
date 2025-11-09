<?php
include 'header.php';
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

// Data dimensi profil, kini dengan ikon dan deskripsi singkat
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

// Ambil data guru untuk dropdown koordinator
$query_guru = "SELECT id_guru, nama_guru FROM guru WHERE role IN ('guru', 'admin') ORDER BY nama_guru ASC";
$result_guru = mysqli_query($koneksi, $query_guru);
$daftar_guru = mysqli_fetch_all($result_guru, MYSQLI_ASSOC);

// Ambil data mapel untuk dropdown multiselect
$query_mapel = "SELECT id_mapel, nama_mapel FROM mata_pelajaran ORDER BY urutan, nama_mapel ASC";
$result_mapel = mysqli_query($koneksi, $query_mapel);
$daftar_mapel = mysqli_fetch_all($result_mapel, MYSQLI_ASSOC);

?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }

    /* Gaya untuk kartu pilihan dimensi */
    .dimension-card {
        border: 2px solid var(--border-color);
        border-radius: 0.5rem;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        height: 100%;
    }
    .dimension-card:hover {
        border-color: var(--primary-color);
        background-color: #e0f2f1; /* Light Teal */
    }
    .dimension-card.selected {
        border-color: var(--secondary-color);
        background-color: #b2dfdb; /* Medium Teal */
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .dimension-card .dimension-icon {
        font-size: 2rem;
        color: var(--primary-color);
    }
    .dimension-card .form-check-input {
        display: none; /* Sembunyikan checkbox asli */
    }
    
    /* [BARU] Style untuk kotak checklist mapel */
    .mapel-checklist-box {
        height: 200px; /* Atur tinggi kotak */
        overflow-y: auto; /* Tambahkan scrollbar vertikal jika perlu */
        border: 1px solid var(--border-color);
        padding: 1rem;
        border-radius: 0.375rem; /* Samakan dengan style Bootstrap */
        background-color: #fff;
    }
    .mapel-checklist-box .form-check {
        margin-bottom: 0.5rem; /* Beri jarak antar item */
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Rencanakan Kegiatan Kokurikuler</h1>
                <p class="lead mb-0 opacity-75">Buat kokurikuler baru untuk tahun ajaran aktif.</p>
            </div>
            <a href="kokurikuler_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <!-- [MODIFIKASI] Tambahkan ID pada form untuk JavaScript -->
        <form action="kokurikuler_aksi.php?aksi=tambah" method="POST" id="formKokurikuler">
            <div class="card-body p-4 p-md-5">
                <div class="row g-5">
                    <div class="col-lg-5">
                        <h4 class="mb-4"><span class="badge bg-primary me-2">1</span>Detail Kegiatan</h4>
                        <div class="mb-4">
                            <label for="tema_kegiatan" class="form-label fs-5 fw-bold">Tema Kegiatan</label>
                            <input type="text" class="form-control form-control-lg" id="tema_kegiatan" name="tema_kegiatan" placeholder="Contoh: Generasi Sehat dan Bugar" required>
                        </div>
                        <div class="mb-3">
                            <label for="semester" class="form-label fw-bold">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="1">Ganjil</option>
                                <option value="2">Genap</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="bentuk_kegiatan" class="form-label fw-bold">Bentuk Kegiatan (Model)</label>
                            <select class="form-select" id="bentuk_kegiatan" name="bentuk_kegiatan" required>
                                <option value="Lintas Disiplin">Pembelajaran Lintas Disiplin</option>
                                <option value="G7KAIH">Gerakan 7KAIH</option>
                                <option value="Cara Lainnya">Cara Lainnya (Khas Sekolah)</option>
                            </select>
                        </div>

                        <!-- Form Koordinator -->
                        <div class="mb-3">
                            <label for="id_koordinator" class="form-label fw-bold">Pilih Koordinator Projek</label>
                            <select class="form-select" id="id_koordinator" name="id_koordinator" required>
                                <option value="">-- Pilih Guru Koordinator --</option>
                                <?php foreach($daftar_guru as $guru): ?>
                                    <option value="<?php echo $guru['id_guru']; ?>"><?php echo htmlspecialchars($guru['nama_guru']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ==================================================== -->
                        <!-- [ROMBAK] Form Mapel Terlibat -->
                        <!-- ==================================================== -->
                        <div class="mb-3">
                            <label for="mapel_terlibat" class="form-label fw-bold">Pilih Mapel yang Berperan</label>
                            <!-- Kotak checklist baru -->
                            <div class="mapel-checklist-box" id="mapel_terlibat">
                                <?php if (empty($daftar_mapel)): ?>
                                    <p class="text-muted">Belum ada data mata pelajaran.</p>
                                <?php else: ?>
                                    <?php foreach($daftar_mapel as $mapel): 
                                        $id_mapel_check = 'mapel_' . $mapel['id_mapel'];
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="mapel_terlibat[]" value="<?php echo $mapel['id_mapel']; ?>" id="<?php echo $id_mapel_check; ?>">
                                        <label class="form-check-label" for="<?php echo $id_mapel_check; ?>">
                                            <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="form-text">Centang semua mata pelajaran yang terlibat dalam projek ini.</div>
                        </div>
                        <!-- ==================================================== -->
                        <!-- [AKHIR ROMBAK] -->
                        <!-- ==================================================== -->

                    </div>

                    <div class="col-lg-7">
                        <h4 class="mb-4"><span class="badge bg-primary me-2">2</span>Pilih Dimensi Profil Lulusan</h4>
                        <div class="row g-3">
                            <?php foreach($dimensi_lulusan as $nama_dimensi => $detail): 
                                $id_dimensi = 'dimensi_' . str_replace(' ', '', $nama_dimensi);
                            ?>
                            <div class="col-md-6">
                                <div class="dimension-card" onclick="toggleDimension(this, '<?php echo $id_dimensi; ?>')">
                                    <input class="form-check-input" type="checkbox" name="dimensi[]" value="<?php echo $nama_dimensi; ?>" id="<?php echo $id_dimensi; ?>">
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
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Rencana Kegiatan</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDimension(cardElement, checkboxId) {
    const checkbox = document.getElementById(checkboxId);
    // Ubah status checkbox
    checkbox.checked = !checkbox.checked;
    // Tambah atau hapus class 'selected' pada kartu
    cardElement.classList.toggle('selected', checkbox.checked);
}

// [BARU] Tambahkan validasi form pada saat submit
$(document).ready(function() {
    $('#formKokurikuler').on('submit', function(e) {
        
        // 1. Cek Dimensi
        if ($('input[name="dimensi[]"]:checked').length === 0) {
            e.preventDefault(); // Hentikan submit
            Swal.fire({
                icon: 'error',
                title: 'Belum Lengkap',
                text: 'Anda harus memilih setidaknya satu Dimensi Profil Lulusan!'
            });
            return false;
        }

        // 2. Cek Mapel Terlibat
        if ($('input[name="mapel_terlibat[]"]:checked').length === 0) {
            e.preventDefault(); // Hentikan submit
            Swal.fire({
                icon: 'error',
                title: 'Belim Lengkap',
                text: 'Anda harus memilih setidaknya satu Mata Pelajaran yang berperan!'
            });
            return false;
        }

        // Jika lolos semua, biarkan form disubmit
    });

    // [OPSIONAL] Inisialisasi Select2 untuk Koordinator (agar lebih mudah dicari)
    $('#id_koordinator').select2({
        theme: 'bootstrap-5'
    });
});
</script>

<?php include 'footer.php'; ?>