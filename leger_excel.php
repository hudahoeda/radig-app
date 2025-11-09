<?php
// FILE INI ADALAH SALINAN DARI leger_pdf.php
// TAPI OUTPUTNYA DIUBAH MENJADI EXCEL

session_start();
include 'koneksi.php';
// Kita TIDAK memerlukan Dompdf di sini
// require_once 'libs/autoload.php';

// =================================================================
// BLOK VALIDASI AKSES (SAMA DENGAN PDF)
// =================================================================
if (!isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    die("Kelas tidak valid.");
}

if (!in_array($_SESSION['role'], ['admin', 'guru'])) {
    die("Akses ditolak. Halaman ini khusus untuk Admin dan Guru.");
}

if ($_SESSION['role'] == 'guru') {
    $id_guru_login = (int)$_SESSION['id_guru'];
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_kelas = ?");
    mysqli_stmt_bind_param($stmt_cek, "ii", $id_guru_login, $id_kelas);
    mysqli_stmt_execute($stmt_cek);
    $result_cek = mysqli_stmt_get_result($stmt_cek);
    if (mysqli_num_rows($result_cek) == 0) {
        die("Akses ditolak. Anda bukan wali kelas untuk kelas ini.");
    }
}
// =================================================================

// --- PENGAMBILAN DATA (SAMA DENGAN PDF) ---

// 1. Ambil data kelas dan tahun ajaran aktif
$q_kelas = mysqli_query($koneksi, "SELECT k.nama_kelas, ta.tahun_ajaran FROM kelas k JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran WHERE k.id_kelas=$id_kelas");
$data_kelas = mysqli_fetch_assoc($q_kelas);
$nama_kelas = $data_kelas['nama_kelas'] ?? 'N/A';
$tahun_ajaran = $data_kelas['tahun_ajaran'] ?? 'N/A';

// 2. Ambil data sekolah
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah LIMIT 1");
$sekolah = mysqli_fetch_assoc($q_sekolah) ?? [];

// 3. Ambil data wali kelas
$q_walikelas = mysqli_query($koneksi, "SELECT g.nama_guru, g.nip FROM guru g JOIN kelas k ON g.id_guru = k.id_wali_kelas WHERE k.id_kelas = $id_kelas");
$walikelas = mysqli_fetch_assoc($q_walikelas) ?? ['nama_guru' => 'Belum Ditentukan', 'nip' => '-'];

// 4. Ambil daftar siswa (TIDAK PERLU DI-CHUNK)
$result_siswa = mysqli_query($koneksi, "SELECT id_siswa, nisn, nis, nama_lengkap FROM siswa WHERE id_kelas=$id_kelas AND status_siswa='Aktif' ORDER BY nama_lengkap ASC");
$daftar_siswa_all = mysqli_fetch_all($result_siswa, MYSQLI_ASSOC);

// =================================================================
// [MODIFIKASI] LOGIKA PENGGABUNGAN MAPEL
// =================================================================
$result_mapel_raw = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel, kode_mapel, urutan FROM mata_pelajaran ORDER BY urutan ASC");
$daftar_mapel_mentah = mysqli_fetch_all($result_mapel_raw, MYSQLI_ASSOC);

$daftar_mapel_final = [];
$id_mapel_agama = [];
$id_mapel_sbdp = [];
$pabd_added = false;
$sbdp_added = false;

// Kategori mapel berdasarkan nama
$agama_list = [
    'Pendidikan Agama Islam dan Budi Pekerti',
    'Pendidikan Agama Kristen dan Budi Pekerti',
    'Pendidikan Agama Katolik dan Budi Pekerti',
    'Pendidikan Agama Hindu dan Budi Pekerti',
    'Pendidikan Agama Budha dan Budi Pekerti'
];
$sbdp_list = ['Seni Rupa', 'Seni Musik', 'Seni Tari', 'Seni Teater', 'Prakarya'];


foreach ($daftar_mapel_mentah as $mapel) {
    $nama_mapel = $mapel['nama_mapel'];

    if (in_array($nama_mapel, $agama_list)) {
        // Jika ini mapel agama
        $id_mapel_agama[] = $mapel['id_mapel'];
        if (!$pabd_added) {
            $daftar_mapel_final[] = ['id_mapel' => 'PABD', 'kode_mapel' => 'PABD'];
            $pabd_added = true;
        }
    } elseif (in_array($nama_mapel, $sbdp_list)) {
        // Jika ini mapel Seni atau Prakarya
        $id_mapel_sbdp[] = $mapel['id_mapel'];
        if (!$sbdp_added) {
            $daftar_mapel_final[] = ['id_mapel' => 'SBdP', 'kode_mapel' => 'SBdP'];
            $sbdp_added = true;
        }
    } else {
        // Mapel normal
        $daftar_mapel_final[] = $mapel;
    }
}
// =================================================================
// [AKHIR MODIFIKASI]
// =================================================================


