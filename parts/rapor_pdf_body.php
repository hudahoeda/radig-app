<?php
// File ini adalah TEMPLATE dan dipanggil oleh rapor_cetak_massal.php
// Variabel $koneksi dan $id_siswa sudah tersedia dari file induk.
// Variabel global $id_tahun_ajaran_pdf, $semester_aktif_pdf, $tanggal_rapor_pdf, $sekolah_pdf, 
// $base64_kab_pdf, $base64_sekolah_pdf, $show_nilai_column_pdf
// dan [BARU] $theme_color_bg, $theme_color_text, $theme_color_kop juga sudah tersedia.

// --- BAGIAN 1: PENGAMBILAN DATA SPESIFIK SISWA & MAPEL ---

// Ambil data Siswa, Kelas, dan Wali Kelas
$q_siswa_pdf = "SELECT s.nama_lengkap, s.nis, s.nisn, s.agama, s.id_kelas, k.nama_kelas, k.fase, g.nama_guru as nama_walikelas, g.nip as nip_walikelas
                FROM siswa s 
                LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
                WHERE s.id_siswa = ?";
$stmt_siswa_pdf = mysqli_prepare($koneksi, $q_siswa_pdf);
mysqli_stmt_bind_param($stmt_siswa_pdf, "i", $id_siswa);
mysqli_stmt_execute($stmt_siswa_pdf);
$siswa_pdf_result = mysqli_stmt_get_result($stmt_siswa_pdf);

if (mysqli_num_rows($siswa_pdf_result) == 0) {
    echo "<p>Error: Data siswa dengan ID $id_siswa tidak ditemukan.</p>";
    return; // Hentikan include file ini jika siswa tidak ada
}
$siswa_pdf = mysqli_fetch_assoc($siswa_pdf_result);
$id_kelas_siswa = $siswa_pdf['id_kelas'] ?? 0; 

// [MODIFIKASI] $theme_color_pdf diganti dengan variabel dari induk
// $theme_color_kop, $theme_color_bg, $theme_color_text sudah ada

// [MODIFIKASI] $show_nilai_column_pdf sudah di-set dari file induk (true)

// Ambil data Rapor utama (spesifik siswa)
// Variabel $rapor dan $id_rapor sudah di-query di file induk (rapor_cetak_massal.php)

// --- LOGIKA FILTER MAPEL GABUNGAN (KELAS + AGAMA) ---
$mapel_agama_map_pdf = [
'Islam' => 2, 
    'Kristen' => 13,
    'Katolik' => 16, 
    'Hindu' => 14,   
    'Buddha' => 15   
];
$agama_siswa_pdf = $siswa_pdf['agama'] ?? ''; 
$id_mapel_agama_siswa_pdf = $mapel_agama_map_pdf[$agama_siswa_pdf] ?? null;
$semua_id_mapel_agama_string_pdf = implode(',', array_values($mapel_agama_map_pdf));
if (empty($semua_id_mapel_agama_string_pdf)) {
    $semua_id_mapel_agama_string_pdf = '0'; 
}

$query_mapel_string_pdf = "
    SELECT 
        mp.id_mapel, 
        mp.nama_mapel, 
        mp.urutan 
    FROM 
        mata_pelajaran AS mp
    JOIN 
        guru_mengajar AS gm ON mp.id_mapel = gm.id_mapel
    WHERE 
        gm.id_kelas = $id_kelas_siswa 
        AND gm.id_tahun_ajaran = $id_tahun_ajaran_pdf
";
$query_mapel_string_pdf .= " AND (";
if ($id_mapel_agama_siswa_pdf) {
    $query_mapel_string_pdf .= "(mp.id_mapel NOT IN ($semua_id_mapel_agama_string_pdf) OR mp.id_mapel = $id_mapel_agama_siswa_pdf)";
} else {
    $query_mapel_string_pdf .= "mp.id_mapel NOT IN ($semua_id_mapel_agama_string_pdf)";
}
$query_mapel_string_pdf .= ") "; 
$query_mapel_string_pdf .= " 
    GROUP BY mp.id_mapel 
    ORDER BY mp.urutan ASC, mp.nama_mapel ASC
";
$semua_mapel_query_pdf = mysqli_query($koneksi, $query_mapel_string_pdf);
// --- AKHIR LOGIKA FILTER MAPEL ---


