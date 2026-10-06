<?php
// ==============================================================================
// FILE: pengaturan_updater_aksi.php
// DESKRIPSI: Mesin inti Auto Updater (Mendownload, mengekstrak, dan mengeksekusi)
// ==============================================================================
ob_start(); // Tangkap output tak terduga (spasi/newline dari koneksi.php)
session_start();
include 'koneksi.php';

// Bersihkan output buffer
ob_clean();
header('Content-Type: application/json');

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die(json_encode(['status' => 'error', 'message' => 'Akses Ditolak']));
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// URL PUSAT UPDATE (Ganti dengan domain Anda)
$UPDATE_SERVER_URL = "https://multischool.sch.id/rapor/build/version.json";

function get_local_version() {
    $file = __DIR__ . '/local_version.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return ['version' => 'v1.0', 'version_code' => 1];
}

// ---------------------------------------------------------
// 1. CEK UPDATE
// ---------------------------------------------------------
if ($action == 'check') {
    $remote_data_json = false;
    $http_code = 0;

    // Coba pakai cURL jika ada
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $UPDATE_SERVER_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $remote_data_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);
    }

    // Fallback jika cURL tidak ada atau gagal
    if (!$remote_data_json || $http_code != 200) {
        $options = [
            "http" => [
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
                "timeout" => 15
            ],
            "ssl" => [
                "verify_peer" => true,
                "verify_peer_name" => true,
            ]
        ];
        $context = stream_context_create($options);
        $remote_data_json = @file_get_contents($UPDATE_SERVER_URL, false, $context);
        if ($remote_data_json !== false) {
            $http_code = 200; // Asumsi sukses jika berhasil di-download
        }
    }

    if (!$remote_data_json || $http_code != 200) {
        $error_detail = "HTTP Code: $http_code. ";
        if (isset($curl_err) && $curl_err) $error_detail .= "CURL Error: $curl_err. ";
        $last_err = error_get_last();
        if ($last_err) $error_detail .= "Stream Error: " . $last_err['message'];

        die(json_encode(['status' => 'error', 'message' => 'Gagal terhubung ke Server Pusat. ' . $error_detail]));
    }

    $remote_data = json_decode($remote_data_json, true);
    $local_data = get_local_version();

    if ($remote_data && isset($remote_data['version_code'])) {
        if ($remote_data['version_code'] > $local_data['version_code']) {
            echo json_encode([
                'status' => 'update_available',
                'managed_deployment' => file_exists('/.dockerenv'),
                'current_version' => $local_data['version'],
                'new_version' => $remote_data['version'],
                'changelog' => $remote_data['changelog'],
                'download_url' => $remote_data['download_url']
            ]);
        } else {
            echo json_encode([
                'status' => 'up_to_date',
                'current_version' => $local_data['version']
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Format data dari server tidak valid.']);
    }
    exit;
}

// ---------------------------------------------------------
// 2. DOWNLOAD & INSTALL
// ---------------------------------------------------------
if ($action == 'install') {
    if (file_exists('/.dockerenv')) {
        http_response_code(409);
        die(json_encode(['status' => 'error', 'message' => 'Server ini dikelola melalui Coolify. Pasang pembaruan melalui repository dan redeploy agar perubahan tersimpan permanen.']));
    }
    // Batas waktu & memori ekstra
    set_time_limit(600);
    ini_set('memory_limit', '1024M');

    $download_url = isset($_POST['download_url']) ? $_POST['download_url'] : '';
    if (empty($download_url)) {
        die(json_encode(['status' => 'error', 'message' => 'URL Unduhan tidak valid.']));
    }

    $tmp_zip_file = __DIR__ . '/build/update_temp.zip';
    if (!is_dir(__DIR__ . '/build')) {
        mkdir(__DIR__ . '/build', 0755, true);
    }

    // A. Unduh File ZIP
    $zip_data = false;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $download_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $zip_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code != 200) $zip_data = false;
    }

    if (!$zip_data) {
        $options = [
            "http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n", "timeout" => 60],
            "ssl" => ["verify_peer" => true, "verify_peer_name" => true]
        ];
        $context = stream_context_create($options);
        $zip_data = @file_get_contents($download_url, false, $context);
    }

    if (!$zip_data) {
        die(json_encode(['status' => 'error', 'message' => 'Gagal mengunduh file update ZIP dari server.']));
    }

    file_put_contents($tmp_zip_file, $zip_data);

    // B. Ekstrak ZIP
    $zip = new ZipArchive;
    if ($zip->open($tmp_zip_file) === TRUE) {
        // Ekstrak ke root direktori
        $zip->extractTo(__DIR__ . '/');
        $zip->close();
    } else {
        unlink($tmp_zip_file);
        die(json_encode(['status' => 'error', 'message' => 'Gagal mengekstrak file ZIP.']));
    }

    // C. Hapus file ZIP sementara
    unlink($tmp_zip_file);

    // D. Eksekusi Script Migrasi SQL Jika Ada
    $sql_file = __DIR__ . '/update_db_migrasi.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        if (!empty(trim($sql_content))) {
            // Karena ini bisa berisi multi query, kita pecah per titik koma
            // Note: Metode mysqli_multi_query lebih disarankan, tapi ini untuk kemudahan
            $queries = explode(';', $sql_content);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    mysqli_query($koneksi, $query);
                }
            }
        }
        // Hapus file sql setelah dieksekusi agar rapi
        unlink($sql_file);
    }

    // E. Otomatisasi Deep Sync Struktur Database (Membaca file SQL master)
    $file_sql_sync = __DIR__ . '/u1444233_rapor (terbaru).sql';
    if (file_exists($file_sql_sync) && file_exists(__DIR__ . '/sync_structure.php')) {
        // Simulasikan POST request agar script sync jalan secara background
        $_POST['btn_sync'] = true;
        // Tangkap output (berupa HTML log) agar tidak merusak response JSON updater
        ob_start();
        include __DIR__ . '/sync_structure.php';
        $sync_output = ob_get_clean();
    }

    // E2. Otomatisasi Perbaikan Auto Increment Database (fix_semua.php)
    $file_fix_semua = __DIR__ . '/fix_semua.php';
    if (file_exists($file_fix_semua)) {
        ob_start();
        include $file_fix_semua;
        $fix_output = ob_get_clean();
    }

    // F. Selesai
    $local_data = get_local_version(); // Harusnya sudah terupdate oleh zip
    echo json_encode([
        'status' => 'success',
        'message' => 'Pembaruan berhasil diinstal! Versi sekarang: ' . $local_data['version']
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak dikenali.']);