// 6. Ambil semua nilai dalam satu query
$q_nilai = "SELECT rda.nilai_akhir, r.id_siswa, rda.id_mapel FROM rapor_detail_akademik rda JOIN rapor r ON rda.id_rapor = r.id_rapor WHERE r.id_kelas = $id_kelas AND r.id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif')";
$result_nilai = mysqli_query($koneksi, $q_nilai);
$nilai_leger = [];
while ($n = mysqli_fetch_assoc($result_nilai)) {
    $id_mapel_db = $n['id_mapel'];
    $id_siswa_db = $n['id_siswa'];
    $nilai_db = $n['nilai_akhir'];

    if (in_array($id_mapel_db, $id_mapel_agama)) {
        // Simpan nilai agama di key 'PABD' HANYA JIKA nilainya valid
        if ($nilai_db > 0) {
            $nilai_leger[$id_siswa_db]['PABD'] = $nilai_db;
        }
    } elseif (in_array($id_mapel_db, $id_mapel_sbdp)) {
        // Kumpulkan semua nilai SBdP (Seni/Prakarya) dalam array
        if ($nilai_db > 0) {
            if (!isset($nilai_leger[$id_siswa_db]['SBdP'])) {
                $nilai_leger[$id_siswa_db]['SBdP'] = [];
            }
            $nilai_leger[$id_siswa_db]['SBdP'][] = $nilai_db;
        }
    } else {
        // Mapel normal
        $nilai_leger[$id_siswa_db][$id_mapel_db] = $nilai_db;
    }
}

// [BARU] Proses Rata-rata Nilai SBdP
foreach ($nilai_leger as $id_siswa => $mapels) {
    if (isset($mapels['SBdP']) && is_array($mapels['SBdP'])) {
        $total_nilai_sbdp = array_sum($mapels['SBdP']);
        $jumlah_mapel_sbdp = count($mapels['SBdP']);
        if ($jumlah_mapel_sbdp > 0) {
            $nilai_leger[$id_siswa]['SBdP'] = round($total_nilai_sbdp / $jumlah_mapel_sbdp);
        } else {
            $nilai_leger[$id_siswa]['SBdP'] = 0; // Seharusnya tidak terjadi
        }
    }
}

// 7. Ambil KKM Dinamis
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$d_kkm = mysqli_fetch_assoc($q_kkm);
$kkm_dinamis = $d_kkm['nilai_pengaturan'] ?? 75;

// --- HEADER UNTUK EKSPOR EXCEL ---
// Ini harus dijalankan SEBELUM ada output HTML apapun
$nama_file = "Leger_Kelas_" . str_replace(' ', '_', $nama_kelas) . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$nama_file\"");
header("Pragma: no-cache");
header("Expires: 0");

// --- Mulai membuat HTML untuk EXCEL ---
?>
<!DOCTYPE html>
<html>
<head>
    <title>Leger Nilai Kelas <?php echo $nama_kelas; ?></title>
    <meta charset="UTF-8">
    <style>
        /* Style sederhana yang bisa dibaca Excel */
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10pt; 
            color: #333; 
        }
        .title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 12pt;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .content-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .content-table th, .content-table td { 
            border: 1px solid #000; 
            padding: 5px; 
            vertical-align: middle;
        }
        .content-table thead th { 
            background-color: #e0e0e0; 
            color: #000;
            font-weight: bold; 
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .nama-siswa { white-space: nowrap; }
        .total-avg-col { background-color: #e0e0e0; font-weight: bold; }
        
        /* Pewarnaan nilai KKM */
        .nilai-kurang { background-color: #f8d7da; color: #721c24; }
        .nilai-cukup { background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>
    
    <div class="title">LEGER NILAI AKHIR SISWA</div>
    <div class="subtitle">
        TAHUN AJARAN: <?php echo htmlspecialchars($tahun_ajaran); ?> | KELAS: <?php echo strtoupper(htmlspecialchars($nama_kelas)); ?>
    </div>
        
    <table class="content-table">
        <thead>
            <tr>
                <!-- Hapus 'vertical-text' class -->
                <th rowspan="2">No</th>
                <th rowspan="2" class="nama-siswa">Nama Siswa</th>
                <!-- [MODIFIKASI] Gunakan daftar mapel final -->
                <th colspan="<?php echo count($daftar_mapel_final); ?>">Mata Pelajaran</th>
                <th rowspan="2">Jumlah</th>
                <th rowspan="2">Rata-Rata</th>
            </tr>
            <tr>
                <!-- [MODIFIKASI] Gunakan daftar mapel final -->
                <?php foreach ($daftar_mapel_final as $mapel): ?>
                    <!-- Hapus 'vertical-text' class -->
                    <th><?php echo htmlspecialchars($mapel['kode_mapel']); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no_urut = 1; 
            // Loop langsung ke semua siswa, bukan per halaman
            foreach($daftar_siswa_all as $siswa): 
            ?>
                <tr>
                    <td class="text-center"><?php echo $no_urut++; ?></td>
                    <td class="text-left"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                    <?php
                    $jumlah_nilai = 0;
                    $jumlah_mapel = 0;
                    // [MODIFIKASI] Gunakan daftar mapel final
                    foreach ($daftar_mapel_final as $mapel):
                        // [MODIFIKASI] Ambil nilai berdasarkan id_mapel (bisa 'PABD', 'SBdP', atau angka)
                        $nilai = $nilai_leger[$siswa['id_siswa']][$mapel['id_mapel']] ?? 0;
                        if ($nilai > 0) {
                            $jumlah_nilai += $nilai;
                            $jumlah_mapel++;
                        }
                        $cell_class = '';
                        
                        if ($nilai > 0 && $nilai < $kkm_dinamis) { $cell_class = 'nilai-kurang'; } 
                        elseif ($nilai >= $kkm_dinamis) { $cell_class = 'nilai-cukup'; }
                    ?>
                        <td class="text-center <?php echo $cell_class; ?>"><?php echo $nilai > 0 ? $nilai : '-'; ?></td>
                    <?php endforeach; ?>
                    <td class="text-center total-avg-col"><?php echo $jumlah_nilai; ?></td>
                    <td class="text-center total-avg-col"><?php echo $jumlah_mapel > 0 ? round($jumlah_nilai / $jumlah_mapel, 2) : '-'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Kop surat dan Tanda tangan dihilangkan untuk versi Excel -->

</body>
</html>