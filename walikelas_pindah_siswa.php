<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Anda harus login sebagai Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_siswa = $_GET['id_siswa'] ?? null;
// Ini adalah bagian penting yang mencegah error 'ID siswa tidak ditemukan'
if (!$id_siswa) {
    echo "<script>Swal.fire('Error','ID siswa tidak ditemukan.','error').then(() => window.location = 'walikelas_identitas_siswa.php');</script>";
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];
$current_class_id = null;
$student_data = null;
$nama_kelas_saat_ini = "Tidak Diketahui";

// Ambil data siswa saat ini dan verifikasi apakah guru yang login adalah wali kelasnya
$q_check_student = mysqli_prepare($koneksi, "
    SELECT s.id_siswa, s.nama_lengkap, s.nis, s.nisn, k.id_kelas, k.nama_kelas
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.id_siswa = ? AND k.id_wali_kelas = ?
");
mysqli_stmt_bind_param($q_check_student, "ii", $id_siswa, $id_wali_kelas);
mysqli_stmt_execute($q_check_student);
$result_check_student = mysqli_stmt_get_result($q_check_student);
$student_data = mysqli_fetch_assoc($result_check_student);

// Jika data siswa tidak ditemukan atau guru bukan wali kelasnya, tolak akses
if (!$student_data) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki izin untuk memindahkan siswa ini.','error').then(() => window.location = 'walikelas_identitas_siswa.php');</script>";
    exit;
}

$current_class_id = $student_data['id_kelas'];
$nama_kelas_saat_ini = $student_data['nama_kelas'];

// Tangani form saat dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_kelas_id = $_POST['kelas_tujuan'] === 'keluar' ? NULL : (int)$_POST['kelas_tujuan'];
    $success = false;

    // Mulai transaksi untuk memastikan data konsisten
    mysqli_begin_transaction($koneksi);
    try {
        // Perbarui kelas siswa
        $q_update_siswa = mysqli_prepare($koneksi, "UPDATE siswa SET id_kelas = ? WHERE id_siswa = ?");
        mysqli_stmt_bind_param($q_update_siswa, "ii", $new_kelas_id, $id_siswa);
        mysqli_stmt_execute($q_update_siswa);
        
        // Hapus entri rapor lama untuk semester dan tahun ajaran saat ini
        $q_semester_aktif = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan='semester_aktif'");
        $semester_aktif = mysqli_fetch_assoc($q_semester_aktif)['nilai_pengaturan'];

        $q_tahun_ajaran = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif'");
        $id_tahun_ajaran = mysqli_fetch_assoc($q_tahun_ajaran)['id_tahun_ajaran'];
        
        $q_delete_rapor = mysqli_prepare($koneksi, "DELETE FROM rapor WHERE id_siswa = ? AND id_tahun_ajaran = ? AND semester = ?");
        mysqli_stmt_bind_param($q_delete_rapor, "iii", $id_siswa, $id_tahun_ajaran, $semester_aktif);
        mysqli_stmt_execute($q_delete_rapor);

        mysqli_commit($koneksi);
        $success = true;
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        throw $exception;
    }

    if ($success) {
        $message = $new_kelas_id ? "Siswa berhasil dipindahkan ke kelas lain." : "Siswa berhasil dikeluarkan dari kelas.";
        echo "<script>Swal.fire('Berhasil','$message','success').then(() => window.location = 'walikelas_identitas_siswa.php');</script>";
    } else {
        echo "<script>Swal.fire('Error','Gagal memindahkan siswa. Silakan coba lagi.','error');</script>";
    }
    exit;
}

// Ambil semua kelas lain untuk dropdown menu
$q_all_classes = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_kelas != ? ORDER BY nama_kelas ASC");
mysqli_stmt_bind_param($q_all_classes, "i", $current_class_id);
mysqli_stmt_execute($q_all_classes);
$result_all_classes = mysqli_stmt_get_result($q_all_classes);
$daftar_kelas = mysqli_fetch_all($result_all_classes, MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1 class="mb-1">Pindahkan Siswa</h1>
    <h4 class="text-muted">Kelas Saat Ini: <?php echo htmlspecialchars($nama_kelas_saat_ini); ?></h4>

    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Formulir Pemindahan Siswa</h5>
        </div>
        <div class="card-body">
            <form action="walikelas_pindah_siswa.php?id_siswa=<?php echo htmlspecialchars($id_siswa); ?>" method="POST">
                <div class="mb-3">
                    <label for="nama_siswa" class="form-label">Nama Siswa</label>
                    <input type="text" id="nama_siswa" class="form-control" value="<?php echo htmlspecialchars($student_data['nama_lengkap']); ?>" readonly>
                </div>
                <div class="mb-3">
                    <label for="kelas_tujuan" class="form-label">Pindah ke Kelas</label>
                    <select class="form-select" id="kelas_tujuan" name="kelas_tujuan" required>
                        <option value="">-- Pilih Kelas Tujuan --</option>
                        <option value="keluar">Keluarkan dari Kelas (Status: Aktif)</option>
                        <?php foreach ($daftar_kelas as $kelas) : ?>
                            <option value="<?php echo htmlspecialchars($kelas['id_kelas']); ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Pindahkan Siswa</button>
                <a href="walikelas_identitas_siswa.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>