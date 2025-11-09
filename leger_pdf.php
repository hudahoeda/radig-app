<?php
// Tampilkan semua error untuk debugging
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

session_start();
include 'koneksi.php';
// Pastikan path ke autoload.php benar
require_once 'libs/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// =================================================================
// BLOK VALIDASI AKSES (UNTUK ADMIN & GURU)
// =================================================================
if (!isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    die("Kelas tidak valid.");
}

// Izinkan akses jika role adalah admin atau guru
if (!in_array($_SESSION['role'], ['admin', 'guru'])) {
    die("Akses ditolak. Halaman ini khusus untuk Admin dan Guru.");
}

// Pengecekan keamanan tambahan KHUSUS untuk GURU
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

// --- PENGAMBILAN DATA (DIOPTIMALKAN) ---

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

// 4. Ambil daftar siswa dan mata pelajaran
$result_siswa = mysqli_query($koneksi, "SELECT id_siswa, nisn, nis, nama_lengkap FROM siswa WHERE id_kelas=$id_kelas AND status_siswa='Aktif' ORDER BY nama_lengkap ASC");
$daftar_siswa = mysqli_fetch_all($result_siswa, MYSQLI_ASSOC);

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


// 5. Ambil semua nilai dalam satu query
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


// 6. Siapkan data tanggal
setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_indonesia.1252');
$tanggal_rapor = strftime('%e %B %Y');
$lokasi_tanggal = ($sekolah['kabupaten_kota'] ?? 'Malang') . ", " . $tanggal_rapor;

// --- PERBAIKAN: Ambil KKM Dinamis ---
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$d_kkm = mysqli_fetch_assoc($q_kkm);
$kkm_dinamis = $d_kkm['nilai_pengaturan'] ?? 75; // Fallback ke 75 jika belum diatur
// --- AKHIR PERBAIKAN ---

// 7. Bagi daftar siswa menjadi 15 per halaman
$siswa_per_halaman = array_chunk($daftar_siswa, 15);

