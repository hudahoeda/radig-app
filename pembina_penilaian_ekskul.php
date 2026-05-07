<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Guru
if ($_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini hanya untuk guru.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_pembina = $_SESSION['id_guru'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

// Ambil daftar ekskul yang dibina oleh guru ini
$q_ekskul_list = mysqli_query($koneksi, "SELECT id_ekskul, nama_ekskul FROM ekstrakurikuler WHERE id_pembina = $id_pembina AND id_tahun_ajaran = $id_tahun_ajaran");
$daftar_ekskul = mysqli_fetch_all($q_ekskul_list, MYSQLI_ASSOC);

// Tentukan ekskul yang akan ditampilkan
$id_ekskul_terpilih = $_GET['ekskul_id'] ?? null;
$ekskul_terpilih_data = null;

if (!empty($daftar_ekskul)) {
    if (!$id_ekskul_terpilih) {
        $id_ekskul_terpilih = $daftar_ekskul[0]['id_ekskul'];
    }
    foreach ($daftar_ekskul as $ekskul) {
        if ($ekskul['id_ekskul'] == $id_ekskul_terpilih) {
            $ekskul_terpilih_data = $ekskul;
            break;
        }
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

    .page-header h1 {
        font-weight: 700;
    }

    .objective-header {
        min-width: 200px;
        max-width: 250px;
        white-space: normal;
        vertical-align: middle !important;
    }

    .table-assessment .sticky-col {
        position: -webkit-sticky;
        position: sticky;
        left: 0;
        z-index: 2;
        background-color: #fff;
        min-width: 200px;
    }

    .table-assessment thead {
        position: -webkit-sticky;
        position: sticky;
        top: 0;
        z-index: 3;
    }

    .value-buttons {
        display: flex;
        justify-content: center;
        gap: 5px;
    }

    .value-buttons .btn-nilai {
        border-radius: 50%;
        width: 38px;
        height: 38px;
        font-weight: 700;
        border: 2px solid transparent;
        transition: all 0.2s ease;
        padding: 0;
        line-height: 34px;
    }

    .value-buttons .btn-nilai:hover {
        transform: scale(1.1);
    }

    .value-buttons .btn-nilai.active {
        box-shadow: 0 0 0 3px var(--primary-color);
        border-color: white;
    }

    .class-group-header {
        background-color: #f2f2f2 !important;
        color: var(--primary-color);
        font-weight: bold;
        padding: 0.8rem 1rem;
    }

    /* Highlight baris yang error saat validasi */
    .row-error {
        background-color: #ffe6e6 !important;
    }

    .row-error td {
        background-color: #ffe6e6 !important;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow-sm">
        <div class="d-sm-flex justify-content-between align-items-start">
            <div>
                <h1 class="display-6 mb-1">Penilaian Ekstrakurikuler</h1>
                <?php if ($ekskul_terpilih_data): ?>
                    <p class="fs-5 text-muted mb-0">
                        <i class="bi bi-award-fill me-2"></i>
                        <?php echo htmlspecialchars($ekskul_terpilih_data['nama_ekskul']); ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if (count($daftar_ekskul) > 1) : ?>
                <ul class="nav nav-pills nav-fill mt-3 mt-sm-0">
                    <?php foreach ($daftar_ekskul as $ekskul) : ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($ekskul['id_ekskul'] == $id_ekskul_terpilih) ? 'active' : 'bg-white text-dark'; ?>" href="?ekskul_id=<?php echo $ekskul['id_ekskul']; ?>">
                                <?php echo htmlspecialchars($ekskul['nama_ekskul']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($id_ekskul_terpilih):
        $q_tujuan = mysqli_query($koneksi, "SELECT id_tujuan_ekskul, deskripsi_tujuan FROM ekskul_tujuan WHERE id_ekskul = $id_ekskul_terpilih AND semester = $semester_aktif ORDER BY id_tujuan_ekskul");
        $daftar_tujuan = mysqli_fetch_all($q_tujuan, MYSQLI_ASSOC);

        $q_peserta = mysqli_query($koneksi, "
            SELECT p.id_peserta_ekskul, s.nama_lengkap, k.nama_kelas 
            FROM ekskul_peserta p 
            JOIN siswa s ON p.id_siswa = s.id_siswa 
            JOIN kelas k ON s.id_kelas = k.id_kelas
            WHERE p.id_ekskul = $id_ekskul_terpilih 
            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC
        ");

        $peserta_per_kelas = [];
        if (mysqli_num_rows($q_peserta) > 0) {
            while ($peserta = mysqli_fetch_assoc($q_peserta)) {
                $peserta_per_kelas[$peserta['nama_kelas']][] = $peserta;
            }
        }

        $data_penilaian = [];
        $q_nilai_ada = mysqli_query($koneksi, "SELECT id_peserta_ekskul, id_tujuan_ekskul, nilai FROM ekskul_penilaian WHERE id_peserta_ekskul IN (SELECT id_peserta_ekskul FROM ekskul_peserta WHERE id_ekskul = $id_ekskul_terpilih)");
        while ($n = mysqli_fetch_assoc($q_nilai_ada)) {
            $data_penilaian[$n['id_peserta_ekskul']][$n['id_tujuan_ekskul']] = $n['nilai'];
        }
        $data_kehadiran = [];
        $q_hadir_ada = mysqli_query($koneksi, "SELECT id_peserta_ekskul, jumlah_hadir, total_pertemuan FROM ekskul_kehadiran WHERE semester = $semester_aktif AND id_peserta_ekskul IN (SELECT id_peserta_ekskul FROM ekskul_peserta WHERE id_ekskul = $id_ekskul_terpilih)");
        $total_pertemuan_umum = 0;
        while ($h = mysqli_fetch_assoc($q_hadir_ada)) {
            $data_kehadiran[$h['id_peserta_ekskul']] = $h['jumlah_hadir'];
            if ($h['total_pertemuan'] > 0) $total_pertemuan_umum = $h['total_pertemuan'];
        }
    ?>
        <form id="formPenilaian" action="pembina_penilaian_aksi.php?aksi=simpan_penilaian" method="POST">
            <input type="hidden" name="id_ekskul" value="<?php echo $id_ekskul_terpilih; ?>">
            <input type="hidden" name="semester" value="<?php echo $semester_aktif; ?>">

            <?php if (empty($daftar_tujuan)): ?>
                <div class="alert alert-danger text-center"><strong>Tujuan Pembelajaran Kosong!</strong><br>Silakan buat terlebih dahulu di menu 'Kelola Tujuan Ekskul' untuk semester ini.</div>
            <?php elseif (empty($peserta_per_kelas)): ?>
                <div class="alert alert-warning text-center"><strong>Peserta Kosong!</strong><br>Belum ada siswa yang didaftarkan pada ekstrakurikuler ini oleh wali kelas.</div>
            <?php else: ?>

                <!-- Pesan Informasi Validasi -->
                <div class="alert alert-info border-start border-info border-4 shadow-sm mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-check fs-3 me-3 text-info"></i>
                        <div>
                            <strong>Info Penyimpanan:</strong>
                            <br>Data siswa hanya akan tersimpan jika <b>Kehadiran</b> DIISI dan <b>Semua Nilai TP</b> DIISI (Lengkap).
                            <br><small class="text-muted">Jika data tidak lengkap, siswa tersebut tidak akan disimpan ke database untuk mencegah data korup.</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Input Nilai Semester <?php echo $semester_aktif; ?></h5>
                        <div class="input-group" style="width: 300px;">
                            <span class="input-group-text fw-bold"><i class="bi bi-calendar-check me-2"></i>Total Pertemuan</span>
                            <input type="number" id="total_pertemuan" name="total_pertemuan_umum" class="form-control" value="<?php echo $total_pertemuan_umum; ?>" placeholder="cth: 16">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-assessment mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th class="sticky-col">Nama Siswa</th>
                                        <th class="text-center">Kehadiran</th>
                                        <?php foreach ($daftar_tujuan as $tujuan): ?>
                                            <th class="objective-header">
                                                <?php echo htmlspecialchars($tujuan['deskripsi_tujuan']); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($peserta_per_kelas as $nama_kelas => $daftar_peserta): ?>
                                        <tr>
                                            <td colspan="<?php echo 2 + count($daftar_tujuan); ?>" class="class-group-header">
                                                <i class="bi bi-person-workspace me-2"></i> Kelas: <?php echo htmlspecialchars($nama_kelas); ?>
                                            </td>
                                        </tr>
                                        <?php foreach ($daftar_peserta as $peserta):
                                            $id_peserta = $peserta['id_peserta_ekskul'];
                                        ?>
                                            <tr class="siswa-row" data-siswa-nama="<?php echo htmlspecialchars($peserta['nama_lengkap']); ?>">
                                                <td class="sticky-col align-middle">
                                                    <strong><?php echo htmlspecialchars($peserta['nama_lengkap']); ?></strong>
                                                </td>
                                                <td class="align-middle" style="min-width: 120px;">
                                                    <input type="number" name="kehadiran[<?php echo $id_peserta; ?>]" class="form-control form-control-sm input-kehadiran" value="<?php echo $data_kehadiran[$id_peserta] ?? ''; ?>" placeholder="Hadir">
                                                </td>
                                                <?php foreach ($daftar_tujuan as $tujuan):
                                                    $id_tujuan = $tujuan['id_tujuan_ekskul'];
                                                    $nilai = $data_penilaian[$id_peserta][$id_tujuan] ?? '';
                                                ?>
                                                    <td class="align-middle text-center">
                                                        <input type="hidden" name="penilaian[<?php echo $id_peserta; ?>][<?php echo $id_tujuan; ?>]" value="<?php echo $nilai; ?>" class="input-nilai">
                                                        <div class="value-buttons" data-peserta="<?php echo $id_peserta; ?>" data-tujuan="<?php echo $id_tujuan; ?>">
                                                            <button type="button" class="btn btn-nilai btn-outline-success <?php echo ($nilai == 'Sangat Baik') ? 'active' : ''; ?>" data-value="Sangat Baik" title="Sangat Baik">SB</button>
                                                            <button type="button" class="btn btn-nilai btn-outline-primary <?php echo ($nilai == 'Baik') ? 'active' : ''; ?>" data-value="Baik" title="Baik">B</button>
                                                            <button type="button" class="btn btn-nilai btn-outline-warning <?php echo ($nilai == 'Cukup') ? 'active' : ''; ?>" data-value="Cukup" title="Cukup">C</button>
                                                            <button type="button" class="btn btn-nilai btn-outline-danger <?php echo ($nilai == 'Kurang') ? 'active' : ''; ?>" data-value="Kurang" title="Kurang">K</button>
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
                </div>
                <div class="mt-4 d-flex justify-content-end">
                    <button type="button" id="btnSimpan" class="btn btn-success btn-lg"><i class="bi bi-check-circle-fill me-2"></i> Simpan Semua Perubahan</button>
                </div>
        </form>
    <?php endif; ?>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Logika Klik Tombol Nilai (UI)
        const valueButtonGroups = document.querySelectorAll('.value-buttons');
        valueButtonGroups.forEach(group => {
            group.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-nilai')) {
                    const button = e.target;
                    const value = button.dataset.value;
                    const targetInput = document.querySelector(`input[name="penilaian[${group.dataset.peserta}][${group.dataset.tujuan}]"]`);
                    group.querySelectorAll('.btn-nilai').forEach(btn => btn.classList.remove('active'));
                    if (targetInput.value === value) {
                        targetInput.value = ''; // Toggle off
                    } else {
                        targetInput.value = value;
                        button.classList.add('active');
                    }
                }
            });
        });

        // 2. [BARU] Logika Validasi Sebelum Submit
        document.getElementById('btnSimpan').addEventListener('click', function() {
            let valid = true;
            let errorMsg = [];
            const rows = document.querySelectorAll('.siswa-row');
            const totalPertemuan = document.getElementById('total_pertemuan').value;

            // Reset style error sebelumnya
            rows.forEach(r => r.classList.remove('row-error'));

            if (!totalPertemuan || totalPertemuan <= 0) {
                Swal.fire('Validasi Gagal', 'Total Pertemuan belum diisi dengan benar.', 'warning');
                return;
            }

            let isAnyDataFilled = false;

            rows.forEach(row => {
                const namaSiswa = row.getAttribute('data-siswa-nama');
                const kehadiranInput = row.querySelector('.input-kehadiran');
                const nilaiInputs = row.querySelectorAll('.input-nilai');

                const kehadiranVal = kehadiranInput.value.trim();
                let filledNilaiCount = 0;
                nilaiInputs.forEach(input => {
                    if (input.value.trim() !== '') filledNilaiCount++;
                });

                const totalObjectives = nilaiInputs.length;
                const isHadirFilled = (kehadiranVal !== '');
                const isNilaiFilled = (filledNilaiCount > 0);
                const isAllNilaiFilled = (filledNilaiCount === totalObjectives);

                // Logika: 
                // 1. Jika kosong semua (Kehadiran & Nilai) -> OK (Dianggap belum dinilai/skip)
                // 2. Jika isi semua (Kehadiran & Semua Nilai) -> OK (Lengkap)
                // 3. Jika sebagian isi (Kehadiran ada tapi nilai kurang, atau sebaliknya) -> ERROR

                if (!isHadirFilled && !isNilaiFilled) {
                    // Baris kosong sepenuhnya, abaikan.
                    return;
                }

                isAnyDataFilled = true; // Ada setidaknya satu data yang mencoba disimpan

                if (isHadirFilled && isAllNilaiFilled) {
                    // Data lengkap, valid.
                    return;
                } else {
                    // Data tidak lengkap (Partial)
                    valid = false;
                    row.classList.add('row-error'); // Highlight baris merah

                    let detailKekurangan = [];
                    if (!isHadirFilled) detailKekurangan.push("Kehadiran");
                    if (!isAllNilaiFilled) detailKekurangan.push(`Nilai (${filledNilaiCount}/${totalObjectives} terisi)`);

                    errorMsg.push(`<b>${namaSiswa}</b>: ${detailKekurangan.join(' & ')} kurang.`);
                }
            });

            if (!isAnyDataFilled) {
                Swal.fire('Info', 'Belum ada data nilai atau kehadiran yang diisi sama sekali.', 'info');
                return;
            }

            if (valid) {
                // Submit form jika lolos validasi
                document.getElementById('formPenilaian').submit();
            } else {
                // Tampilkan error
                Swal.fire({
                    title: 'Data Belum Lengkap!',
                    html: `<div class="text-start">Beberapa siswa memiliki data yang belum lengkap (ditandai merah):<ul class="mt-2 mb-0 text-danger small text-start" style="max-height: 200px; overflow-y:auto;">${errorMsg.map(e => `<li>${e}</li>`).join('')}</ul><br>Mohon lengkapi Kehadiran DAN Semua Nilai TP untuk siswa tersebut sebelum menyimpan.</div>`,
                    icon: 'warning'
                });
            }
        });
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