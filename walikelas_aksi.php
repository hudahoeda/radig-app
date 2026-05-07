<?php
// ==============================================================================
// FILE: walikelas_aksi.php
// DESKRIPSI: Skrip aksi Wali Kelas SUPER LENGKAP (Update Siswa, Absensi, Ekskul, Finalisasi & Kalkulasi Nilai)
// ==============================================================================

// === START: DEBUGGING AKTIF (Harap Hapus Setelah Fix!) ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AKTIF ===

session_start();
// DEBUG POINT A: Sebelum include koneksi
// die("DEBUG A: Code reached session start."); // Uji koneksi sesi

require 'koneksi.php'; // Diganti ke require untuk memastikan file koneksi ada.

// Cek koneksi setelah include 'koneksi.php'
if (!isset($koneksi) || $koneksi === false) {
    die("FATAL ERROR A: Koneksi database GAGAL. Cek pengaturan di file koneksi.php Anda. Error: " . mysqli_connect_error());
}

// DEBUG POINT B: Setelah koneksi berhasil
// die("DEBUG B: Koneksi berhasil. Melanjutkan ke validasi role."); // Uji koneksi DB

// Validasi role Wali Kelas atau Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru', 'admin'])) {
    die("Akses ditolak. Silakan login.");
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id_wali_kelas = $_SESSION['id_guru'] ?? 0; // Pastikan id_guru ada

// Ambil KKM (dibutuhkan untuk fungsi hitungDataRaporSiswa)
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm_db = mysqli_fetch_assoc($q_kkm);
$kkm = $kkm_db ? (int)$kkm_db['nilai_pengaturan'] : 75;


// ======================================================================
// [FUNGSI UTAMA] Menghitung data rapor siswa per mata pelajaran (Menggunakan Logika Anda yang Bekerja)
// ======================================================================
/**
 * Menghitung data rapor siswa per mata pelajaran sesuai dengan Panduan Pembelajaran dan Asesmen (PPA) 2025.
 */
function hitungDataRaporSiswa($koneksi, $id_siswa, $id_kelas, $semester_aktif, $kkm, $daftar_mapel)
{
    $data_rapor_siswa = [];

    // Query 1: Mengambil Sumatif yang terkait Tujuan Pembelajaran (TP)
    // Parameter semester_aktif akan di-bind sebagai string/int, disesuaikan dengan tipe kolom DB
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
        // Menggunakan "iiii" (4 integers)
        mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        if (mysqli_stmt_execute($stmt_sumatif_tp)) {
            $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
            while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
                $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
                foreach ($tps_individu as $desc_tp) {
                    // Rata-rata sederhana per TP (meskipun TP muncul di beberapa asesmen)
                    if (!isset($skor_per_tp[$desc_tp])) {
                        $skor_per_tp[$desc_tp] = [];
                    }
                    $skor_per_tp[$desc_tp][] = $d_nilai['nilai'];
                }
                $komponen_nilai[] = [
                    'nama' => $d_nilai['nama_penilaian'], 'jenis' => $d_nilai['subjenis_penilaian'],
                    'nilai' => $d_nilai['nilai'], 'bobot' => $d_nilai['bobot_penilaian'],
                    'deskripsi_tp' => str_replace('|||', '<br>- ', $d_nilai['deskripsi_tps'])
                ];
                $total_nilai_x_bobot += (int)$d_nilai['nilai'] * (int)$d_nilai['bobot_penilaian'];
                $total_bobot += (int)$d_nilai['bobot_penilaian'];
            }
        }


        // Eksekusi Query 2 (Sumatif Akhir)
        // Menggunakan "iiii" (4 integers)
        mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        if (mysqli_stmt_execute($stmt_sumatif_akhir)) {
            $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
            while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
                $komponen_nilai[] = [
                    'nama' => $d_nilai_akhir['nama_penilaian'], 'jenis' => $d_nilai_akhir['subjenis_penilaian'],
                    'nilai' => $d_nilai_akhir['nilai'], 'bobot' => $d_nilai_akhir['bobot_penilaian'],
                    'deskripsi_tp' => 'Mencakup keseluruhan materi semester.'
                ];
                $total_nilai_x_bobot += (int)$d_nilai_akhir['nilai'] * (int)$d_nilai_akhir['bobot_penilaian'];
                $total_bobot += (int)$d_nilai_akhir['bobot_penilaian'];
            }
        }

        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;

        $rumus_perhitungan = "Belum ada data untuk dihitung.";
        if ($total_bobot > 0) {
            $pembilang_parts = [];
            $penyebut_parts = [];
            foreach ($komponen_nilai as $komponen) {
                $pembilang_parts[] = "({$komponen['nilai']} x {$komponen['bobot']})";
                $penyebut_parts[] = $komponen['bobot'];
            }
            $rumus_pembilang = implode(' + ', $pembilang_parts);
            $rumus_penyebut = implode(' + ', $penyebut_parts);
            $rumus_perhitungan = "( {$rumus_pembilang} ) / ( {$rumus_penyebut} ) = {$total_nilai_x_bobot} / {$total_bobot} ≈ {$nilai_akhir}";
        }

        // == BLOK PEMBUATAN DESKRIPSI SESUAI LOGIKA PPA ANDA YANG LAMA ==
        $deskripsi_final = '';
        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
            // 1. Hitung Rata-rata Akhir Per TP & Bersihkan Kalimat
            $rekap_tp = [];
            $kata_hapus = ['Peserta didik dapat', 'Peserta didik mampu', 'peserta didik mampu', 'siswa dapat', 'siswa mampu', 'mampu', 'memahami', 'menguasai', 'menjelaskan', 'menganalisis', 'mengidentifikasi', 'menentukan', 'menunjukkan'];

            foreach ($skor_per_tp as $deskripsi => $skor_array) {
                $avg = array_sum($skor_array) / count($skor_array);

                // Bersihkan deskripsi dari kata kerja operasional yang berulang
                $desc_clean = trim(str_ireplace($kata_hapus, '', $deskripsi));
                $desc_clean = preg_replace('/\s+/', ' ', $desc_clean); // Hapus spasi ganda
                $desc_clean = lcfirst($desc_clean);

                // Jika deskripsi sudah ada, gunakan nilai rata-rata tertinggi
                if (!isset($rekap_tp[$desc_clean]) || $rekap_tp[$desc_clean]['avg'] < $avg) {
                    $rekap_tp[$desc_clean] = ['avg' => $avg, 'original_desc' => $deskripsi];
                }
            }

            // 2. Filter & Urutkan
            $tp_lulus = [];
            $tp_remedi = [];

            foreach ($rekap_tp as $clean_desc => $data) {
                if ($data['avg'] >= $kkm) {
                    $tp_lulus[$clean_desc] = $data['avg'];
                } else {
                    $tp_remedi[$clean_desc] = $data['avg'];
                }
            }

            // Urutkan untuk ambil Top 2 LULUS dan Top 2 REMEDI
            arsort($tp_lulus); // Tertinggi ke Terendah (Lulus)
            asort($tp_remedi); // Terendah ke Tertinggi (Remedi/Perlu Bimbingan)

            // Ambil maksimal 2 TP terbaik dan terburuk
            $top_tp = array_slice(array_keys($tp_lulus), 0, 2);
            $bottom_tp = array_slice(array_keys($tp_remedi), 0, 2);

            // 3. Susun Deskripsi Final
            $deskripsi_draf = "";

            // Kalimat Kekuatan (Top 2 LULUS)
            if (!empty($top_tp)) {
                $deskripsi_draf .= "Menunjukkan penguasaan yang sangat baik dalam " . implode(', ', $top_tp) . ". ";
            } elseif ($nilai_akhir >= $kkm && empty($top_tp)) {
                $deskripsi_draf .= "Secara keseluruhan, capaian kompetensi sudah tuntas. ";
            }

            // Kalimat Kelemahan/Intervensi (Top 2 REMEDI)
            if (!empty($bottom_tp)) {
                $deskripsi_draf .= "Namun, perlu penguatan lebih lanjut dalam " . implode(', ', $bottom_tp) . ".";
            } else {
                $deskripsi_draf .= "Semua tujuan pembelajaran telah tercapai dengan baik.";
            }

            // Finalisasi: Bersihkan lagi dan pastikan huruf kapital di awal
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
// ======================================================================
// [AKHIR FUNGSI UTAMA]
// ======================================================================


