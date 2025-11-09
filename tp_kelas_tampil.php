<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Admin.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

// Ambil ID Kelas dari URL
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    echo "<script>Swal.fire('Error','Kelas tidak ditemukan.','error').then(() => window.location = 'mapel_tampil.php');</script>";
    exit;
}

// Ambil data detail kelas dan wali kelas
$q_kelas = mysqli_prepare($koneksi, "SELECT k.nama_kelas, k.fase, g.id_guru as id_walas, g.nama_guru as nama_walas FROM kelas k LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru WHERE k.id_kelas = ?");
mysqli_stmt_bind_param($q_kelas, "i", $id_kelas);
mysqli_stmt_execute($q_kelas);
$kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kelas));
$fase_kelas = $kelas['fase'];

// Ambil semua mata pelajaran
$semua_mapel_query = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel FROM mata_pelajaran ORDER BY urutan, nama_mapel ASC");

// Ambil semua guru untuk dropdown
$semua_guru_query = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru ORDER BY nama_guru ASC");
$daftar_guru = mysqli_fetch_all($semua_guru_query, MYSQLI_ASSOC);

// Ambil data guru yang sudah mengajar mapel di kelas ini
$guru_mengajar_query = mysqli_prepare($koneksi, "SELECT id_mapel, id_guru FROM guru_mengajar WHERE id_kelas = ?");
mysqli_stmt_bind_param($guru_mengajar_query, "i", $id_kelas);
mysqli_stmt_execute($guru_mengajar_query);
$result_gm = mysqli_stmt_get_result($guru_mengajar_query);
$guru_mengajar = [];
while ($row = mysqli_fetch_assoc($result_gm)) {
    $guru_mengajar[$row['id_mapel']] = $row['id_guru'];
}

// Ambil data TP yang sudah dipilih untuk kelas ini
$tp_terpilih_query = mysqli_prepare($koneksi, "SELECT id_tp FROM tp_kelas WHERE id_kelas = ?");
mysqli_stmt_bind_param($tp_terpilih_query, "i", $id_kelas);
mysqli_stmt_execute($tp_terpilih_query);
$result_tp = mysqli_stmt_get_result($tp_terpilih_query);
$tp_terpilih = [];
while ($row = mysqli_fetch_assoc($result_tp)) {
    $tp_terpilih[] = $row['id_tp'];
}
?>

<style>
    .page-header { background: linear-gradient(135deg, #28a745, #20c997); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .accordion-button:not(.collapsed) { background-color: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
    .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25); }
    .tp-list { max-height: 300px; overflow-y: auto; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Kelola Pembelajaran</h1>
        <p class="lead mb-0 opacity-75">
            Kelas: <strong><?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong> | 
            Wali Kelas: <strong><?php echo htmlspecialchars($kelas['nama_walas'] ?? 'N/A'); ?></strong>
        </p>
    </div>

    <div class="accordion" id="accordionMapel">
        <?php while ($mapel = mysqli_fetch_assoc($semua_mapel_query)) : 
            $id_mapel = $mapel['id_mapel'];
            $id_guru_pengampu = $guru_mengajar[$id_mapel] ?? $kelas['id_walas']; // Default ke Wali Kelas
        ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading-<?php echo $id_mapel; ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $id_mapel; ?>">
                    <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                </button>
            </h2>
            <div id="collapse-<?php echo $id_mapel; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionMapel">
                <div class="accordion-body">
                    <form action="tp_kelas_aksi.php" method="POST">
                        <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
                        <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">

                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="form-label fw-bold"><i class="bi bi-person-check-fill me-2"></i>Guru Pengampu</label>
                                <select name="id_guru" class="form-select">
                                    <option value="" disabled>-- Pilih Guru --</option>
                                    <?php if ($kelas['id_walas']) : ?>
                                        <option value="<?php echo $kelas['id_walas']; ?>" <?php if($id_guru_pengampu == $kelas['id_walas']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($kelas['nama_walas']); ?> (Wali Kelas)
                                        </option>
                                        <option disabled>--------------------</option>
                                    <?php endif; ?>
                                    <?php foreach ($daftar_guru as $guru) : 
                                        if ($guru['id_guru'] == $kelas['id_walas']) continue; // Lewati jika sudah ditampilkan
                                    ?>
                                        <option value="<?php echo $guru['id_guru']; ?>" <?php if($id_guru_pengampu == $guru['id_guru']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-bold"><i class="bi bi-card-checklist me-2"></i>Pilih Tujuan Pembelajaran (TP) untuk Kelas Ini</label>
                                <div class="list-group tp-list">
                                    <?php
                                    $tp_query = mysqli_prepare($koneksi, "SELECT id_tp, deskripsi_tp FROM tujuan_pembelajaran WHERE id_mapel = ? AND fase = ? ORDER BY semester, deskripsi_tp");
                                    mysqli_stmt_bind_param($tp_query, "is", $id_mapel, $fase_kelas);
                                    mysqli_stmt_execute($tp_query);
                                    $result_tp_mapel = mysqli_stmt_get_result($tp_query);

                                    if(mysqli_num_rows($result_tp_mapel) > 0):
                                        while($tp = mysqli_fetch_assoc($result_tp_mapel)):
                                            $isChecked = in_array($tp['id_tp'], $tp_terpilih);
                                    ?>
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2" type="checkbox" name="tp_ids[]" value="<?php echo $tp['id_tp']; ?>" <?php if($isChecked) echo 'checked'; ?>>
                                            <?php echo htmlspecialchars($tp['deskripsi_tp']); ?>
                                        </label>
                                    <?php 
                                        endwhile;
                                    else:
                                        echo '<div class="list-group-item text-center text-muted">Belum ada TP untuk mapel ini di Fase '.$fase_kelas.'</div>';
                                    endif;
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" name="simpan" class="btn btn-primary"><i class="bi bi-floppy-fill me-2"></i>Simpan Perubahan untuk Mapel Ini</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php include 'footer.php'; ?>