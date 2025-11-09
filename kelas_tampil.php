<?php
include 'header.php';
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil semua tahun ajaran untuk filter
$query_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran, status FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
$daftar_ta = mysqli_fetch_all($query_ta, MYSQLI_ASSOC);

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

// Ambil data kelas berdasarkan tahun ajaran yang dipilih
$query_kelas = "
    SELECT 
        k.id_kelas, k.nama_kelas, k.fase, 
        g.nama_guru, 
        ta.tahun_ajaran,
        (SELECT COUNT(id_siswa) FROM siswa s WHERE s.id_kelas = k.id_kelas) as jumlah_siswa
    FROM kelas k 
    LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
    LEFT JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran 
    WHERE k.id_tahun_ajaran = ?
    ORDER BY k.nama_kelas ASC";
$stmt = mysqli_prepare($koneksi, $query_kelas);
mysqli_stmt_bind_param($stmt, "i", $id_ta_terpilih);
mysqli_stmt_execute($stmt);
$result_kelas = mysqli_stmt_get_result($stmt);
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }

    .class-card {
        transition: all 0.2s ease-in-out;
        border: 1px solid var(--border-color);
    }
    .class-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: var(--primary-color);
    }
    .class-card .card-header {
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
        font-size: 1.2rem;
    }
    .class-card .wali-info {
        font-size: 0.9rem;
    }
    .class-card .wali-info .bi {
        color: var(--secondary-color);
    }
    .class-card .class-stats {
        background-color: #f8f9fa;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Kelas & Siswa</h1>
                <p class="lead mb-0 opacity-75">Kelola daftar kelas, wali kelas, dan siswa per tahun ajaran.</p>
            </div>
            <div class="d-flex mt-3 mt-sm-0">
                <!-- [BARU] Tombol Import -->
                <button type="button" class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#importKelasModal">
                    <i class="bi bi-upload me-2"></i>Import Kelas
                </button>
                <a href="kelas_tambah.php" class="btn btn-outline-light"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Kelas Baru</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="kelas_tampil.php" method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="id_ta" class="form-label fw-bold">Tampilkan Kelas untuk Tahun Ajaran:</label>
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

    <div class="row g-4">
        <?php if (mysqli_num_rows($result_kelas) > 0): ?>
            <?php while ($data = mysqli_fetch_assoc($result_kelas)): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card h-100 shadow-sm class-card">
                        <div class="card-header text-center">
                            <?php echo htmlspecialchars($data['nama_kelas']); ?>
                        </div>
                        <div class="card-body">
                            <div class="wali-info mb-3">
                                <p class="mb-1"><strong>Wali Kelas:</strong></p>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-circle fs-3 me-2"></i>
                                    <span><?php echo htmlspecialchars($data['nama_guru'] ?? '<em class="text-muted">Belum Ditentukan</em>'); ?></span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small class="text-muted">Tahun Ajaran</small>
                                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($data['tahun_ajaran']); ?></p>
                                </div>
                                <div>
                                    <small class="text-muted">Fase</small>
                                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($data['fase']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center class-stats">
                            <div class="fw-bold text-primary">
                                <i class="bi bi-people-fill me-1"></i>
                                <?php echo $data['jumlah_siswa']; ?> Siswa
                            </div>
                            <div class="btn-group">
                                <a href="siswa_tampil.php?id_kelas=<?php echo $data['id_kelas']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Lihat & Kelola Siswa">
                                    <i class="bi bi-people-fill"></i> Kelola Siswa
                                </a>
                                <a href="kelas_edit.php?id=<?php echo $data['id_kelas']; ?>" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Edit Kelas">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <a href="#" onclick="hapusKelas(<?php echo $data['id_kelas']; ?>)" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Hapus Kelas">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card card-body text-center py-5">
                    <i class="bi bi-door-closed fs-1 text-muted"></i>
                    <h4 class="mt-3">Tidak Ada Kelas</h4>
                    <p class="text-muted">Belum ada data kelas yang dibuat untuk tahun ajaran yang dipilih.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- [BARU] Modal untuk Import Kelas -->
<div class="modal fade" id="importKelasModal" tabindex="-1" aria-labelledby="importKelasModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importKelasModalLabel"><i class="bi bi-upload me-2"></i> Import Data Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="kelas_aksi.php?aksi=import_kelas" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Petunjuk:</strong>
                        <ol class="mb-0 ps-3">
                            <li>Unduh template Excel yang disediakan.</li>
                            <li>Isi data kelas sesuai format pada sheet <strong>"Import"</strong>.</li>
                            <li>Gunakan sheet <strong>"Daftar Guru"</strong> untuk copy-paste username wali kelas.</li>
                            <li>Upload file yang sudah diisi.</li>
                        </ol>
                    </div>
                    
                    <a href="template_kelas.php" class="btn btn-success w-100 mb-3">
                        <i class="bi bi-file-earmark-excel-fill me-2"></i>Unduh Template Import Kelas
                    </a>

                    <hr>

                    <label for="file_kelas" class="form-label fw-bold">Pilih File Excel (.xlsx)</label>
                    <input type="file" class="form-control" name="file_kelas" id="file_kelas" accept=".xlsx" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Upload dan Import</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
function hapusKelas(id) {
    Swal.fire({
        title: 'Anda yakin?',
        html: "Menghapus kelas akan melepaskan semua siswa dari kelas ini dan menghapus semua data terkait penilaian di kelas ini! <strong>Tindakan ini tidak dapat dibatalkan.</strong>",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, saya mengerti. Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'kelas_aksi.php?aksi=hapus&id=' + id;
        }
    })
}

// Inisialisasi Tooltip Bootstrap
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
if (isset($_SESSION['pesan'])) {
    // [DIUBAH] Cek apakah pesan berupa JSON (dari import) atau teks biasa
    $pesan = $_SESSION['pesan'];
    if (json_decode($pesan) !== null) {
        echo "<script>Swal.fire(" . $pesan . ");</script>";
    } else {
        echo "<script>Swal.fire({icon: 'success', title: 'Berhasil!', text: '" . addslashes($pesan) . "'});</script>";
    }
    unset($_SESSION['pesan']);
}


// Cek jika ada pesan error dan tampilkan SweetAlert error
if (isset($_SESSION['pesan_error'])) {
    echo "<script>Swal.fire({icon: 'error', title: 'Gagal!', text: '" . addslashes($_SESSION['pesan_error']) . "'});</script>";
    unset($_SESSION['pesan_error']);
}
include 'footer.php';
?>