//======================================================================
// --- AKSI UPDATE IDENTITAS SISWA ---
//======================================================================
if ($aksi == 'update_siswa') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('HTTP/1.0 405 Method Not Allowed');
        exit;
    }

    // --- [AUTO-FIX DATABASE V2.4: Perbaikan Collation Intensif] ---
    // Memastikan semua kolom penting untuk identitas siswa sudah ada
    
    // Kolom baru untuk data Penerimaan Kelas
    $cek_kolom_kelas = mysqli_query($koneksi, "SHOW COLUMNS FROM siswa LIKE 'diterima_di_kelas'");
    if (mysqli_num_rows($cek_kolom_kelas) == 0) {
        mysqli_query($koneksi, "ALTER TABLE siswa ADD COLUMN diterima_di_kelas VARCHAR(20) DEFAULT NULL AFTER sekolah_asal");
    }
     // Kolom baru untuk NIK
    $cek_kolom_nik = mysqli_query($koneksi, "SHOW COLUMNS FROM siswa LIKE 'nik'");
    if (mysqli_num_rows($cek_kolom_nik) == 0) {
        mysqli_query($koneksi, "ALTER TABLE siswa ADD COLUMN nik VARCHAR(30) DEFAULT NULL AFTER nisn");
    }
    // Kolom baru untuk Agama
    $cek_kolom_agama = mysqli_query($koneksi, "SHOW COLUMNS FROM siswa LIKE 'agama'");
    if (mysqli_num_rows($cek_kolom_agama) == 0) {
        mysqli_query($koneksi, "ALTER TABLE siswa ADD COLUMN agama VARCHAR(20) DEFAULT NULL AFTER tanggal_lahir");
    }
    // Kolom baru untuk Data Orang Tua (Telepon Ayah)
    $cek_kolom_telp_ayah = mysqli_query($koneksi, "SHOW COLUMNS FROM siswa LIKE 'telepon_ayah'");
    if (mysqli_num_rows($cek_kolom_telp_ayah) == 0) {
        mysqli_query($koneksi, "ALTER TABLE siswa ADD COLUMN telepon_ayah VARCHAR(20) DEFAULT NULL AFTER pekerjaan_ayah");
    }
    // Kolom baru untuk Data Orang Tua (Telepon Ibu)
    $cek_kolom_telp_ibu = mysqli_query($koneksi, "SHOW COLUMNS FROM siswa LIKE 'telepon_ibu'");
    if (mysqli_num_rows($cek_kolom_telp_ibu) == 0) {
        mysqli_query($koneksi, "ALTER TABLE siswa ADD COLUMN telepon_ibu VARCHAR(20) DEFAULT NULL AFTER pekerjaan_ibu");
    }

    // --- PERBAIKAN COLLATION (PENTING UNTUK MENGATASI ERROR COLLATIONS) ---
    mysqli_set_charset($koneksi, "utf8mb4");

    $tables_to_fix = ['siswa', 'kelas', 'guru']; 
    $siswa_string_cols = ['nama_lengkap', 'nis', 'nisn', 'nik', 'jenis_kelamin', 'agama', 'status_dalam_keluarga', 'sekolah_asal', 'diterima_di_kelas', 'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu', 'nama_wali', 'pekerjaan_wali', 'foto_siswa'];

    foreach ($tables_to_fix as $table) {
        $q_check_table = mysqli_query($koneksi, "SHOW TABLES LIKE '{$table}'");
        if (mysqli_num_rows($q_check_table) > 0) {
            $q_alter_table = "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            mysqli_query($koneksi, $q_alter_table);
            
            if ($table == 'siswa') {
                foreach ($siswa_string_cols as $col) {
                    $q_check_col = mysqli_query($koneksi, "SHOW FULL COLUMNS FROM siswa LIKE '{$col}'");
                    if (mysqli_num_rows($q_check_col) > 0) {
                        $col_info = mysqli_fetch_assoc($q_check_col);
                        $col_type = $col_info['Type'];
                        $current_collation = $col_info['Collation'];

                        if ((strpos(strtoupper($col_type), 'VARCHAR') !== false || strpos(strtoupper($col_type), 'TEXT') !== false) && $current_collation != 'utf8mb4_unicode_ci') {
                           $q_alter_col = "ALTER TABLE `siswa` CHANGE `{$col}` `{$col}` {$col_type} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                           mysqli_query($koneksi, $q_alter_col);
                        }
                    }
                }
            }
        }
    }
    // ----------------------------

    $id_siswa = (int)$_POST['id_siswa'];
    $foto_lama = $_POST['foto_siswa_lama'] ?? null;

    // Keamanan: Pastikan wali kelas berhak mengedit siswa ini
    $stmt_check = mysqli_prepare($koneksi, "SELECT s.id_siswa, s.foto_siswa FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas WHERE s.id_siswa = ? AND (k.id_wali_kelas = ? OR ? = 'admin')");
    $role_admin_check = $_SESSION['role']; 
    mysqli_stmt_bind_param($stmt_check, "iis", $id_siswa, $id_wali_kelas, $role_admin_check);
    
    mysqli_stmt_execute($stmt_check); 
    $result_check = mysqli_stmt_get_result($stmt_check);

    $data_siswa_db = mysqli_fetch_assoc($result_check);

    if (!$data_siswa_db && $_SESSION['role'] != 'admin') { 
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Aksi Gagal', 'text' => 'Aksi tidak diizinkan. Anda hanya dapat mengedit murid di kelas Anda.']);
        header("Location: walikelas_identitas_siswa.php");
        exit;
    }

    if (empty($foto_lama) && isset($data_siswa_db['foto_siswa'])) {
        $foto_lama = $data_siswa_db['foto_siswa'];
    }

    // Proses upload foto (Logika sudah benar)
    $nama_file_foto_final = $foto_lama;
    if (isset($_FILES['foto_siswa']) && $_FILES['foto_siswa']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/foto_siswa/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_tmp = $_FILES['foto_siswa']['tmp_name'];
        $file_ext = strtolower(pathinfo(basename($_FILES['foto_siswa']['name']), PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        if (in_array($file_ext, $allowed_ext) && $_FILES['foto_siswa']['size'] <= 1048576) {
            $nama_file_foto_final = $id_siswa . '_' . time() . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $nama_file_foto_final)) {
                if ($foto_lama && $foto_lama != $nama_file_foto_final && file_exists($upload_dir . $foto_lama)) {
                    @unlink($upload_dir . $foto_lama); 
                }
            } else {
                $nama_file_foto_final = $foto_lama;
            }
        } else {
            $nama_file_foto_final = $foto_lama;
        }
    }

    $diterima_di_kelas_val = isset($_POST['diterima_di_kelas']) ? $_POST['diterima_di_kelas'] : (isset($_POST['diterima_kelas']) ? $_POST['diterima_kelas'] : '');

    // Siapkan query UPDATE DENGAN FIELD LENGKAP (Total 26 kolom update + 1 id_siswa = 27 parameter)
    $query = "UPDATE siswa SET 
                nama_lengkap = ?, nis = ?, nisn = ?, nik = ?, jenis_kelamin = ?, tempat_lahir = ?, 
                tanggal_lahir = ?, agama = ?, status_dalam_keluarga = ?, anak_ke = ?, alamat = ?, 
                telepon_siswa = ?, 
                sekolah_asal = ?, diterima_di_kelas = ?, diterima_tanggal = ?, 
                nama_ayah = ?, pekerjaan_ayah = ?, telepon_ayah = ?, nama_ibu = ?, pekerjaan_ibu = ?, 
                telepon_ibu = ?, 
                nama_wali = ?, alamat_wali = ?, telepon_wali = ?, pekerjaan_wali = ?, 
                foto_siswa = ? 
              WHERE id_siswa = ?";

    // Siapkan data dalam array (Total 27 elemen)
    $data_siswa_update = [
        trim($_POST['nama_lengkap'] ?? ''),
        trim($_POST['nis'] ?? ''),
        trim($_POST['nisn'] ?? ''),
        trim($_POST['nik'] ?? ''),
        $_POST['jenis_kelamin'] ?? 'Laki-laki',
        trim($_POST['tempat_lahir'] ?? ''),
        !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null, 
        trim($_POST['agama'] ?? ''),
        trim($_POST['status_dalam_keluarga'] ?? ''),
        !empty($_POST['anak_ke']) ? (int)$_POST['anak_ke'] : null,
        trim($_POST['alamat'] ?? ''),
        trim($_POST['telepon_siswa'] ?? ''),
        
        // Data Pendidikan
        trim($_POST['sekolah_asal'] ?? ''),
        trim($diterima_di_kelas_val), 
        !empty($_POST['diterima_tanggal']) ? $_POST['diterima_tanggal'] : null, 
        
        // Data Ortu
        trim($_POST['nama_ayah'] ?? ''),
        trim($_POST['pekerjaan_ayah'] ?? ''),
        trim($_POST['telepon_ayah'] ?? ''),
        trim($_POST['nama_ibu'] ?? ''),
        trim($_POST['pekerjaan_ibu'] ?? ''),
        trim($_POST['telepon_ibu'] ?? ''),
        
        // Data Wali
        trim($_POST['nama_wali'] ?? ''),
        trim($_POST['alamat_wali'] ?? ''),
        trim($_POST['telepon_wali'] ?? ''),
        trim($_POST['pekerjaan_wali'] ?? ''),
        
        $nama_file_foto_final,
        $id_siswa 
    ];

    // Buat tipe data secara otomatis
    $tipe_data = '';
    foreach ($data_siswa_update as $value) {
        if (is_int($value) && $value !== null) { 
            $tipe_data .= 'i';
        } elseif (is_float($value) && $value !== null) {
            $tipe_data .= 'd';
        } else {
            $tipe_data .= 's'; 
        }
    }
    
    $expected_params = 27; 
    if (count($data_siswa_update) != $expected_params) {
        error_log("Parameter mismatch in walikelas_aksi: Expected $expected_params, got " . count($data_siswa_update));
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Error Internal', 'text' => 'Terjadi kesalahan saat pemrosesan data (Kode: P-' . count($data_siswa_update) . '). Harap hubungi pengembang.']);
        header("Location: walikelas_edit_siswa.php?id_siswa=" . $id_siswa);
        exit;
    }
    
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, $tipe_data, ...$data_siswa_update);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => "Data siswa '" . htmlspecialchars($data_siswa_update[0]) . "' berhasil diperbarui."]);
    } else {
        error_log("SQL Error on update_siswa: " . mysqli_stmt_error($stmt));
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan saat memperbarui data (SQL Error).']);
    }
    
    mysqli_stmt_close($stmt);

    header("Location: walikelas_edit_siswa.php?id_siswa=" . $id_siswa);
    exit;
}

