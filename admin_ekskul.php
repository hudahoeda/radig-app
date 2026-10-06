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

// ---------------------------------------------------------
// LOGIKA PROGRESS PENILAIAN EKSKUL
// ---------------------------------------------------------
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

$q_progres_ekskul = mysqli_query($koneksi, "
    SELECT
        e.id_ekskul,
        e.nama_ekskul,
        g.nama_guru,
        (SELECT COUNT(id_peserta_ekskul) FROM ekskul_peserta WHERE id_ekskul = e.id_ekskul) AS jml_siswa,
        (SELECT COUNT(id_tujuan_ekskul) FROM ekskul_tujuan WHERE id_ekskul = e.id_ekskul AND semester = $semester_aktif) AS jml_tujuan,
        (
            SELECT COUNT(p.id_penilaian_ekskul)
            FROM ekskul_penilaian p
            JOIN ekskul_peserta ep ON p.id_peserta_ekskul = ep.id_peserta_ekskul
            JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul
            WHERE ep.id_ekskul = e.id_ekskul AND t.semester = $semester_aktif
        ) AS realisasi_nilai
    FROM ekstrakurikuler e
    LEFT JOIN guru g ON e.id_pembina = g.id_guru
    WHERE e.id_tahun_ajaran = $id_tahun_ajaran
    ORDER BY e.nama_ekskul ASC
");

$data_progres = [];
$rekap_belum_selesai = [];
while ($row = mysqli_fetch_assoc($q_progres_ekskul)) {
    $target = $row['jml_siswa'] * $row['jml_tujuan'];
    $realisasi = $row['realisasi_nilai'];

    if ($row['jml_siswa'] == 0 || $row['jml_tujuan'] == 0) {
        $persen = 0;
        $status_text = "Belum Siap (Siswa/TP Kosong)";
        $kurang = "Setup Tdk Lengkap";
    } else {
        $persen = ($target > 0) ? round(($realisasi / $target) * 100) : 0;
        $persen = min(100, $persen);
        $status_text = "$realisasi / $target Input";
        $kurang = $target - $realisasi;
    }

    $row['persen'] = $persen;
    $row['status_text'] = $status_text;

    if ($persen < 100) {
        $rekap_belum_selesai[] = [
            'ekskul' => $row['nama_ekskul'],
            'pembina' => $row['nama_guru'] ?? 'Belum ada pembina',
            'persen' => $persen,
            'kurang' => ($row['jml_siswa'] == 0 || $row['jml_tujuan'] == 0) ? "Belum set TP/Siswa" : "$kurang Input"
        ];
    }

    $data_progres[] = $row;
}

// Generate Teks WA
$teks_wa = "*PENGUMUMAN PROGRESS PENILAIAN EKSKUL*\n";
$teks_wa .= "Semester: $semester_aktif | Tahun Ajaran: $nama_tahun_ajaran\n\n";

if (empty($rekap_belum_selesai) && count($data_progres) > 0) {
    $teks_wa .= "✅ Alhamdulillah, semua pembina ekskul sudah 100% selesai input nilai sumatif!\n";
} else if (count($data_progres) == 0) {
    $teks_wa .= "Belum ada ekskul yang terdaftar.\n";
} else {
    $teks_wa .= "Berikut adalah daftar Ekskul yang *BELUM 100%* menyelesaikan input nilai sumatif. Mohon segera diselesaikan:\n\n";
    foreach ($rekap_belum_selesai as $m) {
        $teks_wa .= "❌ " . $m['ekskul'] . " (" . $m['pembina'] . ") - Progres: " . $m['persen'] . "% (" . $m['kurang'] . ")\n";
    }
    $teks_wa .= "\nTerima kasih atas kerjasamanya. 🙏";
}
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

    <!-- SECTION PROGRESS PENILAIAN EKSKUL -->
    <div class="row mt-3 mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold" style="color: var(--primary-color);">
                        <i class="bi bi-bar-chart-steps me-2"></i> Pantau Progres Penilaian Ekskul
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-outline-success me-2 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRekapWA">
                            <i class="bi bi-whatsapp me-1"></i> Rekap WA
                        </button>
                        <a href="admin_ekskul_progres_pdf.php" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill shadow-sm">
                            <i class="bi bi-file-pdf me-1"></i> Cetak PDF
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4" width="5%">No</th>
                                    <th width="30%">Nama Ekstrakurikuler</th>
                                    <th width="20%">Pembina</th>
                                    <th class="text-center" width="15%">Data (Siswa/TP)</th>
                                    <th width="30%">Progres Pengisian Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data_progres)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada ekskul untuk tahun ajaran ini.</td></tr>
                                <?php else: ?>
                                    <?php $no=1; foreach($data_progres as $p):
                                        // Tentukan warna progress bar
                                        $bg_color = 'bg-danger';
                                        if ($p['persen'] >= 50 && $p['persen'] < 100) $bg_color = 'bg-warning text-dark';
                                        if ($p['persen'] == 100) $bg_color = 'bg-success';
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted"><?php echo $no++; ?></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['nama_ekskul']); ?></td>
                                        <td><?php echo htmlspecialchars($p['nama_guru'] ?? 'Belum ada'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border"><i class="bi bi-people me-1"></i><?php echo $p['jml_siswa']; ?></span>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-bullseye me-1"></i><?php echo $p['jml_tujuan']; ?></span>
                                        </td>
                                        <td class="pe-4">
                                            <div class="d-flex justify-content-between mb-1 small">
                                                <span class="fw-bold <?php echo ($p['persen']==100)?'text-success':'text-muted'; ?>">
                                                    <?php echo $p['persen']; ?>%
                                                </span>
                                                <span class="text-muted" style="font-size:0.75rem;"><?php echo $p['status_text']; ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar <?php echo $bg_color; ?> progress-bar-striped <?php echo ($p['persen']<100)?'progress-bar-animated':''; ?>" role="progressbar" style="width: <?php echo $p['persen']; ?>%" aria-valuenow="<?php echo $p['persen']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
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
        </div>
    </div>
</div>

<!-- MODAL REKAP WA -->
<div class="modal fade" id="modalRekapWA" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-whatsapp me-2"></i>Rekap Progres Belum Selesai (Ekskul)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark">Teks Siap Salin (Copy-Paste) ke Grup WA:</h6>
                    <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="copyToWA()">
                        <i class="bi bi-clipboard me-1"></i>Salin Teks
                    </button>
                </div>
                <textarea id="teksRekapWA" class="form-control border-success mb-4" rows="12" readonly style="font-family: monospace; font-size: 0.9rem; background-color: #f8f9fc; resize: none;"><?php echo htmlspecialchars($teks_wa); ?></textarea>

                <div class="alert border-primary bg-primary bg-opacity-10 mb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-info-circle-fill text-primary me-2"></i>
                        <strong>Butuh laporan formal?</strong> Unduh versi cetak resmi.
                    </div>
                    <a href="admin_ekskul_progres_pdf.php" target="_blank" class="btn btn-sm btn-primary shadow-sm rounded-pill px-3"><i class="bi bi-file-pdf me-1"></i>Cetak PDF</a>
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

// Fungsi Copy to WA
function copyToWA() {
    const copyText = document.getElementById("teksRekapWA");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");

    Swal.fire({
        icon: 'success',
        title: 'Tersalin!',
        text: 'Teks laporan berhasil disalin. Silakan paste di grup WhatsApp.',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>

<?php include 'footer.php'; ?>