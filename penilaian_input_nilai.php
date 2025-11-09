<?php
include 'koneksi.php';
include 'header.php';

// Validasi peran
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Guru dan Admin.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_penilaian = isset($_GET['id_penilaian']) ? (int)$_GET['id_penilaian'] : 0;
if ($id_penilaian == 0) {
    echo "<script>Swal.fire('Error','Penilaian tidak ditemukan.','error').then(() => history.back());</script>";
    include 'footer.php';
    exit;
}

// Ambil detail penilaian, kelas, dan mapel
$query_penilaian = "SELECT p.*, k.nama_kelas, m.nama_mapel FROM penilaian p
                    JOIN kelas k ON p.id_kelas = k.id_kelas
                    JOIN mata_pelajaran m ON p.id_mapel = m.id_mapel
                    WHERE p.id_penilaian = ?";
$stmt = mysqli_prepare($koneksi, $query_penilaian);
mysqli_stmt_bind_param($stmt, "i", $id_penilaian);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$penilaian = mysqli_fetch_assoc($result);

if (!$penilaian) {
    echo "<script>Swal.fire('Error','Data Penilaian tidak ditemukan.','error').then(() => history.back());</script>";
    include 'footer.php';
    exit;
}

// === BAGIAN BARU: Ambil data Tujuan Pembelajaran (TP) yang terkait ===
$query_tp = mysqli_prepare($koneksi, "
    SELECT tp.deskripsi_tp 
    FROM tujuan_pembelajaran tp
    JOIN penilaian_tp pt ON tp.id_tp = pt.id_tp
    WHERE pt.id_penilaian = ?
");
mysqli_stmt_bind_param($query_tp, "i", $id_penilaian);
mysqli_stmt_execute($query_tp);
$result_tp = mysqli_stmt_get_result($query_tp);
$tujuan_pembelajaran = [];
while ($row = mysqli_fetch_assoc($result_tp)) {
    $tujuan_pembelajaran[] = $row['deskripsi_tp'];
}
// ===================================================================

// Ambil siswa dari kelas terkait
$query_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = {$penilaian['id_kelas']} AND status_siswa = 'Aktif' ORDER BY nama_lengkap ASC");

// Ambil nilai yang sudah ada
$query_nilai = mysqli_prepare($koneksi, "SELECT id_siswa, nilai FROM penilaian_detail_nilai WHERE id_penilaian = ?");
mysqli_stmt_bind_param($query_nilai, "i", $id_penilaian);
mysqli_stmt_execute($query_nilai);
$result_nilai = mysqli_stmt_get_result($query_nilai);
$nilai_tersimpan = [];
while ($n = mysqli_fetch_assoc($result_nilai)) {
    $nilai_tersimpan[$n['id_siswa']] = $n['nilai'];
}

// =============================================
// === PERUBAHAN BARU: AMBIL KKM DARI DATABASE ===
// =============================================
$q_kkm_input = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm_input_db = mysqli_fetch_assoc($q_kkm_input);
$kkm = $kkm_input_db ? (int)$kkm_input_db['nilai_pengaturan'] : 75; // Default ke 75 jika KKM tidak ada di DB
// =============================================
// === AKHIR PERUBAHAN ===
// =============================================
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
    .info-item .label { opacity: 0.75; font-size: 0.9rem; }
    .info-item .value { font-weight: 600; font-size: 1.1rem; }
    .table .form-control { text-align: center; font-weight: bold; transition: background-color 0.3s ease; }
    .nilai-kosong { background-color: #e9ecef; color: #495057; }
    .nilai-kurang { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .nilai-cukup { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    /* Style untuk daftar TP */
    .tp-list { list-style-type: none; padding-left: 0; }
    .tp-list li { margin-bottom: 0.5rem; }
    .tp-list li::before { content: "\F28A"; font-family: 'Bootstrap-icons'; vertical-align: middle; margin-right: 8px; color: var(--primary-color); }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">Input Nilai Siswa</h1>
                <p class="lead mb-0 opacity-75"><?php echo htmlspecialchars($penilaian['nama_penilaian']); ?></p>
            </div>
            <div class="mt-3 mt-sm-0 d-flex flex-wrap gap-2">
                <a href="penilaian_excel_template.php?id_penilaian=<?php echo $id_penilaian; ?>" class="btn btn-light"><i class="bi bi-download me-2"></i>Download Template</a>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalImport"><i class="bi bi-upload me-2"></i>Import Nilai</button>
                <a href="penilaian_tampil.php?id_kelas=<?php echo $penilaian['id_kelas']; ?>&id_mapel=<?php echo $penilaian['id_mapel']; ?>" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
            </div>
        </div>
        <hr>
        <div class="info-grid mt-3">
            <div class="info-item"><div class="label">Kelas</div><div class="value"><?php echo htmlspecialchars($penilaian['nama_kelas']); ?></div></div>
            <div class="info-item"><div class="label">Mata Pelajaran</div><div class="value"><?php echo htmlspecialchars($penilaian['nama_mapel']); ?></div></div>
            <div class="info-item"><div class="label">Jenis Penilaian</div><div class="value"><span class="badge fs-6 <?php echo $penilaian['jenis_penilaian'] == 'Sumatif' ? 'bg-success' : 'bg-info'; ?>"><?php echo $penilaian['jenis_penilaian']; ?></span></div></div>
            <?php if ($penilaian['jenis_penilaian'] == 'Sumatif'): ?>
                <div class="info-item"><div class="label">Sub-Jenis</div><div class="value"><span class="badge fs-6 bg-dark"><?php echo htmlspecialchars($penilaian['subjenis_penilaian']); ?></span></div></div>
                <div class="info-item"><div class="label">Bobot</div><div class="value"><span class="badge fs-6 bg-secondary"><?php echo $penilaian['bobot_penilaian']; ?></span></div></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($tujuan_pembelajaran)): ?>
            <div class="mt-3">
                <div class="label">Tujuan Pembelajaran yang Dinilai:</div>
                <ul class="tp-list mt-2">
                    <?php foreach ($tujuan_pembelajaran as $tp): ?>
                        <li><?php echo htmlspecialchars($tp); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        </div>

    <div class="modal fade" id="modalImport" tabindex="-1" aria-labelledby="modalImportLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="modalImportLabel">Import Nilai dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form action="penilaian_aksi.php?aksi=import_nilai" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id_penilaian" value="<?php echo $id_penilaian; ?>">
                    <p>Pilih file template Excel yang sudah diisi nilai siswa. Pastikan format sesuai dengan template yang diunduh.</p>
                    <div class="mb-3">
                        <label for="file_excel" class="form-label">File Excel (.xlsx)</label>
                        <input class="form-control" type="file" name="file_excel" id="file_excel" accept=".xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Unggah dan Proses</button>
                </div>
              </form>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-pencil-fill me-2" style="color: var(--primary-color);"></i>Daftar Siswa & Input Nilai</h5>
            <span class="badge bg-info">Batas Tuntas (KKM): <?php echo $kkm; ?></span>
        </div>
        <form action="penilaian_aksi.php?aksi=simpan_nilai" method="POST">
            <input type="hidden" name="id_penilaian" value="<?php echo $id_penilaian; ?>">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 5%;">No</th>
                                <th>Nama Siswa</th>
                                <th class="text-center" style="width: 20%;">Nilai (0-100)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($siswa = mysqli_fetch_assoc($query_siswa)) : ?>
                                <tr>
                                    <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <td>
                                        <input type="number" name="nilai[<?php echo $siswa['id_siswa']; ?>]" class="form-control grade-input" value="<?php echo $nilai_tersimpan[$siswa['id_siswa']] ?? ''; ?>" min="0" max="100" placeholder="0-100">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                 <a href="penilaian_tampil.php?id_kelas=<?php echo $penilaian['id_kelas']; ?>&id_mapel=<?php echo $penilaian['id_mapel']; ?>" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Semua Nilai</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // === PERUBAHAN BARU: AMBIL KKM DARI PHP ===
    // =============================================
    const KKM_BATAS = <?php echo $kkm; ?>;
    // =============================================

    const enterInputs = document.querySelectorAll('.grade-input');
    enterInputs.forEach((input, index) => {
        input.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const nextIndex = index + 1;
                if (nextIndex < enterInputs.length) {
                    enterInputs[nextIndex].focus();
                    enterInputs[nextIndex].select();
                } else {
                    document.querySelector('button[type="submit"]').focus();
                }
            }
        });
    });

    const gradeInputs = document.querySelectorAll('.grade-input');
    function updateInputColor(input) {
        const value = parseFloat(input.value);
        input.classList.remove('nilai-kosong', 'nilai-kurang', 'nilai-cukup');
        if (input.value === '' || isNaN(value)) {
            input.classList.add('nilai-kosong');
        } else if (value < KKM_BATAS) { // Menggunakan KKM Dinamis
            input.classList.add('nilai-kurang');
        } else if (value >= KKM_BATAS) { // Menggunakan KKM Dinamis
            input.classList.add('nilai-cukup');
        }
    }
    gradeInputs.forEach(input => {
        updateInputColor(input);
        input.addEventListener('input', function() {
            updateInputColor(this);
        });
    });
});
</script>

<?php include 'footer.php'; ?>

