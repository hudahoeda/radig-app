<?php
include 'header.php';
include 'koneksi.php';

$id_guru_login = (int)$_SESSION['id_guru'];
$role_login = $_SESSION['role'];
$id_kegiatan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_kegiatan == 0) {
    echo "<script>Swal.fire('Error','ID Kegiatan tidak valid.','error').then(() => window.location = 'kokurikuler_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// 1. Ambil data kegiatan
$stmt_keg = mysqli_prepare($koneksi, "
    SELECT k.tema_kegiatan, k.id_koordinator, g.nama_guru AS nama_koordinator
    FROM kokurikuler_kegiatan k
    LEFT JOIN guru g ON k.id_koordinator = g.id_guru
    WHERE k.id_kegiatan = ?
");
mysqli_stmt_bind_param($stmt_keg, "i", $id_kegiatan);
mysqli_stmt_execute($stmt_keg);
$kegiatan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_keg));

if (!$kegiatan) {
    echo "<script>Swal.fire('Error','Kegiatan tidak ditemukan.','error').then(() => window.location = 'kokurikuler_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Keamanan
$is_koordinator = ($kegiatan['id_koordinator'] == $id_guru_login);
$is_admin = ($role_login == 'admin');
if (!$is_admin && !$is_koordinator) {
    echo "<script>Swal.fire('Akses Ditolak','Anda bukan Koordinator untuk projek ini.','error').then(() => window.location = 'kokurikuler_pilih.php');</script>";
    include 'footer.php';
    exit;
}

// 2. Ambil Kelas yang Terlibat dalam Kegiatan Ini
$query_kelas_terlibat = "
    SELECT k.id_kelas, k.nama_kelas 
    FROM kokurikuler_kelas_terlibat kkt
    JOIN kelas k ON kkt.id_kelas = k.id_kelas
    WHERE kkt.id_kegiatan = ?
    ORDER BY k.nama_kelas ASC
";
$stmt_kelas = mysqli_prepare($koneksi, $query_kelas_terlibat);
mysqli_stmt_bind_param($stmt_kelas, "i", $id_kegiatan);
mysqli_stmt_execute($stmt_kelas);
$daftar_kelas_terlibat = mysqli_fetch_all(mysqli_stmt_get_result($stmt_kelas), MYSQLI_ASSOC);

// 3. Ambil Semua Guru
$query_guru = "SELECT id_guru, nama_guru, nip FROM guru WHERE role IN ('guru', 'admin') ORDER BY nama_guru ASC";
$daftar_semua_guru = mysqli_fetch_all(mysqli_query($koneksi, $query_guru), MYSQLI_ASSOC);

// 4. Ambil Data Tim Penilai yang Sudah Ada (Group by Kelas)
$query_tim = "SELECT id_kelas, id_guru FROM kokurikuler_tim_penilai WHERE id_kegiatan = ?";
$stmt_tim = mysqli_prepare($koneksi, $query_tim);
mysqli_stmt_bind_param($stmt_tim, "i", $id_kegiatan);
mysqli_stmt_execute($stmt_tim);
$result_tim = mysqli_stmt_get_result($stmt_tim);

$tim_existing = [];
while($row = mysqli_fetch_assoc($result_tim)) {
    $tim_existing[$row['id_kelas']][] = $row['id_guru'];
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2rem; border-radius: 0.75rem; color: white; margin-bottom: 2rem; }
    .kelas-card { border: 1px solid #e0e0e0; border-radius: 0.5rem; margin-bottom: 1.5rem; overflow: hidden; transition: box-shadow 0.2s; }
    .kelas-card:hover { box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08); }
    .kelas-header { background-color: #f8f9fa; padding: 1rem 1.25rem; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
    .kelas-header h5 { margin: 0; font-weight: 700; color: var(--primary-color); }
    .guru-scroll-area { max_height: 300px; overflow-y: auto; padding: 0.5rem; background: #fff; }
    .guru-item { padding: 0.5rem; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; }
    .guru-item:last-child { border-bottom: none; }
    .guru-item:hover { background-color: #f1f8ff; }
    .form-check-input { cursor: pointer; }
    .search-guru { margin-bottom: 10px; }
    /* Sticky footer for save button */
    .sticky-footer { position: sticky; bottom: 0; background: white; padding: 1rem; border-top: 1px solid #ddd; box-shadow: 0 -4px 10px rgba(0,0,0,0.05); z-index: 1000; text-align: right; }
</style>

<div class="container-fluid">
    <div class="page-header shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">Manajemen Tim Penilai</h1>
                <p class="mb-0 opacity-75">Tentukan guru penilai untuk setiap kelas pada projek: <strong><?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?></strong></p>
            </div>
            <a href="kokurikuler_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali</a>
        </div>
    </div>

    <?php if (empty($daftar_kelas_terlibat)): ?>
        <div class="alert alert-warning shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Belum ada kelas yang dipilih!</strong> Silakan edit kegiatan ini dan pilih "Kelas Sasaran" terlebih dahulu.
            <a href="kokurikuler_edit.php?id=<?php echo $id_kegiatan; ?>" class="alert-link">Edit Kegiatan</a>
        </div>
    <?php else: ?>

        <form action="kokurikuler_aksi.php?aksi=simpan_tim" method="POST" id="formTim">
            <input type="hidden" name="id_kegiatan" value="<?php echo $id_kegiatan; ?>">
            
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> Koordinator (<?php echo htmlspecialchars($kegiatan['nama_koordinator']); ?>) otomatis memiliki akses penuh ke semua kelas. Silakan pilih guru pendamping tambahan di bawah ini.
                    </div>
                </div>

                <!-- Input Pencarian Global -->
                <div class="col-12 mb-4">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="globalSearch" class="form-control" placeholder="Ketik nama guru untuk menyaring daftar di semua kelas...">
                    </div>
                </div>

                <?php foreach($daftar_kelas_terlibat as $kelas): 
                    $id_kelas = $kelas['id_kelas'];
                    $guru_terpilih_di_kelas = $tim_existing[$id_kelas] ?? [];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="kelas-card">
                        <div class="kelas-header">
                            <h5><i class="bi bi-door-open-fill me-2"></i>Kelas <?php echo htmlspecialchars($kelas['nama_kelas']); ?></h5>
                            <span class="badge bg-secondary count-badge" id="count_<?php echo $id_kelas; ?>">
                                <?php echo count($guru_terpilih_di_kelas); ?> Guru
                            </span>
                        </div>
                        <div class="p-2 bg-light border-bottom">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input check-all-kelas" type="checkbox" data-target="list_<?php echo $id_kelas; ?>">
                                <label class="form-check-label small fw-bold">Pilih Semua Guru di Kelas Ini</label>
                            </div>
                        </div>
                        <div class="guru-scroll-area" id="list_<?php echo $id_kelas; ?>">
                            <?php foreach($daftar_semua_guru as $guru): 
                                $checked = in_array($guru['id_guru'], $guru_terpilih_di_kelas) ? 'checked' : '';
                                // Jangan tampilkan koordinator di list pilihan agar tidak redundan, 
                                // tapi kalau mau ditampilkan dan disabled juga boleh. Disini kita hide saja biar bersih.
                                if($guru['id_guru'] == $kegiatan['id_koordinator']) continue; 
                            ?>
                            <div class="guru-item">
                                <div class="form-check w-100">
                                    <input class="form-check-input guru-checkbox" type="checkbox" 
                                           name="tim[<?php echo $id_kelas; ?>][]" 
                                           value="<?php echo $guru['id_guru']; ?>" 
                                           id="g_<?php echo $id_kelas; ?>_<?php echo $guru['id_guru']; ?>"
                                           <?php echo $checked; ?>
                                           data-kelas="<?php echo $id_kelas; ?>">
                                    <label class="form-check-label w-100 d-flex justify-content-between align-items-center" for="g_<?php echo $id_kelas; ?>_<?php echo $guru['id_guru']; ?>">
                                        <span class="nama-guru"><?php echo htmlspecialchars($guru['nama_guru']); ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="sticky-footer rounded-top">
                <button type="submit" class="btn btn-success btn-lg shadow"><i class="bi bi-save-fill me-2"></i> Simpan Pembagian Tim</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // 1. Fitur Search Global (Menyaring nama guru di semua box kelas)
    $('#globalSearch').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.guru-item').filter(function() {
            $(this).toggle($(this).find('.nama-guru').text().toLowerCase().indexOf(value) > -1)
        });
    });

    // 2. Update Badge Jumlah saat checkbox diklik
    $('.guru-checkbox').on('change', function() {
        var idKelas = $(this).data('kelas');
        var count = $(`#list_${idKelas} input:checked`).length;
        $(`#count_${idKelas}`).text(count + ' Guru');
    });

    // 3. Fitur "Pilih Semua" per Kelas
    $('.check-all-kelas').on('change', function() {
        var targetId = $(this).data('target');
        var isChecked = $(this).prop('checked');
        
        // Hanya centang yang terlihat (jika sedang difilter search)
        $(`#${targetId} .guru-item:visible input[type="checkbox"]`).prop('checked', isChecked);
        
        // Trigger event change untuk update badge
        $(`#${targetId} input[type="checkbox"]`).first().trigger('change');
    });
});
</script>

<?php
if (isset($_SESSION['pesan'])) {
    $pesan_data = json_decode($_SESSION['pesan'], true);
    if (is_array($pesan_data)) {
        echo "<script>Swal.fire(" . json_encode($pesan_data) . ");</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php'; 
?>