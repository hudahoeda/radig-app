<?php
session_start();
include 'koneksi.php';

// Validasi peran, hanya guru yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    // Kirim response error jika diakses langsung
    http_response_code(403);
    echo "<div class='alert alert-danger'>Akses ditolak.</div>";
    exit;
}

$id_guru = (int)$_SESSION['id_guru'];

// Ambil semua kelas yang DIAJAR oleh guru ini di tahun ajaran aktif
// Ini penting agar guru tidak bisa menugaskan TP ke kelas yang tidak mereka ajar
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
        // Kelompokkan berdasarkan jenjang (misal: "Kelas 7", "Kelas 8")
        preg_match('/^(\d+|[XVI]+)/', $kelas['nama_kelas'], $matches);
        $jenjang = 'Lainnya';
        if (!empty($matches)) {
            $jenjang_raw = $matches[1];
            // Konversi VII -> 7, VIII -> 8, dst.
            $romawi_map = ['VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10, 'XI' => 11, 'XII' => 12];
            $jenjang_angka = $romawi_map[$jenjang_raw] ?? $jenjang_raw;
            $jenjang = 'Kelas ' . $jenjang_angka;
        }
        $kelas_dikelompokkan[$jenjang][] = $kelas;
    }
}
ksort($kelas_dikelompokkan); // Urutkan berdasarkan jenjang

// Kembalikan HTML untuk modal
if (empty($kelas_dikelompokkan)):
?>
    <div class="alert alert-warning">Anda belum ditugaskan untuk mengajar di kelas manapun pada tahun ajaran aktif.</div>
<?php
else:
    foreach ($kelas_dikelompokkan as $jenjang => $daftar_kelas):
?>
        <div class="mb-3">
            <h6 class="jenjang-title"><?php echo $jenjang; ?></h6>
            <div class="row">
                <?php foreach ($daftar_kelas as $kelas): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check my-1">
                            <input class="form-check-input" type="checkbox" name="id_kelas_massal[]" value="<?php echo $kelas['id_kelas']; ?>" id="massal_kelas_<?php echo $kelas['id_kelas']; ?>">
                            <label class="form-check-label" for="massal_kelas_<?php echo $kelas['id_kelas']; ?>">
                                <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    endforeach;
endif;
?>

