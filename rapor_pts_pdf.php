<?php
// =======================================================================
// KONFIGURASI SISTEM & ERROR HANDLING
// =======================================================================
ini_set('display_errors', 0); // Matikan display error agar tidak merusak PDF
ini_set('display_startup_errors', 0);
error_reporting(0);

// Meningkatkan batas memori dan waktu eksekusi
ini_set('memory_limit', '512M'); 
ini_set('max_execution_time', 300);

session_start();
include 'koneksi.php';
require_once 'libs/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// =======================================================================
// 1. VALIDASI INPUT & PENGATURAN
// =======================================================================
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) {
    die("Error: Parameter ID siswa tidak valid.");
}

// Ambil Pengaturan Global
$pengaturan = [];
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while ($r = mysqli_fetch_assoc($q_set)) {
    $pengaturan[$r['nama_pengaturan']] = $r['nilai_pengaturan'];
}

// Konfigurasi Kertas & Warna
$ukuran_kertas = $pengaturan['rapor_ukuran_kertas'] ?? 'A4';
$skema_warna = $pengaturan['rapor_skema_warna'] ?? 'bw';

// Warna Default (Hitam Putih)
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

// Parameter Rapor PTS
$id_tahun_ajaran = 0;
$tahun_ajaran = '-';
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
if ($d_ta = mysqli_fetch_assoc($q_ta)) {
    $id_tahun_ajaran = $d_ta['id_tahun_ajaran'];
    $tahun_ajaran = $d_ta['tahun_ajaran'];
}

$semester_aktif = $pengaturan['semester_aktif'] ?? 1;
$semester_text = ($semester_aktif == 1) ? '1 (Ganjil)' : '2 (Genap)';
$tgl_rapor_db = $pengaturan['tanggal_rapor_pts'] ?? date("Y-m-d");