//======================================================================
// --- AKSI SIMPAN ABSENSI & CATATAN WALI KELAS (DENGAN KEPUTUSAN SEMESTER) ---
//======================================================================
elseif ($aksi == 'simpan_data') {
    $absensi_data = $_POST['absensi'] ?? [];
    $catatan_data = $_POST['catatan'] ?? [];
    // [TAMBAHAN BARU] Tangkap data dari select Keputusan & Tujuan Kelas
    $keputusan_data = $_POST['keputusan'] ?? [];
    $keterangan_data = $_POST['keterangan_keputusan'] ?? [];

    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

    $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
    $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 0;

    $q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ? LIMIT 1");
    mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
    mysqli_stmt_execute($q_kelas);
    $id_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kelas))['id_kelas'] ?? 0;
    mysqli_stmt_close($q_kelas);

    if ($id_kelas == 0) {
        // [PERBAIKAN] Diganti ke plain-text agar SweetAlert di walikelas_data_rapor.php merender dengan cantik
        $_SESSION['pesan'] = 'Anda tidak memiliki kelas aktif yang diampu.';
        header("location: walikelas_data_rapor.php");
        exit();
    }

    mysqli_begin_transaction($koneksi);
    try {
        
        $q_cek_rapor = mysqli_prepare($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ?");
        
        // [PERBAIKAN] Query Update kini menyimpan keputusan_akhir dan keterangan_keputusan (Total 7 Parameter)
        $stmt_update = mysqli_prepare($koneksi, "UPDATE rapor SET sakit=?, izin=?, tanpa_keterangan=?, catatan_wali_kelas=?, keputusan_akhir=?, keterangan_keputusan=? WHERE id_rapor=?");
        
        // [PERBAIKAN] Query Insert kini menyimpan keputusan_akhir dan keterangan_keputusan (Total 10 Parameter)
        $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO rapor (id_siswa, id_kelas, id_tahun_ajaran, semester, sakit, izin, tanpa_keterangan, catatan_wali_kelas, keputusan_akhir, keterangan_keputusan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')");
        
        $semester_aktif_int = (int)$semester_aktif;
        
        foreach ($absensi_data as $id_siswa => $data_absen) {
            $id_siswa_int = (int)$id_siswa;
            $sakit = (int)($data_absen['sakit'] ?? 0);
            $izin = (int)($data_absen['izin'] ?? 0);
            $alpha = (int)($data_absen['tanpa_keterangan'] ?? 0);
            $catatan = $catatan_data[$id_siswa] ?? '';
            
            // Ambil keputusan dari array POST
            $keputusan = $keputusan_data[$id_siswa] ?? '-';
            $keterangan = $keterangan_data[$id_siswa] ?? '';

            mysqli_stmt_bind_param($q_cek_rapor, "iii", $id_siswa_int, $semester_aktif_int, $id_tahun_ajaran);
            mysqli_stmt_execute($q_cek_rapor);
            $result_rapor = mysqli_stmt_get_result($q_cek_rapor);
            
            if (mysqli_num_rows($result_rapor) > 0) {
                $d_rapor = mysqli_fetch_assoc($result_rapor);
                $id_rapor = $d_rapor['id_rapor'];
                
                // Update: 3 integer (sakit, izin, alpha), 3 string (catatan, keputusan, keterangan), 1 integer (id_rapor) => "iiisssi"
                mysqli_stmt_bind_param($stmt_update, "iiisssi", $sakit, $izin, $alpha, $catatan, $keputusan, $keterangan, $id_rapor);
                mysqli_stmt_execute($stmt_update);
            } else {
                // Insert: 7 integer, 3 string => "iiiiiiisss"
                mysqli_stmt_bind_param($stmt_insert, "iiiiiiisss", $id_siswa_int, $id_kelas, $id_tahun_ajaran, $semester_aktif_int, $sakit, $izin, $alpha, $catatan, $keputusan, $keterangan);
                mysqli_stmt_execute($stmt_insert);
            }
        }
        
        mysqli_stmt_close($q_cek_rapor);
        mysqli_stmt_close($stmt_update);
        mysqli_stmt_close($stmt_insert);

        mysqli_commit($koneksi);
        
        // [PERBAIKAN] Diganti ke plain-text agar SweetAlert merender notifikasi sukses tanpa json_encode
        $_SESSION['pesan'] = 'Data absensi, catatan, dan keputusan rapor berhasil disimpan.';
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = 'Terjadi error: ' . $e->getMessage();
    }

    header("location: walikelas_data_rapor.php");
    exit();
}

//======================================================================
// --- AKSI SIMPAN PENDAFTARAN EKSKUL (Menggunakan logika sederhana dari Anda) ---
//======================================================================
elseif ($aksi == 'simpan_pendaftaran_ekskul') {
    mysqli_begin_transaction($koneksi);
    try {
        $id_kelas = (int)$_POST['id_kelas']; // Asumsi id_kelas dikirim via POST
        
        // Fallback untuk id_kelas jika tidak dikirim dari form (ambil dari wali kelas)
        if ($id_kelas === 0) {
            $q_kelas_check = mysqli_query($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = $id_wali_kelas LIMIT 1");
            $id_kelas = mysqli_fetch_assoc($q_kelas_check)['id_kelas'] ?? 0;
            if ($id_kelas === 0) {
                throw new Exception("Kelas tidak ditemukan.");
            }
        }
        
        $pendaftaran_ekskul = $_POST['ekskul'] ?? [];
        
        // 1. Ambil semua siswa di kelas ini
        $query_siswa_kelas = mysqli_query($koneksi, "SELECT id_siswa FROM siswa WHERE id_kelas = $id_kelas");
        $list_id_siswa = [];
        while ($siswa = mysqli_fetch_assoc($query_siswa_kelas)) {
            $list_id_siswa[] = $siswa['id_siswa'];
        }
        
        // 2. Hapus semua pendaftaran ekskul LAMA untuk semua siswa di kelas ini (Reset total)
        if (!empty($list_id_siswa)) {
            $string_id_siswa = implode(',', $list_id_siswa);
            $query_delete = "DELETE FROM ekskul_peserta WHERE id_siswa IN ($string_id_siswa)";
            mysqli_query($koneksi, $query_delete);
        }
        
        // 3. Masukkan pendaftaran BARU
        if (!empty($pendaftaran_ekskul)) {
            $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO ekskul_peserta (id_siswa, id_ekskul) VALUES (?, ?)");
            foreach ($pendaftaran_ekskul as $id_siswa => $list_ekskul) {
                foreach ((array)$list_ekskul as $id_ekskul) {
                    mysqli_stmt_bind_param($stmt_insert, 'ii', $id_siswa, $id_ekskul);
                    mysqli_stmt_execute($stmt_insert);
                }
            }
            mysqli_stmt_close($stmt_insert);
        }
        
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pendaftaran ekstrakurikuler berhasil diperbarui (Reset Total).']);
    } catch (Exception $exception) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan: ' . $exception->getMessage()]);
    }
    header("Location: walikelas_daftarkan_ekskul.php");
    exit();
}

//======================================================================
// --- AKSI HAPUS PESERTA EKSKUL ---
//======================================================================
elseif ($aksi == 'hapus_peserta_ekskul') {
    // 1. Ambil Parameter
    $id_siswa = (int)$_GET['id_siswa'];
    $id_ekskul = (int)$_GET['id_ekskul'];
    
    // 2. Keamanan: Cek apakah siswa ini benar murid dari wali kelas yang login (atau admin)
    $stmt_cek = mysqli_prepare($koneksi, "
        SELECT s.id_siswa 
        FROM siswa s 
        JOIN kelas k ON s.id_kelas = k.id_kelas 
        WHERE s.id_siswa = ? AND (k.id_wali_kelas = ? OR ? = 'admin')
    ");
    $role_admin_check = $_SESSION['role']; 
    mysqli_stmt_bind_param($stmt_cek, "iis", $id_siswa, $id_wali_kelas, $role_admin_check);
    mysqli_stmt_execute($stmt_cek);
    
    if (mysqli_stmt_get_result($stmt_cek)->num_rows > 0) {
        // 3. Proses Hapus
        
        mysqli_begin_transaction($koneksi);
        try {
            // Ambil id_peserta_ekskul
            $q_peserta = mysqli_prepare($koneksi, "SELECT id_peserta_ekskul FROM ekskul_peserta WHERE id_siswa = ? AND id_ekskul = ?");
            mysqli_stmt_bind_param($q_peserta, "ii", $id_siswa, $id_ekskul);
            mysqli_stmt_execute($q_peserta);
            $id_peserta_ekskul = mysqli_fetch_assoc(mysqli_stmt_get_result($q_peserta))['id_peserta_ekskul'] ?? 0;
            mysqli_stmt_close($q_peserta);

            if ($id_peserta_ekskul > 0) {
                 $stmt_delete = mysqli_prepare($koneksi, "DELETE FROM ekskul_peserta WHERE id_peserta_ekskul = ?");
                 mysqli_stmt_bind_param($stmt_delete, "i", $id_peserta_ekskul);
                 mysqli_stmt_execute($stmt_delete);
                 mysqli_stmt_close($stmt_delete);
            }
            
            mysqli_commit($koneksi);
            $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Siswa berhasil dihapus dari ekskul beserta seluruh nilai dan kehadirannya.']);
            
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Gagal menghapus: ' . $e->getMessage()]);
        }
    } else {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Akses ditolak: Anda tidak memiliki akses ke siswa ini.']);
    }

    // Redirect kembali
    header("Location: walikelas_daftarkan_ekskul.php");
    exit();
}

//======================================================================
// --- AKSI FINALISASI RAPOR (Menggunakan logika Sederhana Anda yang Bekerja) ---
//======================================================================
elseif ($aksi == 'finalisasi_semua') {
    // Ambil info tahun ajaran dan semester aktif
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

    $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
    $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 0;
    $semester_aktif_int = (int)$semester_aktif;

    // Ambil tanggal rapor dari pengaturan
    $q_tgl = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'tanggal_rapor' LIMIT 1");
    $tanggal_rapor_db = mysqli_fetch_assoc($q_tgl);
    $tanggal_rapor = $tanggal_rapor_db ? $tanggal_rapor_db['nilai_pengaturan'] : date('Y-m-d'); // Fallback ke hari ini

    // Ambil data kelas yang diampu
    $q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
    mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
    mysqli_stmt_execute($q_kelas);
    $id_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kelas))['id_kelas'] ?? 0;
    mysqli_stmt_close($q_kelas);
    
    if ($id_kelas == 0) {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Anda tidak terdaftar sebagai wali kelas aktif. (ID Kelas tidak ditemukan)']);
        header('Location: walikelas_cetak_rapor.php');
        exit();
    }

    mysqli_begin_transaction($koneksi);
    try {
        // 1. Ambil SEMUA siswa aktif di kelas tersebut
        $q_siswa_kelas = mysqli_prepare($koneksi, "SELECT id_siswa FROM siswa WHERE id_kelas = ? AND status_siswa = 'Aktif'");
        mysqli_stmt_bind_param($q_siswa_kelas, "i", $id_kelas);
        mysqli_stmt_execute($q_siswa_kelas);
        $result_siswa = mysqli_stmt_get_result($q_siswa_kelas);
        $siswa_list = mysqli_fetch_all($result_siswa, MYSQLI_ASSOC);
        $jumlah_siswa_di_kelas = count($siswa_list);
        mysqli_stmt_close($q_siswa_kelas);
        
        if (empty($siswa_list)) {
            $_SESSION['pesan'] = json_encode(['icon' => 'warning', 'title' => 'Gagal Finalisasi', 'text' => 'Tidak ada siswa aktif di kelas Anda yang dapat difinalisasi.']);
            mysqli_rollback($koneksi);
            header('Location: walikelas_cetak_rapor.php');
            exit();
        }

        // 2. Ambil Daftar Mapel yang relevan
        $q_mapel_relevan = mysqli_prepare($koneksi, "SELECT DISTINCT m.id_mapel, m.nama_mapel, m.urutan FROM mata_pelajaran m JOIN penilaian p ON m.id_mapel = p.id_mapel WHERE p.id_kelas = ? AND p.semester = ? ORDER BY m.urutan");
        mysqli_stmt_bind_param($q_mapel_relevan, "ii", $id_kelas, $semester_aktif_int);
        mysqli_stmt_execute($q_mapel_relevan);
        $daftar_mapel = mysqli_fetch_all(mysqli_stmt_get_result($q_mapel_relevan), MYSQLI_ASSOC);
        mysqli_stmt_close($q_mapel_relevan);


        // 3. Siapkan semua statement SQL (Menggunakan statement Sederhana dari Anda)
        $stmt_rapor_detail_upsert = mysqli_prepare($koneksi, "INSERT INTO rapor_detail_akademik (id_rapor, id_mapel, nilai_akhir, capaian_kompetensi, nilai_katrol) 
            VALUES (?, ?, ?, ?, NULL) ON DUPLICATE KEY UPDATE nilai_akhir = VALUES(nilai_akhir), capaian_kompetensi = VALUES(capaian_kompetensi)");
        $stmt_rapor_ekskul_delete = mysqli_prepare($koneksi, "DELETE FROM rapor_detail_ekskul WHERE id_rapor = ?");
        $stmt_rapor_ekskul_insert = mysqli_prepare($koneksi, "INSERT INTO rapor_detail_ekskul (id_rapor, nama_ekskul, keterangan) VALUES (?, ?, ?)");
        
        // UPSERT Rapor Induk (Sederhana: Hanya update Status & Tanggal)
        $stmt_rapor_upsert = mysqli_prepare($koneksi, "INSERT INTO rapor (id_siswa, id_kelas, id_tahun_ajaran, semester, status, tanggal_rapor) 
            VALUES (?, ?, ?, ?, 'Final', ?) 
            ON DUPLICATE KEY UPDATE status = 'Final', tanggal_rapor = VALUES(tanggal_rapor)");
        $stmt_get_rapor_id = mysqli_prepare($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = ? AND id_tahun_ajaran = ? AND semester = ?");

        // 4. Looping untuk setiap siswa
        $finalisasi_count = 0;
        foreach ($siswa_list as $siswa) {
            $id_siswa = $siswa['id_siswa'];

            // --- A. PROSES RAPOR UTAMA (FINALISASI) ---
            mysqli_stmt_bind_param($stmt_rapor_upsert, "iiiis", $id_siswa, $id_kelas, $id_tahun_ajaran, $semester_aktif, $tanggal_rapor);
            mysqli_stmt_execute($stmt_rapor_upsert);
            
            // Ambil ID Rapor
            mysqli_stmt_bind_param($stmt_get_rapor_id, "iii", $id_siswa, $id_tahun_ajaran, $semester_aktif_int);
            mysqli_stmt_execute($stmt_get_rapor_id);
            $id_rapor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_rapor_id))['id_rapor'] ?? null;

            if (!$id_rapor) {
                throw new Exception("Gagal membuat atau menemukan record rapor untuk siswa ID: $id_siswa");
            }
            $finalisasi_count++;

            // --- B. PROSES NILAI AKADEMIK ---
            $data_akademik = hitungDataRaporSiswa($koneksi, $id_siswa, $id_kelas, $semester_aktif, $kkm, $daftar_mapel);
            foreach ($data_akademik as $id_mapel => $detail) {
                if ($detail['nilai_akhir'] !== null) {
                    mysqli_stmt_bind_param($stmt_rapor_detail_upsert, "iiis", $id_rapor, $id_mapel, $detail['nilai_akhir'], $detail['deskripsi']);
                    mysqli_stmt_execute($stmt_rapor_detail_upsert);
                }
            }

            // --- C. PROSES DESKRIPSI EKSKUL (Menggunakan query plain SQL Anda) ---
            mysqli_stmt_bind_param($stmt_rapor_ekskul_delete, "i", $id_rapor);
            mysqli_stmt_execute($stmt_rapor_ekskul_delete); // Clear existing ekskul details
            
            // Query Ekskul (Menggunakan plain SQL dari logic lama Anda)
            $q_data_ekskul = mysqli_query($koneksi, "
                SELECT e.nama_ekskul,
                        GROUP_CONCAT(CONCAT(t.deskripsi_tujuan, ':', p.nilai) ORDER BY FIELD(p.nilai, 'Sangat Baik', 'Baik', 'Cukup', 'Kurang') SEPARATOR ';') as penilaian
                FROM ekskul_peserta ep
                JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
                LEFT JOIN ekskul_penilaian p ON ep.id_peserta_ekskul = p.id_peserta_ekskul
                LEFT JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul AND t.semester = $semester_aktif_int
                WHERE ep.id_siswa = $id_siswa AND e.id_tahun_ajaran = $id_tahun_ajaran
                GROUP BY e.id_ekskul
            ");

            if ($q_data_ekskul && mysqli_num_rows($q_data_ekskul) > 0) {
                while ($ekskul = mysqli_fetch_assoc($q_data_ekskul)) {
                    $nama_ekskul = $ekskul['nama_ekskul'];
                    $penilaian_list = $ekskul['penilaian'] ? explode(';', $ekskul['penilaian']) : [];
                    $keterangan = "Mengikuti kegiatan dengan baik. ";
                    $nilai_sb = [];
                    $nilai_b = [];

                    if (!empty($penilaian_list[0])) {
                        foreach ($penilaian_list as $item) {
                            list($tujuan, $nilai) = array_pad(explode(':', $item, 2), 2, '');
                            if ($nilai == 'Sangat Baik') {
                                $nilai_sb[] = $tujuan;
                            }
                            if ($nilai == 'Baik') {
                                $nilai_b[] = $tujuan;
                            }
                        }
                    }
                    if (!empty($nilai_sb)) {
                        $keterangan = "Sangat aktif dan menunjukkan penguasaan yang sangat baik dalam " . implode(', ', $nilai_sb) . ".";
                    } elseif (!empty($nilai_b)) {
                        $keterangan = "Aktif dan menunjukkan penguasaan yang baik dalam " . implode(', ', $nilai_b) . ".";
                    }

                    mysqli_stmt_bind_param($stmt_rapor_ekskul_insert, "iss", $id_rapor, $nama_ekskul, $keterangan);
                    mysqli_stmt_execute($stmt_rapor_ekskul_insert);
                }
            }
        } // Akhir loop siswa

        // 5. Tutup semua statement yang dipersiapkan
        mysqli_stmt_close($stmt_rapor_detail_upsert);
        mysqli_stmt_close($stmt_rapor_ekskul_delete);
        mysqli_stmt_close($stmt_rapor_ekskul_insert);
        mysqli_stmt_close($stmt_rapor_upsert);
        mysqli_stmt_close($stmt_get_rapor_id);

        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Proses Selesai', 'text' => "Berhasil memproses dan memfinalisasi rapor untuk {$jumlah_siswa_di_kelas} siswa di kelas Anda."]);

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal Total', 'text' => 'Terjadi kesalahan saat finalisasi: ' . $e->getMessage()]);
    }

    header('Location: walikelas_cetak_rapor.php');
    exit();
}

//======================================================================
// --- AKSI BATALKAN FINALISASI RAPOR (Menggunakan logika Sederhana Anda yang Bekerja) ---
//======================================================================
elseif ($aksi == 'batalkan_finalisasi_semua') {
    // Ambil info tahun ajaran dan semester aktif
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

    $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
    $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 0;
    $semester_aktif_int = (int)$semester_aktif;

    // Ambil data kelas yang diampu
    $q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
    mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
    mysqli_stmt_execute($q_kelas);
    $id_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kelas))['id_kelas'] ?? 0;
    mysqli_stmt_close($q_kelas);

    if ($id_kelas == 0) {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Anda tidak terdaftar sebagai wali kelas aktif.']);
        header('Location: walikelas_cetak_rapor.php');
        exit();
    }

    // 1. Ambil semua ID Rapor yang berstatus 'Final' di kelas ini
    $query_rapor_ids = "
        SELECT r.id_rapor 
        FROM rapor r
        JOIN siswa s ON r.id_siswa = s.id_siswa
        WHERE s.id_kelas = ? AND r.id_tahun_ajaran = ? AND r.semester = ? AND r.status = 'Final'
    ";
    $stmt_ids = mysqli_prepare($koneksi, $query_rapor_ids);
    mysqli_stmt_bind_param($stmt_ids, "iii", $id_kelas, $id_tahun_ajaran, $semester_aktif_int);
    mysqli_stmt_execute($stmt_ids);
    $result_ids = mysqli_stmt_get_result($stmt_ids);
    $rapor_ids_to_revert = [];
    while ($row = mysqli_fetch_assoc($result_ids)) {
        $rapor_ids_to_revert[] = $row['id_rapor'];
    }
    mysqli_stmt_close($stmt_ids);

    if (empty($rapor_ids_to_revert)) {
        $_SESSION['pesan'] = json_encode(['icon' => 'info', 'title' => 'Tidak Ada Perubahan', 'text' => 'Tidak ada rapor yang perlu dibatalkan finalisasinya (semua sudah status Draft).']);
        header("Location: walikelas_cetak_rapor.php");
        exit;
    }

    // Ubah array ID menjadi string untuk query IN
    $id_list = implode(',', $rapor_ids_to_revert);
    $jumlah_baris_terpengaruh = count($rapor_ids_to_revert);

    mysqli_begin_transaction($koneksi);
    try {
        // Hapus Nilai Akademik dan Deskripsi (Nilai Katrol dipertahankan)
        $q_update_detail_akademik = "
            UPDATE rapor_detail_akademik 
            SET 
                nilai_akhir = NULL, 
                capaian_kompetensi = NULL 
            WHERE id_rapor IN ($id_list)
        ";
        
        if (!mysqli_query($koneksi, $q_update_detail_akademik)) {
            throw new Exception("Gagal mereset data akademik lama.");
        }

        // Hapus Detail Ekskul
        $q_delete_detail_ekskul = "DELETE FROM rapor_detail_ekskul WHERE id_rapor IN ($id_list)";
        if (!mysqli_query($koneksi, $q_delete_detail_ekskul)) {
            throw new Exception("Gagal menghapus data ekskul lama.");
        }
        
        // Reset Status Rapor Induk ke 'Draft' dan kosongkan kolom deskripsi umum/tanggal
        $q_update_status_draft = "
            UPDATE rapor 
            SET 
                status = 'Draft', 
                deskripsi_kokurikuler = NULL, 
                deskripsi_ekstrakurikuler = NULL,
                catatan_wali_kelas = NULL,
                tanggal_rapor = NULL
            WHERE id_rapor IN ($id_list)
        ";
        
        if (!mysqli_query($koneksi, $q_update_status_draft)) {
            throw new Exception("Gagal mengupdate status rapor.");
        }

        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Pembatalan Berhasil', 'text' => "Finalisasi {$jumlah_baris_terpengaruh} rapor telah dibatalkan. Data nilai dan deskripsi akademik sudah direset. Mohon finalisasi ulang jika diperlukan."]);

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal Batalkan', 'text' => 'Terjadi kesalahan saat pembatalan: ' . $e->getMessage()]);
    }
    
    header('Location: walikelas_cetak_rapor.php');
    exit();
}

//======================================================================
// --- AKSI TIDAK DIKENAL (Fallback) ---
//======================================================================
else {
    header('HTTP/1.0 400 Bad Request');
    exit("Aksi tidak dikenal.");
}
?>