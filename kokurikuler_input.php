<?php
include 'header.php';
include 'koneksi.php';

// --- 1. Validasi & Inisialisasi ---
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_guru_login = (int)$_SESSION['id_guru'];
$role_login = $_SESSION['role'];

// Ambil Tahun Ajaran Aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran = isset($ta_aktif['id_tahun_ajaran']) ? $ta_aktif['id_tahun_ajaran'] : 0;

// Ambil Parameter URL
$id_kegiatan_pilih = isset($_GET['kegiatan']) ? (int)$_GET['kegiatan'] : 0;
// ID Kelas bisa string atau int tergantung database, kita ambil raw dulu lalu escape nanti
$id_kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : ''; 


// --- 2. Ambil Daftar Kegiatan (Logika Aman Collation) ---
$daftar_kegiatan = [];

if ($role_login == 'admin') {
    // Admin: Ambil semua kegiatan tahun ini
    $q_keg = "SELECT id_kegiatan, tema_kegiatan, semester, id_koordinator 
              FROM kokurikuler_kegiatan 
              WHERE id_tahun_ajaran = ? 
              ORDER BY semester, tema_kegiatan";
    $stmt = mysqli_prepare($koneksi, $q_keg);
    mysqli_stmt_bind_param($stmt, "i", $id_tahun_ajaran);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $daftar_kegiatan = mysqli_fetch_all($res, MYSQLI_ASSOC);
} else {
    // Guru: Ambil kegiatan dimana dia terlibat (Koordinator / Tim)
    // Kita gunakan DISTINCT dan LEFT JOIN
    $q_keg = "SELECT DISTINCT k.id_kegiatan, k.tema_kegiatan, k.semester, k.id_koordinator
              FROM kokurikuler_kegiatan k
              LEFT JOIN kokurikuler_tim_penilai kt ON k.id_kegiatan = kt.id_kegiatan
              WHERE k.id_tahun_ajaran = ? 
              AND (k.id_koordinator = ? OR kt.id_guru = ?)
              ORDER BY k.semester, k.tema_kegiatan";
    $stmt = mysqli_prepare($koneksi, $q_keg);
    mysqli_stmt_bind_param($stmt, "iii", $id_tahun_ajaran, $id_guru_login, $id_guru_login);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $daftar_kegiatan = mysqli_fetch_all($res, MYSQLI_ASSOC);
}


// --- 3. Ambil Daftar Kelas (Jika Kegiatan Dipilih) ---
$daftar_kelas = [];
$akses_kelas_diterima = false;

// Helper: Ambil Nama Kelas dari ID (Pisah Query agar aman dari error collation)
function getNamaKelas($koneksi, $arr_ids) {
    if (empty($arr_ids)) return [];
    
    // Escape IDs
    $safe_ids = array_map(function($id) use ($koneksi) {
        return "'" . mysqli_real_escape_string($koneksi, $id) . "'";
    }, $arr_ids);
    $str_ids = implode(',', $safe_ids);
    
    $q = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_kelas IN ($str_ids) ORDER BY nama_kelas ASC");
    return mysqli_fetch_all($q, MYSQLI_ASSOC);
}

if ($id_kegiatan_pilih > 0) {
    // Cek apakah user adalah Koordinator di kegiatan ini?
    $is_koordinator = false;
    foreach ($daftar_kegiatan as $k) {
        if ($k['id_kegiatan'] == $id_kegiatan_pilih && $k['id_koordinator'] == $id_guru_login) {
            $is_koordinator = true;
            break;
        }
    }

    $ids_kelas_target = [];

    if ($role_login == 'admin' || $is_koordinator) {
        // Admin/Koordinator lihat SEMUA kelas sasaran
        $q_ids = mysqli_query($koneksi, "SELECT id_kelas FROM kokurikuler_kelas_terlibat WHERE id_kegiatan = $id_kegiatan_pilih");
        while($r = mysqli_fetch_assoc($q_ids)) $ids_kelas_target[] = $r['id_kelas'];
    } else {
        // Anggota Tim lihat kelas yang ditugaskan saja
        $q_ids = mysqli_query($koneksi, "SELECT id_kelas FROM kokurikuler_tim_penilai WHERE id_kegiatan = $id_kegiatan_pilih AND id_guru = $id_guru_login");
        while($r = mysqli_fetch_assoc($q_ids)) $ids_kelas_target[] = $r['id_kelas'];
    }

    if (!empty($ids_kelas_target)) {
        $daftar_kelas = getNamaKelas($koneksi, $ids_kelas_target);
    }
}


