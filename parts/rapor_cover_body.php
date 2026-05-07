<?php
// =======================================================================
// TEMPLATE SAMPUL RAPOR (INCLUDED FILE)
// Dipanggil oleh: rapor_cetak_massal.php
// =======================================================================

// 1. AMBIL DATA SISWA UNTUK SAMPUL
$q_siswa_cover = mysqli_prepare($koneksi, "SELECT nama_lengkap, nis, nisn FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa_cover, "i", $id_siswa);
mysqli_stmt_execute($q_siswa_cover);
$d_cover = mysqli_fetch_assoc(mysqli_stmt_get_result($q_siswa_cover));

if (!$d_cover) return;

// 2. AMBIL DATA SEKOLAH & JENJANG
// [UPDATE] Menambahkan 'nama_sekolah' ke dalam query
$q_sek_cover = mysqli_query($koneksi, "SELECT jenjang, logo_sekolah, nama_sekolah FROM sekolah WHERE id_sekolah = 1");
$d_sek_cover = mysqli_fetch_assoc($q_sek_cover);

$jenjang = strtoupper($d_sek_cover['jenjang'] ?? 'SD');
$teks_jenjang = ($jenjang == 'SMP') ? 'SEKOLAH MENENGAH PERTAMA' : 'SEKOLAH DASAR';
$singkatan_jenjang = ($jenjang == 'SMP') ? 'SMP' : 'SD';

// 3. PERSIAPAN GAMBAR (MENGGUNAKAN FUNGSI GLOBAL)
$logo_kab_html = '';
if (file_exists('uploads/logo_kabupaten.png')) {
    if (function_exists('get_img_base64_local')) {
        $img_kab = get_img_base64_local('logo_kabupaten.png'); // Tanpa uploads/ karena fungsi sudah handle
        $logo_kab_html = '<img src="'.$img_kab.'" style="height: 120px; width: auto;">';
    }
}

$logo_sek_html = '';
if (!empty($d_sek_cover['logo_sekolah'])) {
    if (function_exists('get_img_base64_local')) {
        // Cek path, jika di DB cuma nama file, tambah folder. Jika function handle folder, sesuaikan.
        // Di main controller: get_img_base64_local($path) -> 'uploads/' . $path
        $img_sek = get_img_base64_local($d_sek_cover['logo_sekolah']);
        $logo_sek_html = '<img src="'.$img_sek.'" style="height: 160px; width: auto;">';
    }
}
?>

<!-- HTML CONTENT START -->
<div class="container" style="text-align: center; font-family: 'Times New Roman', serif; padding-top: 20px;">
    
    <!-- 1. LOGO KABUPATEN/GARUDA (ATAS) -->
    <div style="margin-bottom: 30px;">
        <?php echo $logo_kab_html; ?>
        <?php if (empty($logo_kab_html)): ?>
            <div style="height: 100px;"></div> <!-- Spacer jika logo kosong -->
        <?php endif; ?>
    </div>

    <!-- 2. JUDUL RAPOR -->
    <div style="margin-bottom: 40px;">
        <h1 style="font-size: 26pt; font-weight: bold; margin: 0; letter-spacing: 2px;">RAPOR</h1>
        <h2 style="font-size: 16pt; font-weight: bold; margin-top: 5px; margin-bottom: 20px;">
            <?php echo $teks_jenjang; ?>
        </h2>
        
        <!-- NAMA SEKOLAH -->
        <h2 style="font-size: 24pt; font-weight: bold; margin-top: 20px; margin-bottom: 0; text-transform: uppercase;">
            <?php echo htmlspecialchars($d_sek_cover['nama_sekolah'] ?? 'NAMA SEKOLAH'); ?>
        </h2>
    </div>

    <!-- 3. LOGO SEKOLAH (TENGAH) -->
    <div style="margin: 40px 0;">
        <?php echo $logo_sek_html; ?>
        <?php if (empty($logo_sek_html)): ?>
            <div style="height: 150px; border: 1px dashed #ccc; width: 150px; margin: 0 auto; line-height: 150px; color: #ccc;">Logo Sekolah</div>
        <?php endif; ?>
    </div>

    <!-- 4. NAMA PESERTA DIDIK -->
    <div style="margin-top: 40px;">
        <p style="font-size: 12pt; margin-bottom: 10px;">Nama Peserta Didik:</p>
        
        <!-- KOTAK NAMA -->
        <div style="border: 2px solid #000; padding: 15px; width: 70%; margin: 0 auto; border-radius: 5px;">
            <span style="font-size: 18pt; font-weight: bold; text-transform: uppercase;">
                <?php echo htmlspecialchars($d_cover['nama_lengkap']); ?>
            </span>
        </div>
    </div>

    <!-- 5. NIS / NISN -->
    <div style="margin-top: 20px;">
        <p style="font-size: 12pt; margin-bottom: 5px;">NIS / NISN:</p>
        <p style="font-size: 14pt; font-weight: bold;">
            <?php echo htmlspecialchars(($d_cover['nis']??'-') . ' / ' . ($d_cover['nisn']??'-')); ?>
        </p>
    </div>

    <!-- 6. FOOTER KEMENTERIAN (BAWAH) -->
    <div style="position: fixed; bottom: 80px; left: 0; right: 0; text-align: center;">
        <div style="font-size: 14pt; font-weight: bold;">
            KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH<br>
            REPUBLIK INDONESIA
        </div>
    </div>

</div>
<!-- HTML CONTENT END -->