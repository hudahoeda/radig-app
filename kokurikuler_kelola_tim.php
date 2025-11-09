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

// Ambil data kegiatan dan siapa koordinatornya
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

// KEAMANAN: Cek apakah user ini adalah Admin atau Koordinator dari projek ini
$is_koordinator = ($kegiatan['id_koordinator'] == $id_guru_login);
$is_admin = ($role_login == 'admin');

if (!$is_admin && !$is_koordinator) {
    echo "<script>Swal.fire('Akses Ditolak','Anda bukan Koordinator untuk projek ini.','error').then(() => window.location = 'kokurikuler_pilih.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil semua daftar guru di sekolah
$query_guru = "SELECT id_guru, nama_guru, nip FROM guru WHERE role IN ('guru', 'admin') ORDER BY nama_guru ASC";
$result_guru = mysqli_query($koneksi, $query_guru);
$daftar_semua_guru = mysqli_fetch_all($result_guru, MYSQLI_ASSOC);

// Ambil daftar guru yang SUDAH ADA di tim penilai untuk projek ini
$query_tim = "SELECT id_guru FROM kokurikuler_tim_penilai WHERE id_kegiatan = ?";
$stmt_tim = mysqli_prepare($koneksi, $query_tim);
mysqli_stmt_bind_param($stmt_tim, "i", $id_kegiatan);
mysqli_stmt_execute($stmt_tim);
$result_tim = mysqli_stmt_get_result($stmt_tim);
$tim_terpilih_ids = [];
while($row = mysqli_fetch_assoc($result_tim)) {
    $tim_terpilih_ids[] = $row['id_guru'];
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .guru-list-item {
        padding: 0.75rem 1.25rem;
        border: 1px solid var(--border-color);
        border-radius: 0.375rem;
        margin-bottom: -1px;
        transition: background-color 0.2s;
    }
    .guru-list-item:hover {
        background-color: #f8f9fa;
    }
    .guru-list-item .form-check-input {
        transform: scale(1.2);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Tim Penilai</h1>
                <p class="lead mb-0 opacity-75">Projek: <strong><?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?></strong></p>
            </div>
            <a href="kokurikuler_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <form action="kokurikuler_aksi.php?aksi=simpan_tim" method="POST">
                    <input type="hidden" name="id_kegiatan" value="<?php echo $id_kegiatan; ?>">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Pilih Anggota Tim Penilai</h5>
                        <p class="mb-0 text-muted small">Koordinator: <strong><?php echo htmlspecialchars($kegiatan['nama_koordinator']); ?></strong></p>
                    </div>
                    <div class="card-body p-0">
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php foreach($daftar_semua_guru as $guru): ?>
                                <?php
                                $is_terpilih = in_array($guru['id_guru'], $tim_terpilih_ids);
                                $is_koordinator_guru = ($guru['id_guru'] == $kegiatan['id_koordinator']);
                                $disabled = $is_koordinator_guru ? 'disabled' : '';
                                $checked = ($is_terpilih || $is_koordinator_guru) ? 'checked' : '';
                                ?>
                                <label class="list-group-item d-flex justify-content-between align-items-center guru-list-item <?php echo $disabled ? 'bg-light' : ''; ?>" for="guru_<?php echo $guru['id_guru']; ?>">
                                    <div>
                                        <span class="fw-bold"><?php echo htmlspecialchars($guru['nama_guru']); ?></span>
                                        <small class="text-muted d-block"><?php echo $guru['nip'] ? 'NIP: ' . $guru['nip'] : 'Tanpa NIP'; ?></small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" 
                                               id="guru_<?php echo $guru['id_guru']; ?>" 
                                               name="tim_guru[]" 
                                               value="<?php echo $guru['id_guru']; ?>" 
                                               <?php echo $checked; ?> 
                                               <?php echo $disabled; ?>>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer text-end bg-light p-3">
                        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Tim Penilai</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="alert alert-info shadow-sm" role="alert">
                <h4 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>Informasi</h4>
                <p>Silakan pilih guru-guru yang akan Anda libatkan sebagai tim penilai untuk projek ini.</p>
                <hr>
                <ul class="mb-0 small" style="padding-left: 1.2rem;">
                    <li>Guru yang Anda pilih akan mendapatkan akses untuk menginput nilai asesmen di akun mereka.</li>
                    <li>Koordinator (Anda) otomatis termasuk dalam tim penilai.</li>
                    <li>Guru yang tidak dipilih tidak akan melihat menu penilaian untuk projek ini.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

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