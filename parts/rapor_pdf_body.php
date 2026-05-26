<?php
// =======================================================================
// TEMPLATE KONTEN RAPOR (INCLUDED FILE)
// Dipanggil oleh: rapor_cetak_massal.php di dalam loop
// Variabel tersedia: $koneksi, $id_siswa, $id_tahun_ajaran_pdf, $semester_aktif_pdf
// =======================================================================

// [PENTING] DEFINISI FUNGSI HITUNG DESKRIPSI (Sama persis dengan rapor_pdf.php)
// Kita bungkus dengan !function_exists agar tidak error saat looping massal
if (!function_exists('hitungDeskripsiOtomatis')) {
    function hitungDeskripsiOtomatis($koneksi, $id_siswa, $id_kelas, $id_mapel, $kkm, $semester_aktif) {
        // 1. Ambil Nilai Sumatif Lingkup Materi (TP)
        $stmt_sumatif_tp = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian, GROUP_CONCAT(tp.deskripsi_tp SEPARATOR '|||') as deskripsi_tps
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian 
            JOIN penilaian_tp ptp ON p.id_penilaian = ptp.id_penilaian
            JOIN tujuan_pembelajaran tp ON ptp.id_tp = tp.id_tp 
            WHERE p.subjenis_penilaian = 'Sumatif TP' 
            AND pdn.id_siswa = ? AND p.id_mapel = ? AND p.id_kelas = ? AND p.semester = ? 
            GROUP BY p.id_penilaian, pdn.nilai, p.bobot_penilaian
        ");
        
        // 2. Ambil Nilai Sumatif Akhir
        $stmt_sumatif_akhir = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian 
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian 
            WHERE p.subjenis_penilaian IN ('Sumatif Akhir Semester', 'Sumatif Akhir Tahun')
            AND p.jenis_penilaian = 'Sumatif' 
            AND pdn.id_siswa = ? AND p.id_mapel = ? AND p.id_kelas = ? AND p.semester = ?
        ");
        
        $skor_per_tp = []; 
        $total_nilai_x_bobot = 0; 
        $total_bobot = 0;

        // Eksekusi Query Sumatif TP
        mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_tp);
        $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
        
        while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
            $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
            foreach($tps_individu as $desc_tp) {
                if (!isset($skor_per_tp[$desc_tp])) { $skor_per_tp[$desc_tp] = []; }
                $skor_per_tp[$desc_tp][] = $d_nilai['nilai'];
            }
            $total_nilai_x_bobot += $d_nilai['nilai'] * $d_nilai['bobot_penilaian'];
            $total_bobot += $d_nilai['bobot_penilaian'];
        }

        // Eksekusi Query Sumatif Akhir
        mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_akhir);
        $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
        
        while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
            $total_nilai_x_bobot += $d_nilai_akhir['nilai'] * $d_nilai_akhir['bobot_penilaian'];
            $total_bobot += $d_nilai_akhir['bobot_penilaian'];
        }

        // Hitung Nilai Akhir
        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;
        
        // LOGIKA DESKRIPSI (Rules: Top 2 & Bottom 2)
        $deskripsi_final = '';
        
        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
            $rekap_tp = [];
            $kata_hapus = ['Peserta didik dapat', 'Peserta didik mampu', 'peserta didik mampu', 'siswa dapat', 'siswa mampu', 'mampu', 'memahami', 'menguasai', 'menjelaskan', 'menganalisis', 'mengidentifikasi', 'menentukan', 'menunjukkan'];

            foreach ($skor_per_tp as $deskripsi => $skor_array) {
                $avg = array_sum($skor_array) / count($skor_array);
                
                $desc_clean = trim(str_ireplace($kata_hapus, '', $deskripsi));
                $desc_clean = preg_replace('/\s+/', ' ', $desc_clean);
                $desc_clean = lcfirst($desc_clean);

                if (!isset($rekap_tp[$desc_clean]) || $rekap_tp[$desc_clean]['avg'] < $avg) {
                     $rekap_tp[$desc_clean] = ['avg' => $avg];
                }
            }

            $tp_lulus = [];
            $tp_remedi = [];

            foreach ($rekap_tp as $clean_desc => $data) {
                if ($data['avg'] >= $kkm) {
                    $tp_lulus[$clean_desc] = $data['avg'];
                } else {
                    $tp_remedi[$clean_desc] = $data['avg'];
                }
            }

            arsort($tp_lulus); 
            asort($tp_remedi); 

            $top_tp = array_slice(array_keys($tp_lulus), 0, 2);
            $bottom_tp = array_slice(array_keys($tp_remedi), 0, 2); 
            
            $deskripsi_draf = "";
            
            if (!empty($top_tp)) {
                $deskripsi_draf .= "Menunjukkan penguasaan yang sangat baik dalam " . implode(', ', $top_tp) . ". ";
            } elseif ($nilai_akhir >= $kkm && empty($top_tp)) {
                $deskripsi_draf .= "Secara keseluruhan, capaian kompetensi sudah tuntas. ";
            }
            
            if (!empty($bottom_tp)) {
                $deskripsi_draf .= "Namun, perlu penguatan lebih lanjut dalam " . implode(', ', $bottom_tp) . ".";
            } else {
                $deskripsi_draf .= "Semua tujuan pembelajaran telah tercapai dengan baik.";
            }

            $deskripsi_final = ucfirst(trim($deskripsi_draf));
            
        } elseif ($nilai_akhir !== null && $nilai_akhir >= $kkm) {
            $deskripsi_final = 'Capaian kompetensi secara umum sudah menunjukkan ketuntasan yang baik.';
        } elseif ($nilai_akhir !== null && $nilai_akhir < $kkm) {
            $deskripsi_final = 'Perlu ditingkatkan lagi pada beberapa tujuan pembelajaran untuk mencapai ketuntasan minimum.';
        } else {
            $deskripsi_final = '-';
        }

        return $deskripsi_final;
    }
}

