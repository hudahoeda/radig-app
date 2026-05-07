<?php
session_start();
include 'koneksi.php';
require_once 'libs/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// =======================================================================
// 1. VALIDASI & PENGATURAN
// =======================================================================
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) die("Error: ID Siswa tidak valid.");

// Ambil Pengaturan (Ukuran Kertas)
$pengaturan = [];
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while($r = mysqli_fetch_assoc($q_set)) {
    $pengaturan[$r['nama_pengaturan']] = $r['nilai_pengaturan'];
}
$ukuran_kertas = $pengaturan['rapor_ukuran_kertas'] ?? 'A4';

// =======================================================================
// 2. DATA SEKOLAH & JENJANG
// =======================================================================
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);
if (!$sekolah) die("Error: Data sekolah tidak ditemukan.");

$jenjang = strtoupper($sekolah['jenjang'] ?? 'SD');
$teks_jenjang = ($jenjang == 'SMP') ? 'SEKOLAH MENENGAH PERTAMA' : 'SEKOLAH DASAR';

// =======================================================================
// 3. DATA SISWA
// =======================================================================
$q_siswa = mysqli_prepare($koneksi, "SELECT nama_lengkap, nis, nisn FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa, "i", $id_siswa);
mysqli_stmt_execute($q_siswa);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($q_siswa));
if (!$siswa) die("Error: Data siswa tidak ditemukan.");

// =======================================================================
// 4. HELPER GAMBAR
// =======================================================================
function get_img_base64_local($path) {
    if (!empty($path) && file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return '';
}

// Persiapan Logo
$logo_kab_html = '';
if (file_exists('uploads/logo_kabupaten.png')) {
    $img_kab = get_img_base64_local('uploads/logo_kabupaten.png');
    if($img_kab) $logo_kab_html = '<img src="'.$img_kab.'" style="height: 120px; width: auto;">';
}

$logo_sek_html = '';
if (!empty($sekolah['logo_sekolah'])) {
    $img_sek = get_img_base64_local('uploads/' . $sekolah['logo_sekolah']);
    if($img_sek) $logo_sek_html = '<img src="'.$img_sek.'" style="height: 160px; width: auto;">';
}

// =======================================================================
// 5. GENERATE HTML
// =======================================================================
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cover Rapor - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
        /* Mengatur margin halaman agar konten terpusat rapi */
        @page { margin: 2cm 2cm 1cm 2cm; }
        
        body { 
            font-family: 'Times New Roman', Times, serif; 
            text-align: center; 
            color: #000;
        }

        .container {
            padding-top: 10px;
        }

        h1 { font-size: 26pt; font-weight: bold; margin: 0; letter-spacing: 2px; }
        h2 { font-weight: bold; margin: 0; }
        
        .nama-siswa-box {
            border: 2px solid #000; 
            padding: 15px; 
            width: 70%; 
            margin: 0 auto; 
            border-radius: 5px;
        }
        
        .footer-text {
            position: fixed; 
            bottom: 40px; 
            left: 0; 
            right: 0; 
            text-align: center;
            font-size: 14pt; 
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- 1. LOGO KABUPATEN -->
        <div style="margin-bottom: 30px;">
            <?php echo $logo_kab_html; ?>
            <?php if (empty($logo_kab_html)): ?>
                <div style="height: 100px;"></div>
            <?php endif; ?>
        </div>

        <!-- 2. JUDUL RAPOR -->
        <div style="margin-bottom: 40px;">
            <h1>RAPOR</h1>
            
            <h2 style="font-size: 16pt; margin-top: 5px; margin-bottom: 20px;">
                <?php echo $teks_jenjang; ?>
            </h2>
            
            <!-- NAMA SEKOLAH -->
            <h2 style="font-size: 24pt; margin-top: 20px; text-transform: uppercase;">
                <?php echo htmlspecialchars($sekolah['nama_sekolah']); ?>
            </h2>
        </div>

        <!-- 3. LOGO SEKOLAH -->
        <div style="margin: 40px 0;">
            <?php echo $logo_sek_html; ?>
            <?php if (empty($logo_sek_html)): ?>
                <div style="height: 150px; border: 1px dashed #ccc; width: 150px; margin: 0 auto; line-height: 150px; color: #ccc;">Logo</div>
            <?php endif; ?>
        </div>

        <!-- 4. NAMA SISWA -->
        <div style="margin-top: 40px;">
            <p style="font-size: 12pt; margin-bottom: 10px;">Nama Peserta Didik:</p>
            <div class="nama-siswa-box">
                <span style="font-size: 18pt; font-weight: bold; text-transform: uppercase;">
                    <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                </span>
            </div>
        </div>

        <!-- 5. NISN -->
        <div style="margin-top: 20px;">
            <p style="font-size: 12pt; margin-bottom: 5px;">NIS / NISN:</p>
            <p style="font-size: 14pt; font-weight: bold;">
                <?php echo htmlspecialchars(($siswa['nis']??'-') . ' / ' . ($siswa['nisn']??'-')); ?>
            </p>
        </div>

        <!-- 6. FOOTER -->
        <div class="footer-text">
            KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH<br>
            REPUBLIK INDONESIA
        </div>

    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', $_SERVER['DOCUMENT_ROOT']); 
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// [LOGIKA UKURAN KERTAS]
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
$safe_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $siswa['nama_lengkap']);
$dompdf->stream("Cover - " . $safe_name . ".pdf", ["Attachment" => 0]);
exit();
?>