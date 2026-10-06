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

// [SECURITY HARDENING] Membatasi cetak massal maksimal 50 siswa untuk mencegah OOM
if (count($ids) > 50) {
    die("<html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon: 'error', title: 'Batas Terlampaui', text: 'Maksimal pencetakan massal adalah 50 siswa dalam satu waktu untuk menjaga kestabilan server. Anda memilih " . count($ids) . " siswa. Silakan cetak secara bertahap.'}).then(() => { window.close(); });</script></body></html>");
}

// =======================================================================
// 2. FUNGSI BANTUAN
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

// Tahun Ajaran & Semester (Prioritas Parameter GET, Fallback Global Aktif)
if (isset($_GET['ta']) && isset($_GET['semester'])) {
    $id_tahun_ajaran_pdf = (int)$_GET['ta'];
    $semester_aktif_pdf = (int)$_GET['semester'];
    $q_ta_pdf = mysqli_query($koneksi, "SELECT tahun_ajaran FROM tahun_ajaran WHERE id_tahun_ajaran = $id_tahun_ajaran_pdf LIMIT 1");
    $d_ta_pdf = mysqli_fetch_assoc($q_ta_pdf);
    $tahun_ajaran_pdf = $d_ta_pdf['tahun_ajaran'] ?? '-';
} else {
    $q_ta_pdf = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $d_ta_pdf = mysqli_fetch_assoc($q_ta_pdf);
    $id_tahun_ajaran_pdf = $d_ta_pdf['id_tahun_ajaran'] ?? 0;
    $tahun_ajaran_pdf = $d_ta_pdf['tahun_ajaran'] ?? '-';
    $semester_aktif_pdf = $pengaturan_pdf['semester_aktif'] ?? 1;
}

$semester_text_pdf = ($semester_aktif_pdf == 1) ? '1 (Ganjil)' : '2 (Genap)';
$tanggal_rapor_db = $pengaturan_pdf['tanggal_rapor'] ?? date("Y-m-d");
$tanggal_rapor_pdf = tanggal_indo($tanggal_rapor_db);

$q_sekolah_pdf = mysqli_query($koneksi, "SELECT * FROM sekolah LIMIT 1");
$sekolah_pdf = mysqli_fetch_assoc($q_sekolah_pdf);

// [KOP DESIGNER] Text & Toggle
$kop_baris_1 = $pengaturan_pdf['kop_baris_1'] ?? 'PEMERINTAH KABUPATEN ' . strtoupper($sekolah_pdf['kabupaten_kota'] ?? '');
$kop_baris_2 = $pengaturan_pdf['kop_baris_2'] ?? 'DINAS PENDIDIKAN';
$kop_baris_3 = $pengaturan_pdf['kop_baris_3'] ?? strtoupper($sekolah_pdf['nama_sekolah'] ?? '');
$kop_baris_4 = $pengaturan_pdf['kop_baris_4'] ?? ($sekolah_pdf['jalan'] ?? '') . ', Desa/Kel. ' . ($sekolah_pdf['desa_kelurahan'] ?? '') . ', Kec. ' . ($sekolah_pdf['kecamatan'] ?? '') . '<br>Telp: ' . ($sekolah_pdf['telepon'] ?? '') . ' Email: ' . ($sekolah_pdf['email'] ?? '');
$kop_logo_kiri_tampil = $pengaturan_pdf['kop_logo_kiri_tampil'] ?? '1';
$kop_logo_kanan_tampil = $pengaturan_pdf['kop_logo_kanan_tampil'] ?? '1';
$logo_kiri = $pengaturan_pdf['logo_kiri'] ?? 'logo_kabupaten.png';

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

$base64_kiri_pdf = '';
$path_kiri_pdf = 'uploads/' . $logo_kiri;
if (!file_exists($path_kiri_pdf)) {
    $path_kiri_pdf = 'uploads/logo_kabupaten.png';
}
if (file_exists($path_kiri_pdf)) {
    $type_kiri_pdf = pathinfo($path_kiri_pdf, PATHINFO_EXTENSION);
    $data_kiri_pdf = file_get_contents($path_kiri_pdf);
    $base64_kiri_pdf = 'data:image/' . $type_kiri_pdf . ';base64,' . base64_encode($data_kiri_pdf);
}
$base64_sekolah_pdf = get_img_base64_local($sekolah_pdf['logo_sekolah'] ?? '');