// 1. AMBIL DATA SISWA SPESIFIK
$q_siswa_pdf = "SELECT s.nama_lengkap, s.nis, s.nisn, s.agama, s.id_kelas, k.nama_kelas, k.fase, g.nama_guru as nama_walikelas, g.nip as nip_walikelas
                FROM siswa s 
                LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
                WHERE s.id_siswa = ?";
$stmt_siswa_pdf = mysqli_prepare($koneksi, $q_siswa_pdf);
mysqli_stmt_bind_param($stmt_siswa_pdf, "i", $id_siswa);
mysqli_stmt_execute($stmt_siswa_pdf);
$result_siswa = mysqli_stmt_get_result($stmt_siswa_pdf);

if (mysqli_num_rows($result_siswa) == 0) return; // Skip jika data rusak
$siswa_pdf = mysqli_fetch_assoc($result_siswa);
$id_kelas_siswa = $siswa_pdf['id_kelas'];

// 2. AMBIL DATA RAPOR (SAKIT, IZIN, CATATAN)
$q_rapor = mysqli_prepare($koneksi, "SELECT * FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ? LIMIT 1");
mysqli_stmt_bind_param($q_rapor, "iii", $id_siswa, $semester_aktif_pdf, $id_tahun_ajaran_pdf);
mysqli_stmt_execute($q_rapor);
$rapor_pdf = mysqli_fetch_assoc(mysqli_stmt_get_result($q_rapor));
$id_rapor = $rapor_pdf['id_rapor'] ?? 0;
$show_nilai_column_pdf = true;

