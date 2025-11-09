<?php
session_start();
include 'koneksi.php';

// Validasi role Wali Kelas
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    die("Akses ditolak.");
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

// Ambil data kelas yang diampu
$q_kelas = mysqli_query($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = $id_wali_kelas AND id_tahun_ajaran = $id_tahun_ajaran");
$kelas_data = mysqli_fetch_assoc($q_kelas);
$id_kelas = $kelas_data['id_kelas'] ?? 0;

if (!$id_kelas) {
    $_SESSION['pesan_error'] = "Anda tidak terdaftar sebagai wali kelas aktif.";
    header('Location: dashboard.php');
    exit();
}

// Ambil semua siswa di kelas ini
$q_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas");

mysqli_begin_transaction($koneksi);
try {
    while ($siswa = mysqli_fetch_assoc($q_siswa)) {
        $id_siswa = $siswa['id_siswa'];

        // Ambil id_rapor siswa ini
        $q_get_rapor = mysqli_query($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = $id_siswa AND id_tahun_ajaran = $id_tahun_ajaran AND semester = $semester_aktif LIMIT 1");
        $d_rapor = mysqli_fetch_assoc($q_get_rapor);
        
        // Jika siswa belum punya record rapor utama, lewati.
        if (!$d_rapor) {
            continue;
        }
        $id_rapor = $d_rapor['id_rapor'];

        // 1. HAPUS data ekskul lama untuk siswa ini agar data selalu fresh
        mysqli_query($koneksi, "DELETE FROM rapor_detail_ekskul WHERE id_rapor = $id_rapor");

        // 2. AMBIL data ekskul dan penilaian terbaru
        $q_data_ekskul = mysqli_query($koneksi, "
            SELECT 
                e.nama_ekskul,
                GROUP_CONCAT(CONCAT(t.deskripsi_tujuan, ':', p.nilai) ORDER BY FIELD(p.nilai, 'Sangat Baik', 'Baik', 'Cukup', 'Kurang') SEPARATOR ';') as penilaian
            FROM ekskul_peserta ep
            JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
            LEFT JOIN ekskul_penilaian p ON ep.id_peserta_ekskul = p.id_peserta_ekskul
            LEFT JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul AND t.semester = $semester_aktif
            WHERE ep.id_siswa = $id_siswa AND e.id_tahun_ajaran = $id_tahun_ajaran
            GROUP BY e.id_ekskul
        ");

        if ($q_data_ekskul && mysqli_num_rows($q_data_ekskul) > 0) {
            $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO rapor_detail_ekskul (id_rapor, nama_ekskul, keterangan) VALUES (?, ?, ?)");
            
            while($ekskul = mysqli_fetch_assoc($q_data_ekskul)) {
                $nama_ekskul = $ekskul['nama_ekskul'];
                $penilaian_list = $ekskul['penilaian'] ? explode(';', $ekskul['penilaian']) : [];
                
                $keterangan = "Mengikuti kegiatan dengan baik. ";
                $nilai_sb = []; // Sangat Baik
                $nilai_b = [];  // Baik
                
                if(!empty($penilaian_list[0])){
                    foreach ($penilaian_list as $item) {
                        list($tujuan, $nilai) = array_pad(explode(':', $item, 2), 2, '');
                        if ($nilai == 'Sangat Baik') $nilai_sb[] = $tujuan;
                        if ($nilai == 'Baik') $nilai_b[] = $tujuan;
                    }
                }
                
                if (!empty($nilai_sb)) {
                    $keterangan = "Sangat aktif dan menunjukkan penguasaan yang sangat baik dalam " . implode(', ', $nilai_sb) . ".";
                } elseif (!empty($nilai_b)) {
                    $keterangan = "Aktif dan menunjukkan penguasaan yang baik dalam " . implode(', ', $nilai_b) . ".";
                }

                // 3. SIMPAN keterangan per ekskul ke tabel rapor_detail_ekskul
                mysqli_stmt_bind_param($stmt_insert, "iss", $id_rapor, $nama_ekskul, $keterangan);
                mysqli_stmt_execute($stmt_insert);
            }
        }
    }
    
    mysqli_commit($koneksi);
    $_SESSION['pesan'] = "Deskripsi ekstrakurikuler untuk seluruh siswa berhasil dibuat ulang!";
    
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['pesan_error'] = "Terjadi kegagalan: " . $e->getMessage();
}

header('Location: walikelas_cetak_rapor.php');
exit();
?>