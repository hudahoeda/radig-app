<?php
// =================================================================
// KONFIGURASI & AKSES
// =================================================================
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

session_start();
include 'koneksi.php';
require_once 'libs/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. VALIDASI AKSES
if (!isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    die("Kelas tidak valid.");
}

if (!in_array($_SESSION['role'], ['admin', 'guru'])) {
    die("Akses ditolak.");
}

if ($_SESSION['role'] == 'guru') {
    $id_guru_login = (int)$_SESSION['id_guru'];
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_kelas = ?");
    mysqli_stmt_bind_param($stmt_cek, "ii", $id_guru_login, $id_kelas);
    mysqli_stmt_execute($stmt_cek);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt_cek)) == 0) {
        die("Akses ditolak. Anda bukan wali kelas untuk kelas ini.");
    }
}

// =================================================================
// 2. PENGAMBILAN DATA & PENGATURAN
// =================================================================

// Ambil Pengaturan Global (Untuk Warna & Kertas)
$pengaturan = [];
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while ($r = mysqli_fetch_assoc($q_set)) {
    $pengaturan[$r['nama_pengaturan']] = $r['nilai_pengaturan'];
}

// Konfigurasi Warna (Tema) - Agar sinkron dengan Rapor
$skema_warna = $pengaturan['rapor_skema_warna'] ?? 'bw';
$theme_bg = '#444444'; 
$theme_text = '#FFFFFF'; 
$theme_kop = '#000000';

switch ($skema_warna) {
    case 'light_blue': $theme_bg = '#E3F2FD'; $theme_text = '#0D47A1'; $theme_kop = '#0D47A1'; break;
    case 'light_green': $theme_bg = '#E8F5E9'; $theme_text = '#1B5E20'; $theme_kop = '#1B5E20'; break;
    case 'light_teal': $theme_bg = '#E0F2F1'; $theme_text = '#004D40'; $theme_kop = '#004D40'; break;
    case 'light_purple': $theme_bg = '#EDE7F6'; $theme_text = '#311B92'; $theme_kop = '#311B92'; break;
    case 'light_red': $theme_bg = '#FFEBEE'; $theme_text = '#B71C1C'; $theme_kop = '#B71C1C'; break;
}

// Data Kelas & Tahun Ajaran
$q_kelas = mysqli_query($koneksi, "SELECT k.nama_kelas, ta.id_tahun_ajaran, ta.tahun_ajaran FROM kelas k JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran WHERE k.id_kelas=$id_kelas");
$data_kelas = mysqli_fetch_assoc($q_kelas);
$nama_kelas = $data_kelas['nama_kelas'] ?? 'N/A';
$tahun_ajaran = $data_kelas['tahun_ajaran'] ?? 'N/A';
$id_tahun_ajaran_aktif = $data_kelas['id_tahun_ajaran'] ?? 0;

// Data Sekolah
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah LIMIT 1");
$sekolah = mysqli_fetch_assoc($q_sekolah) ?? [];

// Data Wali Kelas
$q_walikelas = mysqli_query($koneksi, "SELECT g.nama_guru, g.nip FROM guru g JOIN kelas k ON g.id_guru = k.id_wali_kelas WHERE k.id_kelas = $id_kelas");
$walikelas = mysqli_fetch_assoc($q_walikelas) ?? ['nama_guru' => 'Belum Ditentukan', 'nip' => '-'];

// Data KKM
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm = mysqli_fetch_assoc($q_kkm)['nilai_pengaturan'] ?? 75;

// Ambil semester aktif
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Data Siswa (Hanya Aktif)
$result_siswa = mysqli_query($koneksi, "SELECT id_siswa, nis, nama_lengkap FROM siswa WHERE id_kelas=$id_kelas AND status_siswa='Aktif' ORDER BY nama_lengkap ASC");
$daftar_siswa = [];
while ($row_s = mysqli_fetch_assoc($result_siswa)) {
    $daftar_siswa[] = $row_s;
}

