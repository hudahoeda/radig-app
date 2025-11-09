<?php
session_start();
include 'koneksi.php';
require_once 'libs/autoload.php'; // Sesuaikan path ke autoload Dompdf Anda

use Dompdf\Dompdf;
use Dompdf\Options;

$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : '';

if ($id_siswa == 0 || !in_array($tipe, ['masuk', 'keluar'])) {
    die("Error: Parameter tidak valid.");
}

// 1. Ambil data siswa
$q_siswa = mysqli_prepare($koneksi, "SELECT nama_lengkap, nis FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa, "i", $id_siswa);
mysqli_stmt_execute($q_siswa);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($q_siswa));
if (!$siswa) die("Siswa tidak ditemukan.");

// 2. Ambil data kepala sekolah (termasuk jabatan)
$q_sekolah = mysqli_query($koneksi, "SELECT nama_kepsek, nip_kepsek, jabatan_kepsek FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);

// 3. Ambil data mutasi yang relevan
$data_mutasi = null;
if ($tipe == 'keluar') {
    $q_mutasi = mysqli_prepare($koneksi, "SELECT * FROM mutasi_keluar WHERE id_siswa = ? ORDER BY id_mutasi_keluar DESC LIMIT 1");
} else { // tipe == 'masuk'
    $q_mutasi = mysqli_prepare($koneksi, "SELECT * FROM mutasi_masuk WHERE id_siswa = ? ORDER BY id_mutasi_masuk DESC LIMIT 1");
}
mysqli_stmt_bind_param($q_mutasi, "i", $id_siswa);
mysqli_stmt_execute($q_mutasi);
$data_mutasi = mysqli_fetch_assoc(mysqli_stmt_get_result($q_mutasi));


ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Keterangan Pindah Sekolah - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; }
        .header { text-align: center; font-weight: bold; font-size: 14pt; text-decoration: underline; text-transform: uppercase; }
        .student-name { margin-top: 20px; margin-bottom: 10px; }
        .main-table { width: 100%; border-collapse: collapse; border: 2px solid black; }
        .main-table th, .main-table td { border: 1px solid black; padding: 6px; vertical-align: top; }
        .main-table th { text-align: center; font-weight: bold; }
        .inner-table { width: 100%; border-collapse: collapse; }
        .inner-table td { border: none; padding: 2px 0; }
        .signature-block { line-height: 1.4; }
        .signature-space { height: 60px; }
    </style>
</head>
<body>

    <div class="header">KETERANGAN PINDAH SEKOLAH</div>
    <div class="student-name">Nama Peserta Didik : <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>

    <?php if ($tipe == 'keluar'): ?>
    <table class="main-table">
        <thead>
            <tr>
                <th colspan="4" style="border: none; border-bottom: 2px solid black; font-weight:bold; text-decoration: none;">KELUAR</th>
            </tr>
            <tr>
                <th style="width:15%;">Tanggal</th>
                <th style="width:20%;">Kelas yang ditinggalkan</th>
                <th style="width:25%;">Alasan</th>
                <th style="width:40%;">Tanda Tangan Kepala Sekolah,<br>Stempel Sekolah, dan Tanda Tangan<br>Orang Tua/Wali</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < 3; $i++): ?>
            <tr style="height: 5cm;">
                <td><?php echo ($i == 0 && $data_mutasi) ? date('d F Y', strtotime($data_mutasi['tanggal_keluar'])) : '&nbsp;'; ?></td>
                <td><?php echo ($i == 0 && $data_mutasi) ? htmlspecialchars($data_mutasi['kelas_ditinggalkan']) : ''; ?></td>
                <td><?php echo ($i == 0 && $data_mutasi) ? htmlspecialchars($data_mutasi['alasan']) : ''; ?></td>
                <td>
                    <div class="signature-block" style="line-height: 1.5;">
                        Kepala Sekolah,
                        <div class="signature-space"></div>
                        <b><u><?php echo htmlspecialchars($sekolah['nama_kepsek']); ?></u></b><br>
                        <?php echo htmlspecialchars($sekolah['jabatan_kepsek']); ?><br>
                        NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek']); ?>
                        <div style="margin-top: 1cm;">Orang Tua/Wali,</div>
                        <div class="signature-space"></div>
                        ...........................................
                    </div>
                </td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <?php elseif ($tipe == 'masuk'): ?>
    <table class="main-table">
         <thead>
            <tr>
                <th style="width: 5%;">NO</th>
                <th colspan="2">MASUK</th>
            </tr>
        </thead>
        <tbody>
        <?php for ($i = 0; $i < 3; $i++): ?>
            <tr style="height: 6cm;">
                <td style="text-align: center;"><?php echo $i + 1; ?></td>
                <td style="width: 60%; padding: 10px;">
                    <table class="inner-table">
                        <tr><td style="width:35%;">Nama Peserta Didik</td><td>: <?php echo ($i == 0) ? htmlspecialchars($siswa['nama_lengkap']) : '...................................................'; ?></td></tr>
                        <tr><td>Nomor Induk</td><td>: <?php echo ($i == 0) ? htmlspecialchars($siswa['nis']) : '...................................................'; ?></td></tr>
                        <tr><td>Nama Sekolah</td><td>: <?php echo ($i == 0 && $data_mutasi) ? htmlspecialchars($data_mutasi['sekolah_asal']) : '...................................................'; ?></td></tr>
                        <tr><td colspan="2" style="padding-top:10px;">Masuk di Sekolah ini:</td></tr>
                        <tr><td style="padding-left:15px;">a. Tanggal</td><td>: <?php echo ($i == 0 && $data_mutasi) ? date('d F Y', strtotime($data_mutasi['tanggal_masuk'])) : '...................................................'; ?></td></tr>
                        <tr><td style="padding-left:15px;">b. Di Kelas</td><td>: <?php echo ($i == 0 && $data_mutasi) ? htmlspecialchars($data_mutasi['diterima_di_kelas']) : '...................................................'; ?></td></tr>
                        <tr><td style="padding-left:15px;">c. Tahun Pelajaran</td><td>: <?php echo ($i == 0 && $data_mutasi) ? htmlspecialchars($data_mutasi['tahun_pelajaran']) : '...................................................'; ?></td></tr>
                    </table>
                </td>
                <td style="padding: 10px;">
                    <div class="signature-block">
                        Kepala Sekolah,
                        <div class="signature-space"></div>
                        <b><u><?php echo htmlspecialchars($sekolah['nama_kepsek']); ?></u></b><br>
                        <?php echo htmlspecialchars($sekolah['jabatan_kepsek']); ?><br>
                        NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek']); ?>
                    </div>
                </td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>
    <?php endif; ?>

</body>
</html>
<?php
// Proses rendering PDF
$html = ob_get_clean();
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Keterangan Pindah Sekolah - " . htmlspecialchars($siswa['nama_lengkap']) . ".pdf";
$dompdf->stream($filename, ["Attachment" => 0]);
exit();
?>