<?php
// =======================================================================
// TEMPLATE IDENTITAS SISWA (BODY MASSAL) - FIXED FOTO
// =======================================================================

// 1. AMBIL DATA SISWA
$q_identitas = mysqli_prepare($koneksi, "SELECT * FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_identitas, "i", $id_siswa);
mysqli_stmt_execute($q_identitas);
$d_sis = mysqli_fetch_assoc(mysqli_stmt_get_result($q_identitas));

if (!$d_sis) return; // Skip jika data kosong

// 2. FUNGSI TANGGAL (Cek if exists agar tidak error saat loop)
if (!function_exists('tanggal_indo_massal')) {
    function tanggal_indo_massal($tanggal) {
        if(empty($tanggal) || $tanggal == '0000-00-00') return "-";
        $bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
        $pecahkan = explode('-', $tanggal);
        if(count($pecahkan) != 3) return $tanggal;
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }
}

// 3. LOGIKA TANGGAL TANDA TANGAN
$tgl_ttd_raw = !empty($d_sis['diterima_tanggal']) ? $d_sis['diterima_tanggal'] : date('Y-m-d');
$tgl_ttd_fix = tanggal_indo_massal($tgl_ttd_raw);

// 4. LOGIKA FOTO (PERBAIKAN UTAMA DI SINI)
$foto_html = ''; 

// Tentukan folder upload menggunakan Path Absolut Sistem (Lebih aman untuk PDF)
// Asumsi: folder 'uploads' sejajar dengan file script ini. 
// Jika struktur folder berbeda, sesuaikan __DIR__ . '/../uploads/' dst.
$base_dir = __DIR__ . '/../uploads/foto_siswa/'; 
$nama_file = $d_sis['foto_siswa'];
$full_path = $base_dir . $nama_file;

if (!empty($nama_file) && file_exists($full_path)) {
    // Ambil ekstensi file
    $type = pathinfo($full_path, PATHINFO_EXTENSION);
    // Baca file
    $data = file_get_contents($full_path);
    // Convert ke base64
    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    
    // Render tag IMG
    $foto_html = '<img src="' . $base64 . '" style="width: 100%; height: 100%; object-fit: cover;">';
}

// Placeholder jika foto kosong / tidak ketemu
if (empty($foto_html)) {
    $foto_html = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 8pt; color: #aaa; text-align: center; font-family: sans-serif;">FOTO<br>3 x 4</div>';
}
?>

<!-- HTML CONTENT START -->
<style>
    .mass-container { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #333; padding: 0 10px; }
    .header-title { text-align: center; font-weight: bold; font-size: 14pt; margin-bottom: 20px; text-transform: uppercase; border-bottom: 2px solid #333; padding-bottom: 10px; }
    
    .bio-table { width: 100%; border-collapse: collapse; }
    .bio-table td { padding: 4px 4px; vertical-align: top; border-bottom: 1px solid #eee; }
    
    .col-no { width: 4%; text-align: center; color: #666; font-weight: bold; }
    .col-label { width: 32%; color: #444; }
    .col-sep { width: 2%; text-align: center; color: #444; }
    .col-value { width: 62%; font-weight: 600; color: #000; }
    
    .cat-row td { background-color: #f8f8f8; font-weight: bold; color: #333; border-bottom: 1px solid #ccc; padding-top: 6px; padding-bottom: 6px; }
    .indent { padding-left: 15px; }
    .sub-label { color: #555; }
    
    .sig-table { margin-top: 30px; width: 100%; page-break-inside: avoid; }
    .photo-box { width: 3cm; height: 4cm; border: 1px solid #ccc; background: #fff; padding: 3px; display: inline-block; box-shadow: 2px 2px 5px rgba(0,0,0,0.05); }
</style>

<div class="mass-container">

    <div class="header-title">
        IDENTITAS PESERTA DIDIK
    </div>

    <table class="bio-table">
        <!-- DATA PRIBADI -->
        <tr>
            <td class="col-no">1.</td>
            <td class="col-label">Nama Peserta Didik (Lengkap)</td>
            <td class="col-sep">:</td>
            <td class="col-value" style="text-transform: uppercase; font-size: 11pt;"><?php echo htmlspecialchars($d_sis['nama_lengkap']); ?></td>
        </tr>
        <tr>
            <td class="col-no">2.</td>
            <td class="col-label">Nomor Induk / NISN</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars(($d_sis['nis']??'-') . ' / ' . ($d_sis['nisn']??'-')); ?></td>
        </tr>
        <tr>
            <td class="col-no">3.</td>
            <td class="col-label">Tempat, Tanggal Lahir</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['tempat_lahir']) . ', ' . tanggal_indo_massal($d_sis['tanggal_lahir']); ?></td>
        </tr>
        <tr>
            <td class="col-no">4.</td>
            <td class="col-label">Jenis Kelamin</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo ($d_sis['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan'; ?></td>
        </tr>
        <tr>
            <td class="col-no">5.</td>
            <td class="col-label">Agama</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['agama']); ?></td>
        </tr>
        <tr>
            <td class="col-no">6.</td>
            <td class="col-label">Status dalam Keluarga</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['status_dalam_keluarga']); ?></td>
        </tr>
        <tr>
            <td class="col-no">7.</td>
            <td class="col-label">Anak ke</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['anak_ke']); ?></td>
        </tr>
        <tr>
            <td class="col-no">8.</td>
            <td class="col-label">Alamat Peserta Didik</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['alamat']); ?></td>
        </tr>
        <tr>
            <td class="col-no">9.</td>
            <td class="col-label">Nomor Telepon Rumah/HP</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['telepon_siswa']); ?></td>
        </tr>
        <tr>
            <td class="col-no">10.</td>
            <td class="col-label">Sekolah Asal</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['sekolah_asal'] ?? '-'); ?></td>
        </tr>

        <!-- DITERIMA -->
        <tr class="cat-row">
            <td class="col-no">11.</td>
            <td colspan="3">Diterima di Sekolah Ini</td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">a. Di Kelas</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['diterima_di_kelas'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Pada Tanggal</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo tanggal_indo_massal($d_sis['diterima_tanggal']); ?></td>
        </tr>

        <!-- ORANG TUA -->
        <tr class="cat-row">
            <td class="col-no">12.</td>
            <td colspan="3">Nama Orang Tua</td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">a. Ayah</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['nama_ayah']); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Ibu</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['nama_ibu']); ?></td>
        </tr>
        <tr>
            <td class="col-no">13.</td>
            <td class="col-label">Alamat Orang Tua</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['alamat']); ?></td>
        </tr>
        <tr>
            <td class="col-no">14.</td>
            <td class="col-label">Pekerjaan Orang Tua</td>
            <td class="col-sep">:</td>
            <td class="col-value"></td>
        </tr>
        <tr>
            <td style="border-bottom:none;"></td>
            <td class="col-label indent sub-label" style="border-bottom:none;">a. Ayah</td>
            <td class="col-sep" style="border-bottom:none;">:</td>
            <td class="col-value" style="border-bottom:none;"><?php echo htmlspecialchars($d_sis['pekerjaan_ayah']); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Ibu</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['pekerjaan_ibu']); ?></td>
        </tr>

        <!-- WALI -->
        <tr class="cat-row">
            <td class="col-no">15.</td>
            <td colspan="3">Wali Peserta Didik</td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">a. Nama Wali</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['nama_wali'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">b. Alamat Wali</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['alamat_wali'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">c. No. Telepon</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['telepon_wali'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td></td>
            <td class="col-label indent sub-label">d. Pekerjaan</td>
            <td class="col-sep">:</td>
            <td class="col-value"><?php echo htmlspecialchars($d_sis['pekerjaan_wali'] ?? '-'); ?></td>
        </tr>
    </table>

    <!-- TANDA TANGAN (Layout Rapat) -->
    <table class="sig-table">
        <tr>
            <!-- Spacer Kiri -->
            <td style="width: 30%;"></td>

            <!-- Foto (Align Right) -->
            <td style="width: 25%; text-align: right; vertical-align: bottom; padding-right: 10px; padding-bottom: 5px;">
                <div class="photo-box">
                    <?php echo $foto_html; ?>
                </div>
            </td>

            <!-- Tanda Tangan (Align Left/Center) -->
            <td style="width: 45%; vertical-align: top; text-align: center;">
                <div style="font-size: 10pt;">
                    <?php echo htmlspecialchars($sekolah_pdf['kabupaten_kota'] ?? 'Kabupaten'); ?>, <?php echo $tgl_ttd_fix; ?><br>
                    Kepala Sekolah,<br>
                    <br><br><br><br><br>
                    <b style="text-decoration: underline; font-size: 11pt;"><?php echo htmlspecialchars($sekolah_pdf['nama_kepsek']); ?></b><br>
                    NIP. <?php echo htmlspecialchars($sekolah_pdf['nip_kepsek']); ?>
                </div>
            </td>
        </tr>
    </table>

</div>
<!-- HTML CONTENT END -->
