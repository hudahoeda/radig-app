<?php
// Set variabel halaman untuk Active State di Sidebar
$page = 'admin_laporan';
$title = 'Detail Laporan Kelas';

include 'header.php';
include 'koneksi.php';

// ==========================================================
// 1. VALIDASI AKSES & DATA AWAL
// ==========================================================
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran_aktif = $d_ta['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_aktif = $d_ta['tahun_ajaran'] ?? '-';

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil ID Kelas dari URL
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0 && $id_tahun_ajaran_aktif > 0) {
    $q_first_class = mysqli_query($koneksi, "SELECT id_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas LIMIT 1");
    if ($d_first_class = mysqli_fetch_assoc($q_first_class)) {
        $id_kelas = $d_first_class['id_kelas'];
    }
}

// Ambil semua kelas untuk dropdown (Filter TA Aktif)
$query_semua_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas ASC");

// Inisialisasi variabel detail kelas
$nama_walikelas = 'Belum Ditentukan';
$foto_walikelas = '';
$nip_walikelas = '-';
$siswa_list = [];
$rapor_final_count = 0;
$total_siswa = 0;

if ($id_kelas > 0) {
    // Ambil detail kelas & wali (Termasuk NIP)
    $stmt_kelas = mysqli_prepare($koneksi, "SELECT k.nama_kelas, g.nama_guru, g.foto_guru, g.nip FROM kelas k LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru WHERE k.id_kelas = ?");
    mysqli_stmt_bind_param($stmt_kelas, "i", $id_kelas);
    mysqli_stmt_execute($stmt_kelas);
    $data_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kelas));
    $nama_kelas_terpilih = $data_kelas['nama_kelas'] ?? '-';
    $nama_walikelas = $data_kelas['nama_guru'] ?? 'Belum Ditentukan';
    $foto_walikelas = $data_kelas['foto_guru'] ?? '';
    $nip_walikelas = $data_kelas['nip'] ?? '-';

    // Ambil daftar siswa
    $query_siswa = "
        SELECT 
            s.id_siswa, s.nisn, s.nama_lengkap, s.foto_siswa,
            r.status AS status_rapor 
        FROM siswa s
        LEFT JOIN rapor r ON s.id_siswa = r.id_siswa 
            AND r.id_tahun_ajaran = ? AND r.semester = ?
        WHERE s.id_kelas = ? AND s.status_siswa = 'Aktif' 
        ORDER BY s.nama_lengkap ASC
    ";
    $stmt_siswa = mysqli_prepare($koneksi, $query_siswa);
    mysqli_stmt_bind_param($stmt_siswa, "iii", $id_tahun_ajaran_aktif, $semester_aktif, $id_kelas);
    mysqli_stmt_execute($stmt_siswa);
    $result_siswa = mysqli_stmt_get_result($stmt_siswa);
    
    while($row = mysqli_fetch_assoc($result_siswa)){
        $siswa_list[] = $row;
        if(($row['status_rapor'] ?? '') == 'Final'){
            $rapor_final_count++;
        }
    }
    $total_siswa = count($siswa_list);
}

$persentase_kelas = ($total_siswa > 0) ? round(($rapor_final_count / $total_siswa) * 100) : 0;
?>

