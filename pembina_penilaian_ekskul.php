<?php
// --- DEBUG MODE (Matikan jika sudah produksi) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ----------------------------------------------------------------------

include 'header.php';
include 'koneksi.php';

// Validasi Koneksi Database
if (!$koneksi) {
    die("<div class='alert alert-danger'>Kesalahan Koneksi Database: " . mysqli_connect_error() . "</div>");
}

// Validasi role Guru
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini hanya untuk guru.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_pembina = $_SESSION['id_guru'];

// Ambil info tahun ajaran aktif sesuai skema SQL: kolom 'tahun_ajaran'
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
if (!$q_ta) {
    die("<div class='alert alert-danger'>Query Gagal (Tahun Ajaran): " . mysqli_error($koneksi) . "</div>");
}
$ta_data = mysqli_fetch_assoc($q_ta);

if (!$ta_data) {
    echo "<div class='container py-5'><div class='alert alert-warning shadow-sm'><i class='bi bi-exclamation-triangle me-2'></i>Tahun ajaran aktif tidak ditemukan.</div></div>";
    include 'footer.php';
    exit;
}
$id_tahun_ajaran = $ta_data['id_tahun_ajaran'];

// Ambil semester aktif dari tabel pengaturan
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
if (!$q_smt) {
    die("<div class='alert alert-danger'>Query Gagal (Semester): " . mysqli_error($koneksi) . "</div>");
}
$res_smt = mysqli_fetch_assoc($q_smt);
$semester_aktif = $res_smt ? $res_smt['nilai_pengaturan'] : 1;

// Ambil daftar ekskul yang dibina
$q_ekskul_list = mysqli_query($koneksi, "SELECT id_ekskul, nama_ekskul FROM ekstrakurikuler WHERE id_pembina = $id_pembina AND id_tahun_ajaran = $id_tahun_ajaran");
if (!$q_ekskul_list) {
    die("<div class='alert alert-danger'>Query Gagal (Daftar Ekskul): " . mysqli_error($koneksi) . "</div>");
}

$daftar_ekskul = [];
while ($row_e = mysqli_fetch_assoc($q_ekskul_list)) {
    $daftar_ekskul[] = $row_e;
}

// Tentukan ekskul terpilih
$id_ekskul_terpilih = isset($_GET['ekskul_id']) ? $_GET['ekskul_id'] : null;

