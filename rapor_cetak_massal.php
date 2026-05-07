<?php
// =======================================================================
// KONFIGURASI SISTEM & ERROR HANDLING
// =======================================================================
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Meningkatkan batas memori dan waktu eksekusi untuk proses berat
ini_set('memory_limit', '1024M'); 
ini_set('max_execution_time', 3600); // 1 Jam

session_start();
include 'koneksi.php';
require_once 'libs/autoload.php'; 

// [CRITICAL] Memperbesar limit concat agar deskripsi panjang tidak terpotong saat massal
mysqli_query($koneksi, "SET SESSION group_concat_max_len = 1000000");

use Dompdf\Dompdf;
use Dompdf\Options;

// =======================================================================
// 1. VALIDASI INPUT & PARAMETER
// =======================================================================
$tipe_cetak = isset($_GET['tipe']) ? $_GET['tipe'] : ''; // rapor, sampul, identitas
$ids_string = isset($_GET['ids']) ? $_GET['ids'] : '';
$kertas_get = isset($_GET['kertas']) ? $_GET['kertas'] : ''; // Opsi override kertas dari URL

$allowed_types = ['sampul', 'identitas', 'rapor'];
if (empty($tipe_cetak) || empty($ids_string) || !in_array($tipe_cetak, $allowed_types)) {
    die("Error: Parameter tidak lengkap atau tipe cetak salah.");
}

// Konversi string ID "1,2,3" menjadi array [1, 2, 3]
$ids = array_map('intval', explode(',', $ids_string));
if (empty($ids)) {
    die("Error: Tidak ada siswa dipilih.");
}

// =======================================================================
// 2. FUNGSI BANTUAN (SINKRONISASI DENGAN SINGLE PRINT)
// =======================================================================

// Fungsi Tanggal Indo
if (!function_exists('tanggal_indo')) {
    function tanggal_indo($tanggal) {
        if(empty($tanggal) || $tanggal == '0000-00-00') return "-";
        $bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
        $pecahkan = explode('-', $tanggal);
        if(count($pecahkan) != 3) return $tanggal;
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }
}