// --- 4. Ambil Data Siswa & Dimensi (Jika Kelas Dipilih) ---
$daftar_siswa = [];
$daftar_dimensi = [];
$nilai_tersimpan = [];
$catatan_tersimpan = [];

if ($id_kegiatan_pilih > 0 && !empty($id_kelas_pilih)) {
    // A. Ambil Dimensi
    $q_dim = mysqli_query($koneksi, "SELECT id_target, nama_dimensi FROM kokurikuler_target_dimensi WHERE id_kegiatan = $id_kegiatan_pilih ORDER BY id_target ASC");
    if ($q_dim) {
        $daftar_dimensi = mysqli_fetch_all($q_dim, MYSQLI_ASSOC);
    }

    // B. Ambil Siswa
    $safe_id_kelas = mysqli_real_escape_string($koneksi, $id_kelas_pilih);
    $q_sis = mysqli_query($koneksi, "SELECT * FROM siswa WHERE id_kelas = '$safe_id_kelas' AND status_siswa = 'Aktif'");
    
    if ($q_sis) {
        while ($row = mysqli_fetch_assoc($q_sis)) {
            // Normalisasi Nama Siswa
            if (!isset($row['nama_siswa'])) {
                if (isset($row['nama_lengkap'])) $row['nama_siswa'] = $row['nama_lengkap'];
                elseif (isset($row['nama'])) $row['nama_siswa'] = $row['nama'];
                elseif (isset($row['nm_siswa'])) $row['nama_siswa'] = $row['nm_siswa'];
                else $row['nama_siswa'] = 'Siswa #' . $row['id_siswa'];
            }
            $daftar_siswa[] = $row;
        }
        // Sorting PHP
        usort($daftar_siswa, function($a, $b) {
            return strcasecmp($a['nama_siswa'], $b['nama_siswa']);
        });
    }

    // C. Ambil Nilai Existing
    if (!empty($daftar_dimensi)) {
        $target_ids = array_column($daftar_dimensi, 'id_target');
        $str_target = implode(',', $target_ids);
        
        $q_nilai = "SELECT id_target, id_siswa, nilai_kualitatif, catatan_guru 
                    FROM kokurikuler_asesmen 
                    WHERE id_target IN ($str_target) 
                    AND id_siswa IN (SELECT id_siswa FROM siswa WHERE id_kelas = '$safe_id_kelas')";
        
        $res_nilai = mysqli_query($koneksi, $q_nilai);
        if ($res_nilai) {
            while ($row = mysqli_fetch_assoc($res_nilai)) {
                $nilai_tersimpan[$row['id_siswa']][$row['id_target']] = $row['nilai_kualitatif'];
                $catatan_tersimpan[$row['id_siswa']][$row['id_target']] = $row['catatan_guru'];
            }
        }
    }
}
?>