$daftar_mapel_rapor_pdf = [];
$nilai_tersimpan_pdf = [];
if ($id_rapor > 0) {
    $detail_akademik_query_pdf = mysqli_query($koneksi, "SELECT id_mapel, nilai_akhir, capaian_kompetensi FROM rapor_detail_akademik WHERE id_rapor = $id_rapor");
    if ($detail_akademik_query_pdf) {
        while ($d_pdf = mysqli_fetch_assoc($detail_akademik_query_pdf)) {
            $nilai_tersimpan_pdf[$d_pdf['id_mapel']] = $d_pdf;
        }
    }
}
if ($semua_mapel_query_pdf) { 
    while ($mapel_pdf = mysqli_fetch_assoc($semua_mapel_query_pdf)) {
        $id_mapel_pdf = $mapel_pdf['id_mapel'];
        if (isset($nilai_tersimpan_pdf[$id_mapel_pdf])) {
            $daftar_mapel_rapor_pdf[] = [
                'nama_mapel' => $mapel_pdf['nama_mapel'], 
                'nilai_akhir' => $nilai_tersimpan_pdf[$id_mapel_pdf]['nilai_akhir'], 
                'capaian_kompetensi' => $nilai_tersimpan_pdf[$id_mapel_pdf]['capaian_kompetensi']
            ];
        } else {
            $daftar_mapel_rapor_pdf[] = [
                'nama_mapel' => $mapel_pdf['nama_mapel'], 
                'nilai_akhir' => '-', 
                'capaian_kompetensi' => '-'
            ];
        }
    }
}

// --- [LOGIKA PENGGABUNGAN SENI & PRAKARYA DIMASUKKAN DI SINI] ---
$seni_mapel_names = ['Seni Musik', 'Seni Rupa', 'Seni Tari', 'Seni Teater']; 
$prakarya_mapel_names = ['Prakarya'];

$seni_data = null;
$prakarya_data = null;
$final_daftar_mapel_pdf = [];
$found_seni_index = -1;
$found_prakarya_index = -1;

foreach ($daftar_mapel_rapor_pdf as $index => $mapel) {
    if (in_array($mapel['nama_mapel'], $seni_mapel_names)) {
        $seni_data = $mapel;
        $found_seni_index = $index;
    } elseif (in_array($mapel['nama_mapel'], $prakarya_mapel_names)) {
        $prakarya_data = $mapel;
        $found_prakarya_index = $index;
    }
}

$combined_data = null;
if ($seni_data && $prakarya_data) {
    $nilai_seni = is_numeric($seni_data['nilai_akhir']) ? (int)$seni_data['nilai_akhir'] : 0;
    $nilai_prakarya = is_numeric($prakarya_data['nilai_akhir']) ? (int)$prakarya_data['nilai_akhir'] : 0;
    $nilai_rata_rata = '-';
    
    if ($nilai_seni > 0 && $nilai_prakarya > 0) {
        $nilai_rata_rata = round(($nilai_seni + $nilai_prakarya) / 2);
    } elseif ($nilai_seni > 0) {
        $nilai_rata_rata = $nilai_seni;
    } elseif ($nilai_prakarya > 0) {
        $nilai_rata_rata = $nilai_prakarya;
    }

    $deskripsi_gabungan = trim($seni_data['capaian_kompetensi']) . "\n" . trim($prakarya_data['capaian_kompetensi']);
    
    $combined_data = [
        'nama_mapel' => 'Seni Budaya dan Prakarya',
        'nilai_akhir' => $nilai_rata_rata,
        'capaian_kompetensi' => trim($deskripsi_gabungan)
    ];

} elseif ($seni_data) {
    $seni_data['nama_mapel'] = 'Seni Budaya dan Prakarya';
    $combined_data = $seni_data;
} elseif ($prakarya_data) {
    $prakarya_data['nama_mapel'] = 'Seni Budaya dan Prakarya';
    $combined_data = $prakarya_data;
}

$mapel_sudah_digabung = false;
foreach ($daftar_mapel_rapor_pdf as $index => $mapel) {
    if ($index == $found_seni_index || $index == $found_prakarya_index) {
        if (!$mapel_sudah_digabung && $combined_data) {
            $final_daftar_mapel_pdf[] = $combined_data;
            $mapel_sudah_digabung = true;
        }
    } else {
        $final_daftar_mapel_pdf[] = $mapel;
    }
}

