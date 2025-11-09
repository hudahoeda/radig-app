<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Admin
if ($_SESSION['role'] !== 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil info tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
if (!$q_ta || mysqli_num_rows($q_ta) == 0) {
    die("Error: Tidak ada tahun ajaran yang aktif. Silakan atur di manajemen tahun ajaran.");
}
$d_ta = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran = $d_ta['id_tahun_ajaran'];
$nama_tahun_ajaran = $d_ta['tahun_ajaran'];

// Ambil semua data guru untuk pilihan dropdown pembina
$q_guru = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru WHERE role = 'guru' ORDER BY nama_guru ASC");

// Ambil semua data ekstrakurikuler yang sudah ada untuk tahun ajaran aktif
$q_ekskul = mysqli_query($koneksi, "
    SELECT ekskul.id_ekskul, ekskul.nama_ekskul, guru.nama_guru 
    FROM ekstrakurikuler AS ekskul
    LEFT JOIN guru ON ekskul.id_pembina = guru.id_guru
    WHERE ekskul.id_tahun_ajaran = $id_tahun_ajaran 
    ORDER BY ekskul.nama_ekskul ASC
");
?>

<style>
    /* Gaya konsisten untuk semua halaman */
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Ekstrakurikuler</h1>
                <p class="lead mb-0 opacity-75">Tahun Ajaran Aktif: <?php echo htmlspecialchars($nama_tahun_ajaran); ?></p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-list-stars me-2" style="color: var(--primary-color);"></i>
                        Daftar Ekstrakurikuler
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 5%;">No</th>
                                    <th>Nama Ekstrakurikuler</th>
                                    <th>Pembina</th>
                                    <th class="text-center" style="width: 15%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($q_ekskul) > 0) : ?>
                                    <?php $no = 1; while ($ekskul = mysqli_fetch_assoc($q_ekskul)) : ?>
                                        <tr>
                                            <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($ekskul['nama_ekskul']); ?></td>
                                            <td><?php echo htmlspecialchars($ekskul['nama_guru'] ?? '<i>Pembina Dihapus</i>'); ?></td>
                                            <td class="text-center">
    <a href="admin_ekskul_edit.php?id=<?php echo $ekskul['id_ekskul']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit Ekskul">
        <i class="bi bi-pencil-square"></i>
    </a>
    <a href="#" onclick="hapusEkskul(<?php echo $ekskul['id_ekskul']; ?>)" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Hapus Ekskul">
        <i class="bi bi-trash-fill"></i>
    </a>
</td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="text-center p-5 text-muted">Belum ada data ekstrakurikuler untuk tahun ajaran ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-plus-circle-dotted me-2" style="color: var(--primary-color);"></i>
                        Tambah Ekskul Baru
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="admin_ekskul_aksi.php?aksi=tambah" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="nama_ekskul" class="form-label fw-bold">Nama Ekstrakurikuler</label>
                            <input type="text" name="nama_ekskul" id="nama_ekskul" class="form-control" placeholder="Contoh: Pramuka" required>
                            <div class="invalid-feedback">Nama ekskul wajib diisi.</div>
                        </div>
                        <div class="mb-3">
                            <label for="id_pembina" class="form-label fw-bold">Pilih Pembina</label>
                            <select name="id_pembina" id="id_pembina" class="form-select" required>
                                <option value="" disabled selected>-- Pilih Guru Pembina --</option>
                                <?php mysqli_data_seek($q_guru, 0); // Reset pointer query guru ?>
                                <?php while ($guru = mysqli_fetch_assoc($q_guru)) : ?>
                                    <option value="<?php echo $guru['id_guru']; ?>"><?php echo htmlspecialchars($guru['nama_guru']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">Silakan pilih pembina.</div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle-fill me-2"></i>Tambahkan
                            </button>
                        </div>
                    </form>
                </div>
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

// Validasi Form Bootstrap
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})();

// Fungsi Hapus dengan SweetAlert
function hapusEkskul(id) {
    Swal.fire({
        title: 'Anda yakin?',
        text: "Ekskul ini akan dihapus. Semua data peserta dan nilai yang terhubung juga akan terhapus secara permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'admin_ekskul_aksi.php?aksi=hapus&id=' + id;
        }
    })
}
</script>

<?php include 'footer.php'; ?>