// Fungsi Hitung Deskripsi Otomatis (LOGIKA SAMA PERSIS DENGAN SINGLE PRINT)
if (!function_exists('hitungDeskripsiOtomatis')) {
    function hitungDeskripsiOtomatis($koneksi, $id_siswa, $id_kelas, $id_mapel, $kkm, $semester_aktif) {
        
        // 1. Ambil Nilai Sumatif Lingkup Materi (TP)
        $stmt_sumatif_tp = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian, GROUP_CONCAT(tp.deskripsi_tp SEPARATOR '|||') as deskripsi_tps
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian 
            JOIN penilaian_tp ptp ON p.id_penilaian = ptp.id_penilaian
            JOIN tujuan_pembelajaran tp ON ptp.id_tp = tp.id_tp 
            WHERE p.subjenis_penilaian = 'Sumatif TP' 
            AND pdn.id_siswa = ? AND p.id_mapel = ? AND p.id_kelas = ? AND p.semester = ? 
            GROUP BY p.id_penilaian, pdn.nilai, p.bobot_penilaian
        ");
        
        // 2. Ambil Nilai Sumatif Akhir
        $stmt_sumatif_akhir = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian 
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian 
            WHERE p.subjenis_penilaian IN ('Sumatif Akhir Semester', 'Sumatif Akhir Tahun')
            AND p.jenis_penilaian = 'Sumatif' 
            AND pdn.id_siswa = ? AND p.id_mapel = ? AND p.id_kelas = ? AND p.semester = ?
        ");
        
        $skor_per_tp = []; 
        $total_nilai_x_bobot = 0; 
        $total_bobot = 0;

        // Eksekusi Query Sumatif TP
        if ($stmt_sumatif_tp) {
            mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
            mysqli_stmt_execute($stmt_sumatif_tp);
            $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
            
            while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
                $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
                foreach($tps_individu as $desc_tp) {
                    if (!isset($skor_per_tp[$desc_tp])) { $skor_per_tp[$desc_tp] = []; }
                    $skor_per_tp[$desc_tp][] = $d_nilai['nilai'];
                }
                $total_nilai_x_bobot += $d_nilai['nilai'] * $d_nilai['bobot_penilaian'];
                $total_bobot += $d_nilai['bobot_penilaian'];
            }
            mysqli_stmt_close($stmt_sumatif_tp);
        }

        // Eksekusi Query Sumatif Akhir
        if ($stmt_sumatif_akhir) {
            mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
            mysqli_stmt_execute($stmt_sumatif_akhir);
            $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
            
            while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
                $total_nilai_x_bobot += $d_nilai_akhir['nilai'] * $d_nilai_akhir['bobot_penilaian'];
                $total_bobot += $d_nilai_akhir['bobot_penilaian'];
            }
            mysqli_stmt_close($stmt_sumatif_akhir);
        }

        // Hitung Nilai Akhir
        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;
        
        // LOGIKA DESKRIPSI
        $deskripsi_final = '';
        
        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
            $rekap_tp = [];
            $kata_hapus = ['Peserta didik dapat', 'Peserta didik mampu', 'peserta didik mampu', 'siswa dapat', 'siswa mampu', 'mampu', 'memahami', 'menguasai', 'menjelaskan', 'menganalisis', 'mengidentifikasi', 'menentukan', 'menunjukkan'];

            foreach ($skor_per_tp as $deskripsi => $skor_array) {
                $avg = array_sum($skor_array) / count($skor_array);
                
                $desc_clean = trim(str_ireplace($kata_hapus, '', $deskripsi));
                $desc_clean = preg_replace('/\s+/', ' ', $desc_clean);
                $desc_clean = lcfirst($desc_clean);

                if (!isset($rekap_tp[$desc_clean]) || $rekap_tp[$desc_clean]['avg'] < $avg) {
                     $rekap_tp[$desc_clean] = ['avg' => $avg];
                }
            }

            $tp_lulus = [];
            $tp_remedi = [];

            foreach ($rekap_tp as $clean_desc => $data) {
                if ($data['avg'] >= $kkm) {
                    $tp_lulus[$clean_desc] = $data['avg'];
                } else {
                    $tp_remedi[$clean_desc] = $data['avg'];
                }
            }

            arsort($tp_lulus); 
            asort($tp_remedi); 

            $top_tp = array_slice(array_keys($tp_lulus), 0, 2);
            $bottom_tp = array_slice(array_keys($tp_remedi), 0, 2); 
            
            $deskripsi_draf = "";
            
            if (!empty($top_tp)) {
                $deskripsi_draf .= "Menunjukkan penguasaan yang sangat baik dalam " . implode(', ', $top_tp) . ". ";
            } elseif ($nilai_akhir >= $kkm && empty($top_tp)) {
                $deskripsi_draf .= "Secara keseluruhan, capaian kompetensi sudah tuntas. ";
            }
            
            if (!empty($bottom_tp)) {
                $deskripsi_draf .= "Namun, perlu penguatan lebih lanjut dalam " . implode(', ', $bottom_tp) . ".";
            } else {
                $deskripsi_draf .= "Semua tujuan pembelajaran telah tercapai dengan baik.";
            }

            $deskripsi_final = ucfirst(trim($deskripsi_draf));
            
        } elseif ($nilai_akhir !== null && $nilai_akhir >= $kkm) {
            $deskripsi_final = 'Capaian kompetensi secara umum sudah menunjukkan ketuntasan yang baik.';
        } elseif ($nilai_akhir !== null && $nilai_akhir < $kkm) {
            $deskripsi_final = 'Perlu ditingkatkan lagi pada beberapa tujuan pembelajaran untuk mencapai ketuntasan minimum.';
        } else {
            $deskripsi_final = '-';
        }

        return $deskripsi_final;
    }
}

// =======================================================================
// 3. PENGAMBILAN PENGATURAN GLOBAL
// =======================================================================
$pengaturan_pdf = [];
$query_pengaturan_pdf = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while($row_pdf = mysqli_fetch_assoc($query_pengaturan_pdf)){
    $pengaturan_pdf[$row_pdf['nama_pengaturan']] = $row_pdf['nilai_pengaturan'];
}

// Mode Tanpa KOP & Margin
$cetak_tanpa_kop = $pengaturan_pdf['cetak_tanpa_kop'] ?? '0';
$margin_raw = (isset($pengaturan_pdf['margin_atas_tanpa_kop']) && $pengaturan_pdf['margin_atas_tanpa_kop'] !== '') ? $pengaturan_pdf['margin_atas_tanpa_kop'] : '1'; 
$margin_atas = str_replace(',', '.', $margin_raw);
$kkm = isset($pengaturan_pdf['kkm']) ? (int)$pengaturan_pdf['kkm'] : 75;