$daftar_mapel_rapor_pdf = $final_daftar_mapel_pdf;
// --- [AKHIR LOGIKA PENGGABUNGAN] ---


// --- Query Ekstrakurikuler ---
$detail_ekskul_query_pdf = mysqli_prepare($koneksi, "
    SELECT 
        e.nama_ekskul, 
        kh.jumlah_hadir, 
        kh.total_pertemuan,
        (SELECT GROUP_CONCAT(CONCAT(et.deskripsi_tujuan, ': ', pn.nilai) SEPARATOR '; ') 
         FROM ekskul_penilaian pn
         JOIN ekskul_tujuan et ON pn.id_tujuan_ekskul = et.id_tujuan_ekskul
         WHERE 
             pn.id_peserta_ekskul = ep.id_peserta_ekskul 
             AND et.semester = ?) as 'penilaian_deskriptif'
    FROM 
        ekskul_peserta ep
    JOIN 
        ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
    LEFT JOIN 
        ekskul_kehadiran kh ON ep.id_peserta_ekskul = kh.id_peserta_ekskul 
                           AND kh.semester = ?
    WHERE 
        ep.id_siswa = ?
    ORDER BY 
        e.nama_ekskul ASC
");
mysqli_stmt_bind_param($detail_ekskul_query_pdf, "iii", $semester_aktif_pdf, $semester_aktif_pdf, $id_siswa); 
mysqli_stmt_execute($detail_ekskul_query_pdf);
$detail_ekskul_result_pdf = mysqli_stmt_get_result($detail_ekskul_query_pdf);

?>

<!-- 
    BAGIAN HTML TEMPLATE DIMULAI.
    Tidak ada <html>, <head>, <style>, <body>, <footer>, atau watermark di sini.
    Semua itu disediakan oleh rapor_cetak_massal.php
-->

<!-- [MODIFIKASI] KOP HTML BARU (Mengadopsi class dari smp.php) -->
<header>
    <table class="header-table">
        <tr>
            <td class="logo-left">
                <?php 
                    // Variabel $base64_kab_pdf dari file induk
                    if (!empty($base64_kab_pdf)) {
                        echo '<img src="' . $base64_kab_pdf . '" alt="Logo Kabupaten" style="width: 80px;">';
                    }
                ?>
            </td>
            <td class="kop-text">
                <!-- Variabel $sekolah_pdf dari file induk -->
                <h4>PEMERINTAH KABUPATEN <?php echo strtoupper(htmlspecialchars($sekolah_pdf['kabupaten_kota'] ?? '')); ?></h4>
                <p class="dinas-text">DINAS PENDIDIKAN</p>
                <!-- [MODIFIKASI] $theme_color_kop dari induk -->
                <h3 class="school-name" style="color: <?php echo $theme_color_kop; ?>;"><?php echo strtoupper(htmlspecialchars($sekolah_pdf['nama_sekolah'])); ?></h3>
                <p class="school-info">
                    <?php echo htmlspecialchars($sekolah_pdf['jalan'] ?? ''); ?>, Desa/Kel. <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan'] ?? ''); ?>, Kec. <?php echo htmlspecialchars($sekolah_pdf['kecamatan'] ?? ''); ?><br>
                    Telp: <?php echo htmlspecialchars($sekolah_pdf['telepon'] ?? '-'); ?> Email: <?php echo htmlspecialchars($sekolah_pdf['email'] ?? '-'); ?>
                </p>
            </td>
            <td class="logo-right">
                <?php 
                    // Variabel $base64_sekolah_pdf dari file induk
                    if (!empty($base64_sekolah_pdf)):
                        echo '<img src="' . $base64_sekolah_pdf . '" alt="Logo Sekolah" style="width: 80px;">';
                    endif; 
                ?>
            </td>
        </tr>
    </table>
</header>

<!-- [MODIFIKASI] Menambahkan footer individual per siswa -->
<footer>
    <table class="footer-table">
        <tr>
            <td class="nama-sekolah"><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah']); ?></td>
            <td class="nama-siswa"><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? 'Siswa'); ?></td>
            <td class="halaman">Halaman <span class="page-number"></span></td>
            <!-- Catatan: counter(pages) mungkin tidak berfungsi akurat di sini karena ini adalah 'satu' halaman panjang. -->
            <!-- Biarkan 'page-number' saja -->
        </tr>
    </table>
</footer>

