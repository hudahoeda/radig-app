<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

session_start();
include 'koneksi.php';
require_once 'libs/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. VALIDASI AKSES
if ($_SESSION['role'] != 'admin') {
    die("Akses ditolak. Halaman khusus admin.");
}

// 2. AMBIL DATA PENGATURAN
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran_aktif = $ta_aktif['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_label = $ta_aktif['tahun_ajaran'] ?? '-';

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Pengaturan Sekolah untuk KOP
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah LIMIT 1");
$sekolah = mysqli_fetch_assoc($q_sekolah) ?? [];
$q_pengaturan = mysqli_query($koneksi, "SELECT * FROM pengaturan");
$pengaturan = [];
while ($row_p = mysqli_fetch_assoc($q_pengaturan)) {
    $pengaturan[$row_p['nama_pengaturan']] = $row_p['nilai_pengaturan'];
}

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

// 3. AMBIL DATA PROGRESS EKSKUL
$q_progres_ekskul = mysqli_query($koneksi, "
    SELECT
        e.id_ekskul,
        e.nama_ekskul,
        g.nama_guru,
        (SELECT COUNT(id_peserta_ekskul) FROM ekskul_peserta WHERE id_ekskul = e.id_ekskul) AS jml_siswa,
        (SELECT COUNT(id_tujuan_ekskul) FROM ekskul_tujuan WHERE id_ekskul = e.id_ekskul AND semester = $semester_aktif) AS jml_tujuan,
        (
            SELECT COUNT(p.id_penilaian_ekskul)
            FROM ekskul_penilaian p
            JOIN ekskul_peserta ep ON p.id_peserta_ekskul = ep.id_peserta_ekskul
            JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul
            WHERE ep.id_ekskul = e.id_ekskul AND t.semester = $semester_aktif
        ) AS realisasi_nilai
    FROM ekstrakurikuler e
    LEFT JOIN guru g ON e.id_pembina = g.id_guru
    WHERE e.id_tahun_ajaran = $id_tahun_ajaran_aktif
    ORDER BY e.nama_ekskul ASC
");

// 4. RENDER HTML PDF
$tampil_kop_img = ($pengaturan['rapor_tampil_kop'] ?? '0') == '1';
$kop_img_html = '';
if ($tampil_kop_img && !empty($pengaturan['file_kop_sekolah'])) {
    $img = get_img_base64_local($pengaturan['file_kop_sekolah']);
    if($img) $kop_img_html = '<img src="'.$img.'" style="width: 100%; height: auto; max-height: 140px; margin-bottom: 20px;">';
} else {
    $logo_sek_html = '';
    if (!empty($sekolah['logo_sekolah'])) {
        $img_sek = get_img_base64_local($sekolah['logo_sekolah']);
        if($img_sek) $logo_sek_html = '<img src="'.$img_sek.'" style="width: 70px; height: auto;">';
    }
    $kop_img_html = '
    <table width="100%" style="border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 20px;">
        <tr>
            <td width="15%" align="center">'.$logo_sek_html.'</td>
            <td width="85%" align="center">
                <h3 style="margin: 0; font-size: 16px;">PEMERINTAH KABUPATEN/KOTA</h3>
                <h2 style="margin: 0; font-size: 20px;">'.strtoupper($sekolah['nama_sekolah'] ?? 'SEKOLAH').'</h2>
                <p style="margin: 5px 0 0 0; font-size: 12px;">'.$sekolah['alamat_sekolah'].'</p>
            </td>
        </tr>
    </table>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 12px; color: #333; }
        h1, h2, h3, h4, p { margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-4 { margin-top: 20px; }
        .mb-2 { margin-bottom: 10px; }
        .fw-bold { font-weight: bold; }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #666;
            padding: 8px 5px;
            vertical-align: middle;
        }
        table.data-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
        }
        .bg-success { background-color: #28a745; }
        .bg-danger { background-color: #dc3545; }
        .bg-warning { background-color: #ffc107; color: #000; }
        .bg-secondary { background-color: #6c757d; }
    </style>
</head>
<body>
    '.$kop_img_html.'

    <div class="text-center mb-2">
        <h3 class="fw-bold text-uppercase">REKAP PROGRES PENILAIAN EKSTRAKURIKULER</h3>
        <p>Tahun Ajaran: '.$tahun_ajaran_label.' &nbsp;|&nbsp; Semester: '.$semester_aktif.'</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="30%">Nama Ekstrakurikuler</th>
                <th width="25%">Pembina</th>
                <th width="15%">Target Input</th>
                <th width="10%">Persen</th>
                <th width="15%">Status</th>
            </tr>
        </thead>
        <tbody>';

if (mysqli_num_rows($q_progres_ekskul) == 0) {
    $html .= '<tr><td colspan="6" class="text-center">Belum ada data ekstrakurikuler.</td></tr>';
} else {
    $no = 1;
    while ($row = mysqli_fetch_assoc($q_progres_ekskul)) {
        $target = $row['jml_siswa'] * $row['jml_tujuan'];
        $realisasi = $row['realisasi_nilai'];

        if ($row['jml_siswa'] == 0 || $row['jml_tujuan'] == 0) {
            $persen = 0;
            $teks_status = 'Belum Setup';
            $badge = 'bg-secondary';
        } else {
            $persen = ($target > 0) ? round(($realisasi / $target) * 100) : 0;
            $persen = min(100, $persen);
            $teks_status = ($persen == 100) ? 'TUNTAS' : 'BELUM';

            if ($persen == 100) $badge = 'bg-success';
            elseif ($persen >= 50) $badge = 'bg-warning';
            else $badge = 'bg-danger';
        }

        $html .= '
            <tr>
                <td class="text-center">'.$no++.'</td>
                <td class="fw-bold">'.htmlspecialchars($row['nama_ekskul']).'</td>
                <td>'.htmlspecialchars($row['nama_guru'] ?? '-').'</td>
                <td class="text-center">'.$realisasi.' / '.$target.'</td>
                <td class="text-center fw-bold">'.$persen.'%</td>
                <td class="text-center">
                    <span class="status-badge '.$badge.'">'.$teks_status.'</span>
                </td>
            </tr>';
    }
}

$html .= '
        </tbody>
    </table>

    <table width="100%" style="margin-top: 40px;">
        <tr>
            <td width="60%"></td>
            <td width="40%" align="center">
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <br><br><br><br>
                <p class="fw-bold text-uppercase">'.htmlspecialchars($sekolah['nama_kepsek'] ?? '____________________').'</p>
                <p>NIP. '.htmlspecialchars($sekolah['nip_kepsek'] ?? '_________________').'</p>
            </td>
        </tr>
    </table>

</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Rekap_Progres_Ekskul_Smt{$semester_aktif}_{$tahun_ajaran_label}.pdf", array("Attachment" => false));
exit();
?>
