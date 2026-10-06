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

// PATCH SEMENTARA: Perbaiki tipe data ENUM agar mendukung Sumatif Tengah Semester
mysqli_query($koneksi, "ALTER TABLE penilaian MODIFY COLUMN subjenis_penilaian enum('Sumatif TP','Sumatif Tengah Semester','Sumatif Akhir Semester','Sumatif Akhir Tahun') DEFAULT NULL");
// Perbaiki data yang sudah terlanjur disimpan namun terpotong/kosong karena error ENUM
mysqli_query($koneksi, "UPDATE penilaian SET subjenis_penilaian = 'Sumatif Tengah Semester' WHERE jenis_penilaian = 'Sumatif' AND (subjenis_penilaian = '' OR subjenis_penilaian IS NULL)");
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

// Parameter Rapor PTS (Prioritas Parameter GET, Fallback Global Aktif)
if (isset($_GET['ta']) && isset($_GET['semester'])) {
    $id_tahun_ajaran = (int)$_GET['ta'];
    $semester_aktif = (int)$_GET['semester'];
    $q_ta = mysqli_query($koneksi, "SELECT tahun_ajaran FROM tahun_ajaran WHERE id_tahun_ajaran = $id_tahun_ajaran LIMIT 1");
    if ($d_ta = mysqli_fetch_assoc($q_ta)) {
        $tahun_ajaran = $d_ta['tahun_ajaran'];
    } else {
        $tahun_ajaran = '-';
    }
} else {
    $id_tahun_ajaran = 0;
    $tahun_ajaran = '-';
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    if ($d_ta = mysqli_fetch_assoc($q_ta)) {
        $id_tahun_ajaran = $d_ta['id_tahun_ajaran'];
        $tahun_ajaran = $d_ta['tahun_ajaran'];
    }
    $semester_aktif = $pengaturan['semester_aktif'] ?? 1;
}

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
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah LIMIT 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);

// [FITUR BARU] Variabel Tanpa KOP
$cetak_tanpa_kop = $pengaturan['cetak_tanpa_kop'] ?? '0';
$margin_raw = (isset($pengaturan['margin_atas_tanpa_kop']) && $pengaturan['margin_atas_tanpa_kop'] !== '') ? $pengaturan['margin_atas_tanpa_kop'] : '1';
$margin_atas = str_replace(',', '.', $margin_raw);

// [KOP DESIGNER] Text & Toggle
$kop_baris_1 = $pengaturan['kop_baris_1'] ?? 'PEMERINTAH KABUPATEN ' . strtoupper($sekolah['kabupaten_kota'] ?? '');
$kop_baris_2 = $pengaturan['kop_baris_2'] ?? 'DINAS PENDIDIKAN';
$kop_baris_3 = $pengaturan['kop_baris_3'] ?? strtoupper($sekolah['nama_sekolah'] ?? '');
$kop_baris_4 = $pengaturan['kop_baris_4'] ?? ($sekolah['jalan'] ?? '') . ', Desa/Kel. ' . ($sekolah['desa_kelurahan'] ?? '') . ', Kec. ' . ($sekolah['kecamatan'] ?? '') . '<br>Telp: ' . ($sekolah['telepon'] ?? '') . ' Email: ' . ($sekolah['email'] ?? '');
$kop_logo_kiri_tampil = $pengaturan['kop_logo_kiri_tampil'] ?? '1';
$kop_logo_kanan_tampil = $pengaturan['kop_logo_kanan_tampil'] ?? '1';
$logo_kiri = $pengaturan['logo_kiri'] ?? 'logo_kabupaten.png';

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
$q_absen = mysqli_query($koneksi, "SELECT sakit, izin, tanpa_keterangan, id_kelas_historis, id_walikelas_historis FROM rapor WHERE id_siswa='$id_siswa' AND semester='$semester_aktif' AND id_tahun_ajaran='$id_tahun_ajaran'");
if ($d_absen = mysqli_fetch_assoc($q_absen)) {
    $sakit = $d_absen['sakit'];
    $izin = $d_absen['izin'];
    $tanpa_ket = $d_absen['tanpa_keterangan'];

    // --- CEK DATA HISTORIS ---
    if (!empty($d_absen['id_kelas_historis'])) {
        $id_kelas_siswa = $d_absen['id_kelas_historis'];
        $id_wali_hist = $d_absen['id_walikelas_historis'];

        $q_hist = mysqli_query($koneksi, "SELECT k.nama_kelas, k.fase, g.nama_guru, g.nip FROM kelas k LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru WHERE k.id_kelas = $id_kelas_siswa LIMIT 1");
        if ($d_hist = mysqli_fetch_assoc($q_hist)) {
            $siswa['nama_kelas'] = $d_hist['nama_kelas'];
            $siswa['fase'] = $d_hist['fase'];
            if (!empty($id_wali_hist)) {
                $qw = mysqli_query($koneksi, "SELECT nama_guru, nip FROM guru WHERE id_guru = $id_wali_hist LIMIT 1");
                if ($dw = mysqli_fetch_assoc($qw)) {
                    $siswa['nama_walikelas'] = $dw['nama_guru'];
                    $siswa['nip_walikelas'] = $dw['nip'];
                }
            } else {
                $siswa['nama_walikelas'] = $d_hist['nama_guru'];
                $siswa['nip_walikelas'] = $d_hist['nip'];
            }
        }
    }
}