if (!empty($daftar_ekskul)) {
    if (!$id_ekskul_terpilih) {
        $id_ekskul_terpilih = $daftar_ekskul[0]['id_ekskul'];
    }
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, #4e73df, #224abe);
        padding: 2.5rem 2rem;
        border-radius: 1rem;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 10px 20px rgba(78, 115, 223, 0.2);
    }

    .objective-header {
        min-width: 180px;
        max-width: 250px;
        white-space: normal;
        font-size: 0.85rem;
        vertical-align: middle !important;
    }

    .table-assessment .sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
        background-color: #fff;
        min-width: 220px;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    }

    .value-buttons {
        display: flex;
        justify-content: center;
        gap: 6px;
    }

    .value-buttons .btn-nilai {
        border-radius: 10px;
        width: 40px;
        height: 40px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid #e3e6f0;
        background: white;
        color: #858796;
        padding: 0;
    }

    .value-buttons .btn-nilai:hover {
        transform: translateY(-2px);
        border-color: #4e73df;
        color: #4e73df;
    }

    .value-buttons .btn-nilai.active[data-value="Sangat Baik"] { background: #1cc88a; color: white; border-color: #1cc88a; }
    .value-buttons .btn-nilai.active[data-value="Baik"] { background: #4e73df; color: white; border-color: #4e73df; }
    .value-buttons .btn-nilai.active[data-value="Cukup"] { background: #f6c23e; color: white; border-color: #f6c23e; }
    .value-buttons .btn-nilai.active[data-value="Kurang"] { background: #e74a3b; color: white; border-color: #e74a3b; }

    .class-group-header {
        background-color: #f8f9fc !important;
        color: #4e73df;
        font-weight: 800;
        padding: 1rem !important;
        border-left: 5px solid #4e73df !important;
    }

    .row-error {
        background-color: #fff5f5 !important;
    }
    
    .floating-save {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
    }
</style>

<div class="container-fluid py-4">
    <div class="page-header d-md-flex justify-content-between align-items-center">
        <div>
            <h1 class="h2 fw-bold mb-1">Penilaian Ekstrakurikuler</h1>
            <p class="mb-0 opacity-75">Tahun Ajaran: <?= htmlspecialchars($ta_data['tahun_ajaran']) ?> | Semester: <?= $semester_aktif ?></p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php if ($id_ekskul_terpilih): ?>
                <a href="pembina_penilaian_aksi.php?aksi=unduh_template&ekskul_id=<?= $id_ekskul_terpilih ?>&semester=<?= $semester_aktif ?>" class="btn btn-light shadow-sm fw-bold">
                    <i class="bi bi-file-earmark-excel-fill me-2 text-success"></i>Template Excel
                </a>
                <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalImport">
                    <i class="bi bi-cloud-upload-fill me-2"></i>Impor Massal
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Navigasi Ekskul -->
    <?php if (count($daftar_ekskul) > 1) : ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-2">
                <ul class="nav nav-pills nav-fill">
                    <?php foreach ($daftar_ekskul as $ekskul) : ?>
                        <li class="nav-item">
                            <a class="nav-link py-3 <?= ($ekskul['id_ekskul'] == $id_ekskul_terpilih) ? 'active' : 'text-dark'; ?>" 
                               href="?ekskul_id=<?= $ekskul['id_ekskul']; ?>">
                                <i class="bi bi-star-fill me-2"></i><?= htmlspecialchars($ekskul['nama_ekskul']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($id_ekskul_terpilih):
        // Ambil TP dari tabel ekskul_tujuan
        $q_tujuan = mysqli_query($koneksi, "SELECT id_tujuan_ekskul, deskripsi_tujuan FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul_terpilih AND semester = $semester_aktif ORDER BY id_tujuan_ekskul");
        if (!$q_tujuan) {
            die("<div class='alert alert-danger'>Query Gagal (TP): " . mysqli_error($koneksi) . "</div>");
        }
        $daftar_tujuan = [];
        while ($row_t = mysqli_fetch_assoc($q_tujuan)) {
            $daftar_tujuan[] = $row_t;
        }

        // Ambil Peserta
        $q_peserta = mysqli_query($koneksi, "
            SELECT p.id_peserta_ekskul, s.nama_lengkap, s.nis, k.nama_kelas 
            FROM ekskul_peserta p 
            JOIN siswa s ON p.id_siswa = s.id_siswa 
            JOIN kelas k ON s.id_kelas = k.id_kelas
            WHERE p.id_ekskul = $id_ekskul_terpilih 
            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC
        ");
        if (!$q_peserta) {
            die("<div class='alert alert-danger'>Query Gagal (Peserta): " . mysqli_error($koneksi) . "</div>");
        }

        $peserta_per_kelas = [];
        while ($p = mysqli_fetch_assoc($q_peserta)) {
            $peserta_per_kelas[$p['nama_kelas']][] = $p;
        }

        // Ambil Nilai Ada
        $data_penilaian = [];
        $q_nilai_ada = mysqli_query($koneksi, "SELECT id_peserta_ekskul, id_tujuan_ekskul, nilai FROM ekskul_penilaian WHERE id_peserta_ekskul IN (SELECT id_peserta_ekskul FROM ekskul_peserta WHERE id_ekskul = $id_ekskul_terpilih)");
        if ($q_nilai_ada) {
            while ($n = mysqli_fetch_assoc($q_nilai_ada)) {
                $data_penilaian[$n['id_peserta_ekskul']][$n['id_tujuan_ekskul']] = $n['nilai'];
            }
        }

        // Ambil Kehadiran Ada
        $data_kehadiran = [];
        $total_pertemuan_umum = 0;
        $q_hadir_ada = mysqli_query($koneksi, "SELECT id_peserta_ekskul, jumlah_hadir, total_pertemuan FROM ekskul_kehadiran WHERE semester = $semester_aktif AND id_peserta_ekskul IN (SELECT id_peserta_ekskul FROM ekskul_peserta WHERE id_ekskul = $id_ekskul_terpilih)");
        if ($q_hadir_ada) {
            while ($h = mysqli_fetch_assoc($q_hadir_ada)) {
                $data_kehadiran[$h['id_peserta_ekskul']] = $h['jumlah_hadir'];
                if ($h['total_pertemuan'] > 0) $total_pertemuan_umum = $h['total_pertemuan'];
            }
        }
    ?>

        <?php if (empty($daftar_tujuan)): ?>
            <div class="alert alert-danger shadow-sm border-0 py-4 text-center">
                <i class="bi bi-exclamation-octagon fs-1 d-block mb-2"></i>
                <strong>Tujuan Pembelajaran Belum Dibuat!</strong><br>
                Silakan buat TP di menu 'Kelola Ekskul' untuk semester <?= $semester_aktif ?> ini agar penilaian dapat dilakukan.
            </div>
        <?php elseif (empty($peserta_per_kelas)): ?>
            <div class="alert alert-warning shadow-sm border-0 py-4 text-center">
                <i class="bi bi-people-fill fs-1 d-block mb-2"></i>
                <strong>Belum Ada Peserta!</strong><br>
                Belum ada siswa yang terdaftar di ekstrakurikuler ini.
            </div>
        <?php else: ?>

            <form id="formPenilaian" action="pembina_penilaian_aksi.php?aksi=simpan_penilaian" method="POST">
                <input type="hidden" name="id_ekskul" value="<?= $id_ekskul_terpilih; ?>">
                <input type="hidden" name="semester" value="<?= $semester_aktif; ?>">

                <div class="card shadow-sm border-0 overflow-hidden mb-5">
                    <div class="card-header bg-white py-3 d-md-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square me-2"></i>Input Nilai Siswa</h5>
                        <div class="input-group mt-2 mt-md-0" style="max-width: 320px;">
                            <span class="input-group-text bg-light fw-bold border-0"><i class="bi bi-calendar-event me-2"></i>Total Sesi</span>
                            <input type="number" id="total_pertemuan" name="total_pertemuan_umum" class="form-control border-0 bg-light" value="<?= $total_pertemuan_umum; ?>" placeholder="cth: 16">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-assessment" id="tabelNilai">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th class="sticky-col py-3">Nama Siswa</th>
                                    <th width="140">Kehadiran</th>
                                    <?php foreach ($daftar_tujuan as $tujuan): ?>
                                        <th class="objective-header">
                                            <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($tujuan['deskripsi_tujuan']) ?>">
                                                TP <?= $tujuan['id_tujuan_ekskul'] ?> <i class="bi bi-info-circle small opacity-50"></i>
                                            </span>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($peserta_per_kelas as $nama_kelas => $daftar_peserta): ?>
                                    <tr>
                                        <td colspan="<?= 2 + count($daftar_tujuan); ?>" class="class-group-header">
                                            <i class="bi bi-door-closed me-2"></i>Kelas: <?= htmlspecialchars($nama_kelas); ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($daftar_peserta as $peserta): 
                                        $id_p = $peserta['id_peserta_ekskul'];
                                    ?>
                                        <tr class="siswa-row" data-nama="<?= htmlspecialchars($peserta['nama_lengkap']); ?>">
                                            <td class="sticky-col">
                                                <div class="fw-bold"><?= htmlspecialchars($peserta['nama_lengkap']); ?></div>
                                                <div class="small text-muted">NIS: <?= $peserta['nis'] ?></div>
                                            </td>
                                            <td>
                                                <input type="number" name="kehadiran[<?= $id_p; ?>]" 
                                                       class="form-control form-control-sm text-center input-kehadiran" 
                                                       value="<?= isset($data_kehadiran[$id_p]) ? $data_kehadiran[$id_p] : ''; ?>" placeholder="Hadir">
                                            </td>
                                            <?php foreach ($daftar_tujuan as $tujuan): 
                                                $id_t = $tujuan['id_tujuan_ekskul'];
                                                $nilai = isset($data_penilaian[$id_p][$id_t]) ? $data_penilaian[$id_p][$id_t] : '';
                                            ?>
                                                <td class="text-center">
                                                    <input type="hidden" name="penilaian[<?= $id_p; ?>][<?= $id_t; ?>]" 
                                                           value="<?= $nilai; ?>" class="input-nilai">
                                                    <div class="value-buttons" data-peserta="<?= $id_p; ?>" data-tujuan="<?= $id_t; ?>">
                                                        <button type="button" class="btn btn-nilai <?= ($nilai == 'Sangat Baik') ? 'active' : ''; ?>" data-value="Sangat Baik" title="Sangat Baik">SB</button>
                                                        <button type="button" class="btn btn-nilai <?= ($nilai == 'Baik') ? 'active' : ''; ?>" data-value="Baik" title="Baik">B</button>
                                                        <button type="button" class="btn btn-nilai <?= ($nilai == 'Cukup') ? 'active' : ''; ?>" data-value="Cukup" title="Cukup">C</button>
                                                        <button type="button" class="btn btn-nilai <?= ($nilai == 'Kurang') ? 'active' : ''; ?>" data-value="Kurang" title="Kurang">K</button>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="floating-save">
                    <button type="button" id="btnSimpan" class="btn btn-primary btn-lg rounded-pill shadow-lg px-5">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Modal Impor Excel -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Impor Nilai Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="pembina_penilaian_aksi.php?aksi=impor_excel" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_ekskul" value="<?= $id_ekskul_terpilih ?>">
                <input type="hidden" name="semester" value="<?= $semester_aktif ?>">
                <div class="modal-body py-4">
                    <div class="alert alert-info small border-0">
                        <i class="bi bi-info-circle-fill me-2"></i> Gunakan file Excel (.xlsx) hasil unduhan "Template Excel". Jangan mengubah urutan kolom atau ID_PESERTA.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih File Excel (.xlsx)</label>
                        <input type="file" name="file_excel" class="form-control" accept=".xlsx" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">Mulai Unggah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Init Tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Logika Klik Tombol Nilai
        document.querySelectorAll('.value-buttons').forEach(group => {
            group.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-nilai')) {
                    const button = e.target;
                    const val = button.dataset.value;
                    const idPeserta = group.dataset.peserta;
                    const idTujuan = group.dataset.tujuan;
                    const input = document.querySelector(`input[name="penilaian[${idPeserta}][${idTujuan}]"]`);
                    
                    group.querySelectorAll('.btn-nilai').forEach(btn => btn.classList.remove('active'));
                    
                    if (input.value === val) {
                        input.value = ''; 
                    } else {
                        input.value = val;
                        button.classList.add('active');
                    }
                }
            });
        });

        // Tombol Simpan
        const btnSimpan = document.getElementById('btnSimpan');
        if (btnSimpan) {
            btnSimpan.addEventListener('click', function() {
                const totalSesi = document.getElementById('total_pertemuan').value;
                if (!totalSesi || totalSesi <= 0) {
                    Swal.fire('Validasi', 'Mohon isi Total Sesi Pertemuan.', 'warning');
                    return;
                }

                let valid = true;
                let errorSiswa = [];
                const rows = document.querySelectorAll('.siswa-row');

                rows.forEach(row => {
                    const nama = row.dataset.nama;
                    const hadir = row.querySelector('.input-kehadiran').value;
                    const inputsNilai = row.querySelectorAll('.input-nilai');
                    
                    let filledNilai = 0;
                    inputsNilai.forEach(inp => { if(inp.value !== "") filledNilai++; });

                    if (hadir !== "" || filledNilai > 0) {
                        if (hadir === "" || filledNilai < inputsNilai.length) {
                            valid = false;
                            row.classList.add('row-error');
                            errorSiswa.push(nama);
                        } else {
                            row.classList.remove('row-error');
                        }
                    }
                });

                if (!valid) {
                    Swal.fire({
                        title: 'Data Belum Lengkap',
                        html: 'Siswa ini belum lengkap (Hadir & Semua TP wajib):<br><small class="text-danger">' + errorSiswa.join(', ') + '</small>',
                        icon: 'warning'
                    });
                } else {
                    Swal.fire({
                        title: 'Simpan Nilai?',
                        text: 'Data lengkap akan dikirim ke server.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Simpan!'
                    }).then((res) => {
                        if (res.isConfirmed) {
                            Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                            document.getElementById('formPenilaian').submit();
                        }
                    });
                }
            });
        }
    });
</script>

<?php
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire('Berhasil!', '" . addslashes($_SESSION['pesan']) . "', 'success');</script>";
    unset($_SESSION['pesan']);
}
if (isset($_SESSION['error'])) {
    echo "<script>Swal.fire('Gagal!', '" . addslashes($_SESSION['error']) . "', 'error');</script>";
    unset($_SESSION['error']);
}
include 'footer.php';
?>