// Helper Gambar
function get_img_base64_local($path) {
    if (!empty($path)) {
        $full_path = 'uploads/' . $path; // Asumsi path relatif dari root folder script
        if (file_exists($full_path) && is_readable($full_path)) {
            $type = pathinfo($full_path, PATHINFO_EXTENSION);
            $data = file_get_contents($full_path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
    }
    return '';
}

// Persiapan Logo
$logo_kab_html = '';
if (file_exists('uploads/logo_kabupaten.png')) {
    $img = get_img_base64_local('logo_kabupaten.png');
    if($img) $logo_kab_html = '<img src="'.$img.'" style="height: 80px; width: auto;">';
}

$logo_sek_html = '';
if (!empty($sekolah['logo_sekolah'])) {
    $img = get_img_base64_local($sekolah['logo_sekolah']);
    if($img) $logo_sek_html = '<img src="'.$img.'" style="height: 80px; width: auto;">';
}

$tampil_kop_img = ($pengaturan['rapor_tampil_kop'] ?? '0') == '1';
$kop_img_html = '';
if ($tampil_kop_img && !empty($pengaturan['file_kop_sekolah'])) {
    $img = get_img_base64_local($pengaturan['file_kop_sekolah']);
    if($img) $kop_img_html = '<img src="'.$img.'" style="width: 100%; height: auto; max-height: 140px;">';
}


// =================================================================
// 3. LOGIKA MAPEL
// =================================================================
$result_mapel = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel, kode_mapel FROM mata_pelajaran ORDER BY urutan ASC");
$mapel_db = [];
while ($row_m = mysqli_fetch_assoc($result_mapel)) {
    $mapel_db[] = $row_m;
}

$header_mapel = []; 
$id_agama = [];     
$id_sbdp = [];      
$agama_added = false;
$sbdp_added = false;

$agama_names = ['Pendidikan Agama Islam dan Budi Pekerti', 'Pendidikan Agama Kristen dan Budi Pekerti', 'Pendidikan Agama Katolik dan Budi Pekerti', 'Pendidikan Agama Hindu dan Budi Pekerti', 'Pendidikan Agama Budha dan Budi Pekerti', 'Pendidikan Agama Khonghucu dan Budi Pekerti'];
$sbdp_names = ['Seni Rupa', 'Seni Musik', 'Seni Tari', 'Seni Teater', 'Prakarya'];

foreach ($mapel_db as $m) {
    $id_m = (string)$m['id_mapel'];
    if (in_array($m['nama_mapel'], $agama_names)) {
        $id_agama[] = $id_m;
        if (!$agama_added) {
            $header_mapel[] = ['id' => 'PABD', 'kode' => 'PABD'];
            $agama_added = true;
        }
    } elseif (in_array($m['nama_mapel'], $sbdp_names)) {
        $id_sbdp[] = $id_m;
        if (!$sbdp_added) {
            $header_mapel[] = ['id' => 'SBdP', 'kode' => 'SBdP'];
            $sbdp_added = true;
        }
    } else {
        $header_mapel[] = ['id' => $id_m, 'kode' => $m['kode_mapel']];
    }
}

// =================================================================
// 4. PENGAMBILAN NILAI (SMART FALLBACK UNTUK PDF)
// =================================================================

// TAHAP A: AMBIL NILAI DINAMIS DARI BANK NILAI
$q_sumatif = "
    SELECT pdn.id_siswa, p.id_mapel, 
           SUM(pdn.nilai * p.bobot_penilaian) / NULLIF(SUM(p.bobot_penilaian), 0) as rata_sumatif
    FROM penilaian_detail_nilai pdn
    JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
    WHERE p.id_kelas = $id_kelas AND p.semester = $semester_aktif AND p.jenis_penilaian = 'Sumatif'
    GROUP BY pdn.id_siswa, p.id_mapel
";
$res_sum = mysqli_query($koneksi, $q_sumatif);
$bank_nilai = [];
if ($res_sum) {
    while($rs = mysqli_fetch_assoc($res_sum)) {
        if ($rs['rata_sumatif'] !== null) {
            $bank_nilai[$rs['id_siswa']][$rs['id_mapel']] = round($rs['rata_sumatif']);
        }
    }
}

// TAHAP B: AMBIL NILAI TERSIMPAN DARI TABEL RAPOR
$q_nilai = "SELECT rda.nilai_akhir, rda.nilai_katrol, r.id_siswa, rda.id_mapel 
            FROM rapor_detail_akademik rda 
            JOIN rapor r ON rda.id_rapor = r.id_rapor 
            WHERE r.id_kelas = $id_kelas 
            AND r.semester = $semester_aktif
            AND r.id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif')";
$result_nilai = mysqli_query($koneksi, $q_nilai);
$rapor_db = [];
while ($row = mysqli_fetch_assoc($result_nilai)) {
    $rapor_db[$row['id_siswa']][$row['id_mapel']] = [
        'akhir' => isset($row['nilai_akhir']) ? (float)$row['nilai_akhir'] : 0,
        'katrol' => isset($row['nilai_katrol']) ? (float)$row['nilai_katrol'] : 0
    ];
}

// TAHAP C: GABUNGKAN DATA (Prioritas: Katrol > Rapor > Bank Nilai)
$data_nilai = [];
foreach ($daftar_siswa as $siswa) {
    $sid = $siswa['id_siswa'];
    foreach ($mapel_db as $m) {
        $mid = $m['id_mapel'];
        
        $key_mapel = $mid;
        if (in_array($mid, $id_agama)) $key_mapel = 'PABD';
        if (in_array($mid, $id_sbdp)) $key_mapel = 'SBdP';

        $asli = 0;
        $katrol = 0;

        // Cek apakah sudah ada di Rapor DB
        if (isset($rapor_db[$sid][$mid])) {
            $katrol = $rapor_db[$sid][$mid]['katrol'];
            $asli = $rapor_db[$sid][$mid]['akhir'];
        }

        // SMART FALLBACK: Jika Asli masih 0 (belum digenerate), ambil dari hitungan Bank Nilai
        if ($asli == 0 && isset($bank_nilai[$sid][$mid])) {
            $asli = $bank_nilai[$sid][$mid];
        }

        if ($asli == 0 && $katrol == 0) continue;

        $final = ($katrol > $asli) ? $katrol : $asli;
        $is_katrol = ($katrol > $asli);

        if ($key_mapel == 'SBdP') {
            $data_nilai[$sid][$key_mapel]['asli'][] = $asli;
            $data_nilai[$sid][$key_mapel]['katrol'][] = $katrol;
            $data_nilai[$sid][$key_mapel]['final'][] = $final;
        } else {
            // Untuk PDF, yang ditampilkan cuma nilai Final yang terpilih
            $data_nilai[$sid][$key_mapel] = [
                'nilai' => $final,
                'katrol' => $is_katrol
            ];
        }
    }
}

// Rekapitulasi & Ranking
$rekap_siswa = []; 

foreach ($daftar_siswa as $k => $siswa) {
    $sid = $siswa['id_siswa'];
    $total_nilai = 0;
    $jumlah_mapel = 0;

    // Kalkulasi Rata-rata SBdP
    if (isset($data_nilai[$sid]['SBdP']['final']) && is_array($data_nilai[$sid]['SBdP']['final'])) {
        $arr_asli = array_filter($data_nilai[$sid]['SBdP']['asli'], function($v) { return $v > 0; });
        $arr_katrol = array_filter($data_nilai[$sid]['SBdP']['katrol'], function($v) { return $v > 0; });
        $arr_final = array_filter($data_nilai[$sid]['SBdP']['final'], function($v) { return $v > 0; });
        
        $avg_asli = (count($arr_asli) > 0) ? round(array_sum($arr_asli) / count($arr_asli)) : 0;
        $avg_katrol = (count($arr_katrol) > 0) ? round(array_sum($arr_katrol) / count($arr_katrol)) : 0;
        $avg_final = (count($arr_final) > 0) ? round(array_sum($arr_final) / count($arr_final)) : 0;
        
        $is_katrol_sbdp = ($avg_final > $avg_asli);

        $data_nilai[$sid]['SBdP'] = [
            'nilai' => $avg_final,
            'katrol' => $is_katrol_sbdp
        ];
    } else {
        $data_nilai[$sid]['SBdP'] = ['nilai' => 0, 'katrol' => false];
    }

    // Hitung Total (Berdasarkan Nilai Final/Rapor)
    foreach ($header_mapel as $hm) {
        $data = $data_nilai[$sid][$hm['id']] ?? ['nilai' => 0];
        $val = $data['nilai'];
        if ($val > 0) {
            $total_nilai += $val;
            $jumlah_mapel++;
        }
    }

    $rata_rata = ($jumlah_mapel > 0) ? round($total_nilai / $jumlah_mapel, 2) : 0;

    $daftar_siswa[$k]['total'] = $total_nilai;
    $daftar_siswa[$k]['rata'] = $rata_rata;
    $rekap_siswa[$sid] = $total_nilai; // Untuk sorting
}

// [PERMINTAAN] URUTKAN SESUAI RANKING (DESCENDING TOTAL NILAI)
arsort($rekap_siswa); // Sort nilai dari tinggi ke rendah, key (id_siswa) tetap terjaga

$rank = 1;
$rank_map = [];
$sorted_daftar_siswa = [];

foreach ($rekap_siswa as $sid => $total) {
    $rank_map[$sid] = $rank++;
    foreach ($daftar_siswa as $ds) {
        if ($ds['id_siswa'] == $sid) {
            $sorted_daftar_siswa[] = $ds;
            break;
        }
    }
}
// Tambahkan siswa yang mungkin tidak punya nilai di akhir
foreach ($daftar_siswa as $ds) {
    if (!isset($rekap_siswa[$ds['id_siswa']])) {
        $sorted_daftar_siswa[] = $ds;
        $rank_map[$ds['id_siswa']] = $rank++;
    }
}

// Gunakan daftar siswa yang sudah diurutkan
$daftar_siswa = $sorted_daftar_siswa;

// Bagi halaman (15 siswa per halaman)
$siswa_per_halaman = array_chunk($daftar_siswa, 15);

// Set Tanggal
setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_indonesia.1252');
function tgl_indo_full($tanggal){
    $bulan = array (
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecahkan = explode('-', $tanggal);
    if(count($pecahkan)!=3) return $tanggal;
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}
$tanggal_cetak = tgl_indo_full(date('Y-m-d'));
$lokasi_tanggal = ($sekolah['kabupaten_kota'] ?? 'Malang') . ", " . $tanggal_cetak;

// =================================================================
// 5. GENERATE HTML PDF
// =================================================================
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Leger Nilai Kelas <?php echo $nama_kelas; ?></title>
    <style>
        @page { margin: 10mm 10mm 10mm 10mm; } 
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 9pt; color: #333; }
        
        /* KOP SURAT (Disesuaikan dengan format rapor_cetak_massal.php) */
        .header-table { width: 100%; border-bottom: 3px solid #000; padding-bottom: 5px; margin-bottom: 15px; }
        .header-table .logo-left { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .logo-right { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .kop-text { text-align: center; vertical-align: middle; }
        .header-table h4, .header-table h3, .header-table p { margin: 0; line-height: 1.2; }
        .header-table h4 { font-size: 14pt; }
        .header-table .dinas-text { font-size: 13pt; margin-top: 2px; }
        .header-table .school-name { font-size: 20pt; font-weight: bold; margin: 5px 0; color: <?php echo $theme_kop; ?>; } /* Warna Tema KOP */
        .header-table .school-info { font-size: 9pt; line-height: 1.3; }

        .header-img-container { width: 100%; text-align: center; margin-bottom: 10px; border-bottom: 3px solid #000; padding-bottom: 5px; }
        .header-img-container img { width: 100%; height: auto; max-height: 140px; }
        
        .title-block { text-align: center; margin-bottom: 15px; }
        .title-block h4 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase; color: #333; letter-spacing: 1px; }
        .title-block span { font-size: 10pt; color: #555; }
        
        /* TABEL LEGER MODERN */
        .leger-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        .leger-table th, .leger-table td { border: 1px solid #ccc; padding: 6px 4px; text-align: center; } /* Border lebih halus */
        .leger-table th { 
            background-color: <?php echo $theme_bg; ?>; /* Warna Tema Header */
            color: <?php echo $theme_text; ?>; /* Warna Teks Header */
            font-weight: bold; 
            vertical-align: middle; 
            height: 35px;
            border-color: #999;
        }
        .leger-table td { border-color: #ddd; vertical-align: middle; }
        .leger-table td.nama { 
            text-align: left; 
            padding-left: 8px; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            max-width: 180px; 
            font-weight: 500;
        }
        .leger-table tr:nth-child(even) { background-color: #f9f9f9; } /* Zebra Striping */
        
        /* Warna Nilai */
        .nilai-kurang { background-color: #ffebee; color: #b71c1c; font-weight: bold; }
        .nilai-katrol { color: #0d47a1; font-weight: bold; }
        .bg-total { background-color: #eeeeee; font-weight: bold; color: #333; }
        .rank-col { background-color: #fffde7; font-weight: bold; color: #f57f17; font-size: 9pt; }
        
        .page-break { page-break-after: always; }
        
        .signature-table { width: 100%; margin-top: 30px; page-break-inside: avoid; font-size: 10pt; }
        .signature-table td { width: 33%; text-align: center; vertical-align: top; }
        .signature-space { height: 70px; }
    </style>
</head>
<body>

<?php foreach ($siswa_per_halaman as $idx => $data_halaman): ?>

    <!-- KOP SURAT (Konsisten dengan Rapor) -->
    <?php if ($tampil_kop_img && !empty($kop_img_html)): ?>
        <div class="header-img-container">
            <?php echo $kop_img_html; ?>
        </div>
    <?php else: ?>
        <table class="header-table">
            <tr>
                <td class="logo-left">
                    <?php if (!empty($logo_kab_html)) echo $logo_kab_html; ?>
                </td>
                <td class="kop-text">
                    <h4>PEMERINTAH KABUPATEN <?php echo strtoupper(htmlspecialchars($sekolah['kabupaten_kota'] ?? '')); ?></h4>
                    <p class="dinas-text">DINAS PENDIDIKAN</p>
                    <h3 class="school-name"><?php echo strtoupper(htmlspecialchars($sekolah['nama_sekolah'] ?? 'NAMA SEKOLAH')); ?></h3>
                    <p class="school-info">
                        <?php echo htmlspecialchars($sekolah['jalan'] ?? ''); ?>, <?php echo htmlspecialchars($sekolah['desa_kelurahan'] ?? ''); ?>, Kec. <?php echo htmlspecialchars($sekolah['kecamatan'] ?? ''); ?><br>
                        Telp: <?php echo htmlspecialchars($sekolah['telepon'] ?? '-'); ?> Email: <?php echo htmlspecialchars($sekolah['email'] ?? '-'); ?>
                    </p>
                </td>
                <td class="logo-right">
                    <?php if (!empty($logo_sek_html)) echo $logo_sek_html; ?>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <div class="title-block">
        <h4>LEGER NILAI AKHIR - KELAS <?php echo strtoupper($nama_kelas); ?></h4>
        <span>Tahun Ajaran: <?php echo $tahun_ajaran; ?> | Semester: <?php echo $semester_aktif; ?></span>
    </div>

    <table class="leger-table">
        <thead>
            <tr>
                <th width="25">No</th>
                <th width="65">NIS</th>
                <th width="180">Nama Siswa</th>
                <!-- Header Mapel -->
                <?php foreach ($header_mapel as $hm): ?>
                    <th><?php echo $hm['kode']; ?></th>
                <?php endforeach; ?>
                <th width="45">Jml</th>
                <th width="45">Rata</th>
                <th width="35">Rank</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = ($idx * 15) + 1;
            foreach ($data_halaman as $siswa): 
                $sid = $siswa['id_siswa'];
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo $siswa['nis']; ?></td>
                <td class="nama"><?php echo strtoupper($siswa['nama_lengkap']); ?></td>
                
                <?php foreach ($header_mapel as $hm): ?>
                    <?php 
                        $data = $data_nilai[$sid][$hm['id']] ?? ['nilai' => 0, 'katrol' => false];
                        $nilai = $data['nilai'];
                        $is_katrol = $data['katrol'];
                        
                        $class = "";
                        if ($nilai > 0 && $nilai < $kkm) $class .= " nilai-kurang";
                        if ($is_katrol) $class .= " nilai-katrol";
                    ?>
                    <td class="<?php echo $class; ?>">
                        <?php echo ($nilai > 0) ? $nilai : '-'; ?>
                    </td>
                <?php endforeach; ?>
                
                <td class="bg-total"><?php echo $siswa['total']; ?></td>
                <td class="bg-total"><?php echo $siswa['rata']; ?></td>
                <td class="rank-col"><?php echo $rank_map[$sid]; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- TTD hanya di halaman terakhir -->
    <?php if ($idx == count($siswa_per_halaman) - 1): ?>
    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br>Kepala Sekolah
                <div class="signature-space"></div>
                <strong><u><?php echo htmlspecialchars($sekolah['nama_kepsek'] ?? ''); ?></u></strong><br>
                <span style="font-size: 8pt;"><?php echo htmlspecialchars($sekolah['jabatan_kepsek'] ?? ''); ?></span><br>
                NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek'] ?? '-'); ?>
            </td>
            <td></td>
            <td>
                <?php echo $lokasi_tanggal; ?><br>Wali Kelas
                <div class="signature-space"></div>
                <strong><u><?php echo htmlspecialchars($walikelas['nama_guru']); ?></u></strong><br>
                NIP. <?php echo htmlspecialchars($walikelas['nip']); ?>
            </td>
        </tr>
    </table>
    
    <div style="font-size: 8pt; margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 5px; color: #555;">
        <strong>Keterangan:</strong><br>
        <span style="color: #b71c1c; background-color: #ffebee; border: 1px solid #ccc; padding: 0 5px; font-size: 7pt;">Merah</span> : Nilai di bawah KKM (<?php echo $kkm; ?>)<br>
        <span style="color: #0d47a1; font-weight: bold;">Biru Tebal</span> : Nilai Hasil Katrol
    </div>
    <?php endif; ?>

    <?php if ($idx < count($siswa_per_halaman) - 1): ?>
        <div class="page-break"></div>
    <?php endif; ?>

<?php endforeach; ?>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', $_SERVER['DOCUMENT_ROOT']); 
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// [PENTING] Set kertas ke LANDSCAPE agar muat banyak kolom
// Cek pengaturan dari DB jika ada, default A4 Landscape
if (isset($pengaturan['rapor_ukuran_kertas']) && $pengaturan['rapor_ukuran_kertas'] == 'F4') {
    // Ukuran F4 dalam point (8.5 x 13 inch)
    $width_pt = 612; // 8.5 inch * 72
    $height_pt = 936; // 13 inch * 72
    $dompdf->setPaper([0, 0, $width_pt, $height_pt], 'landscape'); // F4 Landscape manual
} else {
    $dompdf->setPaper('A4', 'landscape');
}

$dompdf->render();
$dompdf->stream("Leger_Smt" . $semester_aktif . "_Kelas_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $nama_kelas) . ".pdf", array("Attachment" => 0));
?>