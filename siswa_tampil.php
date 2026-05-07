<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'koneksi.php';
include 'header.php';

// [SWEETALERT INTEGRATION: AKSES]
// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Akses Ditolak',
            text: 'Anda tidak memiliki wewenang untuk mengakses halaman ini.',
            confirmButtonColor: '#d33'
        }).then(() => {
            window.location = 'dashboard.php';
        });
    </script>";
    include 'footer.php';
    exit;
}

// Ambil ID Kelas dari URL dan pastikan valid
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Kelas Tidak Ditemukan',
            text: 'ID Kelas tidak valid atau tidak ditemukan.',
            confirmButtonColor: '#3085d6'
        }).then(() => {
            window.location = 'kelas_tampil.php';
        });
    </script>";
    include 'footer.php';
    exit;
}

// Ambil detail kelas (nama kelas dan nama wali kelas)
$query_kelas = "SELECT k.nama_kelas, g.nama_guru 
                FROM kelas k 
                LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
                WHERE k.id_kelas=$id_kelas";
$result_kelas_detail = mysqli_query($koneksi, $query_kelas);
$data_kelas = mysqli_fetch_assoc($result_kelas_detail);
$nama_kelas = $data_kelas['nama_kelas'] ?? 'Tidak Ditemukan';
$nama_walikelas = $data_kelas['nama_guru'] ?? '<i>Belum Ditentukan</i>';

