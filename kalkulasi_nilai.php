<?php

/**
 * Menghitung data rapor siswa per mata pelajaran sesuai dengan Panduan Pembelajaran dan Asesmen (PPA) 2025.
 * @param mysqli $koneksi Koneksi database mysqli.
 * @param int $id_siswa ID Siswa.
 * @param int $id_kelas ID Kelas.
 * @param int $semester_aktif Semester aktif (1 atau 2).
 * @param int $kkm Kriteria Ketuntasan Minimum.
 * @param array $daftar_mapel Daftar mata pelajaran yang dihitung.
 * @return array Mengembalikan array data rapor per id_mapel.
 */
function hitungDataRaporSiswa($koneksi, $id_siswa, $id_kelas, $semester_aktif, $kkm, $daftar_mapel) {
    $data_rapor_siswa = [];
    
    // Query 1: Mengambil Sumatif yang terkait Tujuan Pembelajaran (TP)
    $stmt_sumatif_tp = mysqli_prepare($koneksi, "
        SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian, 
                GROUP_CONCAT(tp.deskripsi_tp SEPARATOR '|||') as deskripsi_tps
        FROM penilaian_detail_nilai pdn
        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
        JOIN penilaian_tp ptp ON p.id_penilaian = ptp.id_penilaian
        JOIN tujuan_pembelajaran tp ON ptp.id_tp = tp.id_tp
        WHERE p.subjenis_penilaian = 'Sumatif TP' AND pdn.id_siswa = ? AND p.id_mapel = ? 
        AND p.id_kelas = ? AND p.semester = ?
        GROUP BY p.id_penilaian, pdn.nilai, p.bobot_penilaian
    ");
    
    // Query 2: Mengambil Sumatif Akhir Semester (SAS) atau Akhir Tahun (SAT)
    $stmt_sumatif_akhir = mysqli_prepare($koneksi, "
        SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian
        FROM penilaian_detail_nilai pdn
        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
        WHERE p.subjenis_penilaian IN ('Sumatif Akhir Semester', 'Sumatif Akhir Tahun')
        AND p.jenis_penilaian = 'Sumatif' AND pdn.id_siswa = ? AND p.id_mapel = ?
        AND p.id_kelas = ? AND p.semester = ?
    ");
        
    foreach ($daftar_mapel as $mapel) {
        $id_mapel = $mapel['id_mapel'];
        
        $skor_per_tp = []; 
        $komponen_nilai = [];
        $total_nilai_x_bobot = 0; 
        $total_bobot = 0;
        
        // Eksekusi Query 1 (Sumatif TP)
        if ($stmt_sumatif_tp) {
            mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
            mysqli_stmt_execute($stmt_sumatif_tp);
            $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
            while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
                $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
                foreach($tps_individu as $desc_tp) {
                    if (!isset($skor_per_tp[$desc_tp])) { $skor_per_tp[$desc_tp] = []; }
                    $skor_per_tp[$desc_tp][] = $d_nilai['nilai'];
                }
                $komponen_nilai[] = [
                    'nama' => $d_nilai['nama_penilaian'], 'jenis' => $d_nilai['subjenis_penilaian'],
                    'nilai' => $d_nilai['nilai'], 'bobot' => $d_nilai['bobot_penilaian'],
                    'deskripsi_tp' => str_replace('|||', '<br>- ', $d_nilai['deskripsi_tps'])
                ];
                $total_nilai_x_bobot += $d_nilai['nilai'] * $d_nilai['bobot_penilaian'];
                $total_bobot += $d_nilai['bobot_penilaian'];
            }
        }

        // Eksekusi Query 2 (Sumatif Akhir)
        if ($stmt_sumatif_akhir) {
            mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
            mysqli_stmt_execute($stmt_sumatif_akhir);
            $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
            while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
                $komponen_nilai[] = [
                    'nama' => $d_nilai_akhir['nama_penilaian'], 'jenis' => $d_nilai_akhir['subjenis_penilaian'],
                    'nilai' => $d_nilai_akhir['nilai'], 'bobot' => $d_nilai_akhir['bobot_penilaian'],
                    'deskripsi_tp' => 'Mencakup keseluruhan materi semester.'
                ];
                $total_nilai_x_bobot += $d_nilai_akhir['nilai'] * $d_nilai_akhir['bobot_penilaian'];
                $total_bobot += $d_nilai_akhir['bobot_penilaian'];
            }
        }

        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;
        
        $rumus_perhitungan = "Belum ada data untuk dihitung.";
        if ($total_bobot > 0) {
            $pembilang_parts = []; $penyebut_parts = [];
            foreach ($komponen_nilai as $komponen) {
                $pembilang_parts[] = "({$komponen['nilai']} x {$komponen['bobot']})";
                $penyebut_parts[] = $komponen['bobot'];
            }
            $rumus_pembilang = implode(' + ', $pembilang_parts);
            $rumus_penyebut = implode(' + ', $penyebut_parts);
            $rumus_perhitungan = "( {$rumus_pembilang} ) / ( {$rumus_penyebut} ) = {$total_nilai_x_bobot} / {$total_bobot} ≈ {$nilai_akhir}";
        }

        // == BLOK PEMBUATAN DESKRIPSI SESUAI PPA 2025 ==
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
                     $rekap_tp[$desc_clean] = ['avg' => $avg, 'original_desc' => $deskripsi];
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
            $deskripsi_final = 'Data penilaian belum lengkap atau belum ada penilaian sumatif yang diinput.';
        }
        // == AKHIR BLOK DESKRIPSI ==

        $data_rapor_siswa[$id_mapel] = [
            'nilai_akhir' => $nilai_akhir, 
            'deskripsi' => $deskripsi_final,
            'komponen_nilai' => $komponen_nilai,
            'rumus_perhitungan' => $rumus_perhitungan
        ];
    }
    // Tutup statement yang disiapkan
    if ($stmt_sumatif_tp) mysqli_stmt_close($stmt_sumatif_tp);
    if ($stmt_sumatif_akhir) mysqli_stmt_close($stmt_sumatif_akhir);
    
    return $data_rapor_siswa;
}
?>