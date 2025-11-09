<?php
include 'koneksi.php';
include 'header.php';

// Pastikan hanya GURU yang bisa mengakses halaman ini, bukan admin
if ($_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak', text: 'Halaman ini hanya untuk Guru.'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID guru yang sedang login dari sesi
$id_guru_login = $_SESSION['id_guru'];

// Ambil id mapel dan kelas dari URL
$id_mapel = isset($_GET['id_mapel']) ? (int)$_GET['id_mapel'] : 0;
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;

// Ambil tahun ajaran aktif untuk query
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta_aktif)['id_tahun_ajaran'] ?? 0;
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .card-title i { color: var(--primary-color); }
    .penilaian-group-header { font-weight: 600; color: var(--secondary-color); padding-bottom: 0.5rem; border-bottom: 2px solid var(--secondary-color); margin-top: 2.5rem; margin-bottom: 1.5rem; }
    .deskripsi-rapor-cell { max-width: 400px; min-width: 300px; white-space: normal; font-size: 0.85em; }
</style>

<div class="container-fluid">
<?php
// =============================================
// TAMPILAN 1: FORM FILTER JIKA MAPEL/KELAS BELUM DIPILIH
// =============================================
if ($id_mapel == 0 || $id_kelas == 0) :

    // [Logika untuk Tampilan 1 - tidak diubah]
    // ... (kode yang sudah ada untuk memilih kelas/mapel) ...
    $penugasan_by_mapel = [];
    $total_mapel_ditugaskan = 0;
    $total_kelas_diampu = 0;
    $ada_penugasan_dasar = false;

    if ($id_tahun_ajaran_aktif > 0) {
        $stmt_dasar = mysqli_prepare($koneksi, "
            SELECT COUNT(DISTINCT id_mapel) as total_mapel, COUNT(DISTINCT id_kelas) as total_kelas
            FROM guru_mengajar WHERE id_guru = ? AND id_tahun_ajaran = ?
        ");
        mysqli_stmt_bind_param($stmt_dasar, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
        mysqli_stmt_execute($stmt_dasar);
        $result_dasar = mysqli_stmt_get_result($stmt_dasar);
        if ($data_dasar = mysqli_fetch_assoc($result_dasar)) {
            $total_mapel_ditugaskan = $data_dasar['total_mapel'];
            $total_kelas_diampu = $data_dasar['total_kelas'];
        }
        $ada_penugasan_dasar = ($total_mapel_ditugaskan > 0);
        mysqli_stmt_close($stmt_dasar);

        if ($ada_penugasan_dasar) {
            $query_dropdown = "
                SELECT mp.id_mapel, mp.nama_mapel, k.id_kelas, k.nama_kelas
                FROM guru_mengajar gm
                JOIN mata_pelajaran mp ON gm.id_mapel = mp.id_mapel
                JOIN kelas k ON gm.id_kelas = k.id_kelas
                WHERE gm.id_guru = ? AND gm.id_tahun_ajaran = ?
                ORDER BY mp.nama_mapel, k.nama_kelas
            ";
            $stmt_dropdown = mysqli_prepare($koneksi, $query_dropdown);
            mysqli_stmt_bind_param($stmt_dropdown, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
            mysqli_stmt_execute($stmt_dropdown);
            $result_dropdown = mysqli_stmt_get_result($stmt_dropdown);
            while ($row = mysqli_fetch_assoc($result_dropdown)) {
                $penugasan_by_mapel[$row['id_mapel']]['nama_mapel'] = $row['nama_mapel'];
                $penugasan_by_mapel[$row['id_mapel']]['kelas'][] = [
                    'id_kelas' => $row['id_kelas'],
                    'nama_kelas' => $row['nama_kelas']
                ];
            }
            mysqli_stmt_close($stmt_dropdown);
        }
    }

    $q_total_penilaian = mysqli_query($koneksi, "SELECT COUNT(id_penilaian) as total FROM penilaian WHERE id_guru = $id_guru_login");
    $total_penilaian_dibuat = mysqli_fetch_assoc($q_total_penilaian)['total'];
?>
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Bank Nilai Akademik</h1>
        <p class="lead mb-0 opacity-75">Kelola semua penilaian dan input nilai siswa per kelas.</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-start border-primary border-4">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-journal-bookmark-fill fs-1 text-primary me-3"></i>
                    <div>
                        <div class="text-muted">Total Mapel Ditugaskan</div>
                        <div class="fs-4 fw-bold"><?php echo $total_mapel_ditugaskan; ?> Mapel</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-start border-success border-4">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-door-open-fill fs-1 text-success me-3"></i>
                    <div>
                        <div class="text-muted">Total Kelas Diampu</div>
                        <div class="fs-4 fw-bold"><?php echo $total_kelas_diampu; ?> Kelas</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-start border-info border-4">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-card-checklist fs-1 text-info me-3"></i>
                    <div>
                        <div class="text-muted">Total Penilaian Dibuat</div>
                        <div class="fs-4 fw-bold"><?php echo $total_penilaian_dibuat; ?> Penilaian</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm filter-card">
        <div class="card-body p-4 p-md-5">
             <div class="text-center mb-4">
                <h3 class="card-title"><i class="bi bi-filter-circle-fill text-primary me-2"></i>Pilih Mata Pelajaran & Kelas</h3>
                <p class="text-muted">Untuk melanjutkan, silakan pilih mata pelajaran dan kelas yang Anda ampu.</p>
            </div>
            
            <?php
            $cek_tp_q = mysqli_query($koneksi, "SELECT 1 FROM tujuan_pembelajaran WHERE id_guru_pembuat = $id_guru_login AND id_tahun_ajaran = $id_tahun_ajaran_aktif LIMIT 1");
            $ada_tp_dibuat = mysqli_num_rows($cek_tp_q) > 0;

            if ($ada_penugasan_dasar && !$ada_tp_dibuat) {
            ?>
                <div class="alert alert-info mt-4 text-center">
                    <h4 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Langkah Selanjutnya!</h4>
                    <p>Anda sudah terdaftar mengajar, namun sistem belum menemukan <strong>Tujuan Pembelajaran (TP)</strong> yang Anda buat untuk tahun ajaran ini.</p>
                    <hr>
                    <p class="mb-0">Silakan buat TP terlebih dahulu di menu <strong>"Kelola TP Saya"</strong> agar dapat melanjutkan ke tahap penilaian.</p>
                    <a href="tp_guru_tampil.php" class="btn btn-primary mt-3"><i class="bi bi-card-checklist me-2"></i> Buka Menu Kelola TP Saya</a>
                </div>
            <?php
            } elseif (!empty($penugasan_by_mapel)) {
            ?>
                 <form action='penilaian_tampil.php' method='GET' class="mt-4">
                    <div class='row justify-content-center'>
                        <div class='col-md-5 mb-3'>
                            <label for="id_mapel" class="form-label fw-bold">Mata Pelajaran</label>
                            <select class='form-select form-select-lg' id='id_mapel' name='id_mapel' required>
                                <option value='' disabled selected>-- Pilih Mata Pelajaran --</option>
                                <?php
                                foreach ($penugasan_by_mapel as $id_m => $data_mapel) {
                                    echo "<option value='{$id_m}'>" . htmlspecialchars($data_mapel['nama_mapel']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class='col-md-5 mb-3'>
                            <label for="id_kelas" class="form-label fw-bold">Kelas</label>
                            <select class='form-select form-select-lg' id='id_kelas' name='id_kelas' required disabled>
                                <option value='' disabled selected>-- Pilih Kelas --</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type='submit' class='btn btn-primary btn-lg mt-3' id="tombolTampilkan" disabled><i class="bi bi-arrow-right-circle-fill me-2"></i>Tampilkan Penilaian</button>
                    </div>
                </form>
            <?php
            } else {
            ?>
                <div class="alert alert-warning mt-4 text-center">
                    <strong>Penugasan Kosong!</strong><br>
                    Anda belum ditugaskan untuk mengajar di kelas manapun pada tahun ajaran aktif ini. Silakan hubungi Administrator.
                </div>
            <?php
            }
            ?>
        </div>
    </div>
<?php
// =============================================
// TAMPILAN 2: DAFTAR PENILAIAN SETELAH MAPEL/KELAS DIPILIH
// =============================================
else:
    // Ambil data kelas dan mapel
    $q_kelas = mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas=$id_kelas");
    $d_kelas = mysqli_fetch_assoc($q_kelas);
    $q_mapel = mysqli_query($koneksi, "SELECT nama_mapel FROM mata_pelajaran WHERE id_mapel=$id_mapel");
    $d_mapel = mysqli_fetch_assoc($q_mapel);

    // Ambil KKM
    $q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
    $kkm_db = mysqli_fetch_assoc($q_kkm);
    $kkm = $kkm_db ? (int)$kkm_db['nilai_pengaturan'] : 75;
    
    // (Fungsi hitungDataRaporSiswa tetap sama seperti di file Anda)
    function hitungDataRaporSiswa($koneksi, $id_siswa, $id_kelas, $id_mapel, $kkm) {
        $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
        $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

        $stmt_sumatif_tp = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian, GROUP_CONCAT(tp.deskripsi_tp SEPARATOR '|||') as deskripsi_tps
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian JOIN penilaian_tp ptp ON p.id_penilaian = ptp.id_penilaian
            JOIN tujuan_pembelajaran tp ON ptp.id_tp = tp.id_tp WHERE p.subjenis_penilaian = 'Sumatif TP' AND pdn.id_siswa = ? AND p.id_mapel = ? 
            AND p.id_kelas = ? AND p.semester = ? GROUP BY p.id_penilaian, pdn.nilai, p.bobot_penilaian
        ");
        $stmt_sumatif_akhir = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian WHERE p.subjenis_penilaian IN ('Sumatif Akhir Semester', 'Sumatif Akhir Tahun')
            AND p.jenis_penilaian = 'Sumatif' AND pdn.id_siswa = ? AND p.id_mapel = ? AND p.id_kelas = ? AND p.semester = ?
        ");
        
        $skor_per_tp = []; $total_nilai_x_bobot = 0; $total_bobot = 0;

        mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_tp);
        $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
        while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
            $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
            foreach($tps_individu as $desc_tp) {
                if (!isset($skor_per_tp[$desc_tp])) { $skor_per_tp[$desc_tp] = []; }
                $skor_per_tp[$desc_tp][] = $d_nilai['nilai'];
            }
            $total_nilai_x_bobot += $d_nilai['nilai'] * $d_nilai['bobot_penilaian'];
            $total_bobot += $d_nilai['bobot_penilaian'];
        }

        mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_akhir);
        $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
        while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
            $total_nilai_x_bobot += $d_nilai_akhir['nilai'] * $d_nilai_akhir['bobot_penilaian'];
            $total_bobot += $d_nilai_akhir['bobot_penilaian'];
        }

        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;
        
        $deskripsi_final = '';
        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
            $tp_dikuasai = []; $tp_perlu_peningkatan = [];
            foreach ($skor_per_tp as $deskripsi => $skor_array) {
                $rata_rata_tp = array_sum($skor_array) / count($skor_array);
                $deskripsi_bersih = lcfirst(trim(str_replace(['Peserta didik dapat', 'peserta didik mampu', 'mampu'], '', $deskripsi)));
                if ($rata_rata_tp >= $kkm) { $tp_dikuasai[] = $deskripsi_bersih; } else { $tp_perlu_peningkatan[] = $deskripsi_bersih; }
            }
            $deskripsi_draf = "";
            if (!empty($tp_dikuasai)) { $deskripsi_draf .= "Menunjukkan penguasaan yang baik dalam " . implode(', ', array_unique($tp_dikuasai)) . ". "; }
            if (!empty($tp_perlu_peningkatan)) { $deskripsi_draf .= "Perlu penguatan dalam " . implode(', ', array_unique($tp_perlu_peningkatan)) . "."; }
            $deskripsi_final = (empty(trim($deskripsi_draf))) ? 'Capaian kompetensi sudah baik pada seluruh materi.' : ucfirst(trim($deskripsi_draf));
        } elseif ($nilai_akhir !== null) {
            $deskripsi_final = 'Capaian kompetensi secara umum sudah menunjukkan ketuntasan yang baik.';
        }
        return ['nilai_akhir' => $nilai_akhir, 'deskripsi' => $deskripsi_final];
    }
    
    // Ambil daftar penilaian
    $query = "SELECT p.*, GROUP_CONCAT(tp.deskripsi_tp SEPARATOR ', ') AS deskripsi_tp FROM penilaian p
              LEFT JOIN penilaian_tp pt ON p.id_penilaian = pt.id_penilaian LEFT JOIN tujuan_pembelajaran tp ON pt.id_tp = tp.id_tp
              WHERE p.id_kelas = ? AND p.id_mapel = ? AND p.id_guru = ? GROUP BY p.id_penilaian
              ORDER BY p.jenis_penilaian, p.tanggal_penilaian DESC, p.id_penilaian DESC";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "iii", $id_kelas, $id_mapel, $id_guru_login);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $penilaian_dikelompokkan = ['Sumatif' => [], 'Formatif' => []];
    while ($data = mysqli_fetch_assoc($result)) {
        $penilaian_dikelompokkan[$data['jenis_penilaian']][] = $data;
    }