<main>
    <table class="info-table">
            <tr>
                <td width="20%">Nama Murid</td><td width="1%">:</td><td width="34%"><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? ''); ?></td>
                <td width="20%">Kelas</td><td width="1G%">:</td><td width="24%"><?php echo htmlspecialchars($siswa_pdf['nama_kelas'] ?? ''); ?></td>
            </tr>
            <tr>
                <td>NISN / NIS</td><td>:</td><td><?php echo htmlspecialchars($siswa_pdf['nisn'] ?? ''); ?> / <?php echo htmlspecialchars($siswa_pdf['nis'] ?? ''); ?></td>
                <td>Fase</td><td>:</td><td><?php echo htmlspecialchars($siswa_pdf['fase'] ?? ''); ?></td>
            </tr>
            <tr>
                <td>Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah']); ?></td>
                <td>Semester</td><td>:</td><td><?php echo $semester_text_pdf; ?></td>
            </tr>
                <tr>
                <td>Alamat Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['jalan'] ?? ''); ?>, Desa/Kel. <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan'] ?? ''); ?></td>
                <td>Tahun Ajaran</td><td>:</td><td><?php echo htmlspecialchars($tahun_ajaran_pdf); ?></td>
            </tr>
    </table>

    <div class="section-title">A. NILAI AKADEMIK</div>
    <table class="content-table">
        <thead>
            <tr>
                <!-- [MODIFIKASI] Menggunakan variabel warna dari induk -->
                <th width="5%" style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">No.</th>
                <th width="<?php echo $show_nilai_column_pdf ? '25%' : '33%'; ?>" style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">Mata Pelajaran</th>
                <?php if ($show_nilai_column_pdf): ?>
                    <th width="8%" style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">Nilai Akhir</th>
                <?php endif; ?>
                <th style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no_pdf = 1;
            foreach ($daftar_mapel_rapor_pdf as $d_pdf):
            ?>
            <tr>
                <td class="text-center"><?php echo $no_pdf++; ?></td>
                <td><?php echo htmlspecialchars($d_pdf['nama_mapel']); ?></td>
                <?php if ($show_nilai_column_pdf): ?>
                    <td class="text-center"><?php echo $d_pdf['nilai_akhir']; ?></td>
                <?php endif; ?>
                <td class="capaian"><?php echo !empty($d_pdf['capaian_kompetensi']) ? nl2br(htmlspecialchars($d_pdf['capaian_kompetensi'])) : '-'; ?></td>
            </tr>
            <?php
            endforeach;
            if (empty($daftar_mapel_rapor_pdf)) {
                $colspan_pdf = $show_nilai_column_pdf ? 4 : 3;
                echo "<tr><td colspan='$colspan_pdf' class='text-center'>Data nilai akademik belum tersedia atau belum ada mapel yang diatur untuk kelas ini.</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
    <!-- ====================================================== -->
    <!-- ### [PERBAIKAN] HAPUS MANUAL PAGE BREAK ### -->
    <!-- ====================================================== -->
    <!-- <div class="page-break"></div> --> 
    <!-- Baris ini dihapus agar konten B, C, D, E mengalir alami -->
    
    <table class="section-header-table">
        <thead>
            <tr>
                <!-- [MODIFIKASI] Menggunakan variabel warna dari induk -->
                <th style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">B. KOKURIKULER</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="capaian"><?php echo !empty($rapor_pdf['deskripsi_kokurikuler']) ? nl2br(htmlspecialchars($rapor_pdf['deskripsi_kokurikuler'])) : '-'; ?></td>
            </tr>
        </tbody>
    </table>
    <!-- ====================================================== -->
    <!-- ### AKHIR PERBAIKAN] ### -->
    <!-- ====================================================== -->

    <div class="section-title">C. EKSTRAKURIKULER</div>
    <table class="content-table">
        <thead>
            <tr>
                <!-- [MODIFIKASI] Menggunakan variabel warna dari induk -->
                <th width="5%" style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">No.</th>
                <th width="35%" style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">Ekstrakurikuler</th>
                <th style="background-color: <?php echo $theme_color_bg; ?>; color: <?php echo $theme_color_text; ?>;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no_ekskul_pdf = 1;
            // [PERBAIKAN] Menggunakan $detail_ekskul_result_pdf, BUKAN $detail_ekskul_query_pdf
            if($detail_ekskul_result_pdf && mysqli_num_rows($detail_ekskul_result_pdf) > 0){
                while($e_pdf = mysqli_fetch_assoc($detail_ekskul_result_pdf)):
                    $keterangan_pdf = "";
                    if (!empty($e_pdf['penilaian_deskriptif'])) {
                        $keterangan_pdf .= "Menunjukkan penguasaan: " . str_replace('; ', ', ', htmlspecialchars($e_pdf['penilaian_deskriptif'])) . ".";
                    } else {
                        $keterangan_pdf .= "Penilaian deskriptif belum diisi.";
                    }
                    
                    if (isset($e_pdf['jumlah_hadir']) && isset($e_pdf['total_pertemuan']) && $e_pdf['total_pertemuan'] > 0) {
                        $persentase_hadir_pdf = round(($e_pdf['jumlah_hadir'] / $e_pdf['total_pertemuan']) * 100);
                        $keterangan_pdf .= " Kehadiran: " . htmlspecialchars($e_pdf['jumlah_hadir']) . "/" . htmlspecialchars($e_pdf['total_pertemuan']) . " ($persentase_hadir_pdf%).";
                    } else if (isset($e_pdf['jumlah_hadir']) && $e_pdf['jumlah_hadir'] !== null) {
                         $keterangan_pdf .= " Kehadiran: " . htmlspecialchars($e_pdf['jumlah_hadir']) . " pertemuan.";
                    } else {
                        $keterangan_pdf .= " Kehadiran belum diisi.";
                    }
            ?>
            <tr>
                <td class="text-center"><?php echo $no_ekskul_pdf++; ?></td>
                <td><?php echo htmlspecialchars($e_pdf['nama_ekskul']); ?></td>
                <td class="capaian"><?php echo $keterangan_pdf; ?></td>
            </tr>
            <?php
                endwhile;
            } else {
                echo "<tr><td colspan='3' class='text-center'>Tidak mengikuti kegiatan ekstrakurikuler.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div style="width: 100%; margin-top: 15px; page-break-inside: avoid;">
        <div style="width: 40%; float: left;">
            <div class="section-title" style="margin-top:0;">D. KETIDAKHADIRAN</div>
            <table class="content-table">
                <tr><td width="70%">Sakit</td><td>: <?php echo $rapor_pdf['sakit'] ?? 0; ?> hari</td></tr>
                <tr><td>Izin</td><td>: <?php echo $rapor_pdf['izin'] ?? 0; ?> hari</td></tr>
                <tr><td>Tanpa Keterangan</td><td>: <?php echo $rapor_pdf['tanpa_keterangan'] ?? 0; ?> hari</td></tr>
            </table>
        </div>
        <div style="width: 55%; float: right;">
            <div class="section-title" style="margin-top:0;">E. CATATAN WALI KELAS</div>
            <table class="content-table" style="height: 80px;">
                <tr><td class="capaian"><?php echo !empty($rapor_pdf['catatan_wali_kelas']) ? nl2br(htmlspecialchars($rapor_pdf['catatan_wali_kelas'])) : '-'; ?></td></tr>
            </table>
        </div>
    </div>
    
    <div style="clear:both;"></div>
    
    <div class="section-title">Tanggapan Orang Tua/Wali Murid</div>
    <table class="content-table"><tr><td style="height: 80px;"></td></tr></table>

    <table class="signature-table" style="width: 100%; margin-top: 50px;">
        <tr>
            <td style="width: 33.33%; text-align: center;">
                Orang Tua/Wali Murid
                <div class="signature-space"></div>
                ( ................................. )
            </td>
            <td style="width: 33.33%; text-align: center;">
                Mengetahui,<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <strong><u><?php echo htmlspecialchars($sekolah_pdf['nama_kepsek']); ?></u></strong><br>
                <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah_pdf['jabatan_kepsek']); ?></span><br>
                NIP. <?php echo htmlspecialchars($sekolah_pdf['nip_kepsek']); ?>
            </td>
            <td style="width: 33.33%; text-align: center;">
                <?php echo htmlspecialchars($sekolah_pdf['kabupaten_kota']); ?>, <?php echo $tanggal_rapor_pdf; ?><br>
                Wali Kelas
                <div class="signature-space"></div>
                <strong><u><?php echo htmlspecialchars($siswa_pdf['nama_walikelas'] ?? ''); ?></u></strong><br>
                NIP. <?php echo htmlspecialchars($siswa_pdf['nip_walikelas'] ?? ''); ?>
            </td>
        </tr>
    </table>
</main>