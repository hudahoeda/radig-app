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

// 2. FUNGSI BANTUAN
function get_relevant_agama($nama_mapel) {
    if (stripos($nama_mapel, 'islam') !== false) return 'Islam';
    if (stripos($nama_mapel, 'kristen') !== false) return 'Kristen';
    if (stripos($nama_mapel, 'katolik') !== false) return 'Katolik';
    if (stripos($nama_mapel, 'hindu') !== false) return 'Hindu';
    if (stripos($nama_mapel, 'budha') !== false) return 'Budha';
    if (stripos($nama_mapel, 'konghucu') !== false) return 'Konghucu';
    return null;
}

// 3. AMBIL DATA
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

// 4. HITUNG REKAP BELUM SELESAI
$rekap_belum_selesai = [];
$q_kelas = mysqli_query($koneksi, "SELECT k.id_kelas, k.nama_kelas FROM kelas k WHERE k.id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY k.nama_kelas ASC");

while($kelas = mysqli_fetch_assoc($q_kelas)){
    $id_kelas = $kelas['id_kelas'];
    $nama_kelas = $kelas['nama_kelas'];

    $q_mapel_ajar = mysqli_query($koneksi, "
        SELECT gm.id_mapel, m.nama_mapel, g.nama_guru
        FROM guru_mengajar gm
        JOIN mata_pelajaran m ON gm.id_mapel = m.id_mapel
        JOIN guru g ON gm.id_guru = g.id_guru
        WHERE gm.id_kelas = $id_kelas AND gm.id_tahun_ajaran = $id_tahun_ajaran_aktif
        ORDER BY m.urutan ASC
    ");

    while($mapel = mysqli_fetch_assoc($q_mapel_ajar)) {
        $id_mapel = $mapel['id_mapel'];
        $relevant_agama = get_relevant_agama($mapel['nama_mapel']);

        $siswa_where = "(s.id_kelas = $id_kelas OR r.id_kelas = $id_kelas)";
        if ($relevant_agama) {
            if ($relevant_agama == 'Buddha') {
                $siswa_where .= " AND (s.agama = 'Buddha' OR s.agama = 'Budha')";
            } elseif ($relevant_agama == 'Khonghucu') {
                $siswa_where .= " AND (s.agama = 'Khonghucu' OR s.agama = 'Konghucu')";
            } else {
                $siswa_where .= " AND s.agama = '" . mysqli_real_escape_string($koneksi, $relevant_agama) . "'";
            }
        }
        $q_count = mysqli_query($koneksi, "SELECT COUNT(DISTINCT s.id_siswa) as total FROM siswa s LEFT JOIN rapor r ON s.id_siswa = r.id_siswa WHERE $siswa_where");
        $relevant_siswa_count = mysqli_fetch_assoc($q_count)['total'] ?? 0;

        $q_stat = mysqli_query($koneksi, "
            SELECT COUNT(id_penilaian) as jml_asesmen FROM penilaian
            WHERE id_kelas = $id_kelas AND id_mapel = $id_mapel
            AND semester = $semester_aktif AND jenis_penilaian = 'Sumatif'
        ");
        $jml_asesmen = mysqli_fetch_assoc($q_stat)['jml_asesmen'];
        $target_nilai = $jml_asesmen * $relevant_siswa_count;

        $q_nilai_masuk_str = "
            SELECT COUNT(pdn.id_detail_nilai) as jml_masuk
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
            JOIN siswa s ON pdn.id_siswa = s.id_siswa
            WHERE p.id_kelas = $id_kelas AND p.id_mapel = $id_mapel
            AND p.semester = $semester_aktif AND p.jenis_penilaian = 'Sumatif'";

        if ($relevant_agama) {
            if ($relevant_agama == 'Buddha') {
                $q_nilai_masuk_str .= " AND (s.agama = 'Buddha' OR s.agama = 'Budha')";
            } elseif ($relevant_agama == 'Khonghucu') {
                $q_nilai_masuk_str .= " AND (s.agama = 'Khonghucu' OR s.agama = 'Konghucu')";
            } else {
                $q_nilai_masuk_str .= " AND s.agama = '" . mysqli_real_escape_string($koneksi, $relevant_agama) . "'";
            }
        }
        $q_nilai = mysqli_query($koneksi, $q_nilai_masuk_str);
        $jml_masuk = mysqli_fetch_assoc($q_nilai)['jml_masuk'];

        $persen = ($target_nilai > 0) ? round(($jml_masuk / $target_nilai) * 100) : 0;
        $persen = min(100, $persen);

        if ($persen < 100 && $target_nilai > 0) {
            $rekap_belum_selesai[$nama_kelas][] = [
                'mapel' => $mapel['nama_mapel'],
                'guru' => $mapel['nama_guru'],
                'persen' => $persen,
                'kurang' => ($target_nilai - $jml_masuk) . " nilai"
            ];
        } elseif ($jml_asesmen == 0 && $relevant_siswa_count > 0) {
            $rekap_belum_selesai[$nama_kelas][] = [
                'mapel' => $mapel['nama_mapel'],
                'guru' => $mapel['nama_guru'],
                'persen' => 0,
                'kurang' => "0 asesmen"
            ];
        }
    }
}

// 5. RENDER HTML PDF
$tampil_kop_img = ($pengaturan['rapor_tampil_kop'] ?? '0') == '1';
$kop_img_html = '';
if ($tampil_kop_img && !empty($pengaturan['file_kop_sekolah'])) {
    $img = get_img_base64_local($pengaturan['file_kop_sekolah']);
    if($img) $kop_img_html = '<img src="'.$img.'" style="width: 100%; height: auto; max-height: 140px; margin-bottom: 20px;">';
} else {
    $logo_sek_html = '';
    if (!empty($sekolah['logo_sekolah'])) {
        $img = get_img_base64_local($sekolah['logo_sekolah']);
        if($img) $logo_sek_html = '<img src="'.$img.'" style="height: 70px; width: auto;">';
    }
    $kop_teks_1 = $pengaturan['kop_baris_1'] ?? 'PEMERINTAH KOTA';
    $kop_teks_2 = $pengaturan['kop_baris_2'] ?? 'DINAS PENDIDIKAN';
    $kop_teks_3 = $pengaturan['kop_baris_3'] ?? $sekolah['nama_sekolah'];
    $kop_teks_4 = $pengaturan['kop_baris_4'] ?? $sekolah['alamat_sekolah'];

    $kop_img_html = '
    <table width="100%" style="border-bottom: 3px solid #000; padding-bottom: 5px; margin-bottom: 20px;">
        <tr>
            <td width="15%" style="text-align: center;">'.$logo_sek_html.'</td>
            <td width="70%" style="text-align: center; line-height: 1.2;">
                <div style="font-size: 14px;">'.strtoupper($kop_teks_1).'</div>
                <div style="font-size: 14px;">'.strtoupper($kop_teks_2).'</div>
                <div style="font-size: 18px; font-weight: bold;">'.strtoupper($kop_teks_3).'</div>
                <div style="font-size: 11px;">'.$kop_teks_4.'</div>
            </td>
            <td width="15%"></td>
        </tr>
    </table>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 12px; color: #333; }
        .judul { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .subjudul { text-align: center; font-size: 12px; margin-bottom: 25px; }
        .table-data { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table-data th { background-color: #f2f2f2; border: 1px solid #ccc; padding: 8px; text-align: left; font-weight: bold; }
        .table-data td { border: 1px solid #ccc; padding: 8px; }
        .kelas-header { background-color: #009688; color: white; font-weight: bold; padding: 8px; font-size: 14px; border: 1px solid #00796B; margin-top: 15px;}
        .empty-msg { text-align: center; font-style: italic; padding: 20px; font-size: 14px; border: 1px dashed #ccc; }
        .progress-badge { font-weight: bold; color: #d32f2f; }
        .success-badge { font-weight: bold; color: #388e3c; }
    </style>
</head>
<body>
    '.$kop_img_html.'
    <div class="judul">REKAPITULASI PROGRES PENILAIAN BELUM SELESAI</div>
    <div class="subjudul">SEMESTER '.$semester_aktif.' TAHUN AJARAN '.$tahun_ajaran_label.'</div>
';

if (empty($rekap_belum_selesai)) {
    $html .= '<div class="empty-msg"><strong style="color: #388e3c;">Alhamdulillah!</strong> Semua mata pelajaran di seluruh kelas telah 100% menyelesaikan input penilaian rapor.</div>';
} else {
    foreach ($rekap_belum_selesai as $nama_kelas => $list_mapel) {
        $html .= '<div class="kelas-header">KELAS '.$nama_kelas.'</div>';
        $html .= '<table class="table-data">';
        $html .= '<thead><tr><th width="5%">No</th><th width="35%">Mata Pelajaran</th><th width="30%">Nama Guru Pengampu</th><th width="15%">Progres</th><th width="15%">Kekurangan</th></tr></thead>';
        $html .= '<tbody>';
        $no = 1;
        foreach ($list_mapel as $m) {
            $html .= '<tr>';
            $html .= '<td style="text-align: center;">'.$no++.'</td>';
            $html .= '<td>'.$m['mapel'].'</td>';
            $html .= '<td>'.$m['guru'].'</td>';
            $html .= '<td style="text-align: center;"><span class="progress-badge">'.$m['persen'].'%</span></td>';
            $html .= '<td style="text-align: center;">'.$m['kurang'].'</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
}

// Tanggal dan Tanda Tangan Admin
$tanggal_cetak = date('d-m-Y');
$html .= '
<table width="100%" style="margin-top: 40px; border: none;">
    <tr>
        <td width="60%"></td>
        <td width="40%" style="text-align: center; line-height: 1.5;">
            Ditetapkan di: ............................<br>
            Tanggal Cetak: '.$tanggal_cetak.'<br>
            <strong>Administrator Sistem</strong><br>
            <br><br><br><br>
            _________________________
        </td>
    </tr>
</table>
</body>
</html>';

// Inisialisasi DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Rekap_Penilaian_Belum_Selesai_Smt' . $semester_aktif . '_TA' . str_replace('/', '-', $tahun_ajaran_label) . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
exit;
?>
