<?php
include 'header.php';
include 'koneksi.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error',title: 'Akses Ditolak'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID Kelas dari URL dan pastikan valid
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    echo "<script>Swal.fire('Error','ID Kelas tidak valid.','error').then(() => window.location = 'kelas_tampil.php');</script>";
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

// Ambil daftar siswa beserta nama Guru Walinya
$query_siswa = mysqli_query($koneksi, "
    SELECT 
        s.id_siswa, s.nisn, s.nis, s.nama_lengkap, s.foto_siswa,
        gw.nama_guru AS nama_guru_wali
    FROM 
        siswa s
    LEFT JOIN 
        guru gw ON s.id_guru_wali = gw.id_guru
    WHERE 
        s.id_kelas = $id_kelas 
    ORDER BY 
        s.nama_lengkap ASC
");
$jumlah_siswa = mysqli_num_rows($query_siswa);
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    
    .info-panel {
        background-color: #f8f9fa;
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
    }
    .table-students img { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
    .table-students .student-name { font-weight: 600; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Siswa</h1>
                <p class="lead mb-0 opacity-75">Kelas: <?php echo htmlspecialchars($nama_kelas); ?></p>
            </div>
            <div class="d-flex mt-3 mt-sm-0">
                <a href="kelas_tampil.php" class="btn btn-outline-light me-2"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
                <a href="siswa_tambah.php?id_kelas=<?php echo $id_kelas; ?>" class="btn btn-light"><i class="bi bi-person-plus-fill me-2"></i>Tambah Siswa</a>
            </div>
        </div>
    </div>

    <div class="info-panel p-3 mb-4 d-flex flex-wrap justify-content-start align-items-center">
        <div class="me-4 mb-2 mb-md-0">
            <small class="text-muted">Wali Kelas</small>
            <div class="fw-bold fs-5"><i class="bi bi-person-check-fill text-success me-2"></i><?php echo $nama_walikelas; ?></div>
        </div>
        <div class="border-start ps-3">
            <small class="text-muted">Jumlah Siswa Aktif</small>
            <div class="fw-bold fs-5"><i class="bi bi-people-fill text-primary me-2"></i><?php echo $jumlah_siswa; ?> Siswa</div>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2" style="color: var(--primary-color);"></i>Daftar Siswa</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped table-students align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 5%;">No</th>
                            <th colspan="2" class="ps-3">Nama Lengkap</th>
                            <th>NIS</th>
                            <th>NISN</th>
                            <th>Guru Wali</th>
                            <th class="text-center">Aksi</th>
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
                                <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                <td style="width: 50px;">
                                    <?php if (!empty($siswa['foto_siswa'])): ?>
                                        <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle fs-2 text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="ps-3">
                                    <div class="student-name"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                                <td><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                                <td>
                                    <?php echo $siswa['nama_guru_wali'] ? htmlspecialchars($siswa['nama_guru_wali']) : '<i class="text-muted">Belum Ditetapkan</i>'; ?>
                                </td>
                                <td class="text-center">
                                    <a href="siswa_edit.php?id=<?php echo $siswa['id_siswa']; ?>" class="btn btn-warning btn-sm" data-bs-toggle="tooltip" title="Edit Siswa">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="#" onclick="hapusSiswa(<?php echo $siswa['id_siswa']; ?>, <?php echo $id_kelas; ?>)" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" title="Hapus Siswa">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center p-5 text-muted'>Belum ada siswa di kelas ini.</td></tr>";
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

function hapusSiswa(idSiswa, idKelas) {
    Swal.fire({
        title: 'Anda yakin?',
        text: "Data siswa ini akan dihapus permanen, termasuk semua nilai yang terhubung!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'siswa_aksi.php?aksi=hapus&id=' + idSiswa + '&id_kelas=' + idKelas;
        }
    })
}
</script>

<?php
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire(" . $_SESSION['pesan'] . ");</script>";
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>
