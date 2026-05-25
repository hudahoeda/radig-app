<?php
// =======================================================================
// KONFIGURASI SISTEM & ERROR HANDLING
// =======================================================================
// [PENTING] Nonaktifkan output error agar tidak merusak binary PDF
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Meningkatkan batas memori dan waktu eksekusi untuk proses PDF
ini_set('memory_limit', '512M'); 
ini_set('max_execution_time', 300); // 5 Menit

session_start();
include 'koneksi.php';
// Pastikan path ke folder libs/autoload.php sudah benar sesuai struktur folder Anda
require_once 'libs/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// =======================================================================
// 1. VALIDASI INPUT
// =======================================================================
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) {
    echo "Error: Parameter ID siswa tidak valid.";
    exit;
}

// =======================================================================
// FUNGSI TANGGAL INDONESIA
// =======================================================================
function tanggal_indo($tanggal) {
    if(empty($tanggal) || $tanggal == '0000-00-00') return "-";
    
    $bulan = array (
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    );
    
    $pecahkan = explode('-', $tanggal);
    
    // Pastikan format tanggal valid (YYYY-MM-DD)
    if(count($pecahkan) != 3) return $tanggal;

    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

// =======================================================================
// 2. PENGAMBILAN PENGATURAN GLOBAL
// =======================================================================
$pengaturan_pdf = [];
$query_pengaturan_pdf = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while($row_pdf = mysqli_fetch_assoc($query_pengaturan_pdf)){
    $pengaturan_pdf[$row_pdf['nama_pengaturan']] = $row_pdf['nilai_pengaturan'];
}

// Mode Tanpa KOP & Margin
$cetak_tanpa_kop = $pengaturan_pdf['cetak_tanpa_kop'] ?? '0';

// Handle Margin: Pastikan format angka benar (ganti koma jadi titik)
$margin_raw = (isset($pengaturan_pdf['margin_atas_tanpa_kop']) && $pengaturan_pdf['margin_atas_tanpa_kop'] !== '') 
              ? $pengaturan_pdf['margin_atas_tanpa_kop'] 
              : '1'; 
$margin_atas = str_replace(',', '.', $margin_raw);

// Ambil KKM (Default 75 jika tidak ada di DB)
$kkm = isset($pengaturan_pdf['kkm']) ? (int)$pengaturan_pdf['kkm'] : 75;

// Ambil Tahun Ajaran Aktif
$q_ta_pdf = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta_pdf = mysqli_fetch_assoc($q_ta_pdf);
$id_tahun_ajaran_pdf = $d_ta_pdf['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_pdf = $d_ta_pdf['tahun_ajaran'] ?? '-';

// Ambil Parameter Rapor dari Pengaturan
$semester_aktif_pdf = $pengaturan_pdf['semester_aktif'] ?? 1;
$semester_text_pdf = ($semester_aktif_pdf == 1) ? '1 (Ganjil)' : '2 (Genap)';

// Implementasi Tanggal Indo
$tanggal_rapor_db = $pengaturan_pdf['tanggal_rapor'] ?? date("Y-m-d");
$tanggal_rapor_pdf = tanggal_indo($tanggal_rapor_db);

// Ambil Data Sekolah
$q_sekolah_pdf = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah_pdf = mysqli_fetch_assoc($q_sekolah_pdf);

// =======================================================================
// 3. KONFIGURASI TAMPILAN (WARNA & UKURAN)
// =======================================================================
$ukuran_kertas_pdf = $pengaturan_pdf['rapor_ukuran_kertas'] ?? 'A4';
$skema_warna_pdf = $pengaturan_pdf['rapor_skema_warna'] ?? 'bw';

$theme_color_bg = '#444444'; 
$theme_color_text = '#FFFFFF'; 
$theme_color_kop = '#000000';

switch ($skema_warna_pdf) {
    case 'light_blue': 
        $theme_color_bg = '#E3F2FD'; $theme_color_text = '#0D47A1'; $theme_color_kop = '#0D47A1'; break;
    case 'light_green': 
        $theme_color_bg = '#E8F5E9'; $theme_color_text = '#1B5E20'; $theme_color_kop = '#1B5E20'; break;
    case 'light_teal': 
        $theme_color_bg = '#E0F2F1'; $theme_color_text = '#004D40'; $theme_color_kop = '#004D40'; break;
    case 'light_purple': 
        $theme_color_bg = '#EDE7F6'; $theme_color_text = '#311B92'; $theme_color_kop = '#311B92'; break;
    case 'light_red': 
        $theme_color_bg = '#FFEBEE'; $theme_color_text = '#B71C1C'; $theme_color_kop = '#B71C1C'; break;
    default: 
        $theme_color_bg = '#444444'; $theme_color_text = '#FFFFFF'; $theme_color_kop = '#000000'; break;
}

function hex_to_rgba($hex, $alpha = 1) {
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "rgba($r, $g, $b, $alpha)";
}

// =======================================================================
// 4. PROSES GAMBAR (LOGO, WATERMARK, KOP) KE BASE64
// =======================================================================
// A. Watermark
$watermark_base64 = '';
$watermark_filename_pdf = $pengaturan_pdf['watermark_file'] ?? null;
if (!empty($watermark_filename_pdf)) {
    $server_path_wm = 'uploads/' . $watermark_filename_pdf;
    if (file_exists($server_path_wm) && is_readable($server_path_wm)) {
        $type_pdf = pathinfo($server_path_wm, PATHINFO_EXTENSION);
        $data_pdf = file_get_contents($server_path_wm);
        $watermark_base64 = 'data:image/' . $type_pdf . ';base64,' . base64_encode($data_pdf);
    }
}

// B. KOP Sekolah 
$tampil_kop_img = ($pengaturan_pdf['rapor_tampil_kop'] ?? '0') == '1';

$file_kop = '';
if (!empty($pengaturan_pdf['kop_sekolah'])) {
    $file_kop = $pengaturan_pdf['kop_sekolah'];
} elseif (!empty($pengaturan_pdf['file_kop_sekolah'])) {
    $file_kop = $pengaturan_pdf['file_kop_sekolah'];
}

$kop_base64 = '';

if ($tampil_kop_img && !empty($file_kop)) {
    $path_kop = 'uploads/' . $file_kop;
    if (file_exists($path_kop) && is_readable($path_kop)) {
        $type_kop = pathinfo($path_kop, PATHINFO_EXTENSION);
        $data_kop = file_get_contents($path_kop);
        $kop_base64 = 'data:image/' . $type_kop . ';base64,' . base64_encode($data_kop);
    }
}

// C. Logo Kabupaten
$base64_kab_pdf = '';
$path_kab_pdf = 'uploads/logo_kabupaten.png';
if (file_exists($path_kab_pdf)) {
    $type_kab_pdf = pathinfo($path_kab_pdf, PATHINFO_EXTENSION);
    $data_kab_pdf = file_get_contents($path_kab_pdf);
    $base64_kab_pdf = 'data:image/' . $type_kab_pdf . ';base64,' . base64_encode($data_kab_pdf);
}

// D. Logo Sekolah
$base64_sekolah_pdf = '';
if (!empty($sekolah_pdf['logo_sekolah'])) {
    $path_sekolah_pdf = 'uploads/' . $sekolah_pdf['logo_sekolah'];
    if (file_exists($path_sekolah_pdf)) {
        $type_sekolah_pdf = pathinfo($path_sekolah_pdf, PATHINFO_EXTENSION);
        $data_sekolah_pdf = file_get_contents($path_sekolah_pdf);
        $base64_sekolah_pdf = 'data:image/' . $type_sekolah_pdf . ';base64,' . base64_encode($data_sekolah_pdf);
    }
}

// =======================================================================
// 5. PENGAMBILAN DATA SISWA & RAPOR
// =======================================================================
$q_siswa_pdf = "SELECT s.nama_lengkap, s.nis, s.nisn, s.agama, s.id_kelas, k.nama_kelas, k.fase, g.nama_guru as nama_walikelas, g.nip as nip_walikelas
                FROM siswa s 
                LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
                WHERE s.id_siswa = ?";
$stmt_siswa_pdf = mysqli_prepare($koneksi, $q_siswa_pdf);
mysqli_stmt_bind_param($stmt_siswa_pdf, "i", $id_siswa);
mysqli_stmt_execute($stmt_siswa_pdf);
$siswa_pdf_result = mysqli_stmt_get_result($stmt_siswa_pdf);

if (mysqli_num_rows($siswa_pdf_result) == 0) {
    echo "Error: Data siswa tidak ditemukan.";
    exit;
}
$siswa_pdf = mysqli_fetch_assoc($siswa_pdf_result);
$id_kelas_siswa = $siswa_pdf['id_kelas'] ?? 0;

$q_rapor = mysqli_prepare($koneksi, "SELECT * FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ? LIMIT 1");
mysqli_stmt_bind_param($q_rapor, "iii", $id_siswa, $semester_aktif_pdf, $id_tahun_ajaran_pdf);
mysqli_stmt_execute($q_rapor);
$rapor_pdf = mysqli_fetch_assoc(mysqli_stmt_get_result($q_rapor));
$id_rapor = $rapor_pdf['id_rapor'] ?? 0;

$is_draft = false;
if (!$rapor_pdf || (isset($rapor_pdf['status']) && $rapor_pdf['status'] == 'Draft')) {
    $is_draft = true;
}

$theme_color_bg_css = $theme_color_bg;
if ($is_draft) {
    $theme_color_bg_css = hex_to_rgba($theme_color_bg, 0.8); 
}

$show_nilai_column_pdf = true;

// =======================================================================
// 6. LOGIKA PENGAMBILAN MATA PELAJARAN (FILTER AGAMA & KELAS)
// =======================================================================
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
    if (!empty($agama_siswa_clean) && strpos($nama_mapel_kecil, $agama_siswa_clean) !== false) {
        $id_mapel_agama_siswa_pdf = $row_agama['id_mapel'];
    }
}

$semua_id_mapel_agama_string_pdf = implode(',', $ids_semua_mapel_agama);
if (empty($semua_id_mapel_agama_string_pdf)) { $semua_id_mapel_agama_string_pdf = '0'; }

$query_mapel_string_pdf = "
    SELECT mp.id_mapel, mp.nama_mapel, mp.urutan 
    FROM mata_pelajaran AS mp
    JOIN guru_mengajar AS gm ON mp.id_mapel = gm.id_mapel
    WHERE gm.id_kelas = $id_kelas_siswa AND gm.id_tahun_ajaran = $id_tahun_ajaran_pdf
";

if ($id_mapel_agama_siswa_pdf > 0) {
    $query_mapel_string_pdf .= " AND (mp.id_mapel NOT IN ($semua_id_mapel_agama_string_pdf) OR mp.id_mapel = $id_mapel_agama_siswa_pdf)";
} else {
    $query_mapel_string_pdf .= " AND mp.id_mapel NOT IN ($semua_id_mapel_agama_string_pdf)";
}

$query_mapel_string_pdf .= " GROUP BY mp.id_mapel ORDER BY mp.urutan ASC, mp.nama_mapel ASC";
$semua_mapel_query_pdf = mysqli_query($koneksi, $query_mapel_string_pdf);

// =======================================================================
// [CORE FIX] PENGAMBILAN DATA DARI TABEL RAPOR (MENGEMBALIKAN FUNGSI GENERATE)
// =======================================================================
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

if ($semua_mapel_query_pdf) { 
    while ($mapel_pdf = mysqli_fetch_assoc($semua_mapel_query_pdf)) {
        $id_mapel_pdf = $mapel_pdf['id_mapel'];
        
        $asli = 0;
        $katrol = 0;
        $deskripsi = '-';

        // Ambil data mutlak dari tabel rapor (Wajib Generate Rapor agar muncul)
        if (isset($nilai_tersimpan_pdf[$id_mapel_pdf])) {
            $asli = $nilai_tersimpan_pdf[$id_mapel_pdf]['asli'];
            $katrol = $nilai_tersimpan_pdf[$id_mapel_pdf]['katrol'];
            $deskripsi = $nilai_tersimpan_pdf[$id_mapel_pdf]['capaian_kompetensi'];
        }

        // Penentuan Nilai Final: Pilih yang tertinggi
        $nilai_final = $asli;
        if ($katrol > $asli) {
            $nilai_final = $katrol;
        }

        $daftar_mapel_rapor_pdf[] = [
            'nama_mapel' => $mapel_pdf['nama_mapel'], 
            'nilai_akhir' => ($nilai_final > 0) ? $nilai_final : '-', 
            'capaian_kompetensi' => $deskripsi
        ];
    }
}

// =======================================================================
// 7. LOGIKA PENGGABUNGAN MATA PELAJARAN (SENI & PRAKARYA)
// =======================================================================
$seni_mapel_names = ['Seni Musik', 'Seni Rupa', 'Seni Tari', 'Seni Teater']; 
$prakarya_mapel_names = ['Prakarya']; 

$seni_data = null;
$prakarya_data = null;
$found_seni_index = -1;
$found_prakarya_index = -1;

foreach ($daftar_mapel_rapor_pdf as $index => $mapel) {
    if (in_array($mapel['nama_mapel'], $seni_mapel_names)) {
        $seni_data = $mapel;
        $found_seni_index = $index;
    } elseif (in_array($mapel['nama_mapel'], $prakarya_mapel_names)) {
        $prakarya_data = $mapel;
        $found_prakarya_index = $index;
    }
}

$combined_data = null;
$mapel_yang_dihapus_index = [];

if ($seni_data && $prakarya_data) {
    $nilai_seni = is_numeric($seni_data['nilai_akhir']) ? (int)$seni_data['nilai_akhir'] : 0;
    $nilai_prakarya = is_numeric($prakarya_data['nilai_akhir']) ? (int)$prakarya_data['nilai_akhir'] : 0;
    $nilai_rata_rata = '-';
    
    if ($nilai_seni > 0 && $nilai_prakarya > 0) {
        $nilai_rata_rata = round(($nilai_seni + $nilai_prakarya) / 2);
    } elseif ($nilai_seni > 0) {
        $nilai_rata_rata = $nilai_seni;
    } elseif ($nilai_prakarya > 0) {
        $nilai_rata_rata = $nilai_prakarya;
    }

    $deskripsi_gabungan = trim($seni_data['capaian_kompetensi']) . "\n" . trim($prakarya_data['capaian_kompetensi']);
    
    $combined_data = [
        'nama_mapel' => 'Seni Budaya dan Prakarya',
        'nilai_akhir' => $nilai_rata_rata,
        'capaian_kompetensi' => trim($deskripsi_gabungan)
    ];

} elseif ($seni_data) {
    $seni_data['nama_mapel'] = 'Seni Budaya dan Prakarya';
    $combined_data = $seni_data;
} elseif ($prakarya_data) {
    $prakarya_data['nama_mapel'] = 'Seni Budaya dan Prakarya';
    $combined_data = $prakarya_data;
}

if ($combined_data) {
    if ($found_seni_index !== -1) $mapel_yang_dihapus_index[] = $found_seni_index;
    if ($found_prakarya_index !== -1) $mapel_yang_dihapus_index[] = $found_prakarya_index;

    $insert_index = ($found_seni_index !== -1 && $found_prakarya_index !== -1) 
                    ? min($found_seni_index, $found_prakarya_index) 
                    : ($found_seni_index !== -1 ? $found_seni_index : $found_prakarya_index);
    
    $temp_mapel_list = $daftar_mapel_rapor_pdf;
    
    $mapel_yang_dihapus_index = array_unique($mapel_yang_dihapus_index);
    foreach ($mapel_yang_dihapus_index as $index_to_remove) {
        unset($temp_mapel_list[$index_to_remove]);
    }

    $insert_index = is_int($insert_index) && $insert_index >= 0 ? $insert_index : 0;
    array_splice($temp_mapel_list, $insert_index, 0, [$combined_data]);
    $daftar_mapel_rapor_pdf = array_values($temp_mapel_list);
}


// =======================================================================
// 8. AMBIL DATA EKSTRAKURIKULER
// =======================================================================
$detail_ekskul_query_pdf = mysqli_prepare($koneksi, "
    SELECT 
        e.nama_ekskul, 
        kh.jumlah_hadir, 
        kh.total_pertemuan,
        (SELECT GROUP_CONCAT(CONCAT(et.deskripsi_tujuan, ': ', pn.nilai) SEPARATOR '; ') 
         FROM ekskul_penilaian pn
         JOIN ekskul_tujuan et ON pn.id_tujuan_ekskul = et.id_tujuan_ekskul
         WHERE 
             pn.id_peserta_ekskul = ep.id_peserta_ekskul 
             AND et.semester = ?) as 'penilaian_deskriptif'
    FROM 
        ekskul_peserta ep
    JOIN 
        ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
    LEFT JOIN 
        ekskul_kehadiran kh ON ep.id_peserta_ekskul = kh.id_peserta_ekskul 
                            AND kh.semester = ?
    WHERE 
        ep.id_siswa = ?
    ORDER BY 
        e.nama_ekskul ASC
");
mysqli_stmt_bind_param($detail_ekskul_query_pdf, "iii", $semester_aktif_pdf, $semester_aktif_pdf, $id_siswa); 
mysqli_stmt_execute($detail_ekskul_query_pdf);
$detail_ekskul_result_pdf = mysqli_stmt_get_result($detail_ekskul_query_pdf);

// =======================================================================
// 9. MULAI GENERATE HTML PDF
// =======================================================================
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapor <?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? ''); ?></title>
    
    <style>
        <?php if ($cetak_tanpa_kop == '1'): ?>
            /* [FIX] JIKA TANPA KOP: Margin Atas disesuaikan input user */
            @page { margin: <?php echo $margin_atas; ?>cm 30px 40px 30px; }
            header { display: none; }
        <?php else: ?>
            /* DEFAULT: Margin 150px untuk tempat KOP */
            @page { margin: 170px 30px 40px 30px; } 
            header { position: fixed; top: -150px; left: 0px; right: 0px; height: 140px; }
        <?php endif; ?>

        body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; color: #333; }
        
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
        
        .info-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 10px; }
        .info-table td { padding: 2px 5px; vertical-align: top; }
        
        .content-table { width: 100%; border-collapse: collapse; page-break-inside: auto; margin-top: 10px; }
        .content-table th, .content-table td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: top; page-break-inside: auto; }
        .content-table tr { page-break-inside: auto; page-break-after: auto; }
        .content-table th { background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>; font-weight: bold; text-align: center; vertical-align: middle; }
        
        .nilai-cetak {
            font-weight: bold;
            text-align: center !important;
            vertical-align: middle;
            font-size: 11pt; 
        }

        .section-title { font-weight: bold; font-size: 11pt; margin-top: 20px; margin-bottom: 8px; }
        .text-center { text-align: center !important; }
        .capaian { font-size: 9pt; line-height: 1.4; text-align: justify; }
        
        .signature-table { width: 100%; margin-top: 40px; font-size: 10pt; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; text-align: center; }
        .signature-space { height: 60px; }
        
        /* [CLASS BARU UNTUK PAGE BREAK PINTAR] */
        .keep-together {
            page-break-inside: avoid;
            margin-bottom: 2px; /* Spasi antar blok */
        }

        .watermark { 
    position: fixed; 
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1000; 
    display: flex;
    justify-content: center;
    align-items: center;
    pointer-events: none;
}
.watermark img { 
    opacity: 0.1; 
    width: 100%;
    height: 100%;
    object-fit: cover; /* Memastikan gambar menutupi seluruh area */
}

        .draft-watermark {
            position: fixed; top: 50%; left: 50%;
            font-size: 120pt; font-weight: bold; color: #FF0000; opacity: 0.15;
            transform: translate(-50%, -50%) rotate(-45deg);
            z-index: -1001; width: 150%; text-align: center;
        }

        footer { 
            position: fixed; 
            bottom: -30px; 
            left: 0px; 
            right: 0px; 
            height: 35px; 
            font-size: 8pt; 
            color: #666;
            border-top: 2px solid <?php echo $theme_color_bg; ?>; 
            padding: 8px 30px 0 30px; 
            background-color: #fff; 
        }
        .footer-table { width: 100%; border-collapse: collapse; margin: 0; }
        .footer-table td { padding: 0; vertical-align: top; }
        
        .footer-left { text-align: left; width: 40%; font-weight: bold; color: <?php echo $theme_color_kop; ?>; }
        .footer-center { text-align: center; width: 40%; font-style: italic; color: #999; }
        .footer-right { text-align: right; width: 20%; }
        
        .page-badge {
            background-color: #f0f0f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: #333;
        }
        .footer-right .page-number:after { content: counter(page); }
    </style>
</head>
<body>

    <?php if ($is_draft): ?>
        <div class="draft-watermark">DRAFT</div>
    <?php endif; ?>

    <div class="watermark">
        <?php if (!empty($watermark_base64)): ?>
            <img src="<?php echo $watermark_base64; ?>" alt="Watermark">
        <?php endif; ?>
    </div>

    <!-- KOP DITARUH DI SINI -->
    <?php if ($cetak_tanpa_kop != '1'): ?>
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

    <footer>
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    <?php echo htmlspecialchars($sekolah_pdf['nama_sekolah'] ?? ''); ?>
                </td>
                <td class="footer-center">
                    <?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? 'Siswa'); ?> - <?php echo htmlspecialchars($siswa_pdf['nama_kelas'] ?? ''); ?>
                </td>
                <td class="footer-right">
                    <span class="page-badge">Hal. <span class="page-number"></span></span>
                </td>
            </tr>
        </table>
    </footer>

    <main>
        <!-- Identitas Siswa -->
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

        <div class="section-title">A. NILAI AKADEMIK</div>
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
        
        <!-- [WRAPPER UNTUK B. KOKURIKULER] -->
        <div class="keep-together">
            <div class="section-title" style="margin-top: 0;">B. KOKURIKULER</div>
            <table class="content-table" style="margin-top: 0;">
                <tbody>
                    <tr>
                        <td class="capaian"><?php echo !empty($rapor_pdf['deskripsi_kokurikuler']) ? nl2br(htmlspecialchars($rapor_pdf['deskripsi_kokurikuler'])) : '-'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- [WRAPPER UNTUK C. EKSTRAKURIKULER] -->
        <div class="keep-together">
            <div class="section-title" style="margin-top: 0;">C. EKSTRAKURIKULER</div>
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
                    $predikat_label = [
                        'SB' => 'Sangat Baik',
                        'B'  => 'Baik',
                        'C'  => 'Cukup',
                        'K'  => 'Perlu Bimbingan'
                    ];
                    
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
                                
                                $map_nilai_db = [
                                    'Sangat Baik' => 'SB', 'SB' => 'SB',
                                    'Baik'        => 'B',  'B'  => 'B',
                                    'Cukup'       => 'C',  'C'  => 'C',
                                    'Kurang'      => 'K',  'K'  => 'K',
                                    'Perlu Bimbingan' => 'K'
                                ];

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

        <!-- [WRAPPER UNTUK D. KETIDAKHADIRAN & E. CATATAN] -->
        <div class="keep-together" style="width: 100%;">
            <div style="width: 40%; float: left;">
                <div class="section-title" style="margin-top:0;">D. KETIDAKHADIRAN</div>
                <table class="content-table">
                    <tr><td width="70%">Sakit</td><td>: <?php echo $rapor_pdf['sakit'] ?? 0; ?> hari</td></tr>
                    <tr><td>Izin</td><td>: <?php echo $rapor_pdf['izin'] ?? 0; ?> hari</td></tr>
                    <tr><td>Tanpa Keterangan</td><td>: <?php echo $rapor_pdf['tanpa_keterangan'] ?? 0; ?> hari</td></tr>
                </table>
            </div>
            <div style="width: 55%; float: right;">
                <div class="section-title" style="margin-top:0;">E. CATATAN WALI KELAS</div>
                <table class="content-table" style="height: 80px;">
                    <tr><td class="capaian"><?php echo !empty($rapor_pdf['catatan_wali_kelas']) ? nl2br(htmlspecialchars($rapor_pdf['catatan_wali_kelas'] ?? '-')) : '-'; ?></td></tr>
                </table>
            </div>
            <div style="clear:both;"></div>
        </div>
        
       <?php 
        // Logika aman: Mengecek apakah ini semester 2 dari variabel yang ada
        $is_semester_2 = (isset($semester_aktif_pdf) && $semester_aktif_pdf == 2) || (isset($rapor_pdf['semester']) && $rapor_pdf['semester'] == 2);
        
        // Ganti true ke $is_semester_2 jika ingin menyembunyikan otomatis di semester 1
        if ($is_semester_2): 
        ?>
        <div class="keep-together" style="margin-top: 15px; width: 100%;">
            <!-- Judul Keputusan ditaruh di luar kotak -->
            <div style="font-size: 12pt; font-weight: bold; margin-bottom: 5px;">Keputusan :</div>
            
            <table class="content-table" style="width: 100%; border-collapse: collapse; border: 1px solid #000;">
                <tr>
                    <td style="padding: 10px 15px; text-align: left; border: 1px solid #000;">
                        Berdasarkan pencapaian seluruh kompetensi, ananda <strong><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? 'Siswa'); ?></strong> ditetapkan:<br><br>
                        <div style="text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 5px;">
                        <?php
                            // Mengambil data keputusan dari database rapor
                            $keputusan = $rapor_pdf['keputusan_akhir'] ?? '-';
                            $keterangan = $rapor_pdf['keterangan_keputusan'] ?? '';
                            $nama_kelas = $siswa_pdf['nama_kelas'] ?? '';

                            // Deteksi otomatis Kelas 9 (Kelulusan) vs Kelas 7 & 8 (Kenaikan)
                            // Akan mendeteksi format kelas "IX", "IX-A", "9", "9A"
                            if (strpos(strtoupper($nama_kelas), 'IX') !== false || strpos(strtoupper($nama_kelas), '9') !== false) {
                                if ($keputusan == 'Lulus') {
                                    echo "LULUS DARI SATUAN PENDIDIKAN";
                                } elseif ($keputusan == 'Tidak Lulus') {
                                    echo "TIDAK LULUS DARI SATUAN PENDIDIKAN";
                                } else {
                                    echo "-";
                                }
                            } else {
                                if ($keputusan == 'Naik Kelas') {
                                    echo "NAIK KE KELAS : " . htmlspecialchars($keterangan);
                                } elseif ($keputusan == 'Tinggal Kelas') {
                                    echo "TINGGAL DI KELAS : " . htmlspecialchars($nama_kelas);
                                } else {
                                    echo "-";
                                }
                            }
                        ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- [WRAPPER UNTUK TANDA TANGAN & TANGGAPAN] -->
        <div class="keep-together" style="margin-top: 15px;">
            <div class="section-title" style="margin-top: 0;">Tanggapan Orang Tua/Wali Murid</div>
            <table class="content-table"><tr><td style="height: 60px;"></td></tr></table>

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
                        <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah_pdf['jabatan_kepsek'] ?? ''); ?></span><br>
                        NIP. <?php echo htmlspecialchars($sekolah_pdf['nip_kepsek'] ?? ''); ?>
                    </td>
                    <td style="width: 33.33%; text-align: center;">
                        <?php echo htmlspecialchars($sekolah_pdf['kabupaten_kota'] ?? ''); ?>, <?php echo $tanggal_rapor_pdf; ?><br>
                        Wali Kelas
                        <div class="signature-space"></div>
                        <strong><u><?php echo htmlspecialchars($siswa_pdf['nama_walikelas'] ?? ''); ?></u></strong><br>
                        NIP. <?php echo htmlspecialchars($siswa_pdf['nip_walikelas'] ?? ''); ?>
                    </td>
                </tr>
            </table>
        </div>
    </main>

</body>
</html>
<?php
// =======================================================================
// 10. PROSES RENDER HTML KE PDF
// =======================================================================
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', dirname(__FILE__)); 
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', false); 

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

// Pengaturan Ukuran Kertas
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

$filename = "Rapor - " . preg_replace('/[^a-zA-Z0-9]/', '_', $siswa_pdf['nama_lengkap'] ?? 'Unknown') . " - Smst " . $semester_aktif_pdf . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
exit();
?>