<!-- Include Select2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* Styling UI Premium */
    .page-header { background: linear-gradient(135deg, var(--primary-color, #0d6efd), var(--secondary-color, #6610f2)); padding: 2rem; border-radius: 0.75rem; color: white; margin-bottom: 2rem; }
    .page-header h2 { font-weight: 700; margin-bottom: 0.5rem; }
    
    /* Table Styling with Sticky Headers */
    .table-container { max-height: 70vh; overflow: auto; position: relative; border: 1px solid #dee2e6; border-radius: 0.5rem; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
    .table-assessment { margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
    
    /* Sticky Column (Nama Siswa) */
    .sticky-col { position: sticky; left: 0; z-index: 10; background-color: #fff; width: 280px; min-width: 280px; border-right: 2px solid #dee2e6; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
    
    /* Sticky Header (Dimensi) */
    .table-assessment thead th { position: sticky; top: 0; z-index: 11; background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; }
    .table-assessment thead th.sticky-col { z-index: 12; background-color: #f8f9fa; } /* Corner cell needs highest z-index */
    
    /* Tombol Penilaian (Bulat & Modern) */
    .value-buttons { display: flex; gap: 6px; justify-content: center; margin-bottom: 6px; }
    .btn-nilai { 
        width: 38px; height: 38px; border-radius: 50%; padding: 0; 
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.85rem; border: 1px solid #dee2e6;
        background-color: #fff; color: #6c757d; transition: all 0.2s ease;
    }
    .btn-nilai:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    
    /* State Active (Mendeteksi Nilai Lengkap) */
    .btn-nilai.active { color: #fff; border-color: transparent; transform: scale(1.1); box-shadow: 0 0 0 3px rgba(0,0,0,0.1); }
    
    /* Warna Hijau untuk "Sangat Baik" */
    .btn-nilai.active[data-value="Sangat Baik"] { background-color: #198754; } 
    /* Warna Biru untuk "Baik" */
    .btn-nilai.active[data-value="Baik"] { background-color: #0d6efd; } 
    /* Warna Kuning untuk "Cukup" */
    .btn-nilai.active[data-value="Cukup"] { background-color: #ffc107; color: #000; } 
    /* Warna Merah untuk "Kurang" */
    .btn-nilai.active[data-value="Kurang"] { background-color: #dc3545; } 

    /* Input Catatan */
    .catatan-input { font-size: 0.8rem; border-radius: 0.5rem; border: 1px solid #e9ecef; background-color: #fcfcfc; transition: border-color 0.2s; }
    .catatan-input:focus { background-color: #fff; border-color: #86b7fe; box-shadow: none; }

    /* Floating Action Button */
    .fab-save { position: fixed; bottom: 30px; right: 30px; z-index: 1000; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
</style>

<div class="container-fluid mb-5">
    
    <!-- HEADER -->
    <div class="page-header shadow">
        <h2><i class="bi bi-journal-check me-2"></i>Input Asesmen Kokurikuler</h2>
        <p class="mb-0 opacity-75">Isi penilaian dimensi profil pelajar pancasila dengan mudah dan cepat.</p>
    </div>

    <!-- FILTER SECTION -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-light rounded-3 p-4">
            <form action="" method="GET" class="row g-3">
                <!-- Select Kegiatan -->
                <div class="col-md-5">
                    <label class="form-label fw-bold text-secondary text-uppercase small ls-1">1. Pilih Kegiatan Projek</label>
                    <select name="kegiatan" class="form-select select2" onchange="this.form.submit()">
                        <option value="">-- Cari Kegiatan --</option>
                        <?php foreach($daftar_kegiatan as $keg): ?>
                            <option value="<?php echo $keg['id_kegiatan']; ?>" <?php echo ($id_kegiatan_pilih == $keg['id_kegiatan']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($keg['tema_kegiatan']); ?> (Sem <?php echo $keg['semester']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select Kelas -->
                <div class="col-md-5">
                    <label class="form-label fw-bold text-secondary text-uppercase small ls-1">2. Pilih Kelas Sasaran</label>
                    <select name="kelas" class="form-select select2" onchange="this.form.submit()" <?php echo empty($daftar_kelas) ? 'disabled' : ''; ?>>
                        <option value="">-- Cari Kelas --</option>
                        <?php foreach($daftar_kelas as $kls): ?>
                            <option value="<?php echo htmlspecialchars($kls['id_kelas']); ?>" <?php echo ($id_kelas_pilih == $kls['id_kelas']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kls['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($id_kegiatan_pilih > 0 && empty($daftar_kelas)): ?>
                        <small class="text-danger mt-1 d-block"><i class="bi bi-exclamation-circle"></i> Anda tidak memiliki akses kelas di kegiatan ini.</small>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <a href="kokurikuler_pilih.php" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-left"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <?php if ($id_kegiatan_pilih > 0 && !empty($id_kelas_pilih) && !empty($daftar_siswa)): ?>
        
        <form action="kokurikuler_aksi.php?aksi=simpan_asesmen" method="POST" id="formPenilaian">
            <input type="hidden" name="id_kegiatan" value="<?php echo $id_kegiatan_pilih; ?>">
            <input type="hidden" name="id_kelas_redirect" value="<?php echo htmlspecialchars($id_kelas_pilih); ?>">

            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Lembar Kerja Penilaian</h5>
                        <small class="text-muted">Kelas: <?php 
                            // Cari nama kelas terpilih untuk display
                            foreach($daftar_kelas as $k) if($k['id_kelas'] == $id_kelas_pilih) echo $k['nama_kelas']; 
                        ?></small>
                    </div>
                    
                    <!-- Keterangan Legenda Baru -->
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-success" title="A - Sangat Baik">A : Sangat Baik</span>
                        <span class="badge bg-primary" title="B - Baik">B : Baik</span>
                        <span class="badge text-dark bg-warning" title="C - Cukup">C : Cukup</span>
                        <span class="badge bg-danger" title="D - Kurang">D : Kurang</span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-container">
                        <table class="table table-assessment table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="sticky-col align-middle p-3">Nama Siswa</th>
                                    <?php foreach ($daftar_dimensi as $dim): ?>
                                        <th class="text-center p-3" style="min-width: 260px;">
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="fw-bold mb-2"><?php echo htmlspecialchars($dim['nama_dimensi']); ?></span>
                                                <!-- Tombol Set Semua B (Baik) -->
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 btn-set-all" data-target="<?php echo $dim['id_target']; ?>" data-val="Baik">
                                                    <i class="bi bi-check2-all me-1"></i> Set Semua B
                                                </button>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftar_siswa as $siswa): 
                                    $sid = $siswa['id_siswa'];
                                ?>
                                <tr>
                                    <!-- Nama Siswa Sticky -->
                                    <td class="sticky-col align-middle px-3 py-2 bg-white">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($siswa['nama_siswa']); ?></div>
                                        <small class="text-muted"><?php echo isset($siswa['nis']) ? $siswa['nis'] : ''; ?></small>
                                    </td>

                                    <!-- Kolom Penilaian -->
                                    <?php foreach ($daftar_dimensi as $dim): 
                                        $tid = $dim['id_target'];
                                        $val = isset($nilai_tersimpan[$sid][$tid]) ? $nilai_tersimpan[$sid][$tid] : '';
                                        $cat = isset($catatan_tersimpan[$sid][$tid]) ? $catatan_tersimpan[$sid][$tid] : '';
                                    ?>
                                    <td class="align-middle p-2 bg-light bg-opacity-10 text-center border-start">
                                        
                                        <!-- Hidden Input untuk menyimpan nilai -->
                                        <input type="hidden" name="nilai[<?php echo $sid; ?>][<?php echo $tid; ?>]" class="input-nilai" value="<?php echo htmlspecialchars($val); ?>">
                                        
                                        <!-- Tombol Pilihan A, B, C, D (Data Value = Kata Lengkap) -->
                                        <div class="value-buttons">
                                            <button type="button" class="btn-nilai <?php echo ($val == 'Sangat Baik' || $val == 'A') ? 'active' : ''; ?>" data-value="Sangat Baik" title="Sangat Baik">A</button>
                                            <button type="button" class="btn-nilai <?php echo ($val == 'Baik' || $val == 'B') ? 'active' : ''; ?>" data-value="Baik" title="Baik">B</button>
                                            <button type="button" class="btn-nilai <?php echo ($val == 'Cukup' || $val == 'C') ? 'active' : ''; ?>" data-value="Cukup" title="Cukup">C</button>
                                            <button type="button" class="btn-nilai <?php echo ($val == 'Kurang' || $val == 'D') ? 'active' : ''; ?>" data-value="Kurang" title="Kurang">D</button>
                                        </div>

                                        <!-- Catatan Opsional -->
                                        <input type="text" name="catatan[<?php echo $sid; ?>][<?php echo $tid; ?>]" 
                                               class="form-control form-control-sm catatan-input text-center" 
                                               placeholder="Catatan..." value="<?php echo htmlspecialchars($cat); ?>">
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tombol Simpan Floating -->
            <button type="submit" class="btn btn-primary btn-lg rounded-pill fab-save px-4 fw-bold">
                <i class="bi bi-save-fill me-2"></i> Simpan Data
            </button>
            <div style="height: 100px;"></div> <!-- Spacer bawah -->

        </form>

    <?php elseif ($id_kegiatan_pilih > 0 && !empty($id_kelas_pilih)): ?>
        <div class="alert alert-warning text-center mt-5 shadow-sm">
            <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
            <h4>Data Siswa Kosong</h4>
            <p>Tidak ditemukan data siswa aktif di kelas yang dipilih.</p>
        </div>
    <?php elseif ($id_kegiatan_pilih > 0): ?>
        <div class="text-center mt-5 text-muted opacity-50">
            <i class="bi bi-arrow-up-circle fs-1"></i>
            <p class="mt-2">Silakan pilih <strong>Kelas Sasaran</strong> untuk mulai menilai.</p>
        </div>
    <?php else: ?>
        <div class="text-center mt-5 text-muted opacity-50">
            <i class="bi bi-search fs-1"></i>
            <p class="mt-2">Pilih <strong>Kegiatan Projek</strong> terlebih dahulu.</p>
        </div>
    <?php endif; ?>

</div>

<!-- JAVASCRIPT LOGIC -->
<script>
$(document).ready(function() {
    // 1. Init Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // 2. Logic Tombol Penilaian
    $(document).on('click', '.btn-nilai', function(e) {
        e.preventDefault();
        var btn = $(this);
        var container = btn.closest('td');
        var input = container.find('.input-nilai');

        // Reset active di cell ini
        container.find('.btn-nilai').removeClass('active');
        
        // Set active tombol yg diklik
        btn.addClass('active');
        
        // Update hidden input value
        input.val(btn.data('value'));
    });

    // 3. Logic "Set Semua B" per Kolom (Fix Overwrite & Validasi)
    $(document).on('click', '.btn-set-all', function(e) {
        e.preventDefault();
        var btnSet = $(this);
        var colIndex = btnSet.closest('th').index(); // Dapatkan index kolom
        var valToSet = btnSet.data('val'); // 'Baik'
        
        Swal.fire({
            title: 'Isi Otomatis?',
            text: "Ini akan mengubah SEMUA nilai siswa di kolom ini menjadi 'B - Baik'. Data yang sudah ada akan ditimpa.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Timpa Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Loop setiap baris body
                $('.table-assessment tbody tr').each(function() {
                    // Cari cell sesuai index kolom
                    var cell = $(this).find('td').eq(colIndex);
                    var input = cell.find('.input-nilai');
                    
                    // Langsung set nilai (Overwrite semua)
                    input.val(valToSet);
                    
                    // Update tampilan tombol visual
                    cell.find('.btn-nilai').removeClass('active');
                    // Cari tombol yang data-value nya "Baik" dan aktifkan
                    cell.find('.btn-nilai').filter(function() {
                        return $(this).data('value') === valToSet;
                    }).addClass('active');
                });
                
                // Notifikasi kecil
                const Toast = Swal.mixin({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true
                });
                Toast.fire({ icon: 'success', title: 'Kolom terisi otomatis!' });
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>