// Mulai membuat HTML untuk PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Leger Nilai Kelas <?php echo $nama_kelas; ?></title>
    <style>
        @page { 
            margin: 15mm 10mm 15mm 10mm; /* Atas, Kanan, Bawah, Kiri */
        }
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 8pt; 
            color: #333; 
        }
        .container { width: 100%; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .page-break { page-break-after: always; }
        
        /* --- PERBAIKAN: KOP SURAT BARU --- */
        .header-table { 
            width: 100%; 
            border-bottom: 3px solid #000; 
            padding-bottom: 5px; 
            margin-top: 10px;
        }
        .header-table .logo-left { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .logo-right { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .kop-text { text-align: center; vertical-align: middle; }
        .header-table h4, .header-table h3, .header-table p { margin: 0; line-height: 1.2; }
        .header-table h4 { font-size: 14pt; } /* PEMERINTAH KABUPATEN */
        .header-table .dinas-text { font-size: 13pt; margin-top: 2px; } /* DINAS PENDIDIKAN */
        .header-table .school-name { font-size: 20pt; font-weight: bold; margin: 5px 0; color: #004d40; } /* [TEMA] NAMA SEKOLAH - Teal Gelap */
        .header-table .school-info { font-size: 9pt; line-height: 1.3; } /* Alamat, Telp, Email */
        /* --- AKHIR PERBAIKAN KOP --- */
        
        .title h4 { margin: 0; font-size: 12pt; text-transform: uppercase; text-decoration: underline; font-weight: bold; }
        .title h5 { margin: 5px 0 15px 0; font-size: 10pt; font-weight: normal; }
        
        .content-table { width: 100%; border-collapse: collapse; }
        .content-table th, .content-table td { border: 1px solid #000; padding: 5px; } /* Border diganti ke #000 */
        .content-table thead th { 
            background-color: #e0f2f1; /* [TEMA] Warna header diubah jadi Teal Muda */
            color: #004d40; /* [TEMA] Teks header jadi Teal Gelap */
            font-weight: bold; 
        }
        .content-table tbody tr:nth-child(even) { background-color: #f4f7f6; } /* [TEMA] Off-white lembut */
        .vertical-text { writing-mode: vertical-lr; text-orientation: mixed; white-space: nowrap; padding: 5px; }
        .nama-siswa { width: 200px; white-space: nowrap; }
        .total-avg-col { background-color: #e0f2f1; color: #004d40; font-weight: bold; } /* [TEMA] Samakan dengan header */
        
        .signature-table { margin-top: 30px; width: 100%; page-break-inside: avoid; }
        .signature-table td { width: 50%; padding: 10px; }
        .signature-space { height: 60px; }
        
        .nilai-kurang { background-color: #f8d7da; color: #721c24; }
        .nilai-cukup { background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <?php foreach ($siswa_per_halaman as $halaman => $daftar_siswa_halaman): ?>
    
    <div class="container">
        <!-- ====================================================== -->
        <!-- ### BLOK KOP SURAT BARU ### -->
        <!-- ====================================================== -->
        <table class="header-table">
            <tr>
                <td class="logo-left">
                    <?php
                    // Logo kiri (logo kabupaten) - Asumsi nama file
                    $path_logo_daerah = 'uploads/logo_kabupaten.png';
                    if (file_exists($path_logo_daerah)) {
                        $type = pathinfo($path_logo_daerah, PATHINFO_EXTENSION);
                        $data = file_get_contents($path_logo_daerah);
                        $base64_logo_daerah = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        echo '<img src="' . $base64_logo_daerah . '" alt="Logo Daerah" style="width: 80px;">';
                    }
                    ?>
                </td>
                <td class="kop-text">
                    <h4>PEMERINTAH KABUPATEN <?php echo strtoupper(htmlspecialchars($sekolah['kabupaten_kota'] ?? 'N/A')); ?></h4>
                    <p class="dinas-text">DINAS PENDIDIKAN</p>
                    <h3 class="school-name"><?php echo strtoupper(htmlspecialchars($sekolah['nama_sekolah'] ?? 'NAMA SEKOLAH')); ?></h3>
                    <p class="school-info">
                        <?php 
                        // PERBAIKAN: Menggunakan alamat baru, bukan alamat_legacy
                        $alamat_parts = [];
                        if (!empty($sekolah['jalan']) && $sekolah['jalan'] != '-') $alamat_parts[] = htmlspecialchars($sekolah['jalan']);
                        if (!empty($sekolah['desa_kelurahan'])) $alamat_parts[] = htmlspecialchars($sekolah['desa_kelurahan']);
                        if (!empty($sekolah['kecamatan'])) $alamat_parts[] = 'Kec. ' . htmlspecialchars($sekolah['kecamatan']);
                        if (!empty($sekolah['kabupaten_kota'])) $alamat_parts[] = 'Kab. ' . htmlspecialchars($sekolah['kabupaten_kota']);
                        echo implode(', ', $alamat_parts);
                        
                        $contact_info = [];
                        if (!empty($sekolah['telepon'])) $contact_info[] = 'Telp. ' . htmlspecialchars($sekolah['telepon']);
                        if (!empty($sekolah['email'])) $contact_info[] = 'Email: ' . htmlspecialchars($sekolah['email']);
                        if (!empty($sekolah['website'])) $contact_info[] = 'Website: ' . htmlspecialchars($sekolah['website']);
                        if (!empty($contact_info)) echo '<br>' . implode(' | ', $contact_info);
                        ?>
                    </p>
                </td>
                <td class="logo-right">
                    <?php 
                    // Logo kanan (logo sekolah)
                    if (!empty($sekolah['logo_sekolah']) && file_exists('uploads/' . $sekolah['logo_sekolah'])){
                        $path = 'uploads/' . $sekolah['logo_sekolah'];
                        $type = pathinfo($path, PATHINFO_EXTENSION);
                        $data = file_get_contents($path);
                        $base64_logo_sekolah = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        echo '<img src="' . $base64_logo_sekolah . '" alt="Logo Sekolah" style="width: 80px;">';
                    }
                    ?>
                </td>
            </tr>
        </table>
        <!-- ====================================================== -->
        <!-- ### AKHIR BLOK KOP SURAT ### -->
        <!-- ====================================================== -->

        <div class="title text-center">
            <h4>LEGER NILAI AKHIR SISWA</h4>
            <h5>TAHUN AJARAN: <?php echo htmlspecialchars($tahun_ajaran); ?> | KELAS: <?php echo strtoupper(htmlspecialchars($nama_kelas)); ?></h5>
        </div>
        
        <table class="content-table">
            <thead>
                <tr>
                    <th rowspan="2">No</th>
                    <th rowspan="2" class="nama-siswa">Nama Siswa</th>
                    <!-- [MODIFIKASI] Gunakan daftar mapel final -->
                    <th colspan="<?php echo count($daftar_mapel_final); ?>">Mata Pelajaran</th>
                    <th rowspan="2" class="vertical-text">Jumlah</th>
                    <th rowspan="2" class="vertical-text">Rata-Rata</th>
                </tr>
                <tr>
                    <!-- [MODIFIKASI] Gunakan daftar mapel final -->
                    <?php foreach ($daftar_mapel_final as $mapel): ?>
                        <th class="vertical-text"><?php echo htmlspecialchars($mapel['kode_mapel']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no_urut = $halaman * 15 + 1; 
                foreach($daftar_siswa_halaman as $siswa): 
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
                            
                            // --- PERBAIKAN: Menggunakan KKM Dinamis ---
                            if ($nilai > 0 && $nilai < $kkm_dinamis) { $cell_class = 'nilai-kurang'; } 
                            elseif ($nilai >= $kkm_dinamis) { $cell_class = 'nilai-cukup'; }
                            // --- AKHIR PERBAIKAN ---
                        ?>
                            <td class="text-center <?php echo $cell_class; ?>"><?php echo $nilai > 0 ? $nilai : '-'; ?></td>
                        <?php endforeach; ?>
                        <td class="text-center total-avg-col"><?php echo $jumlah_nilai; ?></td>
                        <td class="text-center total-avg-col"><?php echo $jumlah_mapel > 0 ? round($jumlah_nilai / $jumlah_mapel, 2) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($halaman < count($siswa_per_halaman) - 1): ?>
        <div class="page-break"></div>
    <?php endif; ?>

    <?php endforeach; ?>

    <table class="signature-table">
        <tr>
            <td class="text-center">
                Mengetahui,<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <strong><?php echo htmlspecialchars($sekolah['nama_kepsek'] ?? 'Nama Kepala Sekolah'); ?></strong><br>
                <span style="font-size: 8pt;"><?php echo htmlspecialchars($sekolah['jabatan_kepsek'] ?? 'Jabatan Kepala Sekolah'); ?></span><br>
                NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek'] ?? '-'); ?>
            </td>
            <td class="text-center">
                <?php echo $lokasi_tanggal; ?><br>
                Wali Kelas
                <div class="signature-space"></div>
                <strong><?php echo htmlspecialchars($walikelas['nama_guru']); ?></strong><br>
                NIP. <?php echo htmlspecialchars($walikelas['nip']); ?>
            </td>
        </tr>
    </table>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
// Atur chroot jika path gambar bermasalah
// $options->set('chroot', $_SERVER['DOCUMENT_ROOT'] . '/rapor'); 
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

ob_end_clean();

$nama_file = "Leger Kelas " . str_replace(' ', '_', $nama_kelas) . ".pdf";
$dompdf->stream($nama_file, ["Attachment" => 0]);
?>