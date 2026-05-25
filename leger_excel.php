<?php
session_start();
include 'koneksi.php';

// =================================================================
// 1. VALIDASI AKSES
// =================================================================
if (!isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas == 0) {
    die("Kelas tidak valid.");
}

if (!in_array($_SESSION['role'], ['admin', 'guru'])) {
    die("Akses ditolak.");
}

if ($_SESSION['role'] == 'guru') {
    $id_guru_login = (int)$_SESSION['id_guru'];
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_kelas = ?");
    mysqli_stmt_bind_param($stmt_cek, "ii", $id_guru_login, $id_kelas);
    mysqli_stmt_execute($stmt_cek);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt_cek)) == 0) {
        die("Akses ditolak. Anda bukan wali kelas untuk kelas ini.");
    }
}

// =================================================================
// 2. PENGAMBILAN DATA KELAS & SISWA
// =================================================================
$q_kelas = mysqli_query($koneksi, "SELECT k.nama_kelas, ta.id_tahun_ajaran, ta.tahun_ajaran FROM kelas k JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran WHERE k.id_kelas=$id_kelas");
$data_kelas = mysqli_fetch_assoc($q_kelas);
$nama_kelas = $data_kelas['nama_kelas'] ?? 'N/A';
$tahun_ajaran = $data_kelas['tahun_ajaran'] ?? 'N/A';
$id_tahun_ajaran_aktif = $data_kelas['id_tahun_ajaran'] ?? 0;

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

$result_siswa = mysqli_query($koneksi, "SELECT id_siswa, nis, nama_lengkap FROM siswa WHERE id_kelas=$id_kelas AND status_siswa='Aktif' ORDER BY nama_lengkap ASC");
$daftar_siswa = [];
while ($row_s = mysqli_fetch_assoc($result_siswa)) {
    $daftar_siswa[] = $row_s;
}

// =================================================================
// 3. LOGIKA MAPEL
// =================================================================
$result_mapel = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel, kode_mapel FROM mata_pelajaran ORDER BY urutan ASC");
$mapel_db = [];
while ($row_m = mysqli_fetch_assoc($result_mapel)) {
    $mapel_db[] = $row_m;
}

$header_mapel = []; 
$id_agama = [];     
$id_sbdp = [];      
$agama_added = false;
$sbdp_added = false;

$agama_names = ['Pendidikan Agama Islam dan Budi Pekerti', 'Pendidikan Agama Kristen dan Budi Pekerti', 'Pendidikan Agama Katolik dan Budi Pekerti', 'Pendidikan Agama Hindu dan Budi Pekerti', 'Pendidikan Agama Budha dan Budi Pekerti', 'Pendidikan Agama Khonghucu dan Budi Pekerti'];
$sbdp_names = ['Seni Rupa', 'Seni Musik', 'Seni Tari', 'Seni Teater', 'Prakarya'];

foreach ($mapel_db as $m) {
    $id_m = (string)$m['id_mapel'];
    if (in_array($m['nama_mapel'], $agama_names)) {
        $id_agama[] = $id_m;
        if (!$agama_added) { $header_mapel[] = ['id' => 'PABD', 'kode' => 'PABD']; $agama_added = true; }
    } elseif (in_array($m['nama_mapel'], $sbdp_names)) {
        $id_sbdp[] = $id_m;
        if (!$sbdp_added) { $header_mapel[] = ['id' => 'SBdP', 'kode' => 'SBdP']; $sbdp_added = true; }
    } else {
        $header_mapel[] = ['id' => $id_m, 'kode' => $m['kode_mapel']];
    }
}

// =================================================================
// 4. PENGAMBILAN NILAI (DENGAN SMART FALLBACK)
// =================================================================
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm'");
$kkm_db = mysqli_fetch_assoc($q_kkm);
$kkm = $kkm_db ? (int)$kkm_db['nilai_pengaturan'] : 75;

// TAHAP A: AMBIL NILAI DINAMIS DARI BANK NILAI (Untuk jaga-jaga jika Rapor belum digenerate)
$q_sumatif = "
    SELECT pdn.id_siswa, p.id_mapel, 
           SUM(pdn.nilai * p.bobot_penilaian) / NULLIF(SUM(p.bobot_penilaian), 0) as rata_sumatif
    FROM penilaian_detail_nilai pdn
    JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
    WHERE p.id_kelas = $id_kelas AND p.semester = $semester_aktif AND p.jenis_penilaian = 'Sumatif'
    GROUP BY pdn.id_siswa, p.id_mapel
";
$res_sum = mysqli_query($koneksi, $q_sumatif);
$bank_nilai = [];
if ($res_sum) {
    while($rs = mysqli_fetch_assoc($res_sum)) {
        if ($rs['rata_sumatif'] !== null) {
            $bank_nilai[$rs['id_siswa']][$rs['id_mapel']] = round($rs['rata_sumatif']);
        }
    }
}