// --- PERBAIKAN QUERY ---
// Query diubah untuk menghapus referensi ke id_guru_wali yang tidak ada di tabel siswa SD
$query_siswa = mysqli_query($koneksi, "
    SELECT 
        s.id_siswa, s.nisn, s.nis, s.nama_lengkap, s.foto_siswa
    FROM 
        siswa s
    WHERE 
        s.id_kelas = $id_kelas 
    ORDER BY 
        s.nama_lengkap ASC
");
// --- BATAS PERBAIKAN ---

$jumlah_siswa = mysqli_num_rows($query_siswa);
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .page-header h1 { font-weight: 800; letter-spacing: -0.5px; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    
    .info-panel {
        background-color: white;
        border: none;
        border-radius: 1rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        transition: transform 0.2s;
    }
    .info-panel:hover {
        transform: translateY(-2px);
    }
    
    .table-students img { 
        width: 45px; height: 45px; 
        object-fit: cover; border-radius: 50%; 
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    .table-students img:hover { transform: scale(1.1); }
    .table-students .student-name { font-weight: 600; color: #2c3e50; }
    
    .card { border: none; border-radius: 1rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header { background-color: white; padding: 1.5rem; border-bottom: 1px solid #edf2f7; }
    .table thead th { 
        background-color: #f8fafc; 
        color: #64748b; 
        font-weight: 600; 
        text-transform: uppercase; 
        font-size: 0.85rem; 
        padding: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }
    .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table tbody tr:hover { background-color: #f8fafc; }
    
    .btn-action {
        width: 32px; height: 32px; 
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 0.5rem; transition: all 0.2s;
    }
    .btn-action:hover { transform: translateY(-2px); box-shadow: 0 3px 6px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Siswa</h1>
                <p class="lead mb-0 opacity-90"><i class="bi bi-building me-2"></i>Kelas: <?php echo htmlspecialchars($nama_kelas); ?></p>
            </div>
            <div class="d-flex mt-3 mt-sm-0 gap-2">
                <a href="kelas_tampil.php" class="btn btn-outline-light border-2"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
                <a href="siswa_tambah.php?id_kelas=<?php echo $id_kelas; ?>" class="btn btn-light text-primary"><i class="bi bi-person-plus-fill me-2"></i>Tambah Siswa</a>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="info-panel p-4 d-flex flex-wrap justify-content-start align-items-center">
                <div class="d-flex align-items-center me-5 mb-2 mb-md-0">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3 text-success">
                        <i class="bi bi-person-workspace fs-4"></i>
                    </div>
                    <div>
                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Wali Kelas</small>
                        <div class="fw-bold fs-5 text-dark"><?php echo $nama_walikelas; ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center border-start ps-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3 text-primary">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                    <div>
                        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Jumlah Siswa</small>
                        <div class="fw-bold fs-5 text-dark"><?php echo $jumlah_siswa; ?> Siswa Aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-ul me-2 text-primary"></i>Daftar Siswa</h5>
            <!-- Optional: Add Search within class functionality here later -->
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-students align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">No</th>
                            <th class="ps-3" style="width: 70px;">Foto</th>
                            <th class="ps-3">Nama Lengkap</th>
                            <th>NIS</th>
                            <th>NISN</th>
                            <!-- <TH> Guru Wali Dihapus dari sini -->
                            <th class="text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if($jumlah_siswa > 0){
                            mysqli_data_seek($query_siswa, 0); // Reset pointer
                            $no = 1;
                            while ($siswa = mysqli_fetch_assoc($query_siswa)) {
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-muted"><?php echo $no++; ?></td>
                                <td class="text-center">
                                    <?php if (!empty($siswa['foto_siswa'])): ?>
                                        <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($siswa['nama_lengkap']); ?>">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 45px; height: 45px;">
                                            <i class="bi bi-person text-secondary fs-4"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="ps-3">
                                    <div class="student-name"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                    <small class="text-muted d-md-none">NIS: <?php echo htmlspecialchars($siswa['nis']); ?></small>
                                </td>
                                <td class="text-muted fw-medium"><?php echo htmlspecialchars($siswa['nis']); ?></td>
                                <td class="text-muted fw-medium"><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                                <!-- Kolom data Guru Wali Dihapus dari sini -->
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="siswa_edit.php?id=<?php echo $siswa['id_siswa']; ?>" class="btn btn-warning btn-sm btn-action text-white" data-bs-toggle="tooltip" title="Edit Data">
                                            <i class="bi bi-pencil-fill" style="font-size: 0.9rem;"></i>
                                        </a>
                                        <button onclick="hapusSiswa(<?php echo $siswa['id_siswa']; ?>, <?php echo $id_kelas; ?>, '<?php echo addslashes($siswa['nama_lengkap']); ?>')" class="btn btn-danger btn-sm btn-action" data-bs-toggle="tooltip" title="Hapus Siswa">
                                            <i class="bi bi-trash-fill" style="font-size: 0.9rem;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                            }
                        } else {
                            // --- PERBAIKAN COLSPAN ---
                            echo "<tr><td colspan='6' class='text-center py-5'>
                                <div class='d-flex flex-column align-items-center justify-content-center'>
                                    <i class='bi bi-folder-x fs-1 text-muted mb-3 opacity-50'></i>
                                    <h6 class='text-muted fw-bold'>Belum ada siswa di kelas ini</h6>
                                    <p class='text-muted small'>Silakan klik tombol 'Tambah Siswa' untuk memulai.</p>
                                </div>
                            </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Inisialisasi Tooltip
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function hapusSiswa(idSiswa, idKelas, namaSiswa) {
    Swal.fire({
        title: 'Hapus Siswa?',
        html: `Anda akan menghapus data siswa <b>${namaSiswa}</b>.<br><span class="text-danger small">Tindakan ini tidak dapat dibatalkan dan akan menghapus nilai terkait!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '<i class="bi bi-trash-fill me-1"></i> Ya, Hapus!',
        cancelButtonText: 'Batal',
        focusCancel: true,
        customClass: {
            popup: 'rounded-4 shadow-lg',
            confirmButton: 'rounded-3 px-4',
            cancelButton: 'rounded-3 px-4'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Tampilkan loading saat proses penghapusan
            Swal.fire({
                title: 'Sedang Menghapus...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            window.location.href = 'siswa_aksi.php?aksi=hapus&id=' + idSiswa + '&id_kelas=' + idKelas;
        }
    })
}
</script>

<!-- [SWEETALERT INTEGRATION: PESAN SESSION] -->
<?php
// Ditempatkan SEBELUM footer.php agar tidak konflik
if (isset($_SESSION['pesan'])) {
    $pesan = $_SESSION['pesan'];
    $data_json = json_decode($pesan, true);

    if (json_last_error() == JSON_ERROR_NONE && is_array($data_json)) {
        // Jika JSON valid (format modern)
        echo "<script>
            Swal.fire({
                icon: '" . addslashes($data_json['icon']) . "',
                title: '" . addslashes($data_json['title']) . "',
                html: '" . addslashes($data_json['html']) . "',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        </script>";
    } else {
        // Jika Plain Text (format lama)
        echo "<script>
            Swal.fire({
                icon: 'success', 
                title: 'Berhasil!', 
                html: '" . addslashes($pesan) . "',
                timer: 2000,
                timerProgressBar: true
            });
        </script>";
    }
    unset($_SESSION['pesan']);

} elseif (isset($_SESSION['error'])) { 
    $error_pesan = $_SESSION['error'];
    $data_json = json_decode($error_pesan, true);

    if (json_last_error() == JSON_ERROR_NONE && is_array($data_json)) {
        echo "<script>
            Swal.fire({
                icon: '" . addslashes($data_json['icon']) . "',
                title: '" . addslashes($data_json['title']) . "',
                html: '" . addslashes($data_json['html']) . "'
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error', 
                title: 'Gagal!', 
                html: '" . addslashes($error_pesan) . "'
            });
        </script>";
    }
    unset($_SESSION['error']);
}
?>

<?php include 'footer.php'; ?>