// Persiapan Gambar
$logo_kiri_html = '';
$path_kiri = 'uploads/' . $logo_kiri;
if (!file_exists($path_kiri)) $path_kiri = 'uploads/logo_kabupaten.png';
if (file_exists($path_kiri)) {
    $img = get_img_base64_local($path_kiri);
    if($img) $logo_kiri_html = '<img src="'.$img.'" alt="Logo Kiri" style="width: 80px;">';
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

// =======================================================================
// [PERBAIKAN] LOGIKA FILTER MAPEL AGAMA DINAMIS (Anti-Error)
// =======================================================================
$q_mapel_agama_all = mysqli_query($koneksi, "
    SELECT id_mapel, nama_mapel
    FROM mata_pelajaran
    WHERE (kelompok LIKE '%Agama%' OR nama_mapel LIKE '%Agama%')
");

$ids_semua_mapel_agama = [];
$id_mapel_agama_siswa = 0;
$agama_siswa_clean = strtolower(trim($siswa['agama'] ?? ''));

while ($row_agama = mysqli_fetch_assoc($q_mapel_agama_all)) {
    $ids_semua_mapel_agama[] = $row_agama['id_mapel'];
    $nama_mapel_kecil = strtolower($row_agama['nama_mapel']);
    if (!empty($agama_siswa_clean) && strpos($nama_mapel_kecil, $agama_siswa_clean) !== false) {
        $id_mapel_agama_siswa = $row_agama['id_mapel'];
    }
}

$semua_id_mapel_agama_string = implode(',', $ids_semua_mapel_agama);
if (empty($semua_id_mapel_agama_string)) { $semua_id_mapel_agama_string = '0'; }

$q_mapel_str = "
    SELECT mp.id_mapel, mp.nama_mapel, mp.parent_mapel_id, mp.is_tambahan, mp.agama_khusus
    FROM mata_pelajaran AS mp
    JOIN guru_mengajar AS gm ON mp.id_mapel = gm.id_mapel
    WHERE gm.id_kelas = $id_kelas_siswa AND gm.id_tahun_ajaran = $id_tahun_ajaran
";

// [MODIFIKASI] Filter mapel berdasarkan agama khusus
$agama_filter_aman_pts = mysqli_real_escape_string($koneksi, $siswa['agama'] ?? '');
$q_mapel_str .= " AND (mp.agama_khusus IS NULL OR mp.agama_khusus = '' OR mp.agama_khusus = '$agama_filter_aman_pts') ";

if ($id_mapel_agama_siswa > 0) {
    $q_mapel_str .= " AND (mp.id_mapel NOT IN ($semua_id_mapel_agama_string) OR mp.id_mapel = $id_mapel_agama_siswa)";
} else {
    $q_mapel_str .= " AND mp.id_mapel NOT IN ($semua_id_mapel_agama_string)";
}

$q_mapel_str .= " GROUP BY mp.id_mapel ORDER BY mp.urutan ASC, mp.nama_mapel ASC";
$q_mapel = mysqli_query($koneksi, $q_mapel_str);

$semua_mapel_raw = [];
$max_jumlah_tp = 0; // Untuk menentukan jumlah kolom S1, S2...
$has_sts_global = false; // Flag untuk menampilkan kolom STS

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

    // Hitung Rata-rata Sumatif Lingkup Materi
    $rata_rata = !empty($nilai_sumatif_arr) ? round(array_sum($nilai_sumatif_arr) / count($nilai_sumatif_arr)) : '-';
    
    // Ambil nilai Sumatif Tengah Semester (STS) jika ada
    $q_sts = mysqli_query($koneksi, "
        SELECT pdn.nilai
        FROM penilaian_detail_nilai pdn
        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
        WHERE pdn.id_siswa='$id_siswa'
          AND p.id_mapel='{$mp['id_mapel']}'
          AND p.id_kelas='$id_kelas_siswa'
          AND p.semester='$semester_aktif'
          AND p.subjenis_penilaian='Sumatif Tengah Semester'
        ORDER BY p.id_penilaian DESC LIMIT 1
    ");
    $sts_row = mysqli_fetch_assoc($q_sts);
    if ($sts_row && is_numeric($sts_row['nilai'])) {
        $mp['nilai_sts_input'] = $sts_row['nilai'];
        $has_sts_global = true; // Set flag menjadi true karena ada mapel yang memiliki nilai STS
    } else {
        $mp['nilai_sts_input'] = '-';
    }

    $mp['detail_nilai'] = $nilai_sumatif_arr; // Array nilai [80, 85, 90]
    $mp['nilai_pts'] = $rata_rata;
    $semua_mapel_raw[$mp['id_mapel']] = $mp;
}

// =======================================================================
// LOGIKA PENGGABUNGAN MATA PELAJARAN DINAMIS & PEMISAHAN LAMPIRAN
// =======================================================================
$mapel_yang_dihapus = [];
foreach ($semua_mapel_raw as $id => $mapel) {
    if (!empty($mapel['parent_mapel_id']) && isset($semua_mapel_raw[$mapel['parent_mapel_id']])) {
        // Ini adalah anak. Tambahkan ke parent.
        $pid = $mapel['parent_mapel_id'];

        if (!isset($semua_mapel_raw[$pid]['anak_list'])) {
            $semua_mapel_raw[$pid]['anak_list'] = [];
        }
        $semua_mapel_raw[$pid]['anak_list'][] = $mapel;
        $mapel_yang_dihapus[] = $id;
    }
}

// Hapus anak dari root
foreach ($mapel_yang_dihapus as $id) {
    unset($semua_mapel_raw[$id]);
}

$daftar_nilai_utama = [];
$daftar_nilai_tambahan = [];

foreach ($semua_mapel_raw as $id => $mapel) {
    // Hitung rata-rata jika punya anak
    if (isset($mapel['anak_list']) && count($mapel['anak_list']) > 0) {
        $detail_gabungan = [];
        $total_pts = 0;
        $count_pts = 0;

        $max_len = 0;
        if (is_numeric($mapel['nilai_pts']) && $mapel['nilai_pts'] > 0) {
            $total_pts += $mapel['nilai_pts'];
            $count_pts++;
            $max_len = max($max_len, count($mapel['detail_nilai']));
        }

        foreach ($mapel['anak_list'] as $anak) {
            $max_len = max($max_len, count($anak['detail_nilai']));
            if (is_numeric($anak['nilai_pts']) && $anak['nilai_pts'] > 0) {
                $total_pts += $anak['nilai_pts'];
                $count_pts++;
            }
        }

        for ($i = 0; $i < $max_len; $i++) {
            $sum_val = 0;
            $count_val = 0;

            if (isset($mapel['detail_nilai'][$i]) && is_numeric($mapel['detail_nilai'][$i])) {
                $sum_val += $mapel['detail_nilai'][$i];
                $count_val++;
            }

            foreach ($mapel['anak_list'] as $anak) {
                if (isset($anak['detail_nilai'][$i]) && is_numeric($anak['detail_nilai'][$i])) {
                    $sum_val += $anak['detail_nilai'][$i];
                    $count_val++;
                }
            }
            if ($count_val > 0) {
                $detail_gabungan[] = round($sum_val / $count_val);
            } else {
                $detail_gabungan[] = '-';
            }
        }

        $mapel['detail_nilai'] = $detail_gabungan;
        if ($count_pts > 0) {
            $mapel['nilai_pts'] = round($total_pts / $count_pts);
        } else {
            $mapel['nilai_pts'] = '-';
        }

        // Merge STS
        $sts_gabungan = '-';
        if ($mapel['nilai_sts_input'] !== '-' && is_numeric($mapel['nilai_sts_input'])) {
            $sts_gabungan = $mapel['nilai_sts_input'];
        }
        foreach ($mapel['anak_list'] as $anak) {
            if ($anak['nilai_sts_input'] !== '-' && is_numeric($anak['nilai_sts_input'])) {
                if ($sts_gabungan === '-' || $anak['nilai_sts_input'] > $sts_gabungan) {
                    $sts_gabungan = $anak['nilai_sts_input'];
                }
            }
        }
        $mapel['nilai_sts_input'] = $sts_gabungan;
    }

    // Pisahkan Utama dan Lampiran (Tambahan)
    if ($mapel['is_tambahan'] == '1') {
        $daftar_nilai_tambahan[] = $mapel;
    } else {
        $daftar_nilai_utama[] = $mapel;
    }
}

$daftar_nilai = $daftar_nilai_utama;

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
        <?php if ($cetak_tanpa_kop == '1'): ?>
            @page { margin: <?php echo $margin_atas; ?>cm 30px 40px 30px; }
            header { display: none; }
        <?php else: ?>
            @page { margin: 170px 30px 40px 30px; }
            header { position: fixed; top: -150px; left: 0px; right: 0px; height: 140px; }
        <?php endif; ?>
        
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
        .watermark { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1000; display: flex; justify-content: center; align-items: center; pointer-events: none; }
        .watermark img { opacity: 0.1; width: 100%; height: 100%; object-fit: cover; }
        
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
                    <?php if ($kop_logo_kiri_tampil == '1'): ?>
                    <td class="logo-col"><?php echo $logo_kiri_html; ?></td>
                    <?php endif; ?>

                    <td class="text-col">
                        <h4><?php echo htmlspecialchars($kop_baris_1); ?></h4>
                        <p class="dinas-text" style="font-size: 11pt; margin: 2px 0;"><?php echo htmlspecialchars($kop_baris_2); ?></p>
                        <h3><?php echo htmlspecialchars($kop_baris_3); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars(str_replace('<br>', "\n", $kop_baris_4))); ?></p>
                    </td>

                    <?php if ($kop_logo_kanan_tampil == '1'): ?>
                    <td class="logo-col"><?php echo $logo_sek_html; ?></td>
                    <?php endif; ?>
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
        
        <hr style="border: none; border-top: 2px solid #000; margin: 15px 0;">
        
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
                    <?php if($has_sts_global): ?>
                        <th rowspan="2" width="15%">Nilai<br>PTS / STS</th>
                    <?php endif; ?>
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
                    <?php if($has_sts_global): ?>
                        <td class="nilai-center"><?php echo isset($d['nilai_sts_input']) ? $d['nilai_sts_input'] : '-'; ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; if(empty($daftar_nilai)) echo '<tr><td colspan="'.($has_sts_global ? $max_jumlah_tp + 4 : $max_jumlah_tp + 3).'" class="nilai-center">Belum ada nilai yang tersedia.</td></tr>'; ?>
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
                    <?php if (!empty(trim($sekolah['jabatan_kepsek'] ?? ''))): ?>
                        <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah['jabatan_kepsek']); ?></span><br>
                    <?php endif; ?>
                    <?php if (!empty(trim($sekolah['nip_kepsek'] ?? ''))): ?>
                        NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek']); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($sekolah['kabupaten_kota']); ?>, <?php echo $tanggal_rapor_pts; ?><br>
                    Wali Kelas,<br>
                    <div class="signature-space"></div>
                    <b><?php echo htmlspecialchars($siswa['nama_walikelas']); ?></b><br>
                    <?php if (!empty(trim($siswa['nip_walikelas'] ?? ''))): ?>
                        NIP. <?php echo htmlspecialchars($siswa['nip_walikelas']); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php if (!empty($daftar_nilai_tambahan)): ?>
        <div style="page-break-before: always;"></div>

        <table class="identitas-table">
            <tr>
                <td width="20%">Nama Siswa</td><td width="2%">:</td><td width="48%"><b><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></b></td>
                <td width="15%">Kelas</td><td width="2%">:</td><td width="13%"><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
            </tr>
            <tr>
                <td>NIS / NISN</td><td>:</td><td><?php echo htmlspecialchars($siswa['nis'] . ' / ' . $siswa['nisn']); ?></td>
                <td>Semester</td><td>:</td><td><?php echo $semester_aktif == 1 ? '1 (Ganjil)' : '2 (Genap)'; ?></td>
            </tr>
            <tr>
                <td>Nama Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah['nama_sekolah']); ?></td>
                <td>Tahun Ajaran</td><td>:</td><td><?php echo htmlspecialchars($tahun_ajaran); ?></td>
            </tr>
        </table>

        <div class="section-title" style="text-align: center; margin-top: 20px;">LAMPIRAN: NILAI MATA PELAJARAN TAMBAHAN</div>

        <table class="nilai-table">
            <thead>
                <tr>
                    <th rowspan="2" width="5%">No</th>
                    <th rowspan="2" width="25%">Mata Pelajaran</th>
                    <th colspan="<?php echo $max_jumlah_tp; ?>">Nilai Sumatif Lingkup Materi</th>
                    <th rowspan="2" width="10%">Rata-rata<br>Sumatif</th>
                    <?php if($has_sts_global): ?>
                        <th rowspan="2" width="10%">Nilai<br>PTS / STS</th>
                    <?php endif; ?>
                </tr>
                <tr>
                    <?php for ($i = 1; $i <= $max_jumlah_tp; $i++): ?>
                        <th width="<?php echo (60 / $max_jumlah_tp); ?>%">S<?php echo $i; ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($daftar_nilai_tambahan as $mapel): ?>
                    <tr>
                        <td class="nilai-center"><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($mapel['nama_mapel']); ?></td>
                        <?php
                        for ($i = 0; $i < $max_jumlah_tp; $i++) {
                            $val = isset($mapel['detail_nilai'][$i]) ? $mapel['detail_nilai'][$i] : '-';
                            echo "<td class='nilai-center'>{$val}</td>";
                        }
                        ?>
                        <td class="nilai-center"><b><?php echo $mapel['nilai_pts']; ?></b></td>
                        <?php if($has_sts_global): ?>
                            <td class="nilai-center"><b><?php echo isset($mapel['nilai_sts_input']) ? $mapel['nilai_sts_input'] : '-'; ?></b></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="signature-table" style="margin-top: 50px;">
            <tr>
                <td style="width: 33.33%;">
                    Orang Tua/Wali Murid,<br>
                    <div class="signature-space"></div>
                    ( ................................. )
                </td>
                <td style="width: 33.33%;">
                    Mengetahui,<br>Kepala Sekolah,<br>
                    <div class="signature-space"></div>
                    <b><?php echo htmlspecialchars($sekolah['nama_kepsek']); ?></b><br>
                    <?php if (!empty(trim($sekolah['jabatan_kepsek'] ?? ''))): ?>
                        <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah['jabatan_kepsek']); ?></span><br>
                    <?php endif; ?>
                    <?php if (!empty(trim($sekolah['nip_kepsek'] ?? ''))): ?>
                        NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek']); ?>
                    <?php endif; ?>
                </td>
                <td style="width: 33.33%;">
                    <?php echo htmlspecialchars($sekolah['kabupaten_kota']); ?>, <?php echo $tanggal_rapor_pts; ?><br>
                    Wali Kelas,<br>
                    <div class="signature-space"></div>
                    <b><?php echo htmlspecialchars($siswa['nama_walikelas']); ?></b><br>
                    <?php if (!empty(trim($siswa['nip_walikelas'] ?? ''))): ?>
                        NIP. <?php echo htmlspecialchars($siswa['nip_walikelas']); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php endif; ?>
    </main>

</body>
</html>
<?php
// =======================================================================
// [PERBAIKAN] 5. RENDER PDF
// =======================================================================
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);

// [FIX] Menggunakan dirname(__FILE__) agar aman dari error gambar di hosting online (cPanel)
$options->set('chroot', dirname(__FILE__));
$options->set('isHtml5ParserEnabled', true);

// [FIX] Matikan PHP eval di HTML untuk keamanan
$options->set('isPhpEnabled', false);

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