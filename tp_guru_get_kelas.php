<?php
session_start();
include 'koneksi.php';

// Validasi peran, hanya guru yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    die('<div class="alert alert-danger">Akses Ditolak. Anda harus login sebagai guru.</div>');
}

$id_tp = isset($_GET['id_tp']) ? (int)$_GET['id_tp'] : 0;
$id_guru = (int)$_SESSION['id_guru'];

if ($id_tp == 0) {
    die('<div class="alert alert-danger">ID Tujuan Pembelajaran tidak valid.</div>');
}

// Ambil data TP untuk memastikan guru ini pemiliknya
$q_tp = mysqli_prepare($koneksi, "SELECT 1 FROM tujuan_pembelajaran WHERE id_tp = ? AND id_guru_pembuat = ?");
mysqli_stmt_bind_param($q_tp, "ii", $id_tp, $id_guru);
mysqli_stmt_execute($q_tp);
$result_tp = mysqli_stmt_get_result($q_tp);

if (mysqli_num_rows($result_tp) == 0) {
     die('<div class="alert alert-danger">TP tidak ditemukan atau Anda tidak berhak mengaksesnya.</div>');
}

// Ambil kelas yang diajar oleh guru ini
$query_kelas_guru = "SELECT DISTINCT k.id_kelas, k.nama_kelas
                     FROM kelas k
                     JOIN guru_mengajar gm ON k.id_kelas = gm.id_kelas
                     WHERE gm.id_guru = ?
                     AND k.id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif')
                     ORDER BY k.nama_kelas ASC";
$stmt_kelas_guru = mysqli_prepare($koneksi, $query_kelas_guru);
mysqli_stmt_bind_param($stmt_kelas_guru, "i", $id_guru);
mysqli_stmt_execute($stmt_kelas_guru);
$result_kelas_guru = mysqli_stmt_get_result($stmt_kelas_guru);

$kelas_dikelompokkan = [];
if ($result_kelas_guru) {
    while ($kelas = mysqli_fetch_assoc($result_kelas_guru)) {
        // [PERBAIKAN] Urutan regex diubah (dari terpanjang ke terpendek) dan case-insensitive
        // Ini untuk memastikan 'VIII' terdeteksi sebelum 'VII'
        preg_match('/^(XII|XI|X|IX|VIII|VII|\d+)/i', $kelas['nama_kelas'], $matches);
        $jenjang = 'Lainnya';
        if (!empty($matches)) {
            $jenjang_raw = $matches[1];
            
            // Konversi VII -> 7, VIII -> 8, dst.
            $romawi_map = [
                'VII' => 7, 'VIII' => 8, 'IX' => 9, 
                'X' => 10, 'XI' => 11, 'XII' => 12
            ];
            // Cek apakah $jenjang_raw ada di map, jika tidak, gunakan apa adanya (asumsi itu angka \d+)
            // strtoupper digunakan untuk mencocokkan key di $romawi_map
            $jenjang_angka = $romawi_map[strtoupper($jenjang_raw)] ?? $jenjang_raw;
            
            // Tambahkan "Kelas" di depannya
            $jenjang = 'Kelas ' . $jenjang_angka;
        }
        $kelas_dikelompokkan[$jenjang][] = $kelas;
    }
}
// [PERBAIKAN] Urutkan secara natural (Kelas 7, 8, 9, 10, bukan 10, 7, 8, 9)
ksort($kelas_dikelompokkan, SORT_NATURAL);

// Ambil kelas yang sudah dipilih untuk TP ini
$q_kelas_terpilih = mysqli_prepare($koneksi, "SELECT id_kelas FROM tp_kelas WHERE id_tp = ?");
mysqli_stmt_bind_param($q_kelas_terpilih, "i", $id_tp);
mysqli_stmt_execute($q_kelas_terpilih);
$result_terpilih = mysqli_stmt_get_result($q_kelas_terpilih);
$kelas_terpilih = [];
while($row = mysqli_fetch_assoc($result_terpilih)){
    $kelas_terpilih[] = $row['id_kelas'];
}
?>

<!-- 
[PERBAIKAN] 
Tag <form> dan tombol-tombol (Batal, Simpan) telah dihapus dari file ini.
File ini sekarang hanya mengembalikan daftar kelas.
Formulir dan tombolnya sendiri sudah disediakan di file tp_guru_tampil.php.
-->
<div>
    <?php if(!empty($kelas_dikelompokkan)): ?>
        <?php foreach ($kelas_dikelompokkan as $jenjang => $daftar_kelas): ?>
            <div class="jenjang-group">
                <!-- Menambahkan tombol Pilih Semua / Batal Semua -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="jenjang-title mb-0"><?php echo htmlspecialchars($jenjang); ?></h6>
                    <div>
                        <a href="javascript:void(0)" onclick="toggleJenjang(this, true)" class="btn btn-link btn-sm p-0 text-decoration-none">Pilih Semua</a>
                        <span class="text-muted mx-1">|</span>
                        <a href="javascript:void(0)" onclick="toggleJenjang(this, false)" class="btn btn-link btn-sm p-0 text-decoration-none">Batal Semua</a>
                    </div>
                </div>
                <div class="row">
                    <?php foreach ($daftar_kelas as $kelas): ?>
                        <div class="col-md-6 col-sm-12">
                            <div class="form-check form-switch form-check-lg my-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="id_kelas[]" 
                                       value="<?php echo $kelas['id_kelas']; ?>" 
                                       id="modal_kelas_<?php echo $kelas['id_kelas']; ?>"
                                       <?php if(in_array($kelas['id_kelas'], $kelas_terpilih)) echo 'checked'; ?>>
                                <label class="form-check-label" for="modal_kelas_<?php echo $kelas['id_kelas']; ?>">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning text-center">Anda belum ditugaskan untuk mengajar di kelas manapun pada tahun ajaran aktif. Silakan hubungi Administrator.</div>
    <?php endif; ?>
</div>

<!-- Menambahkan skrip untuk tombol bulk assign -->
<script>
function toggleJenjang(button, checkAll) {
    // 1. Temukan parent 'jenjang-group'
    let parentGroup = button.closest('.jenjang-group');
    if (parentGroup) {
        // 2. Temukan semua checkbox di dalamnya
        let checkboxes = parentGroup.querySelectorAll('.form-check-input[type="checkbox"]');
        // 3. Set status checked
        checkboxes.forEach(cb => {
            cb.checked = checkAll;
        });
    }
}
</script>

