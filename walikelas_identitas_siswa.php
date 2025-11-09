<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Anda harus login sebagai Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil data kelas yang diampu oleh Wali Kelas yang sedang login
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1)");
mysqli_stmt_bind_param($q_kelas, "i", $id_wali_kelas);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);

$id_kelas = $kelas['id_kelas'] ?? null;
$nama_kelas = $kelas['nama_kelas'] ?? 'Anda tidak terdaftar sebagai wali kelas';

$daftar_siswa = [];
if ($id_kelas) {
    // Ambil juga foto siswa
    $q_siswa = mysqli_prepare($koneksi, "SELECT id_siswa, nis, nisn, nama_lengkap, foto_siswa FROM siswa WHERE id_kelas = ? ORDER BY nama_lengkap ASC");
    mysqli_stmt_bind_param($q_siswa, "i", $id_kelas);
    mysqli_stmt_execute($q_siswa);
    $result_siswa = mysqli_stmt_get_result($q_siswa);
    while ($row = mysqli_fetch_assoc($result_siswa)) {
        $daftar_siswa[] = $row;
    }
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
    
    .table-students .student-avatar {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .table-students .student-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid var(--border-color);
    }
    .table-students .student-avatar .icon-placeholder {
        font-size: 40px;
        color: #adb5bd; /* Bootstrap's gray-500 */
    }

    .table-students td {
        vertical-align: middle;
    }
    .table-students tbody tr {
        border-bottom: 1px solid var(--border-color);
    }
    .table-students tbody tr:last-child {
        border-bottom: none;
    }
    .table-students .student-name {
        font-weight: 600;
        color: var(--text-dark);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Kelola Identitas Siswa</h1>
                <p class="lead mb-0 opacity-75">Kelas: <?php echo htmlspecialchars($nama_kelas); ?></p>
            </div>
        </div>
    </div>

    <?php if ($id_kelas): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-people-fill me-2" style="color: var(--primary-color);"></i>Daftar Siswa di Kelas Anda</h5>
            <div class="w-50" style="max-width: 300px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama siswa...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-students mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 5%;">No</th>
                            <th class="text-center" style="width: 10%;">Foto</th>
                            <th>Nama Siswa / NISN</th>
                            <th class="text-center" style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <?php if (empty($daftar_siswa)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Belum ada siswa di kelas ini.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($daftar_siswa as $siswa): ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                <td>
                                    <div class="student-avatar mx-auto">
                                    <?php if (!empty($siswa['foto_siswa'])): ?>
                                        <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle icon-placeholder"></i>
                                    <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="student-name"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                    <small class="text-muted">NIS: <?php echo htmlspecialchars($siswa['nis']); ?> | NISN: <?php echo htmlspecialchars($siswa['nisn']); ?></small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="walikelas_edit_siswa.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Edit Identitas Siswa">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="walikelas_pindah_siswa.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-outline-info btn-sm" data-bs-toggle="tooltip" title="Proses Pindah/Mutasi Siswa">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
            <h3 class="mt-3">Akses Ditolak</h3>
            <p class="text-muted">Anda tidak terdaftar sebagai wali kelas pada tahun ajaran aktif ini.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi Tooltip Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Logika untuk Pencarian Real-time
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('studentTableBody');
    const rows = tableBody.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        for (let i = 0; i < rows.length; i++) {
            let nameCell = rows[i].getElementsByTagName('td')[2]; // Kolom ke-3 (Nama Siswa)
            if (nameCell) {
                let textValue = nameCell.textContent || nameCell.innerText;
                if (textValue.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
    });
});
</script>

<?php
include 'footer.php';
?>