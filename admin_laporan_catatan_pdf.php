<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

session_start();
include 'koneksi.php';

// Validasi role admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak. Hanya admin yang bisa mencetak laporan ini.");
}

require_once 'libs/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Ambil Parameter
$id_guru = isset($_GET['id_guru']) ? (int)$_GET['id_guru'] : 0;
$id_ta = isset($_GET['id_ta']) ? (int)$_GET['id_ta'] : 0;

if ($id_guru == 0 || $id_ta == 0) {
    die("Parameter tidak valid.");
}

// 2. Ambil Data Guru dan Tahun Ajaran
$q_guru = mysqli_prepare($koneksi, "SELECT * FROM guru WHERE id_guru = ?");
mysqli_stmt_bind_param($q_guru, "i", $id_guru);
mysqli_stmt_execute($q_guru);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($q_guru));

$q_ta = mysqli_prepare($koneksi, "SELECT * FROM tahun_ajaran WHERE id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_ta, "i", $id_ta);
mysqli_stmt_execute($q_ta);
$ta = mysqli_fetch_assoc(mysqli_stmt_get_result($q_ta));

if (!$guru || !$ta) {
    die("Data Guru atau Tahun Ajaran tidak ditemukan.");
}

// 3. Ambil Data Sekolah & Pengaturan untuk KOP Surat
$sekolah = [];
$q_sek = mysqli_query($koneksi, "SELECT * FROM sekolah LIMIT 1");
if ($s = mysqli_fetch_assoc($q_sek)) {
    $sekolah = $s;
}

$pengaturan = [];
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while ($r = mysqli_fetch_assoc($q_set)) {
    $pengaturan[$r['nama_pengaturan']] = $r['nilai_pengaturan'];
}

// 4. Ambil Data Siswa Binaan dan Catatan
$q_siswa = mysqli_prepare($koneksi, "
    SELECT s.id_siswa, s.nama_lengkap, s.nis, s.nisn, k.nama_kelas
    FROM siswa s
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.id_guru_wali = ? AND k.id_tahun_ajaran = ? AND s.status_siswa = 'Aktif'
    ORDER BY s.nama_lengkap ASC
");
mysqli_stmt_bind_param($q_siswa, "ii", $id_guru, $id_ta);
mysqli_stmt_execute($q_siswa);
$res_siswa = mysqli_stmt_get_result($q_siswa);

$siswa_binaan = [];
while ($siswa = mysqli_fetch_assoc($res_siswa)) {
    $q_cat = mysqli_prepare($koneksi, "SELECT * FROM catatan_guru_wali WHERE id_siswa = ? ORDER BY tanggal_catatan ASC");
    mysqli_stmt_bind_param($q_cat, "i", $siswa['id_siswa']);
    mysqli_stmt_execute($q_cat);
    $res_cat = mysqli_stmt_get_result($q_cat);
    $siswa['catatan'] = mysqli_fetch_all($res_cat, MYSQLI_ASSOC);

    // Hanya masukkan siswa yang memiliki catatan (opsional, tapi lebih baik untuk laporan)
    if (count($siswa['catatan']) > 0) {
        $siswa_binaan[] = $siswa;
    }
}

// 5. Setup Fungsi Base64 untuk Gambar KOP / Watermark
function get_img_base64_local($path) {
    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return '';
}

// B. KOP Sekolah
$tampil_kop_img = ($pengaturan['rapor_tampil_kop'] ?? '0') == '1';

$file_kop = '';
if (!empty($pengaturan['kop_sekolah'])) {
    $file_kop = $pengaturan['kop_sekolah'];
} elseif (!empty($pengaturan['file_kop_sekolah'])) {
    $file_kop = $pengaturan['file_kop_sekolah'];
}

$kop_base64 = '';
if ($tampil_kop_img && !empty($file_kop)) {
    $path_kop = 'uploads/' . $file_kop;
    if (file_exists($path_kop) && is_readable($path_kop)) {
        $kop_base64 = get_img_base64_local($path_kop);
    }
}

// C. Logo Kiri (Instansi)
$kop_logo_kiri_tampil = $pengaturan['kop_logo_kiri_tampil'] ?? '1';
$logo_kiri = $pengaturan['logo_kiri'] ?? 'logo_kabupaten.png';
$base64_kiri_pdf = '';
$path_kiri_pdf = 'uploads/' . $logo_kiri;
if (!file_exists($path_kiri_pdf)) {
    $path_kiri_pdf = 'uploads/logo_kabupaten.png';
}
if (file_exists($path_kiri_pdf)) {
    $base64_kiri_pdf = get_img_base64_local($path_kiri_pdf);
}

// D. Logo Sekolah
$kop_logo_kanan_tampil = $pengaturan['kop_logo_kanan_tampil'] ?? '1';
$base64_sekolah_pdf = '';
if (!empty($sekolah['logo_sekolah'])) {
    $path_sekolah_pdf = 'uploads/' . $sekolah['logo_sekolah'];
    if (file_exists($path_sekolah_pdf)) {
        $base64_sekolah_pdf = get_img_base64_local($path_sekolah_pdf);
    }
}

$watermark_base64 = '';
$watermark_filename_pdf = $pengaturan['watermark_file'] ?? null;
if (!empty($watermark_filename_pdf)) {
    $server_path_wm = 'uploads/' . $watermark_filename_pdf;
    if (file_exists($server_path_wm) && is_readable($server_path_wm)) {
        $watermark_base64 = get_img_base64_local($server_path_wm);
    }
}

// [KOP DESIGNER] Text KOP
$kop_baris_1 = $pengaturan['kop_baris_1'] ?? 'PEMERINTAH KABUPATEN ' . strtoupper($sekolah['kabupaten_kota'] ?? '');
$kop_baris_2 = $pengaturan['kop_baris_2'] ?? 'DINAS PENDIDIKAN';
$kop_baris_3 = $pengaturan['kop_baris_3'] ?? strtoupper($sekolah['nama_sekolah'] ?? '');
$kop_baris_4 = $pengaturan['kop_baris_4'] ?? ($sekolah['jalan'] ?? '') . ', Desa/Kel. ' . ($sekolah['desa_kelurahan'] ?? '') . ', Kec. ' . ($sekolah['kecamatan'] ?? '') . '<br>Telp: ' . ($sekolah['telepon'] ?? '') . ' Email: ' . ($sekolah['email'] ?? '');

$theme_color_kop = '#000000'; // Default black

// Mode Tanpa KOP & Margin
$cetak_tanpa_kop = $pengaturan['cetak_tanpa_kop'] ?? '0';

// Handle Margin: Pastikan format angka benar (ganti koma jadi titik)
$margin_raw = (isset($pengaturan['margin_atas_tanpa_kop']) && $pengaturan['margin_atas_tanpa_kop'] !== '')
              ? $pengaturan['margin_atas_tanpa_kop']
              : '1';
$margin_atas = str_replace(',', '.', $margin_raw);

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Catatan Guru Wali - <?php echo htmlspecialchars($guru['nama_guru']); ?></title>
    <style>
        <?php if ($cetak_tanpa_kop == '1'): ?>
            /* [FIX] JIKA TANPA KOP: Margin Atas disesuaikan input user */
            @page { margin: <?php echo $margin_atas; ?>cm 30px 40px 30px; }
            header { display: none; }
        <?php else: ?>
            /* DEFAULT: Margin yang pas agar tidak overlap tapi tidak terlalu boros kertas */
            @page { margin: 155px 30px 30px 30px; }
            header { position: fixed; top: -145px; left: 0px; right: 0px; height: 135px; }
        <?php endif; ?>

        body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; color: #333; }

        .header-table { width: 100%; border-bottom: 3px solid #000; padding-bottom: 2px; margin-bottom: 5px; }
        .header-table .logo-left { width: 80px; text-align: center; vertical-align: middle; }
        .header-table .logo-right { width: 80px; text-align: center; vertical-align: middle; }
        .header-table .kop-text { text-align: center; vertical-align: middle; }
        .header-table h4, .header-table h3, .header-table p { margin: 0; line-height: 1.1; }
        .header-table h4 { font-size: 13pt; }
        .header-table .dinas-text { font-size: 12pt; margin-top: 1px; }
        .header-table .school-name { font-size: 16pt; font-weight: bold; margin: 3px 0; color: <?php echo $theme_color_kop; ?>; }
        .header-table .school-info { font-size: 9pt; line-height: 1.2; }

        .header-img-container { width: 100%; text-align: center; margin-bottom: 5px; }
        .header-img-container img { width: 100%; height: auto; max-height: 100px; }

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
            object-fit: cover;
        }

        main { margin-top: 0px; }

        /* JUDUL HALAMAN */
        .page-title { text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .page-subtitle { text-align: center; font-size: 11pt; margin-bottom: 20px; }

        /* INFO GURU */
        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { padding: 3px 0; }
        .info-label { width: 150px; font-weight: bold; }

        /* TABEL DATA */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .data-table th { background-color: #e0e0e0; font-weight: bold; text-align: center; }
        .center { text-align: center; }

        /* TANDA TANGAN */
        .signature-table { width: 100%; margin-top: 40px; page-break-inside: avoid; }
        .signature-table td { width: 50%; text-align: center; vertical-align: top; }
        .sig-space { height: 70px; }
    </style>
</head>
<body>

    <div class="watermark">
        <?php if (!empty($watermark_base64)): ?>
            <img src="<?php echo $watermark_base64; ?>" alt="Watermark">
        <?php endif; ?>
    </div>

    <!-- KOP DITARUH DI SINI -->
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

    <main>
        <!-- JUDUL -->
        <div class="page-title">LAPORAN MONITORING CATATAN GURU WALI</div>
        <div class="page-subtitle">Tahun Ajaran <?php echo htmlspecialchars($ta['tahun_ajaran']); ?></div>

    <!-- INFO GURU -->
    <table class="info-table">
        <tr>
            <td class="info-label">Nama Guru Wali</td>
            <td style="width: 10px;">:</td>
            <td><b><?php echo htmlspecialchars($guru['nama_guru']); ?></b></td>
        </tr>
        <tr>
            <td class="info-label">NIP</td>
            <td>:</td>
            <td><?php echo !empty(trim($guru['nip'])) ? htmlspecialchars($guru['nip']) : '-'; ?></td>
        </tr>
    </table>

    <!-- DAFTAR CATATAN -->
    <?php if (empty($siswa_binaan)): ?>
        <p style="text-align: center; font-style: italic; margin-top: 30px;">Belum ada catatan yang direkam oleh Guru Wali ini di Tahun Ajaran aktif.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 25%;">Nama Siswa / Kelas</th>
                    <th style="width: 15%;">Tgl & Kategori</th>
                    <th style="width: 55%;">Isi Catatan Pembinaan</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                foreach ($siswa_binaan as $s):
                    $first = true;
                    foreach ($s['catatan'] as $c):
                ?>
                    <tr style="page-break-inside: avoid;">
                        <td class="center"><?php echo $first ? $no++ : ''; ?></td>
                        <td>
                            <?php if ($first): ?>
                                <b><?php echo htmlspecialchars($s['nama_lengkap']); ?></b><br>
                                <span style="font-size: 9pt;">NIS: <?php echo htmlspecialchars($s['nis']); ?></span><br>
                                <span style="font-size: 9pt;">Kelas: <?php echo htmlspecialchars($s['nama_kelas']); ?></span>
                            <?php else: ?>
                                <span style="color: #666; font-style: italic; font-size: 9pt;">(Lanjutan) <?php echo htmlspecialchars($s['nama_lengkap']); ?></span>
                            <?php endif; ?>
                        </td>

                        <td style="font-size: 10pt;">
                            <?php echo date('d/m/Y H:i', strtotime($c['tanggal_catatan'])); ?><br>
                            <b><?php echo htmlspecialchars($c['kategori_catatan']); ?></b>
                        </td>
                        <td style="font-size: 10pt; text-align: justify;">
                            <?php echo nl2br(htmlspecialchars($c['isi_catatan'])); ?>
                        </td>
                    </tr>
                <?php
                    $first = false;
                    endforeach;
                endforeach;
                ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- TANDA TANGAN -->
    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br>
                Kepala Sekolah<br>
                <div class="sig-space"></div>
                <b><u><?php echo htmlspecialchars($sekolah['nama_kepsek'] ?? ''); ?></u></b><br>
                NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek'] ?? '-'); ?>
            </td>
            <td>
                <br>
                Guru Wali Binaan<br>
                <div class="sig-space"></div>
                <b><u><?php echo htmlspecialchars($guru['nama_guru']); ?></u></b><br>
                <?php if (!empty(trim($guru['nip']))): ?>
                    NIP. <?php echo htmlspecialchars($guru['nip']); ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    </main>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', dirname(__FILE__));
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Set paper F4 or A4 from global settings
$ukuran_kertas = $pengaturan['rapor_ukuran_kertas'] ?? 'A4';
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
$filename = "Laporan_Catatan_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $guru['nama_guru']) . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
exit();
?>
