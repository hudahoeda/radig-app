<?php
include 'header.php';
include 'koneksi.php';

// Validasi role
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

// Ambil semua tahun ajaran untuk filter
$query_ta_all = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran, status FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
$daftar_ta = mysqli_fetch_all($query_ta_all, MYSQLI_ASSOC);

// Tentukan tahun ajaran yang akan ditampilkan
$id_ta_terpilih = $_GET['id_ta'] ?? null;
if ($id_ta_terpilih === null) {
    foreach ($daftar_ta as $ta) {
        if ($ta['status'] == 'Aktif') {
            $id_ta_terpilih = $ta['id_tahun_ajaran'];
            break;
        }
    }
    if ($id_ta_terpilih === null && !empty($daftar_ta)) {
        $id_ta_terpilih = $daftar_ta[0]['id_tahun_ajaran'];
    }
}

// Ambil data Guru Wali dan siswa binaannya untuk tahun ajaran terpilih
$query_guru_wali = mysqli_prepare($koneksi, "
    SELECT 
        g.id_guru, g.nama_guru, COUNT(DISTINCT s.id_siswa) as jumlah_binaan
    FROM guru g
    JOIN siswa s ON g.id_guru = s.id_guru_wali
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.status_siswa = 'Aktif' AND k.id_tahun_ajaran = ?
    GROUP BY g.id_guru, g.nama_guru
    ORDER BY g.nama_guru ASC
");
mysqli_stmt_bind_param($query_guru_wali, "i", $id_ta_terpilih);
mysqli_stmt_execute($query_guru_wali);
$result_guru_wali = mysqli_stmt_get_result($query_guru_wali);

$data_monitoring = [];
while ($guru = mysqli_fetch_assoc($result_guru_wali)) {
    $id_guru = $guru['id_guru'];
    
    // Ambil siswa dan catatan mereka
    $q_siswa = mysqli_prepare($koneksi, "
        SELECT s.id_siswa, s.nama_lengkap, k.nama_kelas, 
               (SELECT COUNT(*) FROM catatan_guru_wali WHERE id_siswa = s.id_siswa) as jumlah_catatan
        FROM siswa s
        JOIN kelas k ON s.id_kelas = k.id_kelas
        WHERE s.id_guru_wali = ? AND k.id_tahun_ajaran = ? AND s.status_siswa = 'Aktif'
        ORDER BY s.nama_lengkap ASC
    ");
    mysqli_stmt_bind_param($q_siswa, "ii", $id_guru, $id_ta_terpilih);
    mysqli_stmt_execute($q_siswa);
    $res_siswa = mysqli_stmt_get_result($q_siswa);
    
    $siswa_list = [];
    while($siswa = mysqli_fetch_assoc($res_siswa)){
        // Ambil catatan detail untuk siswa ini
        $q_catatan = mysqli_prepare($koneksi, "SELECT * FROM catatan_guru_wali WHERE id_siswa = ? ORDER BY tanggal_catatan DESC");
        mysqli_stmt_bind_param($q_catatan, "i", $siswa['id_siswa']);
        mysqli_stmt_execute($q_catatan);
        $res_catatan = mysqli_stmt_get_result($q_catatan);
        $siswa['catatan'] = mysqli_fetch_all($res_catatan, MYSQLI_ASSOC);
        $siswa_list[] = $siswa;
    }
    $guru['siswa_binaan'] = $siswa_list;
    $data_monitoring[] = $guru;
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .accordion-button:not(.collapsed) {
        background-color: #eef;
    }
    .nested-accordion .accordion-button {
        font-size: 0.95rem;
    }
    .nested-accordion .accordion-body {
        background-color: #f8f9fa;
    }
    .catatan-item {
        border-left: 3px solid #ccc;
        padding-left: 1rem;
        margin-bottom: 1rem;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Monitoring Catatan Guru Wali</h1>
        <p class="lead mb-0 opacity-75">Lihat rekam jejak catatan perkembangan siswa yang diinput oleh Guru Wali.</p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="admin_monitoring_catatan.php" method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="id_ta" class="form-label fw-bold">Tampilkan Data untuk Tahun Ajaran:</label>
                    <select name="id_ta" id="id_ta" class="form-select" onchange="this.form.submit()">
                        <?php foreach($daftar_ta as $ta): ?>
                        <option value="<?php echo $ta['id_tahun_ajaran']; ?>" <?php if($id_ta_terpilih == $ta['id_tahun_ajaran']) echo 'selected'; ?>>
                            <?php echo $ta['tahun_ajaran'] . ($ta['status'] == 'Aktif' ? ' (Aktif)' : ''); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="accordion" id="monitoringAccordion">
                <?php if (empty($data_monitoring)): ?>
                    <div class="text-center p-5 text-muted">Tidak ada data Guru Wali untuk tahun ajaran yang dipilih.</div>
                <?php else: ?>
                    <?php foreach ($data_monitoring as $guru): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-guru-<?php echo $guru['id_guru']; ?>">
                                <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                <span class="badge bg-success ms-3"><?php echo $guru['jumlah_binaan']; ?> Siswa Binaan</span>
                            </button>
                        </h2>
                        <div id="collapse-guru-<?php echo $guru['id_guru']; ?>" class="accordion-collapse collapse" data-bs-parent="#monitoringAccordion">
                            <div class="accordion-body">
                                <div class="accordion nested-accordion" id="nestedAccordion-<?php echo $guru['id_guru']; ?>">
                                    <?php foreach ($guru['siswa_binaan'] as $siswa): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-siswa-<?php echo $siswa['id_siswa']; ?>">
                                                <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (<?php echo htmlspecialchars($siswa['nama_kelas']); ?>)
                                                <span class="badge bg-primary ms-auto"><?php echo $siswa['jumlah_catatan']; ?> Catatan</span>
                                            </button>
                                        </h2>
                                        <div id="collapse-siswa-<?php echo $siswa['id_siswa']; ?>" class="accordion-collapse collapse" data-bs-parent="#nestedAccordion-<?php echo $guru['id_guru']; ?>">
                                            <div class="accordion-body">
                                                <?php if (empty($siswa['catatan'])): ?>
                                                    <p class="text-muted">Belum ada catatan untuk siswa ini.</p>
                                                <?php else: ?>
                                                    <?php foreach ($siswa['catatan'] as $catatan): ?>
                                                    <div class="catatan-item">
                                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($catatan['isi_catatan'])); ?></p>
                                                        <small class="text-muted">
                                                            <strong><?php echo htmlspecialchars($catatan['kategori_catatan']); ?></strong> - <?php echo date('d M Y', strtotime($catatan['tanggal_catatan'])); ?>
                                                        </small>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
