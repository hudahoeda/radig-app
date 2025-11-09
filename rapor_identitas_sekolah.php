<?php
session_start();
include 'koneksi.php'; // Pastikan file ini berisi koneksi ke database Anda
require_once 'libs/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Ambil data sekolah dari database
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);

// --- PERUBAHAN: Logika untuk jenjang dinamis ---
$jenjang_sekolah = strtoupper($sekolah['jenjang'] ?? 'SD');
$nama_jenjang_lengkap = ($jenjang_sekolah == 'SD') ? 'SEKOLAH DASAR' : 'SEKOLAH MENENGAH PERTAMA';
$nama_jenjang_singkat = ($jenjang_sekolah == 'SD') ? 'SD' : 'SMP';
// --- AKHIR PERUBAHAN ---

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Identitas Sekolah</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; font-size: 14pt; }
        .container { padding: 50px; }
        .header { text-align: center; margin-bottom: 50px; }
        h1 { font-size: 22pt; }
        h2 { font-size: 18pt; font-weight: normal; }
        .info-table { width: 100%; border-collapse: collapse; line-height: 2; }
        .info-table td { padding: 5px 0; }
        .info-table td:nth-child(1) { width: 35%; }
        .info-table td:nth-child(2) { width: 2%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RAPOR</h1> 
            <!-- PERUBAHAN: Judul jenjang dinamis -->
            <h2><?php echo $nama_jenjang_lengkap; ?><br>(<?php echo $nama_jenjang_singkat; ?>)</h2>
        </div>
        <table class="info-table">
            <tr>
                <td>Nama Sekolah</td>
                <td>:</td>
                <td><?php echo strtoupper(htmlspecialchars($sekolah['nama_sekolah'])); ?></td> 
            </tr>
            <tr>
                <td>NPSN</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($sekolah['npsn']); ?></td> 
            </tr>
            <tr>
                <td>NIS/NSS/NDS</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($sekolah['nss']); ?></td>
            </tr>
            <tr>
                <td>Alamat Sekolah</td>
                <td>:</td>
                <!-- PERUBAHAN: Menggunakan 'jalan' bukan 'alamat_legacy' -->
                <td><?php echo htmlspecialchars($sekolah['jalan']); ?></td> 
            </tr>
            <tr>
                <td>Kelurahan / Desa</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($sekolah['desa_kelurahan']); ?></td> 
            </tr>
            <tr>
                <td>Kecamatan</td>
                <td>:</td>
                <td><?php echo 'Kec. ' . htmlspecialchars($sekolah['kecamatan']); ?></td> 
            </tr>
            <tr>
                <td>Kota / Kabupaten</td>
                <td>:</td>
                <td><?php echo 'Kab. ' . htmlspecialchars($sekolah['kabupaten_kota']); ?></td> 
            </tr>
            <tr>
                <td>Provinsi</td>
                <td>:</td>
                <td><?php echo 'Prov. ' . htmlspecialchars($sekolah['provinsi']); ?></td> 
            </tr>
            <tr>
                <td>Website</td>
                <td>:</td>
                <td><?php echo !empty($sekolah['website']) ? htmlspecialchars($sekolah['website']) : '-'; ?></td>
            </tr>
            <tr>
                <td>E-mail</td>
                <td>:</td>
                <td><?php echo !empty($sekolah['email']) ? htmlspecialchars($sekolah['email']) : '-'; ?></td>
            </tr>
        </table>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Identitas Sekolah.pdf", ["Attachment" => 0]);
?>