<style>
    /* --- TEAL THEME OVERRIDES --- */
    :root {
        --teal-primary: #009688;
        --teal-dark: #00796b;
        --teal-light: #b2dfdb;
        --accent-orange: #f97316;
    }

    .page-header {
        background: linear-gradient(135deg, var(--teal-primary), var(--teal-dark));
        padding: 2.5rem 2rem; 
        border-radius: 0.75rem; 
        color: white;
    }
    .page-header h1 { font-weight: 700; }

    /* Override Bootstrap */
    .text-primary { color: var(--teal-primary) !important; }
    .bg-primary { background-color: var(--teal-primary) !important; }
    .btn-primary { 
        background-color: var(--teal-primary) !important; 
        border-color: var(--teal-primary) !important; 
    }
    .btn-primary:hover {
        background-color: var(--teal-dark) !important; 
    }
    .btn-outline-primary {
        color: var(--teal-primary) !important;
        border-color: var(--teal-primary) !important;
    }
    .btn-outline-primary:hover {
        background-color: var(--teal-primary) !important;
        color: white !important;
    }
    .bg-teal-subtle {
        background-color: rgba(0, 150, 136, 0.1) !important;
        color: var(--teal-primary) !important;
    }

    /* Table & UI Extras */
    .table-students img { 
        width: 42px; height: 42px; 
        object-fit: cover; border-radius: 50%; 
        border: 2px solid #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .progress-sm { height: 8px; border-radius: 10px; }
    
    .filter-card {
        border: none;
        border-radius: 12px;
        background: #fff;
    }

    .sticky-action-footer {
        position: sticky;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-top: 1px solid #eee;
        z-index: 10;
        padding: 1rem;
    }
</style>

<div class="container-fluid">
    <!-- PAGE HEADER -->
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="mb-1">Detail Laporan: <?php echo htmlspecialchars($nama_kelas_terpilih ?? 'Kelas'); ?></h1>
                <p class="lead mb-0 opacity-75">Monitoring kelengkapan & cetak massal dokumen rapor.</p>
            </div>
            <a href="admin_laporan_kelas.php" class="btn btn-light text-primary fw-bold shadow-sm">
                <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- SELEKSI KELAS & INFO WALI -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-muted text-uppercase small mb-3">Opsi Tampilan</h6>
                    <form action="" method="GET" id="formPilihKelas">
                        <label for="id_kelas" class="form-label small fw-bold">Pilih Kelas Lain:</label>
                        <select name="id_kelas" id="id_kelas" class="form-select mb-4 shadow-sm" onchange="this.form.submit();">
                            <?php if(mysqli_num_rows($query_semua_kelas) > 0) mysqli_data_seek($query_semua_kelas, 0); ?>
                            <?php while($kelas_item = mysqli_fetch_assoc($query_semua_kelas)): ?>
                                <option value="<?php echo $kelas_item['id_kelas']; ?>" <?php if($id_kelas == $kelas_item['id_kelas']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($kelas_item['nama_kelas']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </form>

                    <hr class="my-4 opacity-25">

                    <h6 class="fw-bold text-muted text-uppercase small mb-3">Informasi Wali Kelas</h6>
                    <div class="d-flex align-items-center p-3 bg-light rounded-3">
                        <?php 
                        $path_foto_wali = 'uploads/guru_photos/' . $foto_walikelas;
                        if (!empty($foto_walikelas) && file_exists($path_foto_wali)): 
                        ?>
                            <img src="<?php echo $path_foto_wali; ?>" alt="Foto Wali" class="rounded-circle shadow-sm me-3" style="width: 55px; height: 55px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-teal-subtle rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 55px; height: 55px;">
                                <i class="bi bi-person-fill fs-3"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($nama_walikelas); ?></div>
                            <small class="text-muted">NIP. <?php echo htmlspecialchars($nip_walikelas); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STATISTIK PROGRES KELAS -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <h6 class="fw-bold text-muted text-uppercase small mb-2">Progres Finalisasi Rapor Kelas</h6>
                            <h2 class="fw-bold text-primary mb-3"><?php echo $persentase_kelas; ?>% <small class="text-muted fs-6">Selesai</small></h2>
                            <div class="progress progress-sm mb-3">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $persentase_kelas; ?>%"></div>
                            </div>
                            <p class="mb-0 text-muted small">
                                <i class="bi bi-info-circle me-1"></i> Sebanyak <b><?php echo $rapor_final_count; ?></b> dari <b><?php echo $total_siswa; ?></b> siswa telah memiliki rapor berstatus <b>Final</b>.
                            </p>
                        </div>
                        <div class="col-md-5 text-center d-none d-md-block">
                            <div class="bg-teal-subtle p-4 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <i class="bi bi-file-earmark-check display-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL DAFTAR SISWA -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill me-2 text-primary"></i>Daftar Siswa & Kelengkapan</h5>
            <div class="text-muted small">Semester <?php echo $semester_aktif; ?> | TA <?php echo $tahun_ajaran_aktif; ?></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-students mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3" style="width: 35%;">Siswa</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">
                                Sampul<br>
                                <input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'sampul')">
                            </th>
                            <th class="text-center">
                                Identitas<br>
                                <input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'identitas')">
                            </th>
                            <th class="text-center">
                                Rapor<br>
                                <input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'rapor')">
                            </th>
                            <th class="text-center pe-4" style="width: 15%;">Aksi Individu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_list)): ?>
                            <?php foreach ($siswa_list as $siswa):
                                $status_rapor = $siswa['status_rapor'] ?? 'Draft';
                                $badge_class = 'bg-warning text-dark';
                                if ($status_rapor == 'Final') { $badge_class = 'bg-success'; }
                                
                                $path_foto_siswa = 'uploads/foto_siswa/' . $siswa['foto_siswa'];
                                $has_foto_siswa = !empty($siswa['foto_siswa']) && file_exists($path_foto_siswa);
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <?php if ($has_foto_siswa): ?>
                                            <img src="<?php echo $path_foto_siswa; ?>" alt="Foto" class="me-3">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light border me-3 d-flex align-items-center justify-content-center text-muted" style="width: 42px; height: 42px;">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                            <small class="text-muted">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $badge_class; ?> rounded-pill px-3 shadow-sm"><?php echo $status_rapor; ?></span>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input check-sampul" name="check_sampul[]" value="<?php echo $siswa['id_siswa']; ?>">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input check-identitas" name="check_identitas[]" value="<?php echo $siswa['id_siswa']; ?>">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input check-rapor" name="check_rapor[]" value="<?php echo $siswa['id_siswa']; ?>" <?php echo ($status_rapor != 'Final') ? 'disabled' : ''; ?>>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="btn-group shadow-sm">
                                        <a href="rapor_cover.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" data-bs-toggle="tooltip" title="Cetak Sampul"><i class="bi bi-book"></i></a>
                                        <a href="rapor_identitas_siswa.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" data-bs-toggle="tooltip" title="Cetak Identitas"><i class="bi bi-person-badge"></i></a>
                                        <a href="rapor_pdf.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-primary <?php echo ($status_rapor != 'Final') ? 'disabled' : ''; ?>" target="_blank" data-bs-toggle="tooltip" title="Cetak Rapor"><i class="bi bi-file-pdf-fill"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-emoji-neutral display-4 text-muted mb-3 d-block"></i>
                                    <h6 class="text-muted">Tidak ada data siswa ditemukan untuk kelas ini.</h6>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- TOMBOL CETAK MASSAL (STICKY FOOTER) -->
        <?php if (!empty($siswa_list)): ?>
        <div class="sticky-action-footer text-center shadow-sm">
            <div class="d-flex justify-content-center flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold" onclick="prosesCetakMassal('sampul')">
                    <i class="bi bi-book me-2"></i>Cetak Sampul Terpilih
                </button>
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold" onclick="prosesCetakMassal('identitas')">
                    <i class="bi bi-person-badge me-2"></i>Cetak Identitas Terpilih
                </button>
                <button type="button" class="btn btn-primary px-5 fw-bold" onclick="prosesCetakMassal('rapor')">
                    <i class="bi bi-printer-fill me-2"></i>Cetak Rapor Terpilih
                </button>
            </div>
            <div class="mt-2">
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i> Centang kolom pada tabel di atas untuk melakukan aksi cetak massal.</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
});

function toggleAll(source, type) {
    let checkboxes = document.querySelectorAll('.check-' + type);
    checkboxes.forEach(cb => {
        if (!cb.disabled) {
            cb.checked = source.checked;
        }
    });
}

function prosesCetakMassal(tipeCetak) {
    let listSiswaId = [];
    let checkboxes = document.querySelectorAll('.check-' + tipeCetak);
    checkboxes.forEach(cb => {
        if (cb.checked) { listSiswaId.push(cb.value); }
    });
    
    if (listSiswaId.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Oops...',
            text: 'Silakan pilih minimal satu siswa pada kolom yang sesuai.',
            confirmButtonColor: '#009688'
        });
        return;
    }
    
    let ids = listSiswaId.join(',');
    let url = `rapor_cetak_massal.php?tipe=${tipeCetak}&ids=${ids}`;
    window.open(url, '_blank');
}
</script>

<?php include 'footer.php'; ?>