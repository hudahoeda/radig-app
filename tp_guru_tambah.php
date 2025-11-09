<?php
include 'koneksi.php';
include 'header.php';

// Validasi peran, hanya guru yang bisa mengakses
if ($_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_guru_login = $_SESSION['id_guru'];

// Ambil ID tahun ajaran yang aktif dari database
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta_aktif = mysqli_fetch_assoc($q_ta_aktif);
$id_tahun_ajaran_aktif = $d_ta_aktif['id_tahun_ajaran'] ?? 0;
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }

    .info-card {
        background-color: #e0f2f1; /* Light Teal background */
        border-left: 5px solid var(--primary-color);
        border-radius: 0.5rem;
    }
    .info-card .info-header {
        font-weight: 600;
        color: var(--secondary-color);
    }
    .info-card ul {
        padding-left: 1.2rem;
        font-size: 0.9rem;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Tambah Tujuan Pembelajaran</h1>
                <p class="lead mb-0 opacity-75">Buat entri TP baru untuk mata pelajaran yang Anda ampu.</p>
            </div>
            <a href="tp_guru_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali ke Daftar TP</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <form action="tp_guru_aksi.php?aksi=tambah" method="POST">
                <input type="hidden" name="id_tahun_ajaran" value="<?php echo $id_tahun_ajaran_aktif; ?>">
                <div class="row g-5">
                    
                    <div class="col-lg-7">
                        <h4 class="mb-4">Detail Tujuan Pembelajaran</h4>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="id_mapel" class="form-label fw-bold">Mata Pelajaran</label>
                                <select class="form-select" id="id_mapel" name="id_mapel" required>
                                    <option value="" disabled selected>-- Pilih Mata Pelajaran yang Anda Ampu --</option>
                                    <?php
                                    $query_mapel = "SELECT DISTINCT m.id_mapel, m.nama_mapel FROM guru_mengajar gm 
                                                    JOIN mata_pelajaran m ON gm.id_mapel = m.id_mapel
                                                    WHERE gm.id_guru = ? AND gm.id_tahun_ajaran = ?";
                                    $stmt_mapel = mysqli_prepare($koneksi, $query_mapel);
                                    mysqli_stmt_bind_param($stmt_mapel, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
                                    mysqli_stmt_execute($stmt_mapel);
                                    $result_mapel = mysqli_stmt_get_result($stmt_mapel);
                                    while ($mapel = mysqli_fetch_assoc($result_mapel)) {
                                        echo "<option value='{$mapel['id_mapel']}'>" . htmlspecialchars($mapel['nama_mapel']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label for="deskripsi_tp" class="form-label fw-bold">Deskripsi Lengkap TP</label>
                                <textarea class="form-control" id="deskripsi_tp" name="deskripsi_tp" rows="4" placeholder="Contoh: Peserta didik dapat menulis gagasan, pikiran, pandangan, arahan atau pesan tertulis untuk berbagai tujuan secara logis, kritis, dan kreatif." required></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="kode_tp" class="form-label fw-bold">Kode TP <span class="text-muted fw-normal">(Opsional)</span></label>
                                <input type="text" class="form-control" id="kode_tp" name="kode_tp" placeholder="Contoh: B.IND.7.1">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="semester" class="form-label fw-bold">Semester</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="1">Ganjil</option>
                                    <option value="2">Genap</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="info-card p-4">
                            <h5 class="info-header mb-3"><i class="bi bi-info-circle-fill me-2"></i>Informasi & Petunjuk</h5>
                            
                            <div class="mb-3">
                                <span class="d-block text-muted small">Tahun Ajaran Aktif</span>
                                <p class="fw-bold fs-5 mb-0"><?php echo htmlspecialchars($d_ta_aktif['tahun_ajaran']); ?></p>
                            </div>

                            <hr>

                            <p class="text-muted small mb-2 fw-bold">Tips Pengisian Deskripsi:</p>
                            <ul class="text-muted">
                                <li>Awali deskripsi dengan frasa seperti "Peserta didik dapat..." atau "Siswa mampu...".</li>
                                <li>Fokus pada satu kompetensi spesifik per TP.</li>
                                <li>Gunakan bahasa yang jelas dan terukur.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Tujuan Pembelajaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>