// TAHAP B: AMBIL NILAI TERSIMPAN DARI TABEL RAPOR
$q_nilai = "SELECT rda.nilai_akhir, rda.nilai_katrol, r.id_siswa, rda.id_mapel 
            FROM rapor_detail_akademik rda 
            JOIN rapor r ON rda.id_rapor = r.id_rapor 
            WHERE r.id_kelas = $id_kelas 
            AND r.semester = $semester_aktif
            AND r.id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif')";
$result_nilai = mysqli_query($koneksi, $q_nilai);
$rapor_db = [];
while ($row = mysqli_fetch_assoc($result_nilai)) {
    $rapor_db[$row['id_siswa']][$row['id_mapel']] = [
        'akhir' => isset($row['nilai_akhir']) ? (float)$row['nilai_akhir'] : 0,
        'katrol' => isset($row['nilai_katrol']) ? (float)$row['nilai_katrol'] : 0
    ];
}

// TAHAP C: GABUNGKAN DATA (Prioritas: Katrol > Rapor > Bank Nilai)
$data_nilai = [];
foreach ($daftar_siswa as $siswa) {
    $sid = $siswa['id_siswa'];
    foreach ($mapel_db as $m) {
        $mid = $m['id_mapel'];
        
        $key_mapel = $mid;
        if (in_array($mid, $id_agama)) $key_mapel = 'PABD';
        if (in_array($mid, $id_sbdp)) $key_mapel = 'SBdP';

        $asli = 0;
        $katrol = 0;

        // Cek apakah sudah ada di Rapor DB
        if (isset($rapor_db[$sid][$mid])) {
            $katrol = $rapor_db[$sid][$mid]['katrol'];
            $asli = $rapor_db[$sid][$mid]['akhir'];
        }

        // SMART FALLBACK: Jika Asli masih 0 (belum digenerate), tembak langsung dari hitungan Bank Nilai!
        if ($asli == 0 && isset($bank_nilai[$sid][$mid])) {
            $asli = $bank_nilai[$sid][$mid];
        }

        // Jika keduanya kosong, abaikan
        if ($asli == 0 && $katrol == 0) continue;

        $final = ($katrol > $asli) ? $katrol : $asli;
        $is_katrol = ($katrol > $asli);

        // Masukkan ke array final
        if ($key_mapel == 'SBdP') {
            $data_nilai[$sid][$key_mapel]['asli'][] = $asli;
            $data_nilai[$sid][$key_mapel]['katrol'][] = $katrol;
            $data_nilai[$sid][$key_mapel]['final'][] = $final;
        } else {
            $data_nilai[$sid][$key_mapel] = [
                'asli' => $asli,
                'katrol' => $katrol,
                'final' => $final,
                'is_katrol' => $is_katrol
            ];
        }
    }
}

// REKAP & RANKING
$rekap_siswa = []; 
foreach ($daftar_siswa as $k => $siswa) {
    $sid = $siswa['id_siswa'];
    $total_nilai = 0;
    $jumlah_mapel = 0;

    // Rata-rata SBdP
    if (isset($data_nilai[$sid]['SBdP']['final']) && is_array($data_nilai[$sid]['SBdP']['final'])) {
        $arr_asli = array_filter($data_nilai[$sid]['SBdP']['asli'], function($v) { return $v > 0; });
        $arr_katrol = array_filter($data_nilai[$sid]['SBdP']['katrol'], function($v) { return $v > 0; });
        $arr_final = array_filter($data_nilai[$sid]['SBdP']['final'], function($v) { return $v > 0; });
        
        $avg_asli = (count($arr_asli) > 0) ? round(array_sum($arr_asli) / count($arr_asli)) : 0;
        $avg_katrol = (count($arr_katrol) > 0) ? round(array_sum($arr_katrol) / count($arr_katrol)) : 0;
        $avg_final = (count($arr_final) > 0) ? round(array_sum($arr_final) / count($arr_final)) : 0;
        
        $is_katrol_sbdp = ($avg_final > $avg_asli);

        $data_nilai[$sid]['SBdP'] = [
            'asli' => $avg_asli,
            'katrol' => $avg_katrol,
            'final' => $avg_final,
            'is_katrol' => $is_katrol_sbdp
        ];
    } else {
        $data_nilai[$sid]['SBdP'] = ['asli' => 0, 'katrol' => 0, 'final' => 0, 'is_katrol' => false];
    }

    // Hitung Total dari Nilai Final
    foreach ($header_mapel as $hm) {
        $data = $data_nilai[$sid][$hm['id']] ?? ['final' => 0];
        $val = $data['final'];
        if ($val > 0) {
            $total_nilai += $val;
            $jumlah_mapel++;
        }
    }

    $rata_rata = ($jumlah_mapel > 0) ? round($total_nilai / $jumlah_mapel, 2) : 0;

    $daftar_siswa[$k]['total'] = $total_nilai;
    $daftar_siswa[$k]['rata'] = $rata_rata;
    $rekap_siswa[$sid] = $total_nilai;
}

