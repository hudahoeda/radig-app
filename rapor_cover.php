<?php
session_start();
include 'koneksi.php';
require_once 'libs/autoload.php'; // Panggil Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// Ambil ID Siswa dari URL, contoh: rapor_cover.php?id_siswa=9
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) die("Error: ID Siswa tidak valid.");

// --- PERUBAHAN: Ambil data sekolah (termasuk jenjang) ---
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);
if (!$sekolah) die("Error: Data sekolah tidak ditemukan.");
$jenjang_sekolah = strtoupper($sekolah['jenjang'] ?? 'SD'); // Default ke SD jika null
$nama_jenjang_lengkap = ($jenjang_sekolah == 'SD') ? 'SEKOLAH DASAR' : 'SEKOLAH MENENGAH PERTAMA';
$nama_jenjang_singkat = ($jenjang_sekolah == 'SD') ? 'SD' : 'SMP';
// --- AKHIR PERUBAHAN ---

// Ambil data siswa yang diperlukan
$q_siswa = mysqli_prepare($koneksi, "SELECT nama_lengkap, nis, nisn FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa, "i", $id_siswa);
mysqli_stmt_execute($q_siswa);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($q_siswa));
if (!$siswa) die("Error: Data siswa tidak ditemukan.");


// Fungsi untuk mengubah gambar menjadi base64
function toBase64($path) {
    // Tambahkan @ agar tidak muncul warning jika file not found
    if (!@file_exists($path) || !@is_readable($path)) return '';
    try {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        // Pastikan tipe file adalah gambar yang didukung
        if(!in_array(strtolower($type), ['png', 'jpg', 'jpeg', 'gif'])) return '';
        $data = @file_get_contents($path);
        if ($data === false) return '';
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    } catch (\Exception $e) {
        // Handle error jika terjadi masalah saat baca file
        error_log("Error converting image to base64: " . $e->getMessage());
        return '';
    }
}

// --- PERUBAHAN: Ambil path logo dinamis dari data sekolah ---
$logo_kabupaten_path = 'uploads/logo_kabupaten.png'; // Asumsi path tetap
$logo_sekolah_path = !empty($sekolah['logo_sekolah']) ? 'uploads/' . $sekolah['logo_sekolah'] : 'uploads/logosatap.png'; // Gunakan logo dari DB atau fallback
// --- AKHIR PERUBAHAN ---

$logo_kabupaten_base64 = toBase64($logo_kabupaten_path);
$logo_sekolah_base64 = toBase64($logo_sekolah_path);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cover Rapor - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
        @page { margin: 0; }
        body { font-family: 'Times New Roman', Times, serif; text-align: center; padding: 30px; } /* Tambah padding dasar */
        .container { padding: 40px; /* Kurangi padding container sedikit */ border: 3px double black; height: 90%; /* Beri border dan tinggi relatif */}

        /* Ukuran logo disesuaikan */
        .logo-kabupaten { width: 100px; margin-bottom: 15px; }
        .logo-sekolah { width: 90px; margin-bottom: 15px; }

        h1 { font-size: 26pt; margin: 20px 0 5px 0; } /* Sedikit margin atas bawah */
        h2 { font-size: 18pt; margin: 0 0 25px 0; font-weight: normal; line-height: 1.3; } /* Spasi antar baris */

        .nama-siswa-container { margin-top: 60px; margin-bottom: 10px;} /* Jarak atas/bawah container nama */
        .nama-siswa-box {
            display: inline-block;
            border: 2px solid black;
            padding: 8px 15px; /* Padding lebih kecil */
            margin-top: 5px; /* Jarak dari label */
            min-width: 60%; /* Lebar minimum box nama */
            max-width: 80%; /* Lebar maksimum */
        }
        .nama-siswa-box h3 { /* Ubah dari h2 ke h3 */
             font-weight: bold;
             margin:0;
             font-size: 16pt; /* Ukuran font nama */
             word-wrap: break-word; /* Agar nama panjang bisa wrap */
        }

        .nisn-container { margin-top: 30px; margin-bottom: 60px;} /* Jarak atas/bawah container NISN */
        .nisn-label { font-size: 14pt; margin-bottom: 5px;}
        .nisn-value { font-size: 14pt; font-weight: bold; } /* Buat NISN bold */

        .footer-text {
            position: absolute;
            bottom: 40px; /* Naikkan sedikit posisi footer */
            width: calc(100% - 60px); /* Sesuaikan lebar dengan padding body */
            left: 30px; /* Sesuaikan posisi kiri */
            font-size: 12pt; /* Perkecil font footer */
            font-weight: bold;
            line-height: 1.4; /* Spasi antar baris footer */
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($logo_kabupaten_base64): ?>
            <img src="<?php echo $logo_kabupaten_base64; ?>" class="logo-kabupaten" alt="Logo Kabupaten">
        <?php else: ?>
             <div style="height: 100px; margin-bottom: 15px;"></div> 
        <?php endif; ?>

        <h1>RAPOR</h1>
        
        <h2><?php echo $nama_jenjang_lengkap; ?> <br> (<?php echo $nama_jenjang_singkat; ?>) </h2>
        

        <?php if ($logo_sekolah_base64): ?>
            <img src="<?php echo $logo_sekolah_base64; ?>" class="logo-sekolah" alt="Logo Sekolah">
        <?php else: ?>
             <div style="height: 90px; margin-bottom: 15px;"></div> 
        <?php endif; ?>


        <div class="nama-siswa-container">
            <p style="font-size: 14pt; margin: 0;">Nama Peserta Didik:</p>
            <div class="nama-siswa-box">
                
                <h3><?php echo strtoupper(htmlspecialchars($siswa['nama_lengkap'])); ?></h3>
                
            </div>
        </div>

        <div class="nisn-container">
            <p class="nisn-label">NIS / NISN:</p>
             
            <p class="nisn-value"><?php echo htmlspecialchars($siswa['nis'] ?? '-'); ?> / <?php echo htmlspecialchars($siswa['nisn']); ?></p>
            
        </div>

        <div class="footer-text">
             
            <p>KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH</p>
            <p>REPUBLIK INDONESIA</p>
             
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
$options = new Options();
$options->set('isRemoteEnabled', true);
// Set base path agar Dompdf bisa menemukan gambar
$options->set('chroot', $_SERVER['DOCUMENT_ROOT']); // Penting jika path logo relatif dari root
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Generate nama file yang lebih 'bersih'
$safe_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $siswa['nama_lengkap']);
$dompdf->stream("Cover Rapor - " . $safe_name . ".pdf", ["Attachment" => 0]);
exit(); // Pastikan exit setelah stream
?>
