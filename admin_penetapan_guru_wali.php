<?php
include 'header.php';
include 'koneksi.php';

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
    // Jika tidak ada yang aktif, pilih yang pertama
    if ($id_ta_terpilih === null && !empty($daftar_ta)) {
        $id_ta_terpilih = $daftar_ta[0]['id_tahun_ajaran'];
    }
}


// ===================================================================================
// DATA UNTUK TAB 1: ATUR ALOKASI
// ===================================================================================
// Ambil daftar semua guru untuk dropdown
$query_guru_list = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru WHERE role = 'guru' ORDER BY nama_guru ASC");
$daftar_guru_dropdown = [];
while ($row = mysqli_fetch_assoc($query_guru_list)) {
    $daftar_guru_dropdown[] = $row;
}

// Ambil semua siswa aktif, dikelompokkan per kelas, BERDASARKAN TAHUN AJARAN TERPILIH
$query_siswa_per_kelas = mysqli_prepare($koneksi, "
    SELECT 
        k.id_kelas, k.nama_kelas,
        s.id_siswa, s.nama_lengkap,
        gw.nama_guru AS nama_guru_wali
    FROM kelas k
    JOIN siswa s ON k.id_kelas = s.id_kelas
    LEFT JOIN guru gw ON s.id_guru_wali = gw.id_guru
    WHERE k.id_tahun_ajaran = ? AND s.status_siswa = 'Aktif'
    ORDER BY k.nama_kelas, s.nama_lengkap ASC
");
mysqli_stmt_bind_param($query_siswa_per_kelas, "i", $id_ta_terpilih);
mysqli_stmt_execute($query_siswa_per_kelas);
$result_siswa_per_kelas = mysqli_stmt_get_result($query_siswa_per_kelas);
$data_kelas_siswa = [];
while ($row = mysqli_fetch_assoc($result_siswa_per_kelas)) {
    $data_kelas_siswa[$row['nama_kelas']][] = $row;
}

// ===================================================================================
// DATA UNTUK TAB 2: LIHAT ALOKASI
// ===================================================================================
// Ambil daftar guru yang sudah menjadi guru wali, BERDASARKAN TAHUN AJARAN TERPILIH
$query_guru_wali_info = mysqli_prepare($koneksi, "
    SELECT 
        g.id_guru, g.nama_guru, COUNT(s.id_siswa) as jumlah_binaan
    FROM guru g
    JOIN siswa s ON g.id_guru = s.id_guru_wali
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.status_siswa = 'Aktif' AND k.id_tahun_ajaran = ?
    GROUP BY g.id_guru, g.nama_guru
    ORDER BY g.nama_guru ASC
");
mysqli_stmt_bind_param($query_guru_wali_info, "i", $id_ta_terpilih);
mysqli_stmt_execute($query_guru_wali_info);
$result_guru_wali_info = mysqli_stmt_get_result($query_guru_wali_info);

$data_guru_wali = [];
while ($row = mysqli_fetch_assoc($result_guru_wali_info)) {
    // Ambil detail siswa untuk setiap guru wali
    $id_guru = $row['id_guru'];
    $q_detail_siswa = mysqli_prepare($koneksi, "
        SELECT s.nama_lengkap, k.nama_kelas 
        FROM siswa s 
        JOIN kelas k ON s.id_kelas = k.id_kelas
        WHERE s.id_guru_wali = ? AND s.status_siswa = 'Aktif' AND k.id_tahun_ajaran = ?
        ORDER BY k.nama_kelas, s.nama_lengkap ASC
    ");
    mysqli_stmt_bind_param($q_detail_siswa, "ii", $id_guru, $id_ta_terpilih);
    mysqli_stmt_execute($q_detail_siswa);
    $res_detail_siswa = mysqli_stmt_get_result($q_detail_siswa);
    $detail_siswa = [];
    while ($siswa_row = mysqli_fetch_assoc($res_detail_siswa)) {
        $detail_siswa[] = $siswa_row;
    }
    $row['detail_siswa'] = $detail_siswa;
    $data_guru_wali[] = $row;
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Kelola Guru Wali</h1>
        <p class="lead mb-0 opacity-75">Atur alokasi siswa ke guru wali dan lihat rekapitulasi per tahun ajaran.</p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="admin_penetapan_guru_wali.php" method="GET" class="row g-2 align-items-end">
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

    <div class="card shadow mb-4">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="atur-tab" data-bs-toggle="tab" data-bs-target="#atur" type="button" role="tab"><i class="bi bi-ui-checks-grid me-1"></i> Atur Alokasi</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lihat-tab" data-bs-toggle="tab" data-bs-target="#lihat" type="button" role="tab"><i class="bi bi-eye-fill me-1"></i> Lihat Alokasi per Guru</button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="atur" role="tabpanel">
                    <form action="proses_penetapan_guru_wali.php" method="POST">
                        <input type="hidden" name="id_ta_redirect" value="<?php echo $id_ta_terpilih; ?>">
                        <div class="alert alert-info">
                            <b>Cara Penggunaan:</b> Buka daftar kelas di bawah, centang siswa yang ingin dialokasikan, lalu pilih guru dari dropdown di bagian bawah dan klik "Tetapkan".
                        </div>
                        <?php if (empty($data_kelas_siswa)): ?>
                            <div class="text-center p-5 text-muted">Tidak ada data siswa untuk tahun ajaran yang dipilih.</div>
                        <?php else: ?>
                        <div class="accordion" id="kelasAccordion">
                            <?php foreach ($data_kelas_siswa as $nama_kelas => $siswas): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo str_replace([' ', '/'], '', $nama_kelas); ?>">
                                        <?php echo htmlspecialchars($nama_kelas); ?> 
                                        <span class="badge bg-secondary ms-2"><?php echo count($siswas); ?> siswa</span>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo str_replace([' ', '/'], '', $nama_kelas); ?>" class="accordion-collapse collapse" data-bs-parent="#kelasAccordion">
                                    <div class="accordion-body">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th style="width: 5%;"><input type="checkbox" class="select-all-in-class"></th>
                                                    <th>Nama Siswa</th>
                                                    <th>Guru Wali Saat Ini</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($siswas as $siswa): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="id_siswa[]" value="<?php echo $siswa['id_siswa']; ?>" class="siswa-checkbox"></td>
                                                    <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                                    <td><?php echo $siswa['nama_guru_wali'] ? htmlspecialchars($siswa['nama_guru_wali']) : '<i class="text-muted">Kosong</i>'; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 p-3 border rounded bg-light">
                             <div class="row align-items-center">
                                <div class="col-md-7">
                                    <label for="id_guru_wali_massal" class="form-label fw-bold">Tetapkan siswa yang dicentang ke:</label>
                                    <select id="id_guru_wali_massal" name="id_guru_wali" class="form-select" required>
                                        <option value="" disabled selected>-- Pilih Guru Wali --</option>
                                        <option value="0" class="text-danger">[ Lepaskan Guru Wali dari Siswa Terpilih ]</option>
                                        <?php foreach ($daftar_guru_dropdown as $guru): ?>
                                            <option value="<?php echo $guru['id_guru']; ?>"><?php echo htmlspecialchars($guru['nama_guru']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                    <button type="submit" name="tetapkan_massal" class="btn btn-primary btn-lg"><i class="bi bi-save-fill me-2"></i>Tetapkan Pilihan</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="tab-pane fade" id="lihat" role="tabpanel">
                     <div class="accordion" id="guruWaliAccordion">
                        <?php if (empty($data_guru_wali)): ?>
                            <div class="alert alert-warning">Belum ada satupun guru yang ditetapkan sebagai Guru Wali pada tahun ajaran ini.</div>
                        <?php else: ?>
                            <?php foreach ($data_guru_wali as $guru): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-guru-<?php echo $guru['id_guru']; ?>">
                                        <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                        <span class="badge bg-success ms-2"><?php echo $guru['jumlah_binaan']; ?> siswa binaan</span>
                                    </button>
                                </h2>
                                <div id="collapse-guru-<?php echo $guru['id_guru']; ?>" class="accordion-collapse collapse" data-bs-parent="#guruWaliAccordion">
                                    <div class="accordion-body">
                                        <ul class="list-group">
                                            <?php foreach ($guru['detail_siswa'] as $siswa_binaan): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($siswa_binaan['nama_lengkap']); ?>
                                                <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($siswa_binaan['nama_kelas']); ?></span>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckboxes = document.querySelectorAll('.select-all-in-class');
    selectAllCheckboxes.forEach(function(headerCheckbox) {
        headerCheckbox.addEventListener('change', function(e) {
            const accordionBody = e.target.closest('thead').parentElement.parentElement;
            const studentCheckboxes = accordionBody.querySelectorAll('.siswa-checkbox');
            studentCheckboxes.forEach(function(checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>