?>
    <!-- ============================================= -->
    <!-- === BAGIAN HEADER HALAMAN BARU === -->
    <!-- ============================================= -->
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1"><?php echo htmlspecialchars($d_mapel['nama_mapel']); ?></h1>
                <p class="lead mb-0 opacity-75">Bank Nilai - Kelas <?php echo htmlspecialchars($d_kelas['nama_kelas']); ?></p>
            </div>
            <div class="d-flex mt-3 mt-sm-0">
                <a href="penilaian_tampil.php" class="btn btn-outline-light me-2"><i class="bi bi-arrow-left-right me-2"></i>Ganti</a>
                <a href="penilaian_tambah.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-light"><i class="bi bi-plus-circle-fill me-2"></i>Buat Penilaian</a>
            </div>
        </div>
        <hr>
        <!-- === TOMBOL IMPORT/EXPORT BATCH BARU === -->
        <div class="mt-3 d-flex flex-wrap gap-2">
            <a href="penilaian_excel_template_batch.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-light">
                <i class="bi bi-download me-2"></i>Download Template Kelas
            </a>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalImportBatch">
                <i class="bi bi-upload me-2"></i>Import Nilai Kelas
            </button>
        </div>
    </div>

    <!-- Modal Batch Import -->
    <div class="modal fade" id="modalImportBatch" tabindex="-1" aria-labelledby="modalImportBatchLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="modalImportBatchLabel">Import Nilai (Batch)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form action="penilaian_aksi.php?aksi=import_nilai_batch" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
                    <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">
                    <p>Pilih file template Excel (yang diunduh dari tombol "Download Template Kelas") yang sudah diisi nilai siswa.</p>
                    <div class="mb-3">
                        <label for="file_excel" class="form-label">File Excel (.xlsx)</label>
                        <input class="form-control" type="file" name="file_excel" id="file_excel" accept=".xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Unggah dan Proses</button>
                </div>
              </form>
            </div>
        </div>
    </div>
    <!-- ============================================= -->
    <!-- === AKHIR BAGIAN BARU === -->
    <!-- ============================================= -->


    <?php foreach ($penilaian_dikelompokkan as $jenis => $daftar_penilaian): ?>
        <h3 class="penilaian-group-header">
            <i class="bi <?php echo $jenis == 'Sumatif' ? 'bi-bar-chart-line-fill' : 'bi-check-circle-fill'; ?> me-2"></i>
            Daftar Penilaian <?php echo $jenis; ?>
        </h3>
        
        <?php if (empty($daftar_penilaian)): ?>
            <div class="card card-body text-center"><p class="text-muted mb-0">Belum ada penilaian jenis <strong><?php echo $jenis; ?></strong> yang dibuat.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th>Nama Penilaian</th>
                            <th style="width: 35%;">Tujuan Pembelajaran (TP)</th>
                            <th class="text-center" style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($daftar_penilaian as $data): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td>
                                <strong class="d-block"><?php echo htmlspecialchars($data['nama_penilaian']); ?></strong>
                                <?php if($data['jenis_penilaian'] == 'Sumatif' && !empty($data['subjenis_penilaian'])): ?>
                                    <small class="text-muted fst-italic">(<?php echo htmlspecialchars($data['subjenis_penilaian']); ?> - Bobot: <?php echo $data['bobot_penilaian']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($data['deskripsi_tp'])) {
                                    echo htmlspecialchars($data['deskripsi_tp']);
                                } else {
                                    echo '<i class="text-muted">— Penilaian non-TP (e.g., Sumatif Akhir Semester) —</i>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="penilaian_input_nilai.php?id_penilaian=<?php echo $data['id_penilaian']; ?>" class="btn btn-success btn-sm" title="Input atau Lihat Nilai Siswa"><i class="bi bi-input-cursor-text me-1"></i> Input Nilai</a>
                                    <a href="penilaian_aksi.php?aksi=hapus_penilaian&id_penilaian=<?php echo $data['id_penilaian']; ?>" class="btn btn-outline-danger btn-sm btn-hapus" data-nama="<?php echo htmlspecialchars($data['nama_penilaian']); ?>" title="Hapus Penilaian Ini"><i class="bi bi-trash-fill"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    // (Sisa kode untuk Ringkasan Nilai Rapor tidak diubah)
    $q_siswa_kelas = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif' ORDER BY nama_lengkap");
    $daftar_siswa = mysqli_fetch_all($q_siswa_kelas, MYSQLI_ASSOC);

    $q_sumatif_kelas = mysqli_query($koneksi, "SELECT id_penilaian, nama_penilaian FROM penilaian WHERE id_kelas = $id_kelas AND id_mapel = $id_mapel AND jenis_penilaian = 'Sumatif' ORDER BY tanggal_penilaian, id_penilaian");
    $daftar_sumatif = mysqli_fetch_all($q_sumatif_kelas, MYSQLI_ASSOC);
    
    $nilai_semua_siswa = [];
    if (!empty($daftar_sumatif)) {
        $id_penilaian_list = array_column($daftar_sumatif, 'id_penilaian');
        $id_penilaian_str = implode(',', $id_penilaian_list);
        if(!empty($id_penilaian_str)) {
            $q_nilai = mysqli_query($koneksi, "SELECT id_siswa, id_penilaian, nilai FROM penilaian_detail_nilai WHERE id_penilaian IN ($id_penilaian_str)");
            while($row = mysqli_fetch_assoc($q_nilai)) {
                $nilai_semua_siswa[$row['id_siswa']][$row['id_penilaian']] = $row['nilai'];
            }
        }
    }
    ?>

    <h3 class="penilaian-group-header">
        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>
        Ringkasan Nilai Akhir & Rapor (Satu Kelas)
    </h3>

    <?php if (empty($daftar_siswa) || empty($daftar_sumatif)): ?>
        <div class="card card-body text-center">
            <p class="text-muted mb-0">Data siswa atau penilaian sumatif belum cukup untuk menampilkan ringkasan nilai rapor.</p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" style="font-size: 0.9em;">
                        <thead class="table-light text-center">
                            <tr>
                                <th rowspan="2" class="align-middle">No</th>
                                <th rowspan="2" class="align-middle">Nama Siswa</th>
                                <th colspan="<?php echo count($daftar_sumatif); ?>">Nilai Sumatif</th>
                                <th rowspan="2" class="align-middle bg-success-subtle">Nilai Rapor</th>
                                <th rowspan="2" class="align-middle bg-success-subtle">Deskripsi Capaian Kompetensi (Rapor)</th>
                            </tr>
                            <tr>
                                <?php foreach ($daftar_sumatif as $sumatif) : ?>
                                    <th class="fw-normal" style="min-width: 100px;"><?php echo htmlspecialchars($sumatif['nama_penilaian']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($daftar_siswa as $siswa) : ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td style="min-width: 200px;"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <?php foreach ($daftar_sumatif as $sumatif) :
                                        $nilai_siswa = $nilai_semua_siswa[$siswa['id_siswa']][$sumatif['id_penilaian']] ?? '-';
                                        $text_color = ($nilai_siswa !== '-' && $nilai_siswa < $kkm) ? 'text-danger fw-bold' : '';
                                    ?>
                                        <td class="text-center <?php echo $text_color; ?>"><?php echo $nilai_siswa; ?></td>
                                    <?php endforeach; ?>
                                    
                                    <?php
                                    $data_rapor = hitungDataRaporSiswa($koneksi, $siswa['id_siswa'], $id_kelas, $id_mapel, $kkm);
                                    $nilai_rapor = $data_rapor['nilai_akhir'];
                                    $deskripsi_rapor = $data_rapor['deskripsi'];
                                    
                                    $badge_class = 'bg-secondary';
                                    if ($nilai_rapor !== null) {
                                        if ($nilai_rapor < $kkm) { $badge_class = 'bg-danger'; }
                                        else { $badge_class = 'bg-success'; }
                                    }
                                    ?>
                                    <td class="text-center fw-bold">
                                        <span class="badge <?php echo $badge_class; ?> fs-6">
                                            <?php echo $nilai_rapor ?? 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td class="deskripsi-rapor-cell">
                                        <?php echo htmlspecialchars($deskripsi_rapor); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapelSelect = document.getElementById('id_mapel');
    if (mapelSelect) {
        // (Logika JS untuk Tampilan 1 - tidak diubah)
        const kelasSelect = document.getElementById('id_kelas');
        const tombolTampilkan = document.getElementById('tombolTampilkan');
        const penugasan = <?php echo json_encode($penugasan_by_mapel, JSON_INVALID_UTF8_IGNORE); ?>;

        mapelSelect.addEventListener('change', function() {
            const idMapelTerpilih = this.value;
            kelasSelect.innerHTML = '<option value="" disabled selected>-- Pilih Kelas --</option>';
            kelasSelect.disabled = true;
            tombolTampilkan.disabled = true;
            if (idMapelTerpilih && penugasan[idMapelTerpilih]) {
                penugasan[idMapelTerpilih].kelas.forEach(function(kelas) {
                    const option = document.createElement('option');
                    option.value = kelas.id_kelas;
                    option.textContent = kelas.nama_kelas;
                    kelasSelect.appendChild(option);
                });
                kelasSelect.disabled = false;
            }
        });
        kelasSelect.addEventListener('change', function() {
            tombolTampilkan.disabled = !this.value;
        });
    }

    // (Logika JS untuk tombol hapus - tidak diubah)
    const tombolHapus = document.querySelectorAll('.btn-hapus');
    tombolHapus.forEach(function(tombol) {
        tombol.addEventListener('click', function(event) {
            event.preventDefault();
            const href = this.getAttribute('href');
            const namaPenilaian = this.getAttribute('data-nama');
            Swal.fire({
                title: 'Anda Yakin?',
                html: `Penilaian "<b>${namaPenilaian}</b>" dan semua nilai siswa di dalamnya akan dihapus secara permanen.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus Saja!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });
    });
});
</script>

<?php
// (Logika JS untuk menampilkan pesan session - tidak diubah)
if (isset($_SESSION['pesan'])) {
    $pesan_json = $_SESSION['pesan'];
    if (strpos($pesan_json, '{') === 0) {
        $pesan_data = json_decode($pesan_json, true);
        echo "<script>Swal.fire({icon: '".($pesan_data['icon'] ?? 'info')."', title: '".($pesan_data['title'] ?? 'Pemberitahuan')."', text: '".($pesan_data['text'] ?? '')."'});</script>";
    } else {
        echo "<script>Swal.fire('Berhasil!','" . addslashes($pesan_json) . "','success');</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>
