<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran_aktif = $d_ta['id_tahun_ajaran'] ?? 0;

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil ID Kelas dari URL, jika tidak ada, ambil kelas pertama
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0 && $id_tahun_ajaran_aktif > 0) {
    $q_first_class = mysqli_query($koneksi, "SELECT id_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas LIMIT 1");
    if ($d_first_class = mysqli_fetch_assoc($q_first_class)) {
        $id_kelas = $d_first_class['id_kelas'];
    }
}

// Ambil semua kelas untuk dropdown
$query_semua_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas ASC");

// Inisialisasi variabel
$nama_walikelas = 'N/A';
$siswa_list = [];
$rapor_final_count = 0;
$total_siswa = 0;

if ($id_kelas > 0) {
    // Ambil detail kelas terpilih untuk judul
    $stmt_kelas = mysqli_prepare($koneksi, "SELECT g.nama_guru FROM kelas k LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru WHERE k.id_kelas = ?");
    mysqli_stmt_bind_param($stmt_kelas, "i", $id_kelas);
    mysqli_stmt_execute($stmt_kelas);
    $data_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kelas));
    $nama_walikelas = $data_kelas['nama_guru'] ?? 'Belum Ditentukan';

    // Ambil daftar siswa dan status rapor mereka
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
        if($row['status_rapor'] == 'Final'){
            $rapor_final_count++;
        }
    }
    $total_siswa = count($siswa_list);
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }

    .info-bar {
        background-color: #f8f9fa;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
    }
    .table-students img { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Detail Laporan Kelas</h1>
                <p class="lead mb-0 opacity-75">Lihat status kelengkapan dan lakukan aksi cetak.</p>
            </div>
            <a href="admin_laporan_kelas.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <form action="" method="GET" id="formPilihKelas" class="d-flex align-items-center mb-2 mb-md-0">
                    <label for="id_kelas" class="form-label mb-0 me-2 fw-bold">Tampilkan Kelas:</label>
                    <select name="id_kelas" id="id_kelas" class="form-select" onchange="this.form.submit();" style="width: 250px;">
                        <?php if(mysqli_num_rows($query_semua_kelas) > 0) mysqli_data_seek($query_semua_kelas, 0); ?>
                        <?php while($kelas_item = mysqli_fetch_assoc($query_semua_kelas)): ?>
                            <option value="<?php echo $kelas_item['id_kelas']; ?>" <?php if($id_kelas == $kelas_item['id_kelas']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($kelas_item['nama_kelas']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
                <div class="d-flex align-items-center text-nowrap">
                    <div class="me-3"><small class="text-muted">Wali Kelas:</small><strong class="ms-1"><?php echo htmlspecialchars($nama_walikelas); ?></strong></div>
                    <div><small class="text-muted">Status:</small><strong class="ms-1"><?php echo $rapor_final_count; ?> / <?php echo $total_siswa; ?> Rapor Final</strong></div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-students mb-0">
                    <thead class="text-center table-light">
                        <tr>
                            <th class="text-start ps-3" style="width: 40%;">Nama Siswa</th>
                            <th>Status Rapor</th>
                            <th>Pilih Sampul<br><input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'sampul')"></th>
                            <th>Pilih Identitas<br><input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'identitas')"></th>
                            <th>Pilih Rapor<br><input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'rapor')"></th>
                            <th>Aksi Individu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_list)): ?>
                            <?php foreach ($siswa_list as $siswa):
                                $status_rapor = $siswa['status_rapor'] ?? 'Draft';
                                $badge_class = 'bg-warning text-dark';
                                $icon_class = 'bi-pencil-square';
                                if ($status_rapor == 'Final') { $badge_class = 'bg-success'; $icon_class = 'bi-check-circle-fill'; }
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($siswa['foto_siswa'])): ?>
                                            <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto" class="me-3">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle fs-2 me-3 text-muted"></i>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                            <small class="text-muted">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $badge_class; ?>"><i class="bi <?php echo $icon_class; ?> me-1"></i><?php echo $status_rapor; ?></span>
                                </td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="check_sampul[]" value="<?php echo $siswa['id_siswa']; ?>"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="check_identitas[]" value="<?php echo $siswa['id_siswa']; ?>"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="check_rapor[]" value="<?php echo $siswa['id_siswa']; ?>" <?php echo ($status_rapor != 'Final') ? 'disabled' : ''; ?>></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="rapor_cover.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" data-bs-toggle="tooltip" title="Cetak Sampul"><i class="bi bi-book-half"></i></a>
                                        <a href="rapor_identitas_siswa.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" data-bs-toggle="tooltip" title="Cetak Identitas"><i class="bi bi-person-badge"></i></a>
                                        <a href="rapor_pdf.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-primary <?php echo ($status_rapor != 'Final') ? 'disabled' : ''; ?>" target="_blank" data-bs-toggle="tooltip" title="Cetak Rapor Lengkap"><i class="bi bi-file-earmark-pdf-fill"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center p-4 text-muted">Tidak ada siswa aktif di kelas ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light text-center">
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary" onclick="prosesCetakMassal('sampul')"><i class="bi bi-book-half me-2"></i>Cetak Sampul Terpilih</button>
                <button type="button" class="btn btn-outline-secondary" onclick="prosesCetakMassal('identitas')"><i class="bi bi-person-badge me-2"></i>Cetak Identitas Terpilih</button>
                <button type="button" class="btn btn-success" onclick="prosesCetakMassal('rapor')"><i class="bi bi-file-earmark-pdf-fill me-2"></i>Cetak Rapor Terpilih</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
});

function toggleAll(source, type) {
    let checkboxes = document.getElementsByName('check_' + type + '[]');
    for(let i = 0; i < checkboxes.length; i++) {
        if (!checkboxes[i].disabled) {
            checkboxes[i].checked = source.checked;
        }
    }
}

function prosesCetakMassal(tipeCetak) {
    let listSiswaId = [];
    let checkboxes = document.getElementsByName('check_' + tipeCetak + '[]');
    for(let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) { listSiswaId.push(checkboxes[i].value); }
    }
    if (listSiswaId.length === 0) {
        Swal.fire('Peringatan', 'Silakan pilih minimal satu siswa pada kolom yang sesuai.', 'warning');
        return;
    }
    let ids = listSiswaId.join(',');
    let url = `rapor_cetak_massal.php?tipe=${tipeCetak}&ids=${ids}`;
    window.open(url, '_blank');
}
</script>

<?php include 'footer.php'; ?>