<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Guru
if ($_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini hanya untuk guru.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_pembina = $_SESSION['id_guru'];

// Ambil info tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

// Ambil semua ekstrakurikuler yang dibina oleh guru yang login pada tahun ajaran aktif
$q_ekskul_dibina = mysqli_prepare($koneksi, "SELECT id_ekskul, nama_ekskul FROM ekstrakurikuler WHERE id_pembina = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_ekskul_dibina, "ii", $id_pembina, $id_tahun_ajaran);
mysqli_stmt_execute($q_ekskul_dibina);
$result_ekskul = mysqli_stmt_get_result($q_ekskul_dibina);
$daftar_ekskul = mysqli_fetch_all($result_ekskul, MYSQLI_ASSOC);
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }

    .ekskul-card .card-header {
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
        color: var(--text-muted);
    }
    .nav-tabs .nav-link.active {
        color: var(--primary-color);
        border-color: var(--primary-color) var(--primary-color) #fff;
        border-bottom-width: 3px;
        font-weight: 600;
    }
    .tujuan-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
        background-color: #fff;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Kelola Tujuan Ekstrakurikuler</h1>
        <p class="lead mb-0 opacity-75">Tentukan tujuan pembelajaran untuk setiap ekstrakurikuler yang Anda bina.</p>
    </div>

    <?php if (!empty($daftar_ekskul)): ?>
        <?php foreach ($daftar_ekskul as $ekskul):
            $id_ekskul = $ekskul['id_ekskul'];
        ?>
            <div class="card shadow-sm mb-4 ekskul-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-bicycle me-2"></i><?php echo htmlspecialchars($ekskul['nama_ekskul']); ?></h4>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="myTab-<?php echo $id_ekskul; ?>" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="smt1-tab-<?php echo $id_ekskul; ?>" data-bs-toggle="tab" data-bs-target="#smt1-content-<?php echo $id_ekskul; ?>" type="button" role="tab">Semester 1</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="smt2-tab-<?php echo $id_ekskul; ?>" data-bs-toggle="tab" data-bs-target="#smt2-content-<?php echo $id_ekskul; ?>" type="button" role="tab">Semester 2</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-4" id="myTabContent-<?php echo $id_ekskul; ?>">
                        <div class="tab-pane fade show active" id="smt1-content-<?php echo $id_ekskul; ?>" role="tabpanel">
                            <?php
                            $q_tujuan1 = mysqli_query($koneksi, "SELECT * FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul AND semester = 1 ORDER BY id_tujuan_ekskul ASC");
                            if (mysqli_num_rows($q_tujuan1) > 0) {
                                while ($tujuan = mysqli_fetch_assoc($q_tujuan1)) {
                                    echo '<div class="tujuan-item"><span>' . htmlspecialchars($tujuan['deskripsi_tujuan']) . '</span>';
                                    echo '<a href="pembina_ekskul_aksi.php?aksi=hapus_tujuan&id='.$tujuan['id_tujuan_ekskul'].'" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Yakin ingin menghapus tujuan ini?\')"><i class="bi bi-trash"></i></a></div>';
                                }
                            } else {
                                echo '<p class="text-muted fst-italic">Belum ada tujuan untuk semester 1.</p>';
                            }
                            ?>
                            <form action="pembina_ekskul_aksi.php?aksi=tambah_tujuan" method="POST" class="mt-3">
                                <input type="hidden" name="id_ekskul" value="<?php echo $id_ekskul; ?>">
                                <input type="hidden" name="semester" value="1">
                                <div class="input-group">
                                    <input type="text" name="deskripsi_tujuan" class="form-control" placeholder="Tulis tujuan baru untuk Semester 1..." required>
                                    <button class="btn btn-success" type="submit"><i class="bi bi-plus-lg"></i> Tambah</button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="smt2-content-<?php echo $id_ekskul; ?>" role="tabpanel">
                             <?php
                            $q_tujuan2 = mysqli_query($koneksi, "SELECT * FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul AND semester = 2 ORDER BY id_tujuan_ekskul ASC");
                            if (mysqli_num_rows($q_tujuan2) > 0) {
                                while ($tujuan = mysqli_fetch_assoc($q_tujuan2)) {
                                    echo '<div class="tujuan-item"><span>' . htmlspecialchars($tujuan['deskripsi_tujuan']) . '</span>';
                                    echo '<a href="pembina_ekskul_aksi.php?aksi=hapus_tujuan&id='.$tujuan['id_tujuan_ekskul'].'" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Yakin ingin menghapus tujuan ini?\')"><i class="bi bi-trash"></i></a></div>';
                                }
                            } else {
                                echo '<p class="text-muted fst-italic">Belum ada tujuan untuk semester 2.</p>';
                            }
                            ?>
                            <form action="pembina_ekskul_aksi.php?aksi=tambah_tujuan" method="POST" class="mt-3">
                                <input type="hidden" name="id_ekskul" value="<?php echo $id_ekskul; ?>">
                                <input type="hidden" name="semester" value="2">
                                <div class="input-group">
                                    <input type="text" name="deskripsi_tujuan" class="form-control" placeholder="Tulis tujuan baru untuk Semester 2..." required>
                                    <button class="btn btn-success" type="submit"><i class="bi bi-plus-lg"></i> Tambah</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                 <i class="bi bi-award fs-1 text-muted"></i>
                <h3 class="mt-3">Anda Tidak Membina Ekstrakurikuler Apapun</h3>
                <p class="text-muted">Tidak ada data ekstrakurikuler yang ditugaskan kepada Anda pada tahun ajaran aktif ini.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire('Informasi', '" . addslashes($_SESSION['pesan']) . "', 'info');</script>";
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>