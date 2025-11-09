<?php
include 'koneksi.php';
include 'header.php';

// Pastikan hanya guru yang bisa mengakses
if ($_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_tp = isset($_GET['id_tp']) ? (int)$_GET['id_tp'] : 0;
$id_guru = (int)$_SESSION['id_guru'];

// Ambil detail TP
$q_tp = mysqli_prepare($koneksi, "SELECT tp.*, m.nama_mapel FROM tujuan_pembelajaran tp JOIN mata_pelajaran m ON tp.id_mapel = m.id_mapel WHERE tp.id_tp = ? AND tp.id_guru_pembuat = ?");
mysqli_stmt_bind_param($q_tp, "ii", $id_tp, $id_guru);
mysqli_stmt_execute($q_tp);
$result_tp = mysqli_stmt_get_result($q_tp);
$tp = mysqli_fetch_assoc($result_tp);

if (!$tp) {
    echo "<script>Swal.fire('Error','TP tidak ditemukan atau Anda tidak berhak mengaksesnya.','error').then(() => window.location = 'tp_guru_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// === BAGIAN YANG DIMODIFIKASI: Mengambil dan Mengelompokkan Kelas ===
$q_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif') ORDER BY nama_kelas ASC");

$kelas_dikelompokkan = [];
if ($q_kelas) {
    while ($kelas = mysqli_fetch_assoc($q_kelas)) {
        // Ekstrak angka jenjang dari nama kelas (misal: "VII A" -> "7", "10.1" -> "10")
        preg_match('/^(\d+|[XVI]+)/', $kelas['nama_kelas'], $matches);
        $jenjang = 'Lainnya'; // Default group
        if (!empty($matches)) {
            $jenjang_raw = $matches[1];
            // Konversi romawi ke angka jika perlu (sederhana)
            $romawi = ['X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7];
            if (isset($romawi[$jenjang_raw])) {
                $jenjang = 'Kelas ' . $romawi[$jenjang_raw];
            } else {
                $jenjang = 'Kelas ' . $jenjang_raw;
            }
        }
        $kelas_dikelompokkan[$jenjang][] = $kelas;
    }
}
ksort($kelas_dikelompokkan); // Urutkan berdasarkan jenjang (Kelas 7, Kelas 8, dst.)
// ======================================================================

// Ambil kelas yang sudah dipilih
$q_kelas_terpilih = mysqli_query($koneksi, "SELECT id_kelas FROM tp_kelas WHERE id_tp = $id_tp");
$kelas_terpilih = [];
while($row = mysqli_fetch_assoc($q_kelas_terpilih)){
    $kelas_terpilih[] = $row['id_kelas'];
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
    .info-item .label { opacity: 0.75; font-size: 0.9rem; }
    .info-item .value { font-weight: 600; font-size: 1.1rem; }
    .form-check-lg.form-switch { padding-left: 3.5rem; min-height: 2.5rem; }
    .form-check-lg.form-switch .form-check-input { width: 3rem; height: 1.5rem; }
    .form-check-lg.form-switch .form-check-label { font-size: 1.1rem; padding-top: 0.25rem; }
    
    /* === BAGIAN BARU: Style untuk Pengelompokan Kelas === */
    .jenjang-group {
        border-bottom: 2px solid #eee;
        padding-bottom: 1rem;
        margin-bottom: 1.5rem;
    }
    .jenjang-group:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    .jenjang-title {
        font-weight: 600;
        font-size: 1.25rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }
    /* ======================================================= */
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-2">Tugaskan Tujuan Pembelajaran</h1>
                <p class="lead mb-0 opacity-75 fst-italic">"<?php echo htmlspecialchars($tp['deskripsi_tp']); ?>"</p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="tp_guru_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
            </div>
        </div>
        <hr>
        <div class="info-grid mt-3">
            <div class="info-item"><div class="label">Kode TP</div><div class="value"><span class="badge fs-6 bg-light text-dark"><?php echo htmlspecialchars($tp['kode_tp']); ?></span></div></div>
            <div class="info-item"><div class="label">Mata Pelajaran</div><div class="value"><?php echo htmlspecialchars($tp['nama_mapel']); ?></div></div>
            <div class="info-item"><div class="label">Semester</div><div class="value"><?php echo htmlspecialchars($tp['semester']); ?></div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-check2-square me-2" style="color: var(--primary-color);"></i>Pilih Kelas Penugasan</h5>
        </div>
        <form action="tp_guru_aksi.php?aksi=tugaskan_tp" method="POST">
            <input type="hidden" name="id_tp" value="<?php echo $id_tp; ?>">
            <div class="card-body p-4">
                
                <?php if(!empty($kelas_dikelompokkan)): ?>
                    <?php foreach ($kelas_dikelompokkan as $jenjang => $daftar_kelas): ?>
                        <div class="jenjang-group">
                            <h6 class="jenjang-title"><?php echo $jenjang; ?></h6>
                            <div class="row">
                                <?php foreach ($daftar_kelas as $kelas): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-check form-switch form-check-lg my-2">
                                            <input class="form-check-input" type="checkbox" role="switch" name="id_kelas[]" 
                                                   value="<?php echo $kelas['id_kelas']; ?>" 
                                                   id="kelas_<?php echo $kelas['id_kelas']; ?>"
                                                   <?php if(in_array($kelas['id_kelas'], $kelas_terpilih)) echo 'checked'; ?>>
                                            <label class="form-check-label" for="kelas_<?php echo $kelas['id_kelas']; ?>">
                                                <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-warning text-center">Tidak ada data kelas yang tersedia untuk tahun ajaran aktif.</div>
                <?php endif; ?>
                </div>
            <div class="card-footer text-end">
                <a href="tp_guru_tampil.php" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Penugasan</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>