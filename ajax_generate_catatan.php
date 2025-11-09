<?php
session_start();
include 'koneksi.php';

// Pastikan request datang dari user yang login dan memiliki parameter yang benar
if (!isset($_SESSION['id_guru']) || !isset($_POST['id_siswa'])) {
    echo "Error: Akses ditolak.";
    exit;
}

// Sanitasi input
$id_siswa = (int)$_POST['id_siswa'];

// Ambil info global dari session atau database
$id_wali_kelas = $_SESSION['id_guru'];
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];


// Ambil data spesifik untuk satu siswa
$q_siswa = mysqli_query($koneksi, "SELECT s.id_siswa, s.nama_lengkap, k.fase FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas WHERE s.id_siswa = $id_siswa LIMIT 1");
$siswa = mysqli_fetch_assoc($q_siswa);
$fase_kelas = $siswa['fase'];


// Fungsi untuk generate catatan (di-copy dari implementasi sebelumnya)
function generateAutomaticNote($siswa, $fase, $akademik, $kokurikuler, $absensi, $ekskul) {
    $nama_panggilan = explode(' ', htmlspecialchars($siswa['nama_lengkap']))[0];
    $catatan = [];

    if (strtoupper($fase) == 'A') {
        $catatan[] = "Ananda {$nama_panggilan} menunjukkan perkembangan yang baik dalam aspek fondasi.";
        $koko_summary = [];
        if (!empty($kokurikuler)) {
            foreach($kokurikuler as $k) {
                if(in_array($k['nilai_kualitatif'], ['Sangat Baik', 'Baik'])) {
                    $koko_summary[] = strtolower($k['nama_dimensi']);
                }
            }
        }
        if(!empty($koko_summary)){
            $catatan[] = "Selama kegiatan pembelajaran, Ananda terlihat menonjol dalam hal " . implode(', ', array_unique($koko_summary)) . ".";
        }
        $catatan[] = "Ananda sudah mampu berinteraksi dengan teman-temannya dan mulai menunjukkan kemandirian dalam aktivitas sekolah.";
        $catatan[] = "Mohon terus berikan motivasi dan dukungan di rumah agar antusiasme belajar Ananda terus terjaga. Kolaborasi antara sekolah dan orang tua sangat penting untuk perkembangannya.";
    } else {
        $catatan[] = "Secara keseluruhan, Ananda {$nama_panggilan} telah menunjukkan perkembangan yang positif pada semester ini.";
        if (!empty($akademik)) {
            $kekuatan = $akademik[0];
            $kelemahan = end($akademik);
            $catatan[] = "Ananda menunjukkan kompetensi yang sangat baik dalam mata pelajaran <strong>{$kekuatan['nama_mapel']}</strong> dengan nilai akhir {$kekuatan['nilai_akhir']}.";
            if ($kekuatan['nilai_akhir'] > $kelemahan['nilai_akhir'] && $kelemahan['nilai_akhir'] < 78) {
                 $catatan[] = "Namun, perlu perhatian dan bimbingan lebih lanjut pada mata pelajaran <strong>{$kelemahan['nama_mapel']}</strong> untuk ditingkatkan pada semester berikutnya.";
            }
        } else {
            $catatan[] = "Perkembangan akademik Ananda secara umum sudah cukup baik.";
        }
        if (!empty($kokurikuler)) {
            $dimensi_baik = [];
            foreach ($kokurikuler as $k) {
                if ($k['nilai_kualitatif'] == 'Sangat Baik' || $k['nilai_kualitatif'] == 'Baik') {
                    $dimensi_baik[] = strtolower($k['nama_dimensi']);
                }
            }
            if (count($dimensi_baik) > 0) {
                $catatan[] = "Dari sisi pengembangan karakter, Ananda menunjukkan sikap menonjol dalam hal <strong>" . implode(', ', array_unique($dimensi_baik)) . "</strong>.";
            }
        }
        $total_absen = ($absensi['sakit'] ?? 0) + ($absensi['izin'] ?? 0) + ($absensi['tanpa_keterangan'] ?? 0);
        if ($total_absen == 0) {
            $catatan[] = "Tingkat kehadiran di sekolah sangat baik dan konsisten.";
        } elseif (($absensi['tanpa_keterangan'] ?? 0) > 3) {
            $catatan[] = "Perlu ditingkatkan lagi kedisiplinan dalam kehadiran untuk menunjang proses pembelajaran yang lebih optimal.";
        }
        $catatan[] = "Terus pertahankan semangat belajar dan tingkatkan potensi yang dimiliki. Dengan dukungan dari rumah dan sekolah, kami yakin Ananda {$nama_panggilan} dapat meraih prestasi yang lebih gemilang.";
    }
    return implode(' ', $catatan);
}