arsort($rekap_siswa);
$rank = 1;
$rank_map = [];
foreach ($rekap_siswa as $sid => $total) {
    $rank_map[$sid] = $rank++;
}

// =================================================================
// 5. OUTPUT EXCEL
// =================================================================
$filename = "Leger_Smt" . $semester_aktif . "_Nilai_" . preg_replace('/[^A-Za-z0-9]/', '_', $nama_kelas) . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    body { font-family: Arial, sans-serif; font-size: 10pt; }
    .table-main { border-collapse: collapse; width: 100%; border: 1px solid #000; }
    .table-main th, .table-main td { border: 1px solid #000; padding: 4px; vertical-align: middle; }
    .text-center { text-align: center; }
    .text-left { text-align: left; }
</style>
</head>
<body>

    <table border="0" width="100%">
        <tr>
            <td colspan="<?php echo (count($header_mapel) * 2) + 6; ?>" align="center" style="font-size: 14pt; font-weight: bold;">
                LEGER NILAI AKHIR SISWA
            </td>
        </tr>
        <tr>
            <td colspan="<?php echo (count($header_mapel) * 2) + 6; ?>" align="center" style="font-size: 11pt;">
                TAHUN AJARAN: <?php echo htmlspecialchars($tahun_ajaran); ?> | SEMESTER: <?php echo $semester_aktif; ?> | KELAS: <?php echo strtoupper(htmlspecialchars($nama_kelas)); ?>
            </td>
        </tr>
        <tr><td></td></tr>
    </table>

    <table class="table-main" border="1">
        <thead>
            <tr>
                <th rowspan="2" bgcolor="#D9D9D9" width="40">No</th>
                <th rowspan="2" bgcolor="#D9D9D9" width="100">NIS</th>
                <th rowspan="2" bgcolor="#D9D9D9" width="250">Nama Siswa</th>
                
                <?php foreach ($header_mapel as $hm): ?>
                    <th colspan="2" bgcolor="#D9D9D9" align="center"><b><?php echo $hm['kode']; ?></b></th>
                <?php endforeach; ?>
                
                <th rowspan="2" bgcolor="#D9D9D9" width="60">Jml</th>
                <th rowspan="2" bgcolor="#D9D9D9" width="60">Rata</th>
                <th rowspan="2" bgcolor="#D9D9D9" width="50">Rank</th>
            </tr>
            <tr>
                <?php foreach ($header_mapel as $hm): ?>
                    <th bgcolor="#EFEFEF" width="60" style="font-size: 8pt;">Nilai Akhir</th>
                    <th bgcolor="#EFEFEF" width="60" style="font-size: 8pt;">Nilai Katrol</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($daftar_siswa as $siswa): 
                $sid = $siswa['id_siswa'];
            ?>
            <tr>
                <td align="center"><?php echo $no++; ?></td>
                <td align="left" style='mso-number-format:"\@";'><?php echo $siswa['nis']; ?></td>
                <td align="left"><?php echo $siswa['nama_lengkap']; ?></td>
                
                <?php foreach ($header_mapel as $hm): ?>
                    <?php 
                        $key = (string)$hm['id'];
                        $data = $data_nilai[$sid][$key] ?? ['asli' => 0, 'katrol' => 0];
                        
                        $n_asli = $data['asli'];
                        $n_katrol = $data['katrol'];
                        
                        $td_bg_asli = ""; 
                        $td_style_asli = "color: #333;"; 
                        if ($n_asli > 0 && $n_asli < $kkm) {
                            $td_bg_asli = 'bgcolor="#FFCCCC"'; 
                            $td_style_asli = 'color:#990000; font-weight:bold;';     
                        }
                    ?>
                    
                    <td align="center" <?php echo $td_bg_asli; ?> style="<?php echo $td_style_asli; ?>">
                        <?php echo ($n_asli > 0) ? $n_asli : '-'; ?>
                    </td>
                    
                    <td align="center" style="color: #0000FF; font-weight:bold;">
                        <?php echo ($n_katrol > 0) ? $n_katrol : '-'; ?>
                    </td>
                    
                <?php endforeach; ?>

                <td align="center" bgcolor="#F2F2F2"><b><?php echo $siswa['total']; ?></b></td>
                <td align="center" bgcolor="#F2F2F2"><b><?php echo $siswa['rata']; ?></b></td>
                <td align="center" bgcolor="#FFFFCC"><b><?php echo $rank_map[$sid]; ?></b></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br>
    <table border="0">
        <tr><td colspan="3"><b>Keterangan:</b></td></tr>
        <tr>
            <td align="center" style="border:1px solid #000; color:#0000FF; font-weight:bold;">100</td>
            <td> : Angka pada "Nilai Katrol" (ditampilkan warna biru jika ada inputnya)</td>
        </tr>
        <tr>
            <td align="center" bgcolor="#FFCCCC" style="border:1px solid #000; color:#990000;">60</td>
            <td> : Angka pada "Nilai Akhir" berada di Bawah KKM (<?php echo $kkm; ?>)</td>
        </tr>
    </table>

</body>
</html>