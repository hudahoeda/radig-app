<?php
// Hapus baris ini jika aplikasi sudah online/production
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

session_start();
include 'koneksi.php';

// Validasi role Wali Kelas atau Admin
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    die("Akses ditolak.");
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id_wali_kelas = $_SESSION['id_guru'];

// Ambil KKM (dibutuhkan untuk fungsi hitungDataRaporSiswa)
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm_db = mysqli_fetch_assoc($q_kkm);
$kkm = $kkm_db ? (int)$kkm_db['nilai_pengaturan'] : 75;


// ======================================================================
// [BARU] FUNGSI DARI `walikelas_proses_rapor.php` DICOPY KE SINI
// ======================================================================
/**
 * Menghitung data rapor siswa per mata pelajaran sesuai dengan Panduan Pembelajaran dan Asesmen (PPA) 2025.
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

        // Proses Data dari Query 1 (Sumatif TP)
        mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_tp);
        $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
        while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
            $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
            foreach($tps_individu as $desc_tp) {
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
            $total_nilai_x_bobot += $d_nilai['nilai'] * $d_nilai['bobot_penilaian'];
            $total_bobot += $d_nilai['bobot_penilaian'];
        }

        // Proses Data dari Query 2 (Sumatif Akhir)
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

        // == BLOK PEMBUATAN DESKRIPSI SESUAI PANDUAN PPA 2025 ==
        $deskripsi_final = '';
        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
            $tp_dikuasai = []; 
            $tp_perlu_peningkatan = [];
            
            foreach ($skor_per_tp as $deskripsi => $skor_array) {
                $rata_rata_tp = array_sum($skor_array) / count($skor_array);
                $deskripsi_bersih = lcfirst(trim(str_replace(['Peserta didik dapat', 'peserta didik mampu', 'mampu'], '', $deskripsi)));
                
                if ($rata_rata_tp >= $kkm) {
                    $tp_dikuasai[] = $deskripsi_bersih;
                } else {
                    $tp_perlu_peningkatan[] = $deskripsi_bersih;
                }
            }

            $deskripsi_draf = "";
            if (!empty($tp_dikuasai)) { 
                $deskripsi_draf .= "Menunjukkan penguasaan yang baik dalam " . implode(', ', array_unique($tp_dikuasai)) . ". "; 
            }
            if (!empty($tp_perlu_peningkatan)) { 
                $deskripsi_draf .= "Perlu penguatan dalam " . implode(', ', array_unique($tp_perlu_peningkatan)) . "."; 
            }
            $deskripsi_final = (empty(trim($deskripsi_draf))) ? 'Capaian kompetensi sudah baik pada seluruh materi.' : ucfirst(trim($deskripsi_draf));
        
        } elseif ($nilai_akhir !== null) {
            $deskripsi_final = 'Capaian kompetensi secara umum sudah menunjukkan ketuntasan yang baik.';
        }

        $data_rapor_siswa[$id_mapel] = [
            'nilai_akhir' => $nilai_akhir, 
            'deskripsi' => $deskripsi_final,
            'komponen_nilai' => $komponen_nilai,
            'rumus_perhitungan' => $rumus_perhitungan
        ];
    }
    // Tutup statement yang disiapkan
    mysqli_stmt_close($stmt_sumatif_tp);
    mysqli_stmt_close($stmt_sumatif_akhir);
    
    return $data_rapor_siswa;
}
// ======================================================================
// [AKHIR FUNGSI YANG DICOPY]
// ======================================================================


//======================================================================
// --- AKSI UPDATE IDENTITAS SISWA (METODE OTOMATIS FINAL) ---
//======================================================================
if ($aksi == 'update_siswa') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('HTTP/1.0 405 Method Not Allowed');
        exit;
    }

    $id_siswa = (int)$_POST['id_siswa'];
    // [PERBAIKAN] Ambil foto lama dari input hidden, BUKAN query
    $foto_lama = $_POST['foto_siswa_lama'] ?? null; 

    // Keamanan: Pastikan wali kelas berhak mengedit siswa ini
    $stmt_check = mysqli_prepare($koneksi, "SELECT s.id_siswa, s.foto_siswa FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas WHERE s.id_siswa = ? AND k.id_wali_kelas = ?");
    mysqli_stmt_bind_param($stmt_check, "ii", $id_siswa, $id_wali_kelas);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    $data_siswa_db = mysqli_fetch_assoc($result_check);

    if (!$data_siswa_db && $_SESSION['role'] != 'admin') { // Admin boleh edit semua
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Aksi Gagal', 'text' => 'Aksi tidak diizinkan.']);
        header("Location: walikelas_identitas_siswa.php");
        exit;
    }
    
    // [PERBAIKAN] Gunakan foto dari database jika input hidden kosong (fallback)
    if(empty($foto_lama) && isset($data_siswa_db['foto_siswa'])) {
        $foto_lama = $data_siswa_db['foto_siswa'];
    }

    // Proses upload foto
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
                if ($foto_lama && file_exists($upload_dir . $foto_lama)) {
                    @unlink($upload_dir . $foto_lama); // Gunakan @ untuk menekan error jika file tidak ada
                }
            } else {
                 $nama_file_foto_final = $foto_lama; // Gagal upload, gunakan foto lama
            }
        } else {
            // File tidak valid (ekstensi/ukuran), tetap gunakan foto lama
            $nama_file_foto_final = $foto_lama;
        }
    }

    // Siapkan query
    $query = "UPDATE siswa SET 
                nama_lengkap = ?, nis = ?, nisn = ?, jenis_kelamin = ?, tempat_lahir = ?, 
                tanggal_lahir = ?, agama = ?, status_dalam_keluarga = ?, anak_ke = ?, alamat = ?, 
                telepon_siswa = ?, sekolah_asal = ?, diterima_tanggal = ?, nama_ayah = ?, pekerjaan_ayah = ?, 
                nama_ibu = ?, pekerjaan_ibu = ?, nama_wali = ?, alamat_wali = ?, telepon_wali = ?, 
                pekerjaan_wali = ?, foto_siswa = ? 
              WHERE id_siswa = ?";

    // Siapkan data dalam array, urutan HARUS SAMA dengan '?' di query
    $data_siswa_update = [
        trim($_POST['nama_lengkap']),
        trim($_POST['nis']),
        trim($_POST['nisn']),
        $_POST['jenis_kelamin'],
        trim($_POST['tempat_lahir']),
        !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null, // Tangani tanggal kosong
        trim($_POST['agama']),
        trim($_POST['status_dalam_keluarga']),
        !empty($_POST['anak_ke']) ? (int)$_POST['anak_ke'] : null,
        trim($_POST['alamat']),
        trim($_POST['telepon_siswa']),
        trim($_POST['sekolah_asal']),
        !empty($_POST['diterima_tanggal']) ? $_POST['diterima_tanggal'] : null, // Tangani tanggal kosong
        trim($_POST['nama_ayah']),
        trim($_POST['pekerjaan_ayah']),
        trim($_POST['nama_ibu']),
        trim($_POST['pekerjaan_ibu']),
        trim($_POST['nama_wali']),
        trim($_POST['alamat_wali']),
        trim($_POST['telepon_wali']),
        trim($_POST['pekerjaan_wali']),
        $nama_file_foto_final,
        $id_siswa
    ];

    // --- PERBAIKAN FINAL: BUAT TIPE DATA SECARA OTOMATIS ---
    $tipe_data = '';
    foreach ($data_siswa_update as $value) {
        if (is_int($value)) {
            $tipe_data .= 'i';
        } elseif (is_double($value)) {
            $tipe_data .= 'd';
        } else {
            // default ke string untuk null atau string
            $tipe_data .= 's';
        }
    }
    
    // Pastikan jumlah tipe data cocok dengan jumlah parameter
    if (strlen($tipe_data) != count($data_siswa_update)) {
         $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Error', 'text' => 'Tipe data parameter tidak cocok.']);
         header("Location: walikelas_edit_siswa.php?id_siswa=" . $id_siswa);
         exit;
    }


    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, $tipe_data, ...$data_siswa_update);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => "Data siswa '" . htmlspecialchars($data_siswa_update[0]) . "' berhasil diperbarui."]);
    } else {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan saat memperbarui data: ' . mysqli_stmt_error($stmt)]);
    }

    header("Location: walikelas_edit_siswa.php?id_siswa=" . $id_siswa);
    exit;
}

//======================================================================
// --- KODE LAMA ANDA YANG SUDAH BERFUNGSI DENGAN BAIK ---
//======================================================================
elseif ($aksi == 'simpan_data') {
    $absensi_data = $_POST['absensi'];
    $catatan_data = $_POST['catatan'];

    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

    $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
    $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

    $q_kelas = mysqli_query($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = $id_wali_kelas AND id_tahun_ajaran = $id_tahun_ajaran");
    $id_kelas = mysqli_fetch_assoc($q_kelas)['id_kelas'];

    mysqli_begin_transaction($koneksi);
    try {
        foreach ($absensi_data as $id_siswa => $data_absen) {
            $sakit = (int)$data_absen['sakit'];
            $izin = (int)$data_absen['izin'];
            $alpha = (int)$data_absen['tanpa_keterangan'];
            $catatan = $catatan_data[$id_siswa];
            
            $q_cek_rapor = mysqli_prepare($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ?");
            mysqli_stmt_bind_param($q_cek_rapor, "isi", $id_siswa, $semester_aktif, $id_tahun_ajaran);
            mysqli_stmt_execute($q_cek_rapor);
            $result_rapor = mysqli_stmt_get_result($q_cek_rapor);
            
            if (mysqli_num_rows($result_rapor) > 0) {
                $d_rapor = mysqli_fetch_assoc($result_rapor);
                $id_rapor = $d_rapor['id_rapor'];
                $stmt = mysqli_prepare($koneksi, "UPDATE rapor SET sakit=?, izin=?, tanpa_keterangan=?, catatan_wali_kelas=? WHERE id_rapor=?");
                mysqli_stmt_bind_param($stmt, "iiisi", $sakit, $izin, $alpha, $catatan, $id_rapor);
                mysqli_stmt_execute($stmt);
            } else {
                $stmt = mysqli_prepare($koneksi, "INSERT INTO rapor (id_siswa, id_kelas, id_tahun_ajaran, semester, sakit, izin, tanpa_keterangan, catatan_wali_kelas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iiiiiiis", $id_siswa, $id_kelas, $id_tahun_ajaran, $semester_aktif, $sakit, $izin, $alpha, $catatan);
                mysqli_stmt_execute($stmt);
            }
        }
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data absensi dan catatan berhasil disimpan.']);
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi error: ' . $e->getMessage()]);
    }
    
    header("location: walikelas_data_rapor.php");
    exit();
}
//... (Aksi 'simpan_akademik' dan 'simpan_kokurikuler' yang lama tidak disertakan karena digabung ke finalisasi) ...
elseif ($aksi == 'simpan_pendaftaran_ekskul') {
    mysqli_begin_transaction($koneksi);
    try {
        $id_kelas = (int)$_POST['id_kelas'];
        $pendaftaran_ekskul = $_POST['ekskul'] ?? [];
        $query_siswa_kelas = mysqli_query($koneksi, "SELECT id_siswa FROM siswa WHERE id_kelas = $id_kelas");
        $list_id_siswa = [];
        while ($siswa = mysqli_fetch_assoc($query_siswa_kelas)) {
            $list_id_siswa[] = $siswa['id_siswa'];
        }
        if (!empty($list_id_siswa)) {
            $string_id_siswa = implode(',', $list_id_siswa);
            $query_delete = "DELETE FROM ekskul_peserta WHERE id_siswa IN ($string_id_siswa)";
            mysqli_query($koneksi, $query_delete);
        }
        if (!empty($pendaftaran_ekskul)) {
            $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO ekskul_peserta (id_siswa, id_ekskul) VALUES (?, ?)");
            foreach ($pendaftaran_ekskul as $id_siswa => $list_ekskul) {
                foreach ($list_ekskul as $id_ekskul) {
                    mysqli_stmt_bind_param($stmt_insert, 'ii', $id_siswa, $id_ekskul);
                    mysqli_stmt_execute($stmt_insert);
                }
            }
        }
        mysqli_commit($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => 'Data pendaftaran ekstrakurikuler berhasil diperbarui.']);
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($koneksi);
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan: ' . $exception->getMessage()]);
    }
    header("Location: walikelas_daftarkan_ekskul.php");
    exit();
}
//======================================================================
// --- [MODIFIKASI BESAR] BLOK UNTUK FINALISASI RAPOR ---
//======================================================================
elseif ($aksi == 'finalisasi_semua') {
    // Ambil info tahun ajaran dan semester aktif
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

    $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
    $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

    // Ambil tanggal rapor dari pengaturan
    $q_tgl = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'tanggal_rapor' LIMIT 1");
    $tanggal_rapor_db = mysqli_fetch_assoc($q_tgl);
    $tanggal_rapor = $tanggal_rapor_db ? $tanggal_rapor_db['nilai_pengaturan'] : date('Y-m-d'); // Fallback ke hari ini

    // Ambil data kelas yang diampu
    $q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
    mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
    mysqli_stmt_execute($q_kelas);
    $id_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kelas))['id_kelas'] ?? 0;

    if ($id_kelas == 0) {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Anda tidak terdaftar sebagai wali kelas aktif.']);
        header('Location: walikelas_cetak_rapor.php');
        exit();
    }

    // --- LOGIKA BARU: MENGGABUNGKAN PROSES NILAI & FINALISASI ---
    
    mysqli_begin_transaction($koneksi);
    try {
        // 1. Ambil SEMUA siswa aktif di kelas tersebut
        $q_siswa_kelas = mysqli_prepare($koneksi, "SELECT id_siswa FROM siswa WHERE id_kelas = ? AND status_siswa = 'Aktif'");
        mysqli_stmt_bind_param($q_siswa_kelas, "i", $id_kelas);
        mysqli_stmt_execute($q_siswa_kelas);
        $result_siswa = mysqli_stmt_get_result($q_siswa_kelas);
        $jumlah_siswa_di_kelas = mysqli_num_rows($result_siswa);

        // 2. Ambil Daftar Mapel yang relevan (ada penilaian di kelas & semester ini)
        $q_mapel_relevan = mysqli_prepare($koneksi, "SELECT DISTINCT m.id_mapel, m.nama_mapel, m.urutan FROM mata_pelajaran m JOIN penilaian p ON m.id_mapel = p.id_mapel WHERE p.id_kelas = ? AND p.semester = ? ORDER BY m.urutan");
        mysqli_stmt_bind_param($q_mapel_relevan, "ii", $id_kelas, $semester_aktif);
        mysqli_stmt_execute($q_mapel_relevan);
        $daftar_mapel = mysqli_fetch_all(mysqli_stmt_get_result($q_mapel_relevan), MYSQLI_ASSOC);
        
        // 3. Siapkan semua statement SQL yang akan dipakai berulang kali
        $stmt_rapor_detail_upsert = mysqli_prepare($koneksi, "INSERT INTO rapor_detail_akademik (id_rapor, id_mapel, nilai_akhir, capaian_kompetensi) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nilai_akhir = VALUES(nilai_akhir), capaian_kompetensi = VALUES(capaian_kompetensi)");
        $stmt_rapor_ekskul_delete = mysqli_prepare($koneksi, "DELETE FROM rapor_detail_ekskul WHERE id_rapor = ?");
        $stmt_rapor_ekskul_insert = mysqli_prepare($koneksi, "INSERT INTO rapor_detail_ekskul (id_rapor, nama_ekskul, keterangan) VALUES (?, ?, ?)");
        $stmt_rapor_upsert = mysqli_prepare($koneksi, "INSERT INTO rapor (id_siswa, id_kelas, id_tahun_ajaran, semester, status, tanggal_rapor) 
                     VALUES (?, ?, ?, ?, 'Final', ?) 
                     ON DUPLICATE KEY UPDATE 
                     status = 'Final', tanggal_rapor = VALUES(tanggal_rapor)");
        $stmt_get_rapor_id = mysqli_prepare($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = ? AND id_tahun_ajaran = ? AND semester = ?");


        // 4. Looping untuk setiap siswa
        while ($siswa = mysqli_fetch_assoc($result_siswa)) {
            $id_siswa = $siswa['id_siswa'];
            
            // --- A. PROSES RAPOR UTAMA (FINALISASI) ---
            // Eksekusi UPSERT untuk finalisasi. Ini juga akan MEMBUAT record rapor jika belum ada.
            mysqli_stmt_bind_param($stmt_rapor_upsert, "iiiis", $id_siswa, $id_kelas, $id_tahun_ajaran, $semester_aktif, $tanggal_rapor);
            mysqli_stmt_execute($stmt_rapor_upsert);
            
            // Ambil ID Rapor (bisa yang baru dibuat atau yang sudah ada)
            mysqli_stmt_bind_param($stmt_get_rapor_id, "iii", $id_siswa, $id_tahun_ajaran, $semester_aktif);
            mysqli_stmt_execute($stmt_get_rapor_id);
            $id_rapor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_rapor_id))['id_rapor'];
            
            if (!$id_rapor) {
                // Ini seharusnya tidak terjadi karena UPSERT di atas, tapi sebagai pengaman
                throw new Exception("Gagal membuat atau menemukan record rapor untuk siswa ID: $id_siswa");
            }

            // --- B. PROSES NILAI AKADEMIK ---
            $data_akademik = hitungDataRaporSiswa($koneksi, $id_siswa, $id_kelas, $semester_aktif, $kkm, $daftar_mapel);
            foreach ($data_akademik as $id_mapel => $detail) {
                if ($detail['nilai_akhir'] !== null) {
                    mysqli_stmt_bind_param($stmt_rapor_detail_upsert, "iiis", $id_rapor, $id_mapel, $detail['nilai_akhir'], $detail['deskripsi']);
                    mysqli_stmt_execute($stmt_rapor_detail_upsert);
                }
            }
            
            // --- C. PROSES DESKRIPSI EKSKUL (dari walikelas_generate_deskripsi_aksi.php) ---
            mysqli_stmt_bind_param($stmt_rapor_ekskul_delete, "i", $id_rapor);
            mysqli_stmt_execute($stmt_rapor_ekskul_delete);
            
            $q_data_ekskul = mysqli_query($koneksi, "
                SELECT e.nama_ekskul,
                       GROUP_CONCAT(CONCAT(t.deskripsi_tujuan, ':', p.nilai) ORDER BY FIELD(p.nilai, 'Sangat Baik', 'Baik', 'Cukup', 'Kurang') SEPARATOR ';') as penilaian
                FROM ekskul_peserta ep
                JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
                LEFT JOIN ekskul_penilaian p ON ep.id_peserta_ekskul = p.id_peserta_ekskul
                LEFT JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul AND t.semester = $semester_aktif
                WHERE ep.id_siswa = $id_siswa AND e.id_tahun_ajaran = $id_tahun_ajaran
                GROUP BY e.id_ekskul
            ");

            if ($q_data_ekskul && mysqli_num_rows($q_data_ekskul) > 0) {
                while($ekskul = mysqli_fetch_assoc($q_data_ekskul)) {
                    $nama_ekskul = $ekskul['nama_ekskul'];
                    $penilaian_list = $ekskul['penilaian'] ? explode(';', $ekskul['penilaian']) : [];
                    $keterangan = "Mengikuti kegiatan dengan baik. ";
                    $nilai_sb = []; $nilai_b = [];
                    
                    if(!empty($penilaian_list[0])){
                        foreach ($penilaian_list as $item) {
                            list($tujuan, $nilai) = array_pad(explode(':', $item, 2), 2, '');
                            if ($nilai == 'Sangat Baik') $nilai_sb[] = $tujuan;
                            if ($nilai == 'Baik') $nilai_b[] = $tujuan;
                        }
                    }
                    if (!empty($nilai_sb)) $keterangan = "Sangat aktif dan menunjukkan penguasaan yang sangat baik dalam " . implode(', ', $nilai_sb) . ".";
                    elseif (!empty($nilai_b)) $keterangan = "Aktif dan menunjukkan penguasaan yang baik dalam " . implode(', ', $nilai_b) . ".";

                    mysqli_stmt_bind_param($stmt_rapor_ekskul_insert, "iss", $id_rapor, $nama_ekskul, $keterangan);
                    mysqli_stmt_execute($stmt_rapor_ekskul_insert);
                }
            }
        } // Akhir loop siswa
        
        // 5. Commit transaksi
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
// --- BLOK BARU UNTUK MEMBATALKAN FINALISASI RAPOR ---
//======================================================================
elseif ($aksi == 'batalkan_finalisasi_semua') {
    // Ambil info tahun ajaran dan semester aktif
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

    $q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
    $semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

    // Ambil data kelas yang diampu
    $q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
    mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
    mysqli_stmt_execute($q_kelas);
    $id_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kelas))['id_kelas'] ?? 0;

    if ($id_kelas == 0) {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Anda tidak terdaftar sebagai wali kelas aktif.']);
        header('Location: walikelas_cetak_rapor.php');
        exit();
    }

    // Query untuk update status kembali ke Draft dan menghapus tanggal rapor
    $stmt_batal = mysqli_prepare($koneksi, "UPDATE rapor SET status = 'Draft', tanggal_rapor = NULL WHERE id_kelas = ? AND id_tahun_ajaran = ? AND semester = ? AND status = 'Final'");
    mysqli_stmt_bind_param($stmt_batal, "iii", $id_kelas, $id_tahun_ajaran, $semester_aktif);
    
    if (mysqli_stmt_execute($stmt_batal)) {
        $jumlah_baris_terpengaruh = mysqli_stmt_affected_rows($stmt_batal);
        $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => "Berhasil membatalkan finalisasi. {$jumlah_baris_terpengaruh} rapor siswa kini kembali ke status Draft."]);
    } else {
        $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Terjadi kesalahan saat membatalkan: ' . mysqli_error($koneksi)]);
    }
    
    header('Location: walikelas_cetak_rapor.php');
    exit();
}
// --- AKHIR BLOK BARU ---

else {
    // Aksi tidak dikenal
    $_SESSION['pesan'] = json_encode(['icon' => 'warning', 'title' => 'Aksi Tidak Dikenal', 'text' => 'Aksi yang diminta tidak valid.']);
    header("location: dashboard.php");
    exit();
}
?>