// =======================================================================
// 2. FUNGSI BANTUAN
// =======================================================================
function tanggal_indo($tanggal) {
    if(empty($tanggal) || $tanggal == '0000-00-00') return "-";
    $bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
    $pecahkan = explode('-', $tanggal);
    if(count($pecahkan) != 3) return $tanggal;
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

function get_img_base64_local($path) {
    if (!empty($path) && file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return '';
}

$tanggal_rapor_pts = tanggal_indo($tgl_rapor_db);

// =======================================================================
// 3. PENGAMBILAN DATA (SEKOLAH, SISWA, NILAI)
// =======================================================================

// Data Sekolah
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);

// Data Siswa
$q_siswa = mysqli_prepare($koneksi, "
    SELECT s.*, k.nama_kelas, k.fase, g.nama_guru as nama_walikelas, g.nip as nip_walikelas
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru
    WHERE s.id_siswa = ?
");
mysqli_stmt_bind_param($q_siswa, "i", $id_siswa);
mysqli_stmt_execute($q_siswa);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($q_siswa));
if (!$siswa) die("Error: Data siswa tidak ditemukan.");
$id_kelas_siswa = $siswa['id_kelas'];

// Data Kehadiran
$sakit = 0; $izin = 0; $tanpa_ket = 0;
$q_absen = mysqli_query($koneksi, "SELECT sakit, izin, tanpa_keterangan FROM rapor WHERE id_siswa='$id_siswa' AND semester='$semester_aktif' AND id_tahun_ajaran='$id_tahun_ajaran'");
if ($d_absen = mysqli_fetch_assoc($q_absen)) {
    $sakit = $d_absen['sakit'];
    $izin = $d_absen['izin'];
    $tanpa_ket = $d_absen['tanpa_keterangan'];
}

// Persiapan Gambar
$logo_kab_html = '';
if (file_exists('uploads/logo_kabupaten.png')) {
    $img = get_img_base64_local('uploads/logo_kabupaten.png');
    if($img) $logo_kab_html = '<img src="'.$img.'" alt="Logo Kab" style="width: 80px;">';
}

$logo_sek_html = '';
if (!empty($sekolah['logo_sekolah'])) {
    $img = get_img_base64_local('uploads/' . $sekolah['logo_sekolah']);
    if($img) $logo_sek_html = '<img src="'.$img.'" alt="Logo Sekolah" style="width: 80px;">';
}

$watermark_html = '';
if (!empty($pengaturan['watermark_file'])) {
    $img = get_img_base64_local('uploads/' . $pengaturan['watermark_file']);
    if($img) $watermark_html = '<div class="watermark"><img src="'.$img.'" alt="Watermark"></div>';
}

$kop_img_html = '';
$tampil_kop_img = ($pengaturan['rapor_tampil_kop'] ?? '0') == '1';
if ($tampil_kop_img && !empty($pengaturan['file_kop_sekolah'])) {
    $img = get_img_base64_local('uploads/' . $pengaturan['file_kop_sekolah']);
    if($img) $kop_img_html = '<div class="header-img-container"><img src="'.$img.'" alt="KOP"></div>';
}

// LOGIKA NILAI PTS (DETAIL PER KOLOM)
// 1. Ambil Mapel
$mapel_agama_map = ['Islam' => 2, 'Kristen' => 13, 'Hindu' => 14, 'Buddha' => 15, 'Katolik' => 16, 'Khonghucu' => 0];
$agama_siswa = $siswa['agama'] ?? '';
$id_agama = $mapel_agama_map[$agama_siswa] ?? null;
$excl_agama = implode(',', array_values($mapel_agama_map)) ?: '0';

$q_mapel_str = "SELECT mp.id_mapel, mp.nama_mapel 
                FROM mata_pelajaran mp 
                JOIN guru_mengajar gm ON mp.id_mapel=gm.id_mapel 
                WHERE gm.id_kelas='$id_kelas_siswa' AND gm.id_tahun_ajaran='$id_tahun_ajaran'";

if ($id_agama) {
    $q_mapel_str .= " AND (mp.id_mapel NOT IN ($excl_agama) OR mp.id_mapel = $id_agama)";
} else {
    $q_mapel_str .= " AND mp.id_mapel NOT IN ($excl_agama)";
}
$q_mapel_str .= " GROUP BY mp.id_mapel ORDER BY mp.urutan ASC, mp.nama_mapel ASC";
$q_mapel = mysqli_query($koneksi, $q_mapel_str);

$daftar_nilai = [];
$max_jumlah_tp = 0; // Untuk menentukan jumlah kolom S1, S2...

while ($mp = mysqli_fetch_assoc($q_mapel)) {
    // Ambil detail nilai sumatif TP
    $q_detail_nilai = mysqli_query($koneksi, "
        SELECT pdn.nilai 
        FROM penilaian_detail_nilai pdn
        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
        WHERE pdn.id_siswa='$id_siswa' 
          AND p.id_mapel='{$mp['id_mapel']}' 
          AND p.id_kelas='$id_kelas_siswa' 
          AND p.semester='$semester_aktif'
          AND p.subjenis_penilaian='Sumatif TP'
        ORDER BY p.id_penilaian ASC
    ");
    
    $nilai_sumatif_arr = [];
    while ($dn = mysqli_fetch_assoc($q_detail_nilai)) {
        $nilai_sumatif_arr[] = $dn['nilai'];
    }
    
    // Cek jumlah TP terbanyak untuk membuat kolom dinamis
    if (count($nilai_sumatif_arr) > $max_jumlah_tp) {
        $max_jumlah_tp = count($nilai_sumatif_arr);
    }

    // Hitung Rata-rata
    $rata_rata = !empty($nilai_sumatif_arr) ? round(array_sum($nilai_sumatif_arr) / count($nilai_sumatif_arr)) : '-';
    
    $daftar_nilai[] = [
        'nama_mapel' => $mp['nama_mapel'],
        'detail_nilai' => $nilai_sumatif_arr, // Array nilai [80, 85, 90]
        'nilai_pts' => $rata_rata
    ];
}

// Jika tidak ada nilai sama sekali, set minimal 1 kolom agar tabel tidak rusak
if ($max_jumlah_tp == 0) $max_jumlah_tp = 1;

// =======================================================================
// 4. GENERATE HTML
// =======================================================================
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapor PTS - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
        /* Pengaturan Kertas & Margin */
        @page { margin: 170px 30px 40px 30px; }
        header { position: fixed; top: -150px; left: 0px; right: 0px; height: 140px; }
        
        body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; color: #333; }
        
        /* Style Header/KOP */
        .header-table { width: 100%; border-bottom: 3px solid #000; padding-bottom: 5px; margin-bottom: 10px; }
        .header-table .logo-col { width: 15%; text-align: center; vertical-align: middle; }
        .header-table .text-col { width: 70%; text-align: center; vertical-align: middle; }
        .header-table h4 { font-size: 14pt; margin: 0; }
        .header-table h3 { font-size: 18pt; margin: 5px 0; color: <?php echo $theme_kop; ?>; }
        .header-table p { font-size: 9pt; margin: 0; }
        .header-img-container { text-align: center; margin-bottom: 10px; }
        .header-img-container img { width: 100%; height: auto; max-height: 140px; }

        /* Style Identitas */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10pt; }
        .info-table td { padding: 2px 5px; vertical-align: top; }
        
        /* Style Tabel Nilai */
        .content-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .content-table th, .content-table td { border: 1px solid #000; padding: 6px; font-size: 10pt; }
        .content-table th { background-color: <?php echo $theme_bg; ?>; color: <?php echo $theme_text; ?>; text-align: center; font-weight: bold; vertical-align: middle; }
        .nilai-center { text-align: center; font-weight: bold; }
        
        .section-title { font-weight: bold; font-size: 11pt; margin-top: 15px; margin-bottom: 5px; text-transform: uppercase; text-decoration: underline; }
        
        /* Watermark */
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: -1000; opacity: 0.1; width: 70%; text-align: center; }
        .watermark img { width: 100%; height: auto; }
        
        /* Footer */
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 35px; font-size: 8pt; color: #666; border-top: 2px solid <?php echo $theme_bg; ?>; padding: 8px 30px 0 30px; background-color: #fff; }
        .footer-table { width: 100%; border-collapse: collapse; margin: 0; }
        .footer-table td { padding: 0; vertical-align: top; }
        .footer-left { text-align: left; width: 40%; font-weight: bold; color: <?php echo $theme_kop; ?>; }
        .footer-center { text-align: center; width: 40%; font-style: italic; color: #999; }
        .footer-right { text-align: right; width: 20%; }
        .page-badge { background-color: #f0f0f0; padding: 2px 8px; border-radius: 4px; font-weight: bold; color: #333; }
        .footer-right .page-number:after { content: counter(page); }
        
        /* Tanda Tangan */
        .signature-table { width: 100%; margin-top: 40px; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; text-align: center; vertical-align: top; }
        .signature-space { height: 60px; }
    </style>
</head>
<body>

    <!-- Watermark -->
    <?php echo $watermark_html; ?>

    <!-- Footer -->
    <footer>
        <table class="footer-table">
            <tr>
                <td class="footer-left"><?php echo htmlspecialchars($sekolah['nama_sekolah'] ?? ''); ?></td>
                <td class="footer-center"><?php echo htmlspecialchars($siswa['nama_lengkap'] ?? 'Siswa'); ?> - <?php echo htmlspecialchars($siswa['nama_kelas'] ?? ''); ?></td>
                <td class="footer-right"><span class="page-badge">Hal. <span class="page-number"></span></span></td>
            </tr>
        </table>
    </footer>

    <!-- Header (KOP) -->
    <header>
        <?php if ($kop_img_html): ?>
            <?php echo $kop_img_html; ?>
        <?php else: ?>
            <table class="header-table">
                <tr>
                    <td class="logo-col"><?php echo $logo_kab_html; ?></td>
                    <td class="text-col">
                        <h4>PEMERINTAH KABUPATEN <?php echo strtoupper(htmlspecialchars($sekolah['kabupaten_kota'] ?? '')); ?></h4>
                        <h4>DINAS PENDIDIKAN</h4>
                        <h3><?php echo strtoupper(htmlspecialchars($sekolah['nama_sekolah'])); ?></h3>
                        <p><?php echo htmlspecialchars($sekolah['jalan'] ?? ''); ?>, <?php echo htmlspecialchars($sekolah['desa_kelurahan'] ?? ''); ?>, Kec. <?php echo htmlspecialchars($sekolah['kecamatan'] ?? ''); ?></p>
                        <p>Telp: <?php echo htmlspecialchars($sekolah['telepon'] ?? '-'); ?> Email: <?php echo htmlspecialchars($sekolah['email'] ?? '-'); ?></p>
                    </td>
                    <td class="logo-col"><?php echo $logo_sek_html; ?></td>
                </tr>
            </table>
        <?php endif; ?>
    </header>

    <main>
        <!-- Identitas Siswa -->
        <table class="info-table">
            <tr>
                <td width="20%">Nama Peserta Didik</td><td width="1%">:</td><td width="39%"><b><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></b></td>
                <td width="15%">Kelas</td><td width="1%">:</td><td width="24%"><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
            </tr>
            <tr>
                <td>NISN / NIS</td><td>:</td><td><?php echo htmlspecialchars($siswa['nisn'] . ' / ' . ($siswa['nis'] ?? '-')); ?></td>
                <td>Fase</td><td>:</td><td><?php echo htmlspecialchars($siswa['fase']); ?></td>
            </tr>
            <tr>
                <td>Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah['nama_sekolah']); ?></td>
                <td>Semester</td><td>:</td><td><?php echo $semester_text; ?></td>
            </tr>
            <tr>
                <td>Alamat Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah['jalan'] . ', ' . $sekolah['desa_kelurahan']); ?></td>
                <td>Tahun Ajaran</td><td>:</td><td><?php echo htmlspecialchars($tahun_ajaran); ?></td>
            </tr>
        </table>
        
        <div style="border-bottom: 2px solid #000; margin-bottom: 15px;"></div>
        
        <div style="text-align: center; font-size: 12pt; font-weight: bold; margin-bottom: 15px;">
            LAPORAN HASIL BELAJAR TENGAH SEMESTER (PTS)
        </div>

        <!-- Tabel Nilai -->
        <div class="section-title">A. NILAI AKADEMIK</div>
        <table class="content-table">
            <thead>
                <tr>
                    <th rowspan="2" width="5%">No.</th>
                    <th rowspan="2" width="30%">Mata Pelajaran</th>
                    <th colspan="<?php echo $max_jumlah_tp; ?>">Nilai Sumatif Lingkup Materi</th>
                    <th rowspan="2" width="15%">Rata-Rata<br>Sumatif</th>
                </tr>
                <tr>
                    <?php for($i=1; $i<=$max_jumlah_tp; $i++): ?>
                        <th width="8%">S-<?php echo $i; ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($daftar_nilai as $d): ?>
                <tr>
                    <td class="nilai-center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($d['nama_mapel']); ?></td>
                    
                    <!-- Loop Nilai Per Kolom S1, S2, dst -->
                    <?php for($i=0; $i<$max_jumlah_tp; $i++): ?>
                        <td class="nilai-center">
                            <?php echo isset($d['detail_nilai'][$i]) ? $d['detail_nilai'][$i] : '-'; ?>
                        </td>
                    <?php endfor; ?>

                    <td class="nilai-center"><?php echo $d['nilai_pts']; ?></td>
                </tr>
                <?php endforeach; if(empty($daftar_nilai)) echo '<tr><td colspan="'.($max_jumlah_tp + 3).'" class="nilai-center">Belum ada nilai yang tersedia.</td></tr>'; ?>
            </tbody>
        </table>

        <!-- Tabel Kehadiran -->
        <div style="margin-top: 20px;"></div>
        <div class="section-title">B. KETIDAKHADIRAN</div>
        <table class="content-table" style="width: 60%;">
            <thead>
                <tr>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Tanpa Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="nilai-center"><?php echo $sakit; ?> hari</td>
                    <td class="nilai-center"><?php echo $izin; ?> hari</td>
                    <td class="nilai-center"><?php echo $tanpa_ket; ?> hari</td>
                </tr>
            </tbody>
        </table>

        <!-- Tanda Tangan -->
        <table class="signature-table">
            <tr>
                <td>
                    Orang Tua/Wali Murid,<br>
                    <div class="signature-space"></div>
                    ( ................................. )
                </td>
                <td>
                    Mengetahui,<br>Kepala Sekolah,<br>
                    <div class="signature-space"></div>
                    <b><?php echo htmlspecialchars($sekolah['nama_kepsek']); ?></b><br>
                    NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek']); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($sekolah['kabupaten_kota']); ?>, <?php echo $tanggal_rapor_pts; ?><br>
                    Wali Kelas,<br>
                    <div class="signature-space"></div>
                    <b><?php echo htmlspecialchars($siswa['nama_walikelas']); ?></b><br>
                    NIP. <?php echo htmlspecialchars($siswa['nip_walikelas']); ?>
                </td>
            </tr>
        </table>
    </main>

</body>
</html>
<?php
// =======================================================================
// 5. RENDER PDF
// =======================================================================
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', $_SERVER['DOCUMENT_ROOT']); 
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Logika Pemilihan Kertas (A4 / F4)
switch ($ukuran_kertas) {
    case 'F4':
        $width_pt = 215 * 2.83465;
        $height_pt = 330 * 2.83465;
        $dompdf->setPaper([0, 0, $width_pt, $height_pt], 'portrait');
        break;
    default:
        $dompdf->setPaper('A4', 'portrait');
        break;
}

$dompdf->render();
$filename = "RaporPTS - " . preg_replace('/[^A-Za-z0-9_\-]/', '_', $siswa['nama_lengkap']) . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
exit();
?>