$show_nilai_column_pdf = true;

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
        <?php else: // Tipe Rapor ?>
            <?php if ($cetak_tanpa_kop == '1'): ?>
                @page { margin: <?php echo $margin_atas; ?>cm 30px 40px 30px; }
                header { display: none; }
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
        
        /* CSS TABEL RAPOR */
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
        .keep-together { page-break-inside: avoid; margin-bottom: 2px; }
        
        /* CSS WATERMARK */
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: -1000; text-align: center; width: 100%; }
        .watermark img { opacity: 0.1; width: 100%; height: auto; display: block; }
        .draft-watermark { position: fixed; top: 50%; left: 50%; font-size: 120pt; font-weight: bold; color: #FF0000; opacity: 0.15; transform: translate(-50%, -50%) rotate(-45deg); z-index: -1001; width: 150%; text-align: center; }

        /* CSS FOOTER (Hanya untuk Rapor Massal) */
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

        /* --- STYLES KHUSUS IDENTITAS & SAMPUL --- */
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
                        <?php if ($kop_logo_kiri_tampil == '1'): ?>
                        <td class="logo-left" style="width: 90px;">
                            <?php if (!empty($base64_kiri_pdf)) echo '<img src="' . $base64_kiri_pdf . '" alt="Logo Kiri" style="width: 80px;">'; ?>
                        </td>
                        <?php endif; ?>

                        <td class="kop-text">
                            <h4><?php echo htmlspecialchars($kop_baris_1); ?></h4>
                            <p class="dinas-text"><?php echo htmlspecialchars($kop_baris_2); ?></p>
                            <h3 class="school-name"><?php echo htmlspecialchars($kop_baris_3); ?></h3>
                            <p class="school-info">
                                <?php echo nl2br(htmlspecialchars(str_replace('<br>', "\n", $kop_baris_4))); ?>
                            </p>
                        </td>

                        <?php if ($kop_logo_kanan_tampil == '1'): ?>
                        <td class="logo-right" style="width: 90px;">
                            <?php if (!empty($base64_sekolah_pdf)) echo '<img src="' . $base64_sekolah_pdf . '" alt="Logo Sekolah" style="width: 80px;">'; ?>
                        </td>
                        <?php endif; ?>
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
                    Laporan Hasil Belajar Siswa
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

    <main>
    <!-- ================================================================= -->
    <!-- LOOPING UTAMA DATA SISWA -->
    <!-- ================================================================= -->
    <?php 
    $counter = 0;
    $total_siswa = count($ids);

    foreach ($ids as $id_siswa): 
        $counter++;
        
        // Include file body sesuai tipe
        if ($tipe_cetak == 'sampul') {
            include 'parts/rapor_cover_body.php';
        } elseif ($tipe_cetak == 'identitas') {
            include 'parts/rapor_identitas_body.php';
        } elseif ($tipe_cetak == 'rapor') {

            // =======================================================================
            // INLINE LOGIC RAPOR STANDAR / UMUM (Tanpa Grup Agama)
            // =======================================================================
            $q_siswa_pdf = "SELECT s.nama_lengkap, s.nis, s.nisn, s.agama, s.id_kelas, k.nama_kelas, k.fase, g.nama_guru as nama_walikelas, g.nip as nip_walikelas
                            FROM siswa s
                            LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
                            LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru
                            WHERE s.id_siswa = ?";
            $stmt_siswa_pdf = mysqli_prepare($koneksi, $q_siswa_pdf);
            mysqli_stmt_bind_param($stmt_siswa_pdf, "i", $id_siswa);
            mysqli_stmt_execute($stmt_siswa_pdf);
            $siswa_pdf = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_siswa_pdf));
            $id_kelas_siswa = $siswa_pdf['id_kelas'] ?? 0;
            ?>

            <!-- TAMPILAN HEADER IDENTITAS (Jika tanpa KOP, header global tidak muncul) -->
            <?php
            $q_rapor = mysqli_prepare($koneksi, "SELECT * FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ? LIMIT 1");
            mysqli_stmt_bind_param($q_rapor, "iii", $id_siswa, $semester_aktif_pdf, $id_tahun_ajaran_pdf);
            mysqli_stmt_execute($q_rapor);
            $rapor_pdf = mysqli_fetch_assoc(mysqli_stmt_get_result($q_rapor));
            $id_rapor = $rapor_pdf['id_rapor'] ?? 0;

            // --- CEK DATA HISTORIS ---
            if ($rapor_pdf && isset($rapor_pdf['id_kelas_historis']) && !empty($rapor_pdf['id_kelas_historis'])) {
                $id_kelas_siswa = $rapor_pdf['id_kelas_historis'];
                $id_wali_hist = $rapor_pdf['id_walikelas_historis'];

                $q_hist = mysqli_query($koneksi, "SELECT k.nama_kelas, k.fase, g.nama_guru, g.nip FROM kelas k LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru WHERE k.id_kelas = $id_kelas_siswa LIMIT 1");
                if ($d_hist = mysqli_fetch_assoc($q_hist)) {
                    $siswa_pdf['nama_kelas'] = $d_hist['nama_kelas'];
                    $siswa_pdf['fase'] = $d_hist['fase'];
                    if (!empty($id_wali_hist)) {
                        $qw = mysqli_query($koneksi, "SELECT nama_guru, nip FROM guru WHERE id_guru = $id_wali_hist LIMIT 1");
                        if ($dw = mysqli_fetch_assoc($qw)) {
                            $siswa_pdf['nama_walikelas'] = $dw['nama_guru'];
                            $siswa_pdf['nip_walikelas'] = $dw['nip'];
                        }
                    } else {
                        $siswa_pdf['nama_walikelas'] = $d_hist['nama_guru'];
                        $siswa_pdf['nip_walikelas'] = $d_hist['nip'];
                    }
                }
            }
            // --- END CEK HISTORIS ---

            $is_draft = false;
            if (!$rapor_pdf || (isset($rapor_pdf['status']) && $rapor_pdf['status'] == 'Draft')) {
                $is_draft = true;
                echo '<div class="draft-watermark">DRAFT</div>';
            }

            // --- PENGAMBILAN MAPEL DENGAN FILTER AGAMA SESUAI AGAMA SISWA ---
            $q_mapel_agama_all = mysqli_query($koneksi, "
                SELECT id_mapel, nama_mapel
                FROM mata_pelajaran
                WHERE (kelompok LIKE '%Agama%' OR nama_mapel LIKE '%Agama%')
            ");

            $ids_semua_mapel_agama = [];
            $id_mapel_agama_siswa_pdf = 0;
            $agama_siswa_clean = strtolower(trim($siswa_pdf['agama'] ?? ''));

            while ($row_agama = mysqli_fetch_assoc($q_mapel_agama_all)) {
                $ids_semua_mapel_agama[] = $row_agama['id_mapel'];
                $nama_mapel_kecil = strtolower($row_agama['nama_mapel']);

                // Normalisasi Buddha/Budha dan Khonghucu/Konghucu
                $match = false;
                if ($agama_siswa_clean == 'buddha' || $agama_siswa_clean == 'budha') {
                    if (strpos($nama_mapel_kecil, 'buddha') !== false || strpos($nama_mapel_kecil, 'budha') !== false) {
                        $match = true;
                    }
                } elseif ($agama_siswa_clean == 'khonghucu' || $agama_siswa_clean == 'konghucu') {
                    if (strpos($nama_mapel_kecil, 'khonghucu') !== false || strpos($nama_mapel_kecil, 'konghucu') !== false) {
                        $match = true;
                    }
                } else {
                    if (!empty($agama_siswa_clean) && strpos($nama_mapel_kecil, $agama_siswa_clean) !== false) {
                        $match = true;
                    }
                }

                if ($match) {
                    $id_mapel_agama_siswa_pdf = $row_agama['id_mapel'];
                }
            }

            $semua_id_mapel_agama_string_pdf = implode(',', $ids_semua_mapel_agama);
            if (empty($semua_id_mapel_agama_string_pdf)) { $semua_id_mapel_agama_string_pdf = '0'; }

            $query_mapel_string_pdf = "
                SELECT mp.id_mapel, mp.nama_mapel, mp.urutan, mp.parent_mapel_id, mp.is_tambahan, mp.agama_khusus
                FROM mata_pelajaran AS mp
                JOIN guru_mengajar AS gm ON mp.id_mapel = gm.id_mapel
                WHERE gm.id_kelas = $id_kelas_siswa AND gm.id_tahun_ajaran = $id_tahun_ajaran_pdf
            ";

            // [MODIFIKASI] Filter mapel berdasarkan agama khusus
            $agama_filter_aman_massal = mysqli_real_escape_string($koneksi, $siswa_pdf['agama'] ?? '');
            $query_mapel_string_pdf .= " AND (mp.agama_khusus IS NULL OR mp.agama_khusus = '' OR mp.agama_khusus = '$agama_filter_aman_massal') ";

            if ($id_mapel_agama_siswa_pdf > 0) {
                $query_mapel_string_pdf .= " AND (mp.id_mapel NOT IN ($semua_id_mapel_agama_string_pdf) OR mp.id_mapel = $id_mapel_agama_siswa_pdf)";
            } else {
                $query_mapel_string_pdf .= " AND mp.id_mapel NOT IN ($semua_id_mapel_agama_string_pdf)";
            }

            $query_mapel_string_pdf .= " GROUP BY mp.id_mapel ORDER BY mp.urutan ASC, mp.nama_mapel ASC";
            $semua_mapel_query_pdf = mysqli_query($koneksi, $query_mapel_string_pdf);

            // --- AMBIL DATA NILAI ---
            $daftar_mapel_rapor_pdf = [];
            $nilai_tersimpan_pdf = [];

            if ($id_rapor > 0) {
                $detail_akademik_query_pdf = mysqli_query($koneksi, "SELECT id_mapel, nilai_akhir, nilai_katrol, capaian_kompetensi FROM rapor_detail_akademik WHERE id_rapor = $id_rapor");
                if ($detail_akademik_query_pdf) {
                    while ($d_pdf = mysqli_fetch_assoc($detail_akademik_query_pdf)) {
                        $nilai_tersimpan_pdf[$d_pdf['id_mapel']] = [
                            'asli' => isset($d_pdf['nilai_akhir']) ? (float)$d_pdf['nilai_akhir'] : 0,
                            'katrol' => isset($d_pdf['nilai_katrol']) ? (float)$d_pdf['nilai_katrol'] : 0,
                            'capaian_kompetensi' => $d_pdf['capaian_kompetensi'] ?? '-'
                        ];
                    }
                }
            }

            $semua_mapel_raw = [];
            if ($semua_mapel_query_pdf) {
                while ($mapel_pdf = mysqli_fetch_assoc($semua_mapel_query_pdf)) {
                    $id_mapel_pdf = $mapel_pdf['id_mapel'];
                    $asli = 0; $katrol = 0; $deskripsi = '-';
                    if (isset($nilai_tersimpan_pdf[$id_mapel_pdf])) {
                        $asli = $nilai_tersimpan_pdf[$id_mapel_pdf]['asli'];
                        $katrol = $nilai_tersimpan_pdf[$id_mapel_pdf]['katrol'];
                        $deskripsi = $nilai_tersimpan_pdf[$id_mapel_pdf]['capaian_kompetensi'];
                    }
                    $nilai_final = ($katrol > $asli) ? $katrol : $asli;

                    $mapel_pdf['nilai_akhir'] = ($nilai_final > 0) ? $nilai_final : '-';
                    $mapel_pdf['capaian_kompetensi'] = $deskripsi;
                    $semua_mapel_raw[$id_mapel_pdf] = $mapel_pdf;
                }
            }

            // =======================================================================
            // LOGIKA PENGGABUNGAN MATA PELAJARAN DINAMIS & PEMISAHAN LAMPIRAN
            // =======================================================================
            $mapel_yang_dihapus = [];
            foreach ($semua_mapel_raw as $id => $mapel) {
                if (!empty($mapel['parent_mapel_id']) && isset($semua_mapel_raw[$mapel['parent_mapel_id']])) {
                    $pid = $mapel['parent_mapel_id'];
                    if (!isset($semua_mapel_raw[$pid]['anak_list'])) {
                        $semua_mapel_raw[$pid]['anak_list'] = [];
                    }
                    $semua_mapel_raw[$pid]['anak_list'][] = $mapel;
                    $mapel_yang_dihapus[] = $id;
                }
            }

            foreach ($mapel_yang_dihapus as $id) {
                unset($semua_mapel_raw[$id]);
            }

            $daftar_mapel_utama = [];
            $daftar_mapel_tambahan = [];

            foreach ($semua_mapel_raw as $id => $mapel) {
                if (isset($mapel['anak_list']) && count($mapel['anak_list']) > 0) {
                    $total_nilai = 0;
                    $count_nilai = 0;
                    $deskripsi_gabungan = [];

                    if (is_numeric($mapel['nilai_akhir']) && $mapel['nilai_akhir'] > 0) {
                        $total_nilai += $mapel['nilai_akhir'];
                        $count_nilai++;
                        $deskripsi_gabungan[] = trim($mapel['capaian_kompetensi']);
                    }

                    foreach ($mapel['anak_list'] as $anak) {
                        if (is_numeric($anak['nilai_akhir']) && $anak['nilai_akhir'] > 0) {
                            $total_nilai += $anak['nilai_akhir'];
                            $count_nilai++;
                        }
                        if (trim($anak['capaian_kompetensi']) != '-' && trim($anak['capaian_kompetensi']) != '') {
                            $deskripsi_gabungan[] = trim($anak['capaian_kompetensi']);
                        }
                    }

                    $mapel['nilai_akhir'] = ($count_nilai > 0) ? round($total_nilai / $count_nilai) : '-';
                    $mapel['capaian_kompetensi'] = (count($deskripsi_gabungan) > 0) ? implode("\n", $deskripsi_gabungan) : '-';
                }

                if ($mapel['is_tambahan'] == '1') {
                    $daftar_mapel_tambahan[] = $mapel;
                } else {
                    $daftar_mapel_utama[] = $mapel;
                }
            }

            $daftar_mapel_rapor_pdf = $daftar_mapel_utama;

            // --- AMBIL EKSTRAKURIKULER ---
            $detail_ekskul_query_pdf = mysqli_prepare($koneksi, "
                SELECT e.nama_ekskul, kh.jumlah_hadir, kh.total_pertemuan,
                    (SELECT GROUP_CONCAT(CONCAT(et.deskripsi_tujuan, ': ', pn.nilai) SEPARATOR '; ')
                     FROM ekskul_penilaian pn JOIN ekskul_tujuan et ON pn.id_tujuan_ekskul = et.id_tujuan_ekskul
                     WHERE pn.id_peserta_ekskul = ep.id_peserta_ekskul AND et.semester = ?) as 'penilaian_deskriptif'
                FROM ekskul_peserta ep JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
                LEFT JOIN ekskul_kehadiran kh ON ep.id_peserta_ekskul = kh.id_peserta_ekskul AND kh.semester = ?
                WHERE ep.id_siswa = ? ORDER BY e.nama_ekskul ASC
            ");
            mysqli_stmt_bind_param($detail_ekskul_query_pdf, "iii", $semester_aktif_pdf, $semester_aktif_pdf, $id_siswa);
            mysqli_stmt_execute($detail_ekskul_query_pdf);
            $detail_ekskul_result_pdf = mysqli_stmt_get_result($detail_ekskul_query_pdf);

            // =======================================================================
            // RENDER HTML UNTUK SATU SISWA INI (LAYOUT STANDAR)
            // =======================================================================
            ?>

            <div class="student-wrapper">
                <!-- Identitas Siswa (Dicetak 1 kali di atas rapor masing-masing) -->
                <table class="info-table">
                    <tr>
                        <td width="20%">Nama Murid</td><td width="1%">:</td><td width="34%"><b><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? ''); ?></b></td>
                        <td width="20%">Kelas</td><td width="1%">:</td><td width="24%"><?php echo htmlspecialchars($siswa_pdf['nama_kelas'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td>NISN / NIS</td><td>:</td><td><?php echo htmlspecialchars($siswa_pdf['nisn'] ?? ''); ?> / <?php echo htmlspecialchars($siswa_pdf['nis'] ?? ''); ?></td>
                        <td>Fase</td><td>:</td><td><?php echo htmlspecialchars($siswa_pdf['fase'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td>Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah'] ?? ''); ?></td>
                        <td>Semester</td><td>:</td><td><?php echo $semester_text_pdf; ?></td>
                    </tr>
                    <tr>
                        <td>Alamat Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['jalan'] ?? ''); ?>, <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan'] ?? ''); ?></td>
                        <td>Tahun Ajaran</td><td>:</td><td><?php echo htmlspecialchars($tahun_ajaran_pdf); ?></td>
                    </tr>
                </table>
                <div style="border-bottom: 2px solid #000; margin-bottom: 10px;"></div>

                <!-- A. NILAI AKADEMIK -->
                <div class="section-title" style="margin-top: 0;">A. NILAI AKADEMIK</div>
                <table class="content-table" style="margin-top: 5px;">
                    <thead>
                        <tr>
                            <th width="5%">No.</th>
                            <th width="<?php echo $show_nilai_column_pdf ? '25%' : '33%'; ?>">Mata Pelajaran</th>
                            <?php if ($show_nilai_column_pdf): ?><th width="8%">Nilai Akhir</th><?php endif; ?>
                            <th>Capaian Kompetensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no_pdf = 1;
                        $filtered_mapel_rapor_pdf = array_filter($daftar_mapel_rapor_pdf, fn($e) => $e !== null);

                        foreach ($filtered_mapel_rapor_pdf as $d_pdf):
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no_pdf++; ?></td>
                            <td><?php echo htmlspecialchars($d_pdf['nama_mapel'] ?? '-'); ?></td>
                            <?php if ($show_nilai_column_pdf): ?>
                                <td class="nilai-cetak"><?php echo $d_pdf['nilai_akhir'] ?? '-'; ?></td>
                            <?php endif; ?>
                            <td class="capaian"><?php echo !empty($d_pdf['capaian_kompetensi']) ? nl2br(htmlspecialchars($d_pdf['capaian_kompetensi'] ?? '-')) : '-'; ?></td>
                        </tr>
                        <?php
                        endforeach;

                        if (empty($filtered_mapel_rapor_pdf)) {
                            $colspan_pdf = $show_nilai_column_pdf ? 4 : 3;
                            echo "<tr><td colspan='$colspan_pdf' class='text-center'>Data nilai akademik belum tersedia atau rapor belum di-Generate untuk semester ini.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <!-- B. KOKURIKULER -->
                <div class="keep-together">
                    <div class="section-title">B. KOKURIKULER</div>
                    <table class="content-table" style="margin-top: 0;">
                        <tbody>
                            <tr>
                                <td class="capaian"><?php echo !empty($rapor_pdf['deskripsi_kokurikuler']) ? nl2br(htmlspecialchars($rapor_pdf['deskripsi_kokurikuler'])) : '-'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- C. EKSTRAKURIKULER -->
                <div class="keep-together">
                    <div class="section-title">C. EKSTRAKURIKULER</div>
                    <table class="content-table" style="margin-top: 0;">
                        <thead>
                            <tr>
                                <th width="5%">No.</th>
                                <th width="25%">Ekstrakurikuler</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no_ekskul_pdf = 1;
                            $predikat_label = ['SB' => 'Sangat Baik', 'B' => 'Baik', 'C' => 'Cukup', 'K' => 'Perlu Bimbingan'];
                            $ekskul_data = [];
                            if ($detail_ekskul_result_pdf) {
                                mysqli_data_seek($detail_ekskul_result_pdf, 0);
                                $ekskul_data = mysqli_fetch_all($detail_ekskul_result_pdf, MYSQLI_ASSOC);
                            }

                            if(!empty($ekskul_data)){
                                foreach($ekskul_data as $e_pdf):
                                    $deskripsi_final = "Belum ada penilaian deskriptif.";
                                    if (!empty($e_pdf['penilaian_deskriptif'])) {
                                        $raw_items = explode('; ', $e_pdf['penilaian_deskriptif']);
                                        $groups = [];
                                        $map_nilai_db = ['Sangat Baik' => 'SB', 'SB' => 'SB', 'Baik' => 'B', 'B' => 'B', 'Cukup' => 'C', 'C' => 'C', 'Kurang' => 'K', 'K' => 'K', 'Perlu Bimbingan' => 'K'];
                                        foreach ($raw_items as $item) {
                                            $last_pos = strrpos($item, ': ');
                                            if ($last_pos !== false) {
                                                $aspek = substr($item, 0, $last_pos);
                                                $nilai_raw = trim(substr($item, $last_pos + 2));
                                                $kategori = $map_nilai_db[$nilai_raw] ?? $nilai_raw;
                                                $groups[$kategori][] = strtolower(htmlspecialchars($aspek ?? ''));
                                            }
                                        }
                                        $kalimat_parts = [];
                                        $urutan_cek = ['SB', 'B', 'C', 'K'];
                                        foreach ($urutan_cek as $grade) {
                                            if (isset($groups[$grade]) && count($groups[$grade]) > 0) {
                                                $label = $predikat_label[$grade] ?? $grade;
                                                $list_aspek = $groups[$grade];
                                                if (count($list_aspek) > 1) {
                                                    $last_aspek = array_pop($list_aspek);
                                                    $text_aspek = implode(', ', $list_aspek) . ' dan ' . $last_aspek;
                                                } else {
                                                    $text_aspek = $list_aspek[0];
                                                }
                                                $kalimat_parts[] = "<b>" . $label . "</b> dalam hal " . $text_aspek;
                                            }
                                        }
                                        if (!empty($kalimat_parts)) {
                                            $deskripsi_final = "Menunjukkan perkembangan yang " . implode(", serta ", $kalimat_parts) . ".";
                                            $deskripsi_final = ucfirst($deskripsi_final);
                                        }
                                    }

                                    $keterangan_hadir = "";
                                    $jumlah_hadir = $e_pdf['jumlah_hadir'] ?? 0;
                                    $total_pertemuan = $e_pdf['total_pertemuan'] ?? 0;

                                    if ($total_pertemuan > 0) {
                                        $persentase_hadir_pdf = round(($jumlah_hadir / $total_pertemuan) * 100);
                                        $color_style = ($persentase_hadir_pdf < 70) ? 'color:#d32f2f;' : 'color:#555;';
                                        $keterangan_hadir = "<br><span style='font-size:9pt; font-style:italic; {$color_style}'>Keaktifan kehadiran mencapai <b>{$persentase_hadir_pdf}%</b> (" . htmlspecialchars($jumlah_hadir) . " dari " . htmlspecialchars($total_pertemuan) . " pertemuan).</span>";
                                    } else if ($jumlah_hadir !== null) {
                                         $keterangan_hadir .= "<br><span style='font-size:9pt;'>Kehadiran: " . htmlspecialchars($jumlah_hadir) . " pertemuan.</span>";
                                    }
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $no_ekskul_pdf++; ?></td>
                                <td style="vertical-align: middle;"><b><?php echo htmlspecialchars($e_pdf['nama_ekskul'] ?? '-'); ?></b></td>
                                <td class="capaian" style="padding: 8px; line-height: 1.5;"><?php echo $deskripsi_final . $keterangan_hadir; ?></td>
                            </tr>
                            <?php
                                endforeach;
                            } else {
                                echo "<tr><td colspan='3' class='text-center'>Tidak mengikuti kegiatan ekstrakurikuler.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- D. KETIDAKHADIRAN & E. CATATAN -->
                <div class="keep-together" style="width: 100%;">
                    <div style="width: 40%; float: left;">
                        <div class="section-title">D. KETIDAKHADIRAN</div>
                        <table class="content-table" style="margin-top: 0;">
                            <tr><td width="70%">Sakit</td><td>: <?php echo $rapor_pdf['sakit'] ?? 0; ?> hari</td></tr>
                            <tr><td>Izin</td><td>: <?php echo $rapor_pdf['izin'] ?? 0; ?> hari</td></tr>
                            <tr><td>Tanpa Keterangan</td><td>: <?php echo $rapor_pdf['tanpa_keterangan'] ?? 0; ?> hari</td></tr>
                        </table>
                    </div>
                    <div style="width: 55%; float: right;">
                        <div class="section-title">E. CATATAN WALI KELAS</div>
                        <table class="content-table" style="height: 80px; margin-top: 0;">
                            <tr><td class="capaian"><?php echo !empty($rapor_pdf['catatan_wali_kelas']) ? nl2br(htmlspecialchars($rapor_pdf['catatan_wali_kelas'] ?? '-')) : '-'; ?></td></tr>
                        </table>
                    </div>
                    <div style="clear:both;"></div>
                </div>

               <?php
                $is_semester_2 = (isset($semester_aktif_pdf) && $semester_aktif_pdf == 2) || (isset($rapor_pdf['semester']) && $rapor_pdf['semester'] == 2);
                if ($is_semester_2):
                ?>
                <div class="keep-together" style="margin-top: 15px; width: 100%;">
                    <div style="font-size: 12pt; font-weight: bold; margin-bottom: 5px;">Keputusan :</div>
                    <table class="content-table" style="width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 0;">
                        <tr>
                            <td style="padding: 10px 15px; text-align: left; border: 1px solid #000;">
                                Berdasarkan pencapaian seluruh kompetensi, ananda <strong><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? 'Siswa'); ?></strong> ditetapkan:<br><br>
                                <div style="text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 5px;">
                                <?php
                                    $keputusan = $rapor_pdf['keputusan_akhir'] ?? '-';
                                    $keterangan = $rapor_pdf['keterangan_keputusan'] ?? '';
                                    $nama_kelas = $siswa_pdf['nama_kelas'] ?? '';

                                    // Deteksi otomatis Kelas Akhir berdasarkan Jenjang
                                    $jenjang = strtoupper($sekolah_pdf['jenjang'] ?? 'SMP');
                                    $is_kelas_akhir = false;
                                    $nama_kelas_upper = strtoupper($nama_kelas);

                                    if ($jenjang == 'SD' || $jenjang == 'MI') {
                                        // Cari 'VI' tapi pastikan bukan 'VII' (7) atau 'VIII' (8)
                                        // Atau angka '6'
                                        if ((strpos($nama_kelas_upper, 'VI') !== false && strpos($nama_kelas_upper, 'VII') === false && strpos($nama_kelas_upper, 'VIII') === false) || strpos($nama_kelas_upper, '6') !== false) {
                                            $is_kelas_akhir = true;
                                        }
                                    } elseif ($jenjang == 'SMA' || $jenjang == 'SMK' || $jenjang == 'MA') {
                                        if (strpos($nama_kelas_upper, 'XII') !== false || strpos($nama_kelas_upper, '12') !== false) {
                                            $is_kelas_akhir = true;
                                        }
                                    } else {
                                        // Default SMP / MTs
                                        if (strpos($nama_kelas_upper, 'IX') !== false || strpos($nama_kelas_upper, '9') !== false) {
                                            $is_kelas_akhir = true;
                                        }
                                    }

                                    if ($is_kelas_akhir) {
                                        if ($keputusan == 'Lulus') echo "LULUS DARI SATUAN PENDIDIKAN";
                                        elseif ($keputusan == 'Tidak Lulus') echo "TIDAK LULUS DARI SATUAN PENDIDIKAN";
                                        else echo "-";
                                    } else {
                                        if ($keputusan == 'Naik Kelas') echo "NAIK KE KELAS : " . htmlspecialchars($keterangan);
                                        elseif ($keputusan == 'Tinggal Kelas') echo "TINGGAL DI KELAS : " . htmlspecialchars($nama_kelas);
                                        else echo "-";
                                    }
                                ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <!-- TANDA TANGAN -->
                <div class="keep-together" style="margin-top: 15px;">
                    <div class="section-title">Tanggapan Orang Tua/Wali Murid</div>
                    <table class="content-table" style="margin-top: 0;"><tr><td style="height: 60px;"></td></tr></table>

                    <table class="signature-table" style="width: 100%; margin-top: 50px;">
                        <tr>
                            <td style="width: 33.33%; text-align: center;">
                                Orang Tua/Wali Murid
                                <div class="signature-space"></div>
                                ( ................................. )
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                Mengetahui,<br>
                                Kepala Sekolah
                                <div class="signature-space"></div>
                                <strong><u><?php echo htmlspecialchars($sekolah_pdf['nama_kepsek'] ?? ''); ?></u></strong><br>
                                <?php if (!empty(trim($sekolah_pdf['jabatan_kepsek'] ?? ''))): ?>
                                    <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah_pdf['jabatan_kepsek']); ?></span><br>
                                <?php endif; ?>
                                <?php if (!empty(trim($sekolah_pdf['nip_kepsek'] ?? ''))): ?>
                                    NIP. <?php echo htmlspecialchars($sekolah_pdf['nip_kepsek']); ?>
                                <?php endif; ?>
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <?php echo htmlspecialchars($sekolah_pdf['kabupaten_kota'] ?? ''); ?>, <?php echo $tanggal_rapor_pdf; ?><br>
                                Wali Kelas
                                <div class="signature-space"></div>
                                <strong><u><?php echo htmlspecialchars($siswa_pdf['nama_walikelas'] ?? ''); ?></u></strong><br>
                                <?php if (!empty(trim($siswa_pdf['nip_walikelas'] ?? ''))): ?>
                                    NIP. <?php echo htmlspecialchars($siswa_pdf['nip_walikelas']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php if (!empty($daftar_mapel_tambahan)): ?>
                <div style="page-break-before: always;"></div>

                <table class="info-table">
                    <tr>
                        <td width="20%">Nama Murid</td>
                        <td width="1%">:</td>
                        <td width="34%"><b><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? ''); ?></b></td>
                        <td width="20%">Kelas</td>
                        <td width="1%">:</td>
                        <td width="24%"><?php echo htmlspecialchars($siswa_pdf['nama_kelas'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td>NISN / NIS</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($siswa_pdf['nisn'] ?? ''); ?> / <?php echo htmlspecialchars($siswa_pdf['nis'] ?? ''); ?></td>
                        <td>Fase</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($siswa_pdf['fase'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td>Sekolah</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah'] ?? ''); ?></td>
                        <td>Semester</td>
                        <td>:</td>
                        <td><?php echo $semester_text_pdf; ?></td>
                    </tr>
                    <tr>
                        <td>Alamat Sekolah</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($sekolah_pdf['jalan'] ?? ''); ?>, <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan'] ?? ''); ?></td>
                        <td>Tahun Ajaran</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($tahun_ajaran_pdf); ?></td>
                    </tr>
                </table>
                <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>

                <div class="section-title text-center" style="text-align: center;">LAMPIRAN: NILAI MATA PELAJARAN TAMBAHAN</div>
                <table class="content-table">
                    <thead>
                        <tr>
                            <th width="5%">No.</th>
                            <th width="<?php echo $show_nilai_column_pdf ? '25%' : '33%'; ?>">Mata Pelajaran</th>
                            <?php if ($show_nilai_column_pdf): ?>
                                <th width="8%">Nilai Akhir</th>
                            <?php endif; ?>
                            <th>Capaian Kompetensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no_pdf = 1;
                        foreach ($daftar_mapel_tambahan as $d_pdf):
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no_pdf++; ?></td>
                            <td><?php echo htmlspecialchars($d_pdf['nama_mapel'] ?? '-'); ?></td>
                            <?php if ($show_nilai_column_pdf): ?>
                                <td class="nilai-cetak"><?php echo $d_pdf['nilai_akhir'] ?? '-'; ?></td>
                            <?php endif; ?>
                            <td class="capaian"><?php echo !empty($d_pdf['capaian_kompetensi']) ? nl2br(htmlspecialchars($d_pdf['capaian_kompetensi'] ?? '-')) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <table class="signature-table" style="width: 100%; margin-top: 50px;">
                    <tr>
                        <td style="width: 50%; text-align: center;">
                            Mengetahui,<br>
                            Kepala Sekolah
                            <div class="signature-space"></div>
                            <strong><u><?php echo htmlspecialchars($sekolah_pdf['nama_kepsek'] ?? ''); ?></u></strong><br>
                            <?php if (!empty(trim($sekolah_pdf['jabatan_kepsek'] ?? ''))): ?>
                                <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah_pdf['jabatan_kepsek']); ?></span><br>
                            <?php endif; ?>
                            <?php if (!empty(trim($sekolah_pdf['nip_kepsek'] ?? ''))): ?>
                                NIP. <?php echo htmlspecialchars($sekolah_pdf['nip_kepsek']); ?>
                            <?php endif; ?>
                        </td>
                        <td style="width: 50%; text-align: center;">
                            <?php echo htmlspecialchars($sekolah_pdf['kabupaten_kota'] ?? ''); ?>, <?php echo $tanggal_rapor_pdf; ?><br>
                            Wali Kelas
                            <div class="signature-space"></div>
                            <strong><u><?php echo htmlspecialchars($siswa_pdf['nama_walikelas'] ?? ''); ?></u></strong><br>
                            <?php if (!empty(trim($siswa_pdf['nip_walikelas'] ?? ''))): ?>
                                NIP. <?php echo htmlspecialchars($siswa_pdf['nip_walikelas']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>
            </div> <!-- Akhir wrapper satu siswa -->

            <?php
        } // Akhir blok tipe_cetak = rapor
    ?>
    
    <!-- PAGE BREAK ANTAR SISWA (Kecuali siswa terakhir) -->
    <?php if ($counter < $total_siswa): ?>
        <div class="page-break"></div>
    <?php endif; ?>

    <?php endforeach; ?>
    <!-- AKHIR LOOPING UTAMA -->
    </main>
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
$filename = "Cetak_Massal_" . ucfirst($tipe_cetak) . "_Kelas_" . preg_replace('/[^a-zA-Z0-9]/', '', $kelas['nama_kelas'] ?? '') . "_" . date('Ymd_His') . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
exit();
?>