// Tahun Ajaran & Semester
$q_ta_pdf = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta_pdf = mysqli_fetch_assoc($q_ta_pdf);
$id_tahun_ajaran_pdf = $d_ta_pdf['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_pdf = $d_ta_pdf['tahun_ajaran'] ?? '-';

$semester_aktif_pdf = $pengaturan_pdf['semester_aktif'] ?? 1;
$semester_text_pdf = ($semester_aktif_pdf == 1) ? '1 (Ganjil)' : '2 (Genap)';
$tanggal_rapor_db = $pengaturan_pdf['tanggal_rapor'] ?? date("Y-m-d");
$tanggal_rapor_pdf = tanggal_indo($tanggal_rapor_db);

$q_sekolah_pdf = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah_pdf = mysqli_fetch_assoc($q_sekolah_pdf);

// Konfigurasi Kertas & Warna
$ukuran_kertas_db = $pengaturan_pdf['rapor_ukuran_kertas'] ?? 'A4';
$ukuran_kertas_pdf = !empty($kertas_get) ? $kertas_get : $ukuran_kertas_db;

$skema_warna_pdf = $pengaturan_pdf['rapor_skema_warna'] ?? 'bw';
$theme_color_bg = '#444444'; $theme_color_text = '#FFFFFF'; $theme_color_kop = '#000000';

switch ($skema_warna_pdf) {
    case 'light_blue': $theme_color_bg = '#E3F2FD'; $theme_color_text = '#0D47A1'; $theme_color_kop = '#0D47A1'; break;
    case 'light_green': $theme_color_bg = '#E8F5E9'; $theme_color_text = '#1B5E20'; $theme_color_kop = '#1B5E20'; break;
    case 'light_teal': $theme_color_bg = '#E0F2F1'; $theme_color_text = '#004D40'; $theme_color_kop = '#004D40'; break;
    case 'light_purple': $theme_color_bg = '#EDE7F6'; $theme_color_text = '#311B92'; $theme_color_kop = '#311B92'; break;
    case 'light_red': $theme_color_bg = '#FFEBEE'; $theme_color_text = '#B71C1C'; $theme_color_kop = '#B71C1C'; break;
}

// Helper untuk mengambil gambar lokal agar aman di DomPDF
function get_img_base64_local($path) {
    if (!empty($path)) {
        $full_path = 'uploads/' . $path;
        if (file_exists($full_path) && is_readable($full_path)) {
            $type = pathinfo($full_path, PATHINFO_EXTENSION);
            $data = file_get_contents($full_path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
    }
    return '';
}

// Persiapan Gambar
$watermark_base64 = get_img_base64_local($pengaturan_pdf['watermark_file'] ?? null);

$tampil_kop_img = ($pengaturan_pdf['rapor_tampil_kop'] ?? '0') == '1';
$file_kop = !empty($pengaturan_pdf['kop_sekolah']) ? $pengaturan_pdf['kop_sekolah'] : ($pengaturan_pdf['file_kop_sekolah'] ?? '');
$kop_base64 = ($tampil_kop_img) ? get_img_base64_local($file_kop) : '';

$base64_kab_pdf = '';
if (file_exists('uploads/logo_kabupaten.png')) {
    $base64_kab_pdf = 'data:image/png;base64,' . base64_encode(file_get_contents('uploads/logo_kabupaten.png'));
}
$base64_sekolah_pdf = get_img_base64_local($sekolah_pdf['logo_sekolah'] ?? '');

// =======================================================================
// 4. MULAI GENERATE HTML UTAMA
// =======================================================================
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cetak Massal - <?php echo ucfirst($tipe_cetak); ?></title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; color: #333; }
        
        /* LOGIKA MARGIN HALAMAN BERDASARKAN TIPE CETAK */
        <?php if ($tipe_cetak == 'sampul'): ?>
            @page { margin: 2cm 2cm 1cm 2cm; }
        <?php elseif ($tipe_cetak == 'identitas'): ?>
            @page { margin: 2cm 2cm 2cm 2cm; }
        <?php else: // Tipe Rapor (Grades) ?>
            <?php if ($cetak_tanpa_kop == '1'): ?>
                @page { margin: <?php echo $margin_atas; ?>cm 30px 40px 30px; }
            <?php else: ?>
                @page { margin: 170px 30px 40px 30px; } 
                header { position: fixed; top: -150px; left: 0px; right: 0px; height: 140px; }
            <?php endif; ?>
        <?php endif; ?>

        /* CSS Header KOP (Hanya untuk Rapor) */
        .header-table { width: 100%; border-bottom: 3px solid #000; padding-bottom: 5px; margin-bottom: 10px; }
        .header-table .logo-left { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .logo-right { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .kop-text { text-align: center; vertical-align: middle; }
        .header-table h4, .header-table h3, .header-table p { margin: 0; line-height: 1.2; }
        .header-table h4 { font-size: 14pt; }
        .header-table .dinas-text { font-size: 13pt; margin-top: 2px; }
        .header-table .school-name { font-size: 20pt; font-weight: bold; margin: 5px 0; color: <?php echo $theme_color_kop; ?>; }
        .header-table .school-info { font-size: 9pt; line-height: 1.3; }

        .header-img-container { width: 100%; text-align: center; margin-bottom: 5px; }
        .header-img-container img { width: 100%; height: auto; max-height: 140px; }
        
        main { margin-top: 0px; }
        
        /* CSS TABEL RAPOR (Grades) */
        .info-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 10px; }
        .info-table td { padding: 2px 5px; vertical-align: top; }
        
        .content-table { width: 100%; border-collapse: collapse; page-break-inside: auto; margin-top: 10px; }
        .content-table th, .content-table td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: top; page-break-inside: auto; }
        .content-table tr { page-break-inside: auto; page-break-after: auto; }
        .content-table th { background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>; font-weight: bold; text-align: center; vertical-align: middle; }
        
        .nilai-cetak { font-weight: bold; text-align: center !important; vertical-align: middle; font-size: 11pt; }
        .section-title { font-weight: bold; font-size: 11pt; margin-top: 20px; margin-bottom: 8px; }
        .text-center { text-align: center !important; }
        .capaian { font-size: 9pt; line-height: 1.4; text-align: justify; }
        
        /* CSS TTD UMUM */
        .signature-table { width: 100%; margin-top: 40px; font-size: 10pt; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; text-align: center; vertical-align: top; }
        .signature-space { height: 60px; }
        
        /* CSS WATERMARK */
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: -1000; text-align: center; width: 100%; }
        .watermark img { opacity: 0.1; width: 100%; height: auto; display: block; }

        /* CSS FOOTER (Halaman Rapor) */
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 35px; font-size: 8pt; color: #666; border-top: 2px solid <?php echo $theme_color_bg; ?>; padding: 8px 30px 0 30px; background-color: #fff; }
        .footer-table { width: 100%; border-collapse: collapse; margin: 0; }
        .footer-table td { padding: 0; vertical-align: top; }
        .footer-left { text-align: left; width: 40%; font-weight: bold; color: <?php echo $theme_color_kop; ?>; }
        .footer-center { text-align: center; width: 40%; font-style: italic; color: #999; }
        .footer-right { text-align: right; width: 20%; }
        .page-badge { background-color: #f0f0f0; padding: 2px 8px; border-radius: 4px; font-weight: bold; color: #333; }
        .footer-right .page-number:after { content: counter(page); }
        
        /* CSS KHUSUS MASSAL: Page Break */
        .page-break { page-break-after: always; clear: both; }

        /* --- STYLES KHUSUS IDENTITAS & SAMPUL (DIAMBIL DARI CETAK SATUAN) --- */
        <?php if ($tipe_cetak == 'identitas' || $tipe_cetak == 'sampul'): ?>
            .biodata-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-family: 'Times New Roman', serif; }
            .biodata-table td { padding: 6px 5px; vertical-align: top; border-bottom: 1px solid #e0e0e0; }
            .biodata-table tr:last-child td { border-bottom: none; }
            .label-text { color: #444; }
            .value-text { color: #000; font-weight: 500; }
            
            .nama-siswa-box { border: 2px solid #000; padding: 15px; width: 70%; margin: 0 auto; border-radius: 5px; }
            .footer-text-sampul { position: fixed; bottom: 40px; left: 0; right: 0; text-align: center; font-size: 14pt; font-weight: bold; }
        <?php endif; ?>
    </style>
</head>
<body>

    <!-- GLOBAL HEADER (HANYA MUNCUL JIKA TIPE RAPOR DAN TIDAK TANPA KOP) -->
    <?php if ($tipe_cetak == 'rapor' && $cetak_tanpa_kop != '1'): ?>
        <header>
            <?php if (!empty($kop_base64)): ?>
                <div class="header-img-container">
                    <img src="<?php echo $kop_base64; ?>" alt="KOP Sekolah">
                </div>
            <?php else: ?>
                <table class="header-table">
                    <tr>
                        <td class="logo-left">
                            <?php if (!empty($base64_kab_pdf)) echo '<img src="' . $base64_kab_pdf . '" alt="Logo Kabupaten" style="width: 80px;">'; ?>
                        </td>
                        <td class="kop-text">
                            <h4>PEMERINTAH KABUPATEN <?php echo strtoupper(htmlspecialchars($sekolah_pdf['kabupaten_kota'] ?? '')); ?></h4>
                            <p class="dinas-text">DINAS PENDIDIKAN</p>
                            <h3 class="school-name"><?php echo strtoupper(htmlspecialchars($sekolah_pdf['nama_sekolah'])); ?></h3>
                            <p class="school-info">
                                <?php echo htmlspecialchars($sekolah_pdf['jalan'] ?? ''); ?>, Desa/Kel. <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan'] ?? ''); ?>, Kec. <?php echo htmlspecialchars($sekolah_pdf['kecamatan'] ?? ''); ?><br>
                                Telp: <?php echo htmlspecialchars($sekolah_pdf['telepon'] ?? '-'); ?> Email: <?php echo htmlspecialchars($sekolah_pdf['email'] ?? '-'); ?>
                            </p>
                        </td>
                        <td class="logo-right">
                            <?php if (!empty($base64_sekolah_pdf)) echo '<img src="' . $base64_sekolah_pdf . '" alt="Logo Sekolah" style="width: 80px;">'; ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </header>
    <?php endif; ?>

    <!-- GLOBAL FOOTER (HANYA MUNCUL DI RAPOR) -->
    <?php if ($tipe_cetak == 'rapor'): ?>
    <footer>
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    <?php echo htmlspecialchars($sekolah_pdf['nama_sekolah'] ?? ''); ?>
                </td>
                <td class="footer-center">
                    Rapor Siswa
                </td>
                <td class="footer-right">
                    <span class="page-badge">Hal. <span class="page-number"></span></span>
                </td>
            </tr>
        </table>
    </footer>
    <?php endif; ?>

    <!-- WATERMARK (HANYA DI RAPOR) -->
    <?php if (!empty($watermark_base64) && $tipe_cetak == 'rapor'): ?>
        <div class="watermark">
            <img src="<?php echo $watermark_base64; ?>" alt="Watermark">
        </div>
    <?php endif; ?>

    <!-- ================================================================= -->
    <!-- LOOPING UTAMA DATA SISWA -->
    <!-- ================================================================= -->
    <?php 
    $counter = 0;
    $total_siswa = count($ids);

    foreach ($ids as $id_siswa): 
        $counter++;
        
        // Include file body sesuai tipe (DIPERBAIKI KE FOLDER parts/)
        if ($tipe_cetak == 'sampul') {
            include 'parts/rapor_cover_body.php';
        } elseif ($tipe_cetak == 'identitas') {
            include 'parts/rapor_identitas_body.php';
        } elseif ($tipe_cetak == 'rapor') {
            include 'parts/rapor_pdf_body.php';
        }
    ?>
    
    <!-- PAGE BREAK ANTAR SISWA (Kecuali siswa terakhir) -->
    <?php if ($counter < $total_siswa): ?>
        <div class="page-break"></div>
    <?php endif; ?>

    <?php endforeach; ?>
    <!-- AKHIR LOOPING -->

</body>
</html>
<?php
// =======================================================================
// 5. RENDER PDF (DOMPDF)
// =======================================================================
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', dirname(__FILE__)); 
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', false); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// PENGATURAN UKURAN KERTAS (DINAMIS A4 / F4)
switch ($ukuran_kertas_pdf) {
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

// Nama File Output
$filename = "Cetak_Massal_" . ucfirst($tipe_cetak) . "_" . date('Ymd_His') . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
exit();
?>