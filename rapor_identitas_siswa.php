<?php
// rapor_identitas_siswa.php
session_start();
include 'koneksi.php';
require_once 'libs/autoload.php'; 
use Dompdf\Dompdf;
use Dompdf\Options;

// =======================================================================
// 1. DATA FETCHING
// =======================================================================
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) die("Error: ID Siswa tidak valid.");

// Ambil Data Siswa
$q_siswa = mysqli_prepare($koneksi, "SELECT * FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa, "i", $id_siswa);
mysqli_stmt_execute($q_siswa);
$result_siswa = mysqli_stmt_get_result($q_siswa);
if(mysqli_num_rows($result_siswa) == 0) die("Error: Data siswa tidak ditemukan.");
$siswa = mysqli_fetch_assoc($result_siswa);

// Ambil Data Sekolah
$q_sekolah = mysqli_query($koneksi, "SELECT nama_sekolah, nama_kepsek, nip_kepsek, kabupaten_kota FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);

// Ukuran Kertas
$pengaturan = [];
$q_set = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while($r = mysqli_fetch_assoc($q_set)) {
    $pengaturan[$r['nama_pengaturan']] = $r['nilai_pengaturan'];
}
$ukuran_kertas = $pengaturan['rapor_ukuran_kertas'] ?? 'A4';

// Tanggal TTD
$tanggal_ttd = !empty($siswa['diterima_tanggal']) ? $siswa['diterima_tanggal'] : date('Y-m-d');

// =======================================================================
// 2. HELPER FUNCTIONS
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

// =======================================================================
// 3. FOTO SETUP
// =======================================================================
$foto_html = '';
if (!empty($siswa['foto_siswa'])) {
    $path_foto = 'uploads/foto_siswa/' . $siswa['foto_siswa'];
    $foto_base64 = get_img_base64_local($path_foto);
    if ($foto_base64) {
        $foto_html = '<img src="' . $foto_base64 . '" style="width: 100%; height: 100%; object-fit: cover;">';
    }
}
if (empty($foto_html)) {
    $foto_html = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 8pt; color: #aaa; text-align: center; font-family: sans-serif;">FOTO<br>3 x 4</div>';
}

// =======================================================================
// 4. HTML & CSS
// =======================================================================
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Biodata - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
        /* PAGE SETUP */
        @page { margin: 1.5cm 2cm; } /* Margin diperkecil sedikit agar muat 1 halaman */
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10pt; /* Ukuran font ideal untuk dokumen modern */
            color: #333; 
            line-height: 1.3; 
        }

        /* HEADER */
        .doc-header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .doc-header h1 {
            margin: 0;
            font-size: 14pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
        }

        /* TABLE STYLING */
        .bio-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bio-table td {
            padding: 5px 4px;
            vertical-align: top;
            border-bottom: 1px solid #eee; /* Garis halus modern */
        }
        
        /* Kolom */
        .col-no { width: 4%; text-align: center; color: #666; font-weight: bold; }
        .col-label { width: 32%; color: #444; }
        .col-sep { width: 2%; text-align: center; color: #444; }
        .col-value { width: 62%; font-weight: 600; color: #000; }

        /* Kategori Row (Header Bagian) */
        .category-row td {
            background-color: #f4f4f4;
            font-weight: bold;
            color: #333;
            padding-top: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ccc;
        }

        /* Indentasi & Sub-row */
        .indent { padding-left: 15px; }
        .sub-label { color: #555; }
        
        /* SIGNATURE SECTION */
        .signature-container {
            margin-top: 30px;
            width: 100%;
            page-break-inside: avoid;
        }
        .photo-frame {
            width: 3cm;
            height: 4cm;
            border: 1px solid #ccc;
            padding: 3px;
            background: #fff;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.05); /* Sedikit shadow agar elegan */
        }
        
        /* Helper Utilities */
        .text-upper { text-transform: uppercase; }
        .no-border { border-bottom: none !important; }
    </style>
</head>
<body>

    <!-- Header Dokumen -->
    <div class="doc-header">
        <h1>IDENTITAS PESERTA DIDIK</h1>
    </div>

    <!-- Tabel Data -->
    <table class="bio-table">
        <!-- DATA PRIBADI -->
        <tr>
            <td class="col-no">1.</td>
            <td class="col-label">Nama Peserta Didik (Lengkap)</td>
            <td class="col-sep">:</td>
            <td class="col-value text-upper" style="font-size: 11pt;"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
        </tr>
        <tr>
            <td class="col-no">2.</td>
            <td class="col-label">Nomor Induk / NISN</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['nis']) . ' / ' . htmlspecialchars($siswa['nisn']); ?></td>
        </tr>
        <tr>
            <td class="col-no">3.</td>
            <td class="col-label">Tempat, Tanggal Lahir</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['tempat_lahir']) . ', ' . tanggal_indo($siswa['tanggal_lahir']); ?></td>
        </tr>
        <tr>
            <td class="col-no">4.</td>
            <td class="col-label">Jenis Kelamin</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo ($siswa['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan'; ?></td>
        </tr>
        <tr>
            <td class="col-no">5.</td>
            <td class="col-label">Agama</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['agama']); ?></td>
        </tr>
        <tr>
            <td class="col-no">6.</td>
            <td class="col-label">Status dalam Keluarga</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['status_dalam_keluarga']); ?></td>
        </tr>
        <tr>
            <td class="col-no">7.</td>
            <td class="col-label">Anak ke</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['anak_ke']); ?></td>
        </tr>
        <tr>
            <td class="col-no">8.</td>
            <td class="col-label">Alamat Peserta Didik</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['alamat']); ?></td>
        </tr>
        <tr>
            <td class="col-no">9.</td>
            <td class="col-label">Nomor Telepon Rumah/HP</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['telepon_siswa']); ?></td>
        </tr>
        <tr>
            <td class="col-no">10.</td>
            <td class="col-label">Sekolah Asal</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['sekolah_asal'] ?? '-'); ?></td>
        </tr>

        <!-- BAGIAN DITERIMA -->
        <tr class="category-row">
            <td class="col-no">11.</td>
            <td colspan="3">Diterima di Sekolah Ini</td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">a. Di Kelas</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['diterima_di_kelas'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Pada Tanggal</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo tanggal_indo($siswa['diterima_tanggal']); ?></td>
        </tr>

        <!-- ORANG TUA -->
        <tr class="category-row">
            <td class="col-no">12.</td>
            <td colspan="3">Nama Orang Tua</td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">a. Ayah</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['nama_ayah']); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Ibu</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['nama_ibu']); ?></td>
        </tr>
        <tr>
            <td class="col-no">13.</td>
            <td class="col-label">Alamat Orang Tua</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['alamat']); ?></td>
        </tr>
        <tr>
            <td class="col-no">14.</td>
            <td class="col-label">Pekerjaan Orang Tua</td>
            <td class="col-sep">:</td>
            <td class="col-value"></td> <!-- Kosongkan value untuk header -->
        </tr>
        <tr class="no-border">
            <td></td>
            <td class="col-label indent sub-label">a. Ayah</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['pekerjaan_ayah']); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Ibu</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['pekerjaan_ibu']); ?></td>
        </tr>

        <!-- WALI -->
        <tr class="category-row">
            <td class="col-no">15.</td>
            <td colspan="3">Wali Peserta Didik</td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">a. Nama Wali</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['nama_wali'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Alamat Wali</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['alamat_wali'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">c. No. Telepon</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['telepon_wali'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">d. Pekerjaan</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($siswa['pekerjaan_wali'] ?? '-'); ?></td>
        </tr>
    </table>

    <!-- TANDA TANGAN -->
    <table class="signature-container">
        <tr>
            <!-- SPACER KIRI -->
            <td style="width: 30%;"></td>

            <!-- FOTO (Align Right) -->
            <td style="width: 25%; text-align: right; vertical-align: bottom; padding-right: 10px; padding-bottom: 5px;">
                <div class="photo-frame">
                    <?php echo $foto_html; ?>
                </div>
            </td>

            <!-- TANDA TANGAN -->
            <td style="width: 45%; vertical-align: top; text-align: center;">
                <div style="font-size: 10pt;">
                    <?php echo htmlspecialchars($sekolah['kabupaten_kota']); ?>, <?php echo tanggal_indo($tanggal_ttd); ?><br>
                    Kepala Sekolah,<br>
                    <br><br><br><br><br>
                    <b style="text-decoration: underline; font-size: 11pt;"><?php echo htmlspecialchars($sekolah['nama_kepsek']); ?></b><br>
                    NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek']); ?>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Setup Ukuran Kertas
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
$dompdf->stream("Biodata - " . htmlspecialchars($siswa['nama_lengkap']) . ".pdf", ["Attachment" => 0]);
?>
