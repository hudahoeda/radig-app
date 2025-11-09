<?php
// File: template_download.php
// Berfungsi sebagai router untuk mengirim file template Excel

// Keamanan dasar: Pastikan library ada
if (!file_exists('vendor/autoload.php')) {
    die("Error: Library PhpSpreadsheet (folder vendor) tidak ditemukan.");
}

$tipe = $_GET['tipe'] ?? '';

// Bersihkan output buffer jika ada
if (ob_get_level()) {
    ob_end_clean();
}

// Tentukan file mana yang akan di-include berdasarkan parameter 'tipe'
switch ($tipe) {
    case 'guru':
        // Panggil skrip generator template guru (SIMPEL)
        include 'template_guru_excel.php';
        break;

    case 'guru_mengajar':
        // [BARU] Panggil skrip generator template guru & mengajar (LENGKAP)
        include 'template_guru_mengajar.php';
        break;
        
    case 'siswa':
        // Panggil skrip generator template siswa lengkap
        include 'template_siswa_lengkap.php';
        break;
        
    case 'siswa_simple':
        // Panggil skrip generator template siswa simple (jika masih dipakai)
        include 'template_siswa_excel.php';
        break;

    case 'kelas':
        // [BARU DITAMBAHKAN] Menangani template kelas jika ada
        include 'template_kelas.php';
        break;

    default:
        // Jika tipe tidak dikenal
        header("HTTP/1.0 404 Not Found");
        echo "Error: Tipe template tidak valid atau tidak ditemukan.";
        exit();
}

// Skrip yang di-include sudah memiliki exit()
?>