// Fetch data yang dibutuhkan untuk fungsi di atas
// 1. Data Rapor (Absensi)
$q_rapor = mysqli_query($koneksi, "SELECT sakit, izin, tanpa_keterangan FROM rapor WHERE id_siswa = $id_siswa AND semester = $semester_aktif AND id_tahun_ajaran = $id_tahun_ajaran LIMIT 1");
$data_rapor_siswa = mysqli_fetch_assoc($q_rapor);

// Jika guru baru input absensi di form dan belum disimpan, kita ambil dari POST request
$data_rapor_siswa['sakit'] = isset($_POST['sakit']) ? (int)$_POST['sakit'] : ($data_rapor_siswa['sakit'] ?? 0);
$data_rapor_siswa['izin'] = isset($_POST['izin']) ? (int)$_POST['izin'] : ($data_rapor_siswa['izin'] ?? 0);
$data_rapor_siswa['tanpa_keterangan'] = isset($_POST['alpha']) ? (int)$_POST['alpha'] : ($data_rapor_siswa['tanpa_keterangan'] ?? 0);


// 2. Data Akademik
$data_akademik = [];
$query_akademik = mysqli_prepare($koneksi, "SELECT mp.nama_mapel, rda.nilai_akhir FROM rapor_detail_akademik rda JOIN rapor r ON rda.id_rapor = r.id_rapor JOIN mata_pelajaran mp ON rda.id_mapel = mp.id_mapel WHERE r.id_siswa = ? AND r.semester = ? AND r.id_tahun_ajaran = ? ORDER BY rda.nilai_akhir DESC");
mysqli_stmt_bind_param($query_akademik, "iii", $id_siswa, $semester_aktif, $id_tahun_ajaran);
mysqli_stmt_execute($query_akademik);
$result_akademik = mysqli_stmt_get_result($query_akademik);
while ($row = mysqli_fetch_assoc($result_akademik)) {
    $data_akademik[] = $row;
}

// 3. Data Kokurikuler
$data_kokurikuler = [];
$query_koko = mysqli_prepare($koneksi, "SELECT d.nama_dimensi, a.nilai_kualitatif FROM kokurikuler_asesmen a JOIN kokurikuler_target_dimensi d ON a.id_target = d.id_target JOIN kokurikuler_kegiatan k ON d.id_kegiatan = k.id_kegiatan WHERE a.id_siswa = ? AND k.semester = ? AND k.id_tahun_ajaran = ?");
mysqli_stmt_bind_param($query_koko, "iii", $id_siswa, $semester_aktif, $id_tahun_ajaran);
mysqli_stmt_execute($query_koko);
$result_koko = mysqli_stmt_get_result($query_koko);
while ($row = mysqli_fetch_assoc($result_koko)) {
    $data_kokurikuler[] = $row;
}

// 4. Data Ekskul (tidak digunakan di catatan, tapi bisa ditambahkan jika perlu)
$data_ekskul_siswa = []; // Kosongkan saja untuk efisiensi

// Panggil fungsi generator
$catatan_final = generateAutomaticNote($siswa, $fase_kelas, $data_akademik, $data_kokurikuler, $data_rapor_siswa, $data_ekskul_siswa);

// Kembalikan hasilnya
echo $catatan_final;
exit;