// 3. LOGIKA MAPEL (FILTER AGAMA & URUTAN - DINAMIS)
$q_mapel_agama_all = mysqli_query($koneksi, "
    SELECT id_mapel, nama_mapel 
    FROM mata_pelajaran 
    WHERE (kelompok LIKE '%Agama%' OR nama_mapel LIKE '%Agama%')
");

$ids_semua_mapel_agama = [];
$id_mapel_agama_siswa = 0;
$agama_siswa_clean = strtolower(trim($siswa_pdf['agama'] ?? ''));

while ($row_agama = mysqli_fetch_assoc($q_mapel_agama_all)) {
    $ids_semua_mapel_agama[] = $row_agama['id_mapel'];
    if (!empty($agama_siswa_clean) && strpos(strtolower($row_agama['nama_mapel']), $agama_siswa_clean) !== false) {
        $id_mapel_agama_siswa = $row_agama['id_mapel'];
    }
}

$semua_agama_ids_str = implode(',', $ids_semua_mapel_agama);
if (empty($semua_agama_ids_str)) { $semua_agama_ids_str = '0'; }

$query_mapel_str = "SELECT mp.id_mapel, mp.nama_mapel, mp.urutan 
                    FROM mata_pelajaran mp 
                    JOIN guru_mengajar gm ON mp.id_mapel = gm.id_mapel
                    WHERE gm.id_kelas = '$id_kelas_siswa' AND gm.id_tahun_ajaran = '$id_tahun_ajaran_pdf'";

if ($id_mapel_agama_siswa > 0) {
    $query_mapel_str .= " AND (mp.id_mapel NOT IN ($semua_agama_ids_str) OR mp.id_mapel = $id_mapel_agama_siswa)";
} else {
    $query_mapel_str .= " AND mp.id_mapel NOT IN ($semua_agama_ids_str)";
}

$query_mapel_str .= " GROUP BY mp.id_mapel ORDER BY mp.urutan ASC, mp.nama_mapel ASC";
$q_mapel = mysqli_query($koneksi, $query_mapel_str);

// 4. PREPARE NILAI (DB & LIVE)
$nilai_tersimpan = [];
if ($id_rapor > 0) {
    $q_detail = mysqli_query($koneksi, "SELECT id_mapel, nilai_akhir, nilai_katrol, capaian_kompetensi FROM rapor_detail_akademik WHERE id_rapor = $id_rapor");
    while ($d = mysqli_fetch_assoc($q_detail)) {
        $val = ($d['nilai_katrol'] > 0) ? $d['nilai_katrol'] : $d['nilai_akhir'];
        // Kita simpan nilai DB, tapi deskripsinya nanti kita timpa dengan Live Calculation jika perlu
        $nilai_tersimpan[$d['id_mapel']] = ['nilai' => $val, 'deskripsi' => $d['capaian_kompetensi']];
    }
}

$daftar_mapel_rapor = [];
while ($mp = mysqli_fetch_assoc($q_mapel)) {
    $id_m = $mp['id_mapel'];
    
    // HITUNG LIVE (Selalu hitung deskripsi ringkas sesuai aturan Top 2 / Bottom 2)
    $deskripsi_live = hitungDeskripsiOtomatis($koneksi, $id_siswa, $id_kelas_siswa, $id_m, $kkm, $semester_aktif_pdf);
    
    // Nilai akhir tetap ambil dari DB jika sudah ada (karena mungkin ada nilai katrol)
    $nilai_final = $nilai_tersimpan[$id_m]['nilai'] ?? '-';
    
    // [MODIFIKASI PENTING] 
    // Gunakan $deskripsi_live agar hasilnya RINGKAS dan SESUAI POLA (Top 2 & Bottom 2).
    // Abaikan deskripsi dari database yang mungkin format lama/panjang.
    $deskripsi_final = $deskripsi_live;
    
    $daftar_mapel_rapor[] = [
        'nama_mapel' => $mp['nama_mapel'],
        'nilai_akhir' => $nilai_final,
        'capaian_kompetensi' => $deskripsi_final
    ];
}

// 5. MERGE SENI & PRAKARYA
$seni_data = null; $prakarya_data = null;
$seni_idx = -1; $prakarya_idx = -1;

foreach ($daftar_mapel_rapor as $idx => $m) {
    if (in_array($m['nama_mapel'], ['Seni Musik', 'Seni Rupa', 'Seni Tari', 'Seni Teater'])) { $seni_data = $m; $seni_idx = $idx; }
    if ($m['nama_mapel'] == 'Prakarya') { $prakarya_data = $m; $prakarya_idx = $idx; }
}

if ($seni_data && $prakarya_data) {
    // Jika siswa punya keduanya
    $n1 = (int)$seni_data['nilai_akhir'];
    $n2 = (int)$prakarya_data['nilai_akhir'];
    $avg = ($n1 > 0 && $n2 > 0) ? round(($n1 + $n2) / 2) : max($n1, $n2);
    
    $merged_desc = trim($seni_data['capaian_kompetensi']) . "\n" . trim($prakarya_data['capaian_kompetensi']);
    
    $daftar_mapel_rapor[$seni_idx] = [
        'nama_mapel' => 'Seni Rupa',
        'nilai_akhir' => ($avg > 0 ? $avg : '-'),
        'capaian_kompetensi' => trim($merged_desc)
    ];
    unset($daftar_mapel_rapor[$prakarya_idx]); // Hapus Prakarya
    $daftar_mapel_rapor = array_values($daftar_mapel_rapor); // Re-index
} elseif ($seni_data) {
    $daftar_mapel_rapor[$seni_idx]['nama_mapel'] = 'Seni Rupa';
} elseif ($prakarya_data) {
    $daftar_mapel_rapor[$prakarya_idx]['nama_mapel'] = 'Seni Rupa';
}

// 6. EKSKUL (QUERY LENGKAP)
$q_ekskul = mysqli_prepare($koneksi, "
    SELECT e.nama_ekskul, kh.jumlah_hadir, kh.total_pertemuan,
    (SELECT GROUP_CONCAT(CONCAT(et.deskripsi_tujuan, ': ', pn.nilai) SEPARATOR '; ') 
     FROM ekskul_penilaian pn
     JOIN ekskul_tujuan et ON pn.id_tujuan_ekskul = et.id_tujuan_ekskul
     WHERE pn.id_peserta_ekskul = ep.id_peserta_ekskul AND et.semester = ?) as penilaian_deskriptif
    FROM ekskul_peserta ep
    JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
    LEFT JOIN ekskul_kehadiran kh ON ep.id_peserta_ekskul = kh.id_peserta_ekskul AND kh.semester = ?
    WHERE ep.id_siswa = ? ORDER BY e.nama_ekskul ASC
");
mysqli_stmt_bind_param($q_ekskul, "iii", $semester_aktif_pdf, $semester_aktif_pdf, $id_siswa);
mysqli_stmt_execute($q_ekskul);
$res_ekskul = mysqli_stmt_get_result($q_ekskul);
?>

<!-- HTML CONTENT START -->
<main>
    <!-- Tabel Identitas -->
    <table class="info-table">
        <tr>
            <td width="20%">Nama Murid</td><td width="1%">:</td><td width="34%"><b><?php echo htmlspecialchars($siswa_pdf['nama_lengkap']); ?></b></td>
            <td width="20%">Kelas</td><td width="1%">:</td><td width="24%"><?php echo htmlspecialchars($siswa_pdf['nama_kelas']); ?></td>
        </tr>
        <tr>
            <td>NISN / NIS</td><td>:</td><td><?php echo htmlspecialchars(($siswa_pdf['nisn'] ?? '-') . ' / ' . ($siswa_pdf['nis'] ?? '-')); ?></td>
            <td>Fase</td><td>:</td><td><?php echo htmlspecialchars($siswa_pdf['fase']); ?></td>
        </tr>
        <tr>
            <td>Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah']); ?></td>
            <td>Semester</td><td>:</td><td><?php echo $semester_text_pdf; ?></td>
        </tr>
        <tr>
            <td>Alamat Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['jalan']); ?>, <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan']); ?></td>
            <td>Tahun Ajaran</td><td>:</td><td><?php echo htmlspecialchars($tahun_ajaran_pdf); ?></td>
        </tr>
    </table>
    
    <div style="border-bottom: 1px solid #000; margin-bottom: 5px;"></div>

    <!-- A. NILAI AKADEMIK -->
    <div class="section-title">A. NILAI AKADEMIK</div>
    <table class="content-table">
        <thead>
            <tr>
                <th width="5%">No.</th>
                <th width="25%">Mata Pelajaran</th>
                <th width="8%">Nilai Akhir</th>
                <th>Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($daftar_mapel_rapor as $mapel): 
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($mapel['nama_mapel']); ?></td>
                <td class="nilai-cetak"><?php echo $mapel['nilai_akhir']; ?></td>
                <td class="capaian"><?php echo nl2br(htmlspecialchars($mapel['capaian_kompetensi'])); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($daftar_mapel_rapor)): ?>
            <tr><td colspan="4" class="text-center">Data nilai belum tersedia.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- [PINTAR] B. KOKURIKULER (KEEP TOGETHER) -->
    <div style="page-break-inside: avoid; margin-bottom: 15px;">
        <div class="section-title" style="margin-top: 0;">B. KOKURIKULER</div>
        <table class="content-table" style="margin-top: 0;">
            <tbody>
                <tr>
                    <td class="capaian"><?php echo !empty($rapor_pdf['deskripsi_kokurikuler']) ? nl2br(htmlspecialchars($rapor_pdf['deskripsi_kokurikuler'])) : '-'; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- [PINTAR] C. EKSTRAKURIKULER (KEEP TOGETHER) -->
    <div style="page-break-inside: avoid; margin-bottom: 15px;">
        <div class="section-title" style="margin-top: 0;">C. EKSTRAKURIKULER</div>
        <table class="content-table" style="margin-top: 0;">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="25%">Ekstrakurikuler</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no_eks = 1;
                $ekskul_data = [];
                if ($res_ekskul) {
                    $ekskul_data = mysqli_fetch_all($res_ekskul, MYSQLI_ASSOC);
                }

                if(!empty($ekskul_data)):
                    $predikat_label = ['SB' => 'Sangat Baik', 'B' => 'Baik', 'C' => 'Cukup', 'K' => 'Perlu Bimbingan'];
                    
                    foreach($ekskul_data as $e_pdf):
                        $deskripsi_final = "Belum ada penilaian deskriptif.";
                        
                        // LOGIKA PARSING DESKRIPSI (SB/B/C)
                        if (!empty($e_pdf['penilaian_deskriptif'])) {
                            $raw_items = explode('; ', $e_pdf['penilaian_deskriptif']);
                            $groups = [];
                            $map_nilai_db = ['Sangat Baik' => 'SB', 'SB' => 'SB', 'Baik' => 'B', 'B' => 'B', 'Cukup' => 'C', 'C' => 'C', 'Kurang' => 'K', 'K' => 'K', 'Perlu Bimbingan' => 'K'];

                            foreach ($raw_items as $item) {
                                $last_pos = strrpos($item, ': ');
                                if ($last_pos !== false) {
                                    $aspek = substr($item, 0, $last_pos);
                                    $nilai_raw = trim(substr($item, $last_pos + 2));
                                    $kategori = $map_nilai_db[$nilai_raw] ?? $nilai_raw;
                                    $groups[$kategori][] = strtolower(htmlspecialchars($aspek ?? ''));
                                }
                            }
                            
                            $kalimat_parts = [];
                            $urutan_cek = ['SB', 'B', 'C', 'K'];
                            foreach ($urutan_cek as $grade) {
                                if (isset($groups[$grade]) && count($groups[$grade]) > 0) {
                                    $label = $predikat_label[$grade] ?? $grade;
                                    $list_aspek = $groups[$grade];
                                    if (count($list_aspek) > 1) {
                                        $last_aspek = array_pop($list_aspek);
                                        $text_aspek = implode(', ', $list_aspek) . ' dan ' . $last_aspek;
                                    } else {
                                        $text_aspek = $list_aspek[0];
                                    }
                                    $kalimat_parts[] = "<b>" . $label . "</b> dalam hal " . $text_aspek;
                                }
                            }
                            if (!empty($kalimat_parts)) {
                                $deskripsi_final = "Menunjukkan perkembangan yang " . implode(", serta ", $kalimat_parts) . ".";
                                $deskripsi_final = ucfirst($deskripsi_final);
                            }
                        }

                        // LOGIKA KEHADIRAN
                        $keterangan_hadir = "";
                        $jumlah_hadir = $e_pdf['jumlah_hadir'] ?? 0;
                        $total_pertemuan = $e_pdf['total_pertemuan'] ?? 0;
                        
                        if ($total_pertemuan > 0) {
                            $persentase = round(($jumlah_hadir / $total_pertemuan) * 100);
                            $color = ($persentase < 70) ? 'color:#d32f2f;' : 'color:#555;';
                            $keterangan_hadir = "<br><span style='font-size:9pt; font-style:italic; {$color}'>Keaktifan kehadiran mencapai <b>{$persentase}%</b> (" . htmlspecialchars($jumlah_hadir) . " dari " . htmlspecialchars($total_pertemuan) . " pertemuan).</span>";
                        } elseif ($jumlah_hadir !== null) {
                            $keterangan_hadir .= "<br><span style='font-size:9pt;'>Kehadiran: " . htmlspecialchars($jumlah_hadir) . " pertemuan.</span>";
                        }
                ?>
                <tr>
                    <td class="text-center"><?php echo $no_eks++; ?></td>
                    <td><b><?php echo htmlspecialchars($e_pdf['nama_ekskul']); ?></b></td>
                    <td class="capaian" style="padding: 8px; line-height: 1.5;"><?php echo $deskripsi_final . $keterangan_hadir; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3" class="text-center">Tidak mengikuti kegiatan ekstrakurikuler.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- [PINTAR] D. KETIDAKHADIRAN & E. CATATAN (KEEP TOGETHER) -->
    <div style="page-break-inside: avoid; width: 100%; margin-bottom: 15px;">
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
        <div style="clear:both;"></div>
    </div>

    <!-- [PINTAR] TANDA TANGAN (KEEP TOGETHER) -->
    <div style="page-break-inside: avoid; margin-top: 2px;">
        <div class="section-title" style="margin-top: 0;">Tanggapan Orang Tua/Wali Murid</div>
        <table class="content-table"><tr><td style="height: 60px;"></td></tr></table>

        <table class="signature-table" style="width: 100%; margin-top: 10px;">
            <tr>
                <td style="width: 33.33%; text-align: center;">
                    Orang Tua/Wali Murid
                    <div class="signature-space"></div>
                    ( ................................. )
                </td>
                <td style="width: 33.33%; text-align: center;">
                    Mengetahui,<br>Kepala Sekolah
                    <div class="signature-space"></div>
                    <strong><u><?php echo htmlspecialchars($sekolah_pdf['nama_kepsek']); ?></u></strong><br>
                    <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah_pdf['jabatan_kepsek']); ?></span><br>
                    NIP. <?php echo htmlspecialchars($sekolah_pdf['nip_kepsek']); ?>
                </td>
                <td style="width: 33.33%; text-align: center;">
                    <?php echo htmlspecialchars($sekolah_pdf['kabupaten_kota']); ?>, <?php echo $tanggal_rapor_pdf; ?><br>
                    Wali Kelas
                    <div class="signature-space"></div>
                    <strong><u><?php echo htmlspecialchars($siswa_pdf['nama_walikelas'] ?? '........................'); ?></u></strong><br>
                    NIP. <?php echo htmlspecialchars($siswa_pdf['nip_walikelas'] ?? '........................'); ?>
                </td>
            </tr>
        </table>
    </div>
</main>
<!-- HTML CONTENT END -->
