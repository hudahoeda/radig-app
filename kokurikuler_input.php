<?php
include 'header.php';
include 'koneksi.php';

$id_guru_login = (int)$_SESSION['id_guru']; 
$role_login = $_SESSION['role'];

// Ambil data tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

// Ambil daftar kegiatan kokurikuler yang aktif (Harus difilter juga sesuai hak akses)
$q_kegiatan = mysqli_prepare($koneksi, "
    SELECT DISTINCT k.id_kegiatan, k.tema_kegiatan, k.semester
    FROM kokurikuler_kegiatan k
    LEFT JOIN kokurikuler_tim_penilai kt ON k.id_kegiatan = kt.id_kegiatan
    WHERE k.id_tahun_ajaran = ? 
    AND (
        ? = 'admin' 
        OR k.id_koordinator = ? 
        OR kt.id_guru = ?
    )
    ORDER BY k.semester, k.tema_kegiatan
");
mysqli_stmt_bind_param($q_kegiatan, "issi", $id_tahun_ajaran_aktif, $role_login, $id_guru_login, $id_guru_login);
mysqli_stmt_execute($q_kegiatan);
$daftar_kegiatan = mysqli_fetch_all(mysqli_stmt_get_result($q_kegiatan), MYSQLI_ASSOC);


// Ambil daftar kelas
$q_kelas_all = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas");
$daftar_kelas = mysqli_fetch_all($q_kelas_all, MYSQLI_ASSOC);

$id_kegiatan_pilih = isset($_GET['kegiatan']) ? (int)$_GET['kegiatan'] : 0;
$id_kelas_pilih = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;

// <-- BLOK KEAMANAN (DIPERBERSIH) -->
if ($id_kegiatan_pilih > 0) {
    $is_allowed = false;
    
    if ($role_login == 'admin') {
        $is_allowed = true;
    } else {
        // Cek apakah user adalah koordinator atau tim penilai
        $stmt_cek = mysqli_prepare($koneksi, "
            SELECT k.id_koordinator, kt.id_guru AS id_anggota_tim
            FROM kokurikuler_kegiatan k
            LEFT JOIN kokurikuler_tim_penilai kt ON k.id_kegiatan = kt.id_kegiatan AND kt.id_guru = ?
            WHERE k.id_kegiatan = ?
        ");
        mysqli_stmt_bind_param($stmt_cek, "ii", $id_guru_login, $id_kegiatan_pilih);
        mysqli_stmt_execute($stmt_cek);
        $result_cek = mysqli_stmt_get_result($stmt_cek);
        
        while($cek = mysqli_fetch_assoc($result_cek)) {
            // Cek apakah dia koordinator (bisa jadi koordinatornya null)
            if ($cek['id_koordinator'] == $id_guru_login) {
                $is_allowed = true;
                break;
            }
            // Cek apakah dia anggota tim (bisa jadi anggotanya null)
            if ($cek['id_anggota_tim'] == $id_guru_login) {
                $is_allowed = true;
                break;
            }
        }
    }

    // Jika setelah semua cek, $is_allowed masih false, tolak akses
    if (!$is_allowed) {
        echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang untuk menilai projek ini.','error').then(() => window.location = 'kokurikuler_pilih.php');</script>";
        include 'footer.php';
        exit;
    }
}
// <-- AKHIR BLOK KEAMANAN -->


// Logika untuk menyimpan nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_nilai'])) {
    
    $id_kegiatan_post = (int)$_POST['id_kegiatan'];
    $id_kelas_post = (int)$_POST['id_kelas'];
    $nilai_asesmen = $_POST['nilai'];
    $catatan_data = isset($_POST['catatan']) ? $_POST['catatan'] : [];

    // Logika UPSERT (UPDATE jika ada, INSERT jika belum ada)
    $query = "INSERT INTO kokurikuler_asesmen (id_target, id_siswa, id_guru_penilai, nilai_kualitatif, catatan_guru) VALUES (?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE nilai_kualitatif = VALUES(nilai_kualitatif), catatan_guru = VALUES(catatan_guru)";
    $stmt = mysqli_prepare($koneksi, $query);
    
    foreach ($nilai_asesmen as $id_siswa => $penilaian) {
        foreach ($penilaian as $id_target => $nilai) {
            if (!empty($nilai)) {
                $catatan = isset($catatan_data[$id_siswa][$id_target]) ? $catatan_data[$id_siswa][$id_target] : '';
                mysqli_stmt_bind_param($stmt, "iiiss", $id_target, $id_siswa, $id_guru_login, $nilai, $catatan);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    
    $pesan = [
        'icon' => 'success', 
        'title' => 'Berhasil Disimpan', 
        'text' => 'Semua perubahan asesmen telah tersimpan di database.'
    ];
    $_SESSION['pesan'] = json_encode($pesan);

    header("Location: kokurikuler_input.php?kegiatan=$id_kegiatan_post&kelas=$id_kelas_post");
    exit;
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .table-assessment { table-layout: fixed; }
    .table-assessment .horizontal-header { text-align: center; vertical-align: middle; padding: 0.75rem; width: 190px; white-space: normal; }
    .table-assessment .sticky-col { position: sticky; left: 0; z-index: 2; background-color: #fff; width: 250px; min-width: 250px; }
    .table-assessment thead { position: sticky; top: 0; z-index: 3; }
    .value-buttons { display: flex; justify-content: center; gap: 5px; }
    .value-buttons .btn-nilai { border-radius: 50%; width: 38px; height: 38px; font-weight: 700; transition: all 0.2s ease; }
    .value-buttons .btn-nilai:not(.active) { opacity: 0.4; }
    .value-buttons .btn-nilai:not(.active):hover { opacity: 1; transform: scale(1.1); }
    .value-buttons .btn-nilai.active.btn-sb { background-color: var(--bs-success); color: white; border-color: var(--bs-success); }
    .value-buttons .btn-nilai.active.btn-b { background-color: var(--bs-primary); color: white; border-color: var(--bs-primary); }
    .value-buttons .btn-nilai.active.btn-c { background-color: var(--bs-warning); color: #000; border-color: var(--bs-warning); }
    .value-buttons .btn-nilai.active.btn-k { background-color: var(--bs-danger); color: white; border-color: var(--bs-danger); }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow-sm">
        <h1 class="mb-0">Input Asesmen Kokurikuler</h1>
        <p class="lead opacity-75">Pilih kegiatan dan kelas untuk memulai penilaian.</p>
    </div>

    <!-- INI ADALAH KONTEN YANG SEHARUSNYA MUNCUL -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="kokurikuler_input.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="kegiatan" class="form-label fw-bold">1. Pilih Kegiatan Projek</label>
                    <select name="kegiatan" id="kegiatan" class="form-select" required>
                        <option value="">-- Daftar Kegiatan --</option>
                        <?php foreach($daftar_kegiatan as $kegiatan): ?>
                        <option value="<?php echo $kegiatan['id_kegiatan']; ?>" <?php echo ($id_kegiatan_pilih == $kegiatan['id_kegiatan']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kegiatan['tema_kegiatan']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="kelas" class="form-label fw-bold">2. Pilih Kelas</label>
                    <select name="kelas" id="kelas" class="form-select" required>
                        <option value="">-- Daftar Kelas --</option>
                        <?php foreach($daftar_kelas as $kls): ?>
                        <option value="<?php echo $kls['id_kelas']; ?>" <?php echo ($id_kelas_pilih == $kls['id_kelas']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kls['nama_kelas']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Tampilkan</button>
                </div>
            </form>
        </div>
    </div>
    <!-- AKHIR KONTEN -->


    <!-- TABEL INI HANYA AKAN MUNCUL JIKA KEDUA ID (KEGIATAN & KELAS) SUDAH TERPILIH -->
    <?php if ($id_kegiatan_pilih > 0 && $id_kelas_pilih > 0): 
        // === PERBAIKAN DI SINI: Menghapus "ORDER BY urutan" ===
        $q_dimensi = mysqli_prepare($koneksi, "SELECT id_target, nama_dimensi FROM kokurikuler_target_dimensi WHERE id_kegiatan = ? ORDER BY nama_dimensi");
        mysqli_stmt_bind_param($q_dimensi, "i", $id_kegiatan_pilih);
        mysqli_stmt_execute($q_dimensi);
        $daftar_dimensi = mysqli_fetch_all(mysqli_stmt_get_result($q_dimensi), MYSQLI_ASSOC);

        $q_siswa = mysqli_prepare($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = ? AND status_siswa = 'Aktif' ORDER BY nama_lengkap");
        mysqli_stmt_bind_param($q_siswa, "i", $id_kelas_pilih);
        mysqli_stmt_execute($q_siswa);
        $daftar_siswa = mysqli_fetch_all(mysqli_stmt_get_result($q_siswa), MYSQLI_ASSOC);

        $q_nilai_exist = mysqli_query($koneksi, "SELECT ka.id_target, ka.id_siswa, ka.id_guru_penilai, ka.nilai_kualitatif, g.nama_guru, ka.catatan_guru FROM kokurikuler_asesmen ka JOIN guru g ON ka.id_guru_penilai = g.id_guru WHERE ka.id_siswa IN (SELECT id_siswa FROM siswa WHERE id_kelas = $id_kelas_pilih) AND ka.id_target IN (SELECT id_target FROM kokurikuler_target_dimensi WHERE id_kegiatan = $id_kegiatan_pilih)");
        $nilai_tersimpan = [];
        $catatan_tersimpan = []; // <-- TAMBAHAN: Simpan catatan
        while($row = mysqli_fetch_assoc($q_nilai_exist)) {
            $nilai_tersimpan[$row['id_siswa']][$row['id_target']][] = ['nilai' => $row['nilai_kualitatif'], 'guru' => $row['nama_guru'], 'id_guru' => $row['id_guru_penilai']];
            if ($row['id_guru_penilai'] == $id_guru_login) {
                $catatan_tersimpan[$row['id_siswa']][$row['id_target']] = $row['catatan_guru'];
            }
        }
    ?>
    <div class="alert alert-info shadow-sm" role="alert">
        <h4 class="alert-heading"><i class="bi bi-lightbulb-fill me-2"></i>Pendekatan Penilaian Cepat (Berdasarkan Outlier)</h4>
        <p>Untuk mempercepat proses, sistem ini menggunakan pendekatan sebagai berikut:</p>
        <hr>
        <ol class="mb-0">
            <li>Untuk efisiensi, <strong>semua siswa secara otomatis dinilai "Baik" (B)</strong> sebagai nilai awal.</li>
            <li>Tugas Anda adalah fokus pada siswa <strong>outlier</strong>, yaitu mereka yang menunjukkan perkembangan <strong>Sangat Baik (A)</strong> atau yang <strong>memerlukan bimbingan lebih (C/K)</strong>, lalu ubah nilainya.</li>
            <li>Gunakan tombol <strong><i class="bi bi-check2-all"></i> Set B</strong> di setiap kolom untuk mengembalikan semua siswa ke nilai "Baik" jika diperlukan.</li>
        </ol>
    </div>

    <form action="kokurikuler_input.php?kegiatan=<?php echo $id_kegiatan_pilih; ?>&kelas=<?php echo $id_kelas_pilih; ?>" method="POST">
        <input type="hidden" name="id_kegiatan" value="<?php echo $id_kegiatan_pilih; ?>">
        <input type="hidden" name="id_kelas" value="<?php echo $id_kelas_pilih; ?>">
        
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2" style="color: var(--primary-color);"></i>Lembar Asesmen - <?php echo htmlspecialchars(array_values(array_filter($daftar_kelas, fn($k) => $k['id_kelas'] == $id_kelas_pilih))[0]['nama_kelas'] ?? ''); ?></h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered table-assessment mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="sticky-col">Nama Siswa</th>
                                <?php foreach ($daftar_dimensi as $dimensi): ?>
                                <th class="horizontal-header">
                                    <?php echo htmlspecialchars($dimensi['nama_dimensi']); ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2 set-all-b" data-column-index="<?php echo $dimensi['id_target']; ?>" title="Set semua siswa di kolom ini menjadi Baik (B)"><i class="bi bi-check2-all"></i> Set B</button>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($daftar_siswa as $siswa): ?>
                            <tr>
                                <td class="sticky-col align-middle fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                <?php foreach($daftar_dimensi as $dimensi): 
                                    $penilaian_all = $nilai_tersimpan[$siswa['id_siswa']][$dimensi['id_target']] ?? [];
                                    $nilai_guru_login = '';
                                    foreach($penilaian_all as $p) { if ($p['id_guru'] == $id_guru_login) { $nilai_guru_login = $p['nilai']; break; } }
                                    $nilai_aktif = $nilai_guru_login ?: 'Baik';
                                    $catatan_aktif = $catatan_tersimpan[$siswa['id_siswa']][$dimensi['id_target']] ?? ''; // Ambil catatan
                                ?>
                                <td class="align-middle" data-column="<?php echo $dimensi['id_target']; ?>">
                                    <input type="hidden" name="nilai[<?php echo $siswa['id_siswa']; ?>][<?php echo $dimensi['id_target']; ?>]" value="<?php echo htmlspecialchars($nilai_aktif); ?>">
                                    <div class="value-buttons" data-siswa="<?php echo $siswa['id_siswa']; ?>" data-target="<?php echo $dimensi['id_target']; ?>">
                                        <button type="button" class="btn btn-nilai btn-outline-success btn-sb <?php echo ($nilai_aktif == 'Sangat Baik') ? 'active' : ''; ?>" data-value="Sangat Baik" title="Sangat Baik (A)">A</button>
                                        <button type="button" class="btn btn-nilai btn-outline-primary btn-b <?php echo ($nilai_aktif == 'Baik') ? 'active' : ''; ?>" data-value="Baik" title="Baik (B)">B</button>
                                        <button type="button" class="btn btn-nilai btn-outline-warning btn-c <?php echo ($nilai_aktif == 'Cukup') ? 'active' : ''; ?>" data-value="Cukup" title="Cukup (C)">C</button>
                                        <button type="button" class="btn btn-nilai btn-outline-danger btn-k <?php echo ($nilai_aktif == 'Kurang') ? 'active' : ''; ?>" data-value="Kurang" title="Kurang (K)">K</button>
                                    </div>
                                    
                                    <!-- Input Catatan -->
                                    <textarea name="catatan[<?php echo $siswa['id_siswa']; ?>][<?php echo $dimensi['id_target']; ?>]" class="form-control form-control-sm mt-2" rows="2" placeholder="Catatan..."><?php echo htmlspecialchars($catatan_aktif); ?></textarea>
                                    
                                    <?php if(!empty($penilaian_all)): ?>
                                        <div class="text-center mt-2 d-flex flex-wrap justify-content-center" style="gap: 3px;">
                                        <?php foreach($penilaian_all as $p): ?>
                                            <span class="badge bg-secondary" title="Oleh: <?php echo htmlspecialchars($p['guru']); ?>"><?php echo explode(' ', $p['guru'])[0]; ?>: <?php echo substr($p['nilai'], 0, 1); ?></span>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end bg-light">
                <button type="submit" name="simpan_nilai" class="btn btn-success btn-lg"><i class="bi bi-check-circle-fill me-2"></i> Simpan Semua Perubahan</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
    <!-- AKHIR DARI BLOK IF -->

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function handleButtonClick(button) {
        const group = button.closest('.value-buttons');
        const value = button.dataset.value;
        const targetInput = document.querySelector(`input[name="nilai[${group.dataset.siswa}][${group.dataset.target}]"]`);
        group.querySelectorAll('.btn-nilai').forEach(btn => {
            btn.className = btn.className.replace(/(btn-(success|primary|warning|danger))/g, '').replace('active', '').trim();
        });
        group.querySelector('.btn-sb').classList.add('btn-outline-success');
        group.querySelector('.btn-b').classList.add('btn-outline-primary');
        group.querySelector('.btn-c').classList.add('btn-outline-warning');
        group.querySelector('.btn-k').classList.add('btn-outline-danger');
        targetInput.value = value;
        button.classList.add('active');
        if (value === 'Sangat Baik') { button.classList.remove('btn-outline-success'); button.classList.add('btn-success'); }
        if (value === 'Baik') { button.classList.remove('btn-outline-primary'); button.classList.add('btn-primary'); }
        if (value === 'Cukup') { button.classList.remove('btn-outline-warning'); button.classList.add('btn-warning'); }
        if (value === 'Kurang') { button.classList.remove('btn-outline-danger'); button.classList.add('btn-danger'); }
    }
    document.querySelectorAll('.value-buttons').forEach(group => {
        group.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-nilai')) {
                handleButtonClick(e.target);
            }
        });
    });
    document.querySelectorAll('.set-all-b').forEach(button => {
        button.addEventListener('click', function() {
            const columnIndex = this.dataset.columnIndex;
            const cellsInColumn = document.querySelectorAll(`td[data-column="${columnIndex}"]`);
            cellsInColumn.forEach(cell => {
                const buttonB = cell.querySelector('.btn-b');
                if (buttonB) {
                    handleButtonClick(buttonB);
                }
            });
        });
    });
});
</script>

<?php
if (isset($_SESSION['pesan'])) {
    $pesan_data = json_decode($_SESSION['pesan'], true);
    if (is_array($pesan_data)) {
        echo "<script>Swal.fire(" . json_encode($pesan_data) . ");</script>";
    } else {
        // Fallback jika 'pesan' bukan JSON (dari kode lama)
        echo "<script>Swal.fire({icon: 'success', title: 'Berhasil', text: '" . addslashes($_SESSION['pesan']) . "'});</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>