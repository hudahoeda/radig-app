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

// [BARU] Ambil Tahun Ajaran Aktif untuk memfilter kelas
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta);
$id_ta_aktif = $ta_aktif['id_tahun_ajaran'] ?? 0;

// [BARU] Ambil data Kelas aktif untuk checklist
$query_kelas = "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_ta_aktif ORDER BY nama_kelas ASC";
$result_kelas = mysqli_query($koneksi, $query_kelas);
$daftar_kelas = mysqli_fetch_all($result_kelas, MYSQLI_ASSOC);

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
    
    /* Style untuk kotak checklist mapel & kelas */
    .checklist-box {
        height: 180px; /* Atur tinggi kotak */
        overflow-y: auto; /* Tambahkan scrollbar vertikal */
        border: 1px solid var(--border-color);
        padding: 1rem;
        border-radius: 0.375rem;
        background-color: #fff;
    }
    .checklist-box .form-check {
        margin-bottom: 0.5rem; 
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Rencanakan Kegiatan Kokurikuler</h1>
                <p class="lead mb-0 opacity-75">Buat kokurikuler baru untuk kelas spesifik di tahun ajaran ini.</p>
            </div>
            <a href="kokurikuler_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <form action="kokurikuler_aksi.php?aksi=tambah" method="POST" id="formKokurikuler">
            <div class="card-body p-4 p-md-5">
                <div class="row g-5">
                    <div class="col-lg-5">
                        <h4 class="mb-4"><span class="badge bg-primary me-2">1</span>Detail Kegiatan</h4>
                        <div class="mb-3">
                            <label for="tema_kegiatan" class="form-label fs-5 fw-bold">Tema Kegiatan</label>
                            <input type="text" class="form-control form-control-lg" id="tema_kegiatan" name="tema_kegiatan" placeholder="Contoh: Generasi Sehat dan Bugar" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="semester" class="form-label fw-bold">Semester</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="1">Ganjil</option>
                                    <option value="2">Genap</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="bentuk_kegiatan" class="form-label fw-bold">Bentuk Kegiatan</label>
                                <select class="form-select" id="bentuk_kegiatan" name="bentuk_kegiatan" required>
                                    <option value="Lintas Disiplin">Lintas Disiplin</option>
                                    <option value="G7KAIH">Gerakan 7KAIH</option>
                                    <option value="Cara Lainnya">Khas Sekolah</option>
                                </select>
                            </div>
                        </div>

                        <!-- Form Koordinator -->
                        <div class="mb-3">
                            <label for="id_koordinator" class="form-label fw-bold">Koordinator Projek</label>
                            <select class="form-select" id="id_koordinator" name="id_koordinator" required>
                                <option value="">-- Pilih Guru Koordinator --</option>
                                <?php foreach($daftar_guru as $guru): ?>
                                    <option value="<?php echo $guru['id_guru']; ?>"><?php echo htmlspecialchars($guru['nama_guru']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- [BARU] Form Pilihan Kelas -->
                        <!-- Ini kunci solusinya: Memilih kelas mana saja untuk tema ini -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-success">Pilih Kelas Peserta <span class="text-danger">*</span></label>
                            <div class="checklist-box" id="kelas_sasaran">
                                <?php if (empty($daftar_kelas)): ?>
                                    <p class="text-danger small">Tidak ada kelas aktif di Tahun Ajaran ini.</p>
                                <?php else: ?>
                                    <!-- Tombol centang semua -->
                                    <div class="form-check border-bottom pb-2 mb-2">
                                        <input class="form-check-input" type="checkbox" id="checkAllKelas">
                                        <label class="form-check-label fw-bold" for="checkAllKelas">Pilih Semua Kelas</label>
                                    </div>
                                    <?php foreach($daftar_kelas as $kelas): 
                                        $id_kelas_check = 'kelas_' . $kelas['id_kelas'];
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input kelas-checkbox" type="checkbox" name="kelas_sasaran[]" value="<?php echo $kelas['id_kelas']; ?>" id="<?php echo $id_kelas_check; ?>">
                                        <label class="form-check-label" for="<?php echo $id_kelas_check; ?>">
                                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="form-text text-success">
                                <i class="bi bi-info-circle"></i> Centang kelas yang akan menggunakan tema ini. Jika kelas lain punya tema beda, buat kegiatan baru nanti.
                            </div>
                        </div>

                        <!-- Form Mapel Terlibat -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mapel Terlibat</label>
                            <div class="checklist-box">
                                <?php if (empty($daftar_mapel)): ?>
                                    <p class="text-muted">Belum ada data mapel.</p>
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
                        </div>

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
    checkbox.checked = !checkbox.checked;
    cardElement.classList.toggle('selected', checkbox.checked);
}

$(document).ready(function() {
    // Fitur Check All untuk Kelas
    $('#checkAllKelas').change(function() {
        $('.kelas-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Jika salah satu kelas di-uncheck, uncheck juga "Pilih Semua"
    $('.kelas-checkbox').change(function() {
        if(false == $(this).prop('checked')) {
            $('#checkAllKelas').prop('checked', false);
        }
        // Jika semua tercentang manual, centang "Pilih Semua"
        if ($('.kelas-checkbox:checked').length == $('.kelas-checkbox').length) {
            $('#checkAllKelas').prop('checked', true);
        }
    });

    $('#formKokurikuler').on('submit', function(e) {
        // 1. Cek Dimensi
        if ($('input[name="dimensi[]"]:checked').length === 0) {
            e.preventDefault(); 
            Swal.fire({icon: 'error', title: 'Belum Lengkap', text: 'Pilih minimal satu Dimensi Profil!'});
            return false;
        }

        // 2. Cek Mapel Terlibat
        if ($('input[name="mapel_terlibat[]"]:checked').length === 0) {
            e.preventDefault();
            Swal.fire({icon: 'error', title: 'Belum Lengkap', text: 'Pilih minimal satu Mata Pelajaran!'});
            return false;
        }

        // [BARU] 3. Cek Kelas Sasaran
        if ($('input[name="kelas_sasaran[]"]:checked').length === 0) {
            e.preventDefault();
            Swal.fire({icon: 'error', title: 'Belum Lengkap', text: 'Anda harus memilih minimal satu KELAS peserta untuk kegiatan ini!'});
            return false;
        }
    });

    // Inisialisasi Select2 jika tersedia
    if ($.fn.select2) {
        $('#id_koordinator').select2({ theme: 'bootstrap-5' });
    }
});
</script>

<?php include 'footer.php'; ?>