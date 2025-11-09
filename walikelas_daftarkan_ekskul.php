<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas
if ($_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Wali Kelas yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil info tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

// Ambil data kelas yang diampu
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);

if (!$kelas) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Anda tidak terdaftar sebagai wali kelas pada tahun ajaran aktif.</div></div>";
    include 'footer.php';
    exit;
}
$id_kelas = $kelas['id_kelas'];

// Ambil semua siswa di kelas ini
$q_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas ORDER BY nama_lengkap ASC");
$daftar_siswa = mysqli_fetch_all($q_siswa, MYSQLI_ASSOC);

// Ambil semua ekstrakurikuler
$q_ekskul_list = mysqli_query($koneksi, "SELECT id_ekskul, nama_ekskul FROM ekstrakurikuler WHERE id_tahun_ajaran = $id_tahun_ajaran ORDER BY nama_ekskul ASC");
$daftar_ekskul = mysqli_fetch_all($q_ekskul_list, MYSQLI_ASSOC);

// Ambil data pendaftaran ekskul yang sudah ada
$q_peserta = mysqli_query($koneksi, "SELECT ep.id_siswa, ep.id_ekskul FROM ekskul_peserta ep JOIN siswa s ON ep.id_siswa = s.id_siswa WHERE s.id_kelas = $id_kelas");
$peserta_terdaftar = [];
while ($p = mysqli_fetch_assoc($q_peserta)) {
    $peserta_terdaftar[$p['id_siswa']][$p['id_ekskul']] = true;
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }

    .table-responsive {
        max-height: 70vh; /* Batasi tinggi tabel agar tidak terlalu panjang */
    }
    .table-ekskul thead th {
        position: -webkit-sticky;
        position: sticky;
        top: 0;
        z-index: 3;
        background-color: #f8f9fa; /* Latar belakang header yang terang */
    }
    .table-ekskul .sticky-col {
        position: -webkit-sticky;
        position: sticky;
        left: 0;
        z-index: 2;
        background-color: #fff;
    }
    /* Ganti warna baris saat di-hover */
    .table-hover > tbody > tr:hover > * {
        background-color: #e0f2f1 !important; /* Warna Teal muda */
    }
    .table-ekskul .sticky-col-header {
        z-index: 4 !important;
    }
    /* Hilangkan border vertikal untuk tampilan yang lebih bersih */
    .table-ekskul, .table-ekskul th, .table-ekskul td {
        border-left: none;
        border-right: none;
    }
    .table-ekskul td, .table-ekskul th {
        padding: 1rem 0.75rem; /* Tambah padding agar lebih lega */
    }
    .form-check-input {
        transform: scale(1.2); /* Perbesar sedikit checkbox */
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Kelola Ekstrakurikuler Siswa</h1>
        <p class="lead mb-0 opacity-75">Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></p>
    </div>

    <form action="walikelas_aksi.php?aksi=simpan_pendaftaran_ekskul" method="POST">
        <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="bi bi-person-check-fill me-2" style="color: var(--primary-color);"></i>
                    Daftar Keikutsertaan Siswa
                </h5>
                <small class="text-muted">Centang ekskul yang diikuti oleh setiap siswa. Gunakan checkbox di header kolom untuk memilih semua.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-ekskul align-middle">
                        <thead class="text-center">
                            <tr>
                                <th class="sticky-col sticky-col-header text-start">Nama Siswa</th>
                                <?php foreach ($daftar_ekskul as $ekskul): ?>
                                    <th>
                                        <?php echo htmlspecialchars($ekskul['nama_ekskul']); ?><br>
                                        <input class="form-check-input mt-1" type="checkbox" title="Pilih Semua di Kolom Ini" data-ekskul-id="<?php echo $ekskul['id_ekskul']; ?>">
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar_siswa as $siswa):
                                $id_siswa = $siswa['id_siswa'];
                            ?>
                            <tr>
                                <td class="sticky-col fw-bold">
                                    <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                                </td>
                                <?php foreach ($daftar_ekskul as $ekskul):
                                    $id_ekskul = $ekskul['id_ekskul'];
                                    $checked = isset($peserta_terdaftar[$id_siswa][$id_ekskul]) ? 'checked' : '';
                                ?>
                                <td class="text-center">
                                    <div class="form-check d-flex justify-content-center">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="ekskul[<?php echo $id_siswa; ?>][]"
                                               value="<?php echo $id_ekskul; ?>"
                                               data-member-of-ekskul="<?php echo $id_ekskul; ?>"
                                               <?php echo $checked; ?>>
                                    </div>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-4 d-flex justify-content-end">
            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Pendaftaran</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logika untuk fitur "Pilih Semua" per kolom
    const headerCheckboxes = document.querySelectorAll('thead input[type="checkbox"][data-ekskul-id]');
    
    headerCheckboxes.forEach(headerCheckbox => {
        headerCheckbox.addEventListener('change', function() {
            const ekskulId = this.getAttribute('data-ekskul-id');
            const isChecked = this.checked;
            
            const memberCheckboxes = document.querySelectorAll(`tbody input[type="checkbox"][data-member-of-ekskul="${ekskulId}"]`);
            memberCheckboxes.forEach(memberCheckbox => {
                memberCheckbox.checked = isChecked;
            });
        });
    });
});
</script>

<?php
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire('Berhasil!','" . addslashes($_SESSION['pesan']) . "','success');</script>";
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>