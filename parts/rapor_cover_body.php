<?php
// File ini dipanggil oleh rapor_cetak_massal.php
// Variabel $koneksi dan $id_siswa sudah tersedia dari file tersebut.

// Ambil data siswa yang diperlukan
$q_siswa_cover = mysqli_prepare($koneksi, "SELECT nama_lengkap, nis, nisn FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa_cover, "i", $id_siswa);
mysqli_stmt_execute($q_siswa_cover);
$siswa_cover_result = mysqli_stmt_get_result($q_siswa_cover);

if (!$siswa_cover_result || mysqli_num_rows($siswa_cover_result) === 0) {
    echo "<p>Data siswa dengan ID $id_siswa tidak ditemukan.</p>";
    return;
}
$siswa_cover = mysqli_fetch_assoc($siswa_cover_result);

// --- Ambil data sekolah (termasuk jenjang dan logo) ---
$q_sekolah_cover = mysqli_query($koneksi, "SELECT jenjang, logo_sekolah FROM sekolah WHERE id_sekolah = 1");
$sekolah_cover = mysqli_fetch_assoc($q_sekolah_cover);

$jenjang_sekolah = strtoupper($sekolah_cover['jenjang'] ?? 'SD'); // Default ke SD
$nama_jenjang_lengkap = ($jenjang_sekolah == 'SD') ? 'SEKOLAH DASAR' : 'SEKOLAH MENENGAH PERTAMA';
$nama_jenjang_singkat = ($jenjang_sekolah == 'SD') ? 'SD' : 'SMP';
// --- AKHIR PENGAMBILAN DATA SEKOLAH ---


// Fungsi untuk mengubah gambar menjadi base64
if (!function_exists('toBase64')) {
    function toBase64($path) {
        // Tambahkan @ agar tidak muncul warning jika file not found
        if (!@file_exists($path) || !@is_readable($path)) return '';
        try {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            // Pastikan tipe file adalah gambar yang didukung
            if(!in_array(strtolower($type), ['png', 'jpg', 'jpeg', 'gif'])) return '';
            $data = @file_get_contents($path);
            if ($data === false) return '';
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        } catch (\Exception $e) {
            error_log("Error converting image to base64: " . $e->getMessage());
            return '';
        }
    }
}

// --- Path logo dinamis ---
$logo_kabupaten = toBase64('uploads/logo_kabupaten.png'); // Asumsi path tetap
$logo_sekolah_path = !empty($sekolah_cover['logo_sekolah']) ? 'uploads/' . $sekolah_cover['logo_sekolah'] : 'uploads/logosatap.png'; // Fallback
$logo_sekolah = toBase64($logo_sekolah_path);
// --- AKHIR LOGO ---
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cover Rapor - <?php echo htmlspecialchars($siswa_cover['nama_lengkap']); ?></title>
    <!-- GANTI STYLE: Menggunakan CSS dari rapor_cover.php -->
    <style>
        @page { margin: 0; }
        body { font-family: 'Times New Roman', Times, serif; text-align: center; padding: 30px; } /* Tambah padding dasar */
        
        /* Container utama dengan border dan page-break */
        .container { 
            padding: 40px; 
            border: 3px double black; 
            height: 90%; /* Tinggi relatif */
            
            /* --- PERBAIKAN: Halaman Kosong --- */
            /* Variabel $is_last_page (boolean) diharapkan di-set oleh file pemanggil (rapor_cetak_massal.php)
              Jika ini BUKAN halaman terakhir, paksa pindah halaman.
            */
            <?php
            if (!isset($is_last_page) || $is_last_page === false) {
                echo "page-break-after: always;";
            } else {
                // Jika ini halaman terakhir, JANGAN pindah halaman.
                echo "page-break-after: auto;";
            }
            ?>
            /* --- AKHIR PERBAIKAN --- */
        }

        /* Ukuran logo disesuaikan */
        .logo-kabupaten { width: 100px; margin-bottom: 15px; }
        .logo-sekolah { width: 90px; margin-bottom: 15px; }

        h1 { font-size: 26pt; margin: 20px 0 5px 0; } /* Sedikit margin atas bawah */
        h2 { font-size: 18pt; margin: 0 0 25px 0; font-weight: normal; line-height: 1.3; } /* Spasi antar baris */

        .nama-siswa-container { margin-top: 60px; margin-bottom: 10px;} /* Jarak atas/bawah container nama */
        .nama-siswa-box {
            display: inline-block;
            border: 2px solid black;
            padding: 8px 15px; /* Padding lebih kecil */
            margin-top: 5px; /* Jarak dari label */
            min-width: 60%; /* Lebar minimum box nama */
            max-width: 80%; /* Lebar maksimum */
        }
        .nama-siswa-box h3 { /* Ubah dari h2 ke h3 */
             font-weight: bold;
             margin:0;
             font-size: 16pt; /* Ukuran font nama */
             word-wrap: break-word; /* Agar nama panjang bisa wrap */
        }

        .nisn-container { margin-top: 30px; margin-bottom: 60px;} /* Jarak atas/bawah container NISN */
        .nisn-label { font-size: 14pt; margin-bottom: 5px;}
        .nisn-value { font-size: 14pt; font-weight: bold; } /* Buat NISN bold */

        .footer-text {
            /* Hapus absolute positioning agar lebih fleksibel di dlm container */
            margin-top: 60px; /* Beri jarak dari NISN */
            font-size: 12pt; /* Perkecil font footer */
            font-weight: bold;
            line-height: 1.4; /* Spasi antar baris footer */
        }
    </style>
</head>
<body>
    <!-- GANTI BODY: Menggunakan struktur HTML dari rapor_cover.php -->
    <div class="container">
        
        <!-- Bagian Atas: Logo dan Judul -->
        <div>
            <?php if ($logo_kabupaten): ?>
                <img src="<?php echo $logo_kabupaten; ?>" class="logo-kabupaten" alt="Logo Kabupaten">
            <?php else: ?>
                 <div style="height: 100px; margin-bottom: 15px;"></div> 
            <?php endif; ?>

            <h1>RAPOR</h1>
            
            <h2><?php echo $nama_jenjang_lengkap; ?> <br> (<?php echo $nama_jenjang_singkat; ?>) </h2>
            
            <?php if ($logo_sekolah): ?>
                <img src="<?php echo $logo_sekolah; ?>" class="logo-sekolah" alt="Logo Sekolah">
            <?php else: ?>
                 <div style="height: 90px; margin-bottom: 15px;"></div> 
            <?php endif; ?>
        </div>

        <!-- Bagian Tengah: Info Siswa -->
        <div>
            <div class="nama-siswa-container">
                <p style="font-size: 14pt; margin: 0;">Nama Peserta Didik:</p>
                <div class="nama-siswa-box">
                    <!-- Gunakan variabel $siswa_cover -->
                    <h3><?php echo strtoupper(htmlspecialchars($siswa_cover['nama_lengkap'])); ?></h3>
                </div>
            </div>

            <div class="nisn-container">
                <p class="nisn-label">NIS / NISN:</p>
                <!-- Gunakan variabel $siswa_cover -->
                <p class="nisn-value"><?php echo htmlspecialchars($siswa_cover['nis'] ?? '-'); ?> / <?php echo htmlspecialchars($siswa_cover['nisn']); ?></p>
            </div>
        </div>

        <!-- Bagian Bawah: Footer -->
        <div class="footer-text">
            <p>KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH</p>
            <p>REPUBLIK INDONESIA</p>
        </div>
        
    </div>
</body>
</html>