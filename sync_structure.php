<?php
// Pengaturan memory dan waktu eksekusi agar tidak timeout untuk file SQL besar
set_time_limit(0);
ini_set('memory_limit', '-1');

$file_sql = 'u1444233_rapor (terbaru).sql'; // Ganti dengan nama file SQL Anda yang sebenarnya jika berbeda
$logs = [];
$status_class = "";

// Fungsi Parsing Sederhana untuk memecah Create Table
function getTableDefinitions($sql_content) {
    $tables = [];
    // Regex untuk mengambil blok CREATE TABLE
    // Mencocokkan pola: CREATE TABLE `nama_tabel` ( ... ) ENGINE=...
    preg_match_all('/CREATE TABLE\s+[`]?(\w+)[`]?\s*\((.*?)^\) ENGINE/ms', $sql_content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $tableName = $match[1];
        $body = $match[2];
        $tables[$tableName] = $body;
    }
    return $tables;
}

// Fungsi untuk membersihkan definisi kolom dari file SQL
function parseColumnLine($line) {
    $line = trim($line);
    // Hapus koma di akhir jika ada (karena explode mungkin menyisakan koma)
    if (substr($line, -1) == ',') {
        $line = substr($line, 0, -1);
    }
    return $line;
}

if (isset($_POST['btn_sync'])) {
    // Cek keberadaan file koneksi dan file SQL
    if (file_exists('koneksi.php')) {
        include 'koneksi.php';
        
        // Deteksi variabel koneksi dari file koneksi.php
        // Biasanya $koneksi atau $conn
        if (isset($koneksi)) $db = $koneksi;
        elseif (isset($conn)) $db = $conn;
        elseif (isset($link)) $db = $link;
        
        if (isset($db)) {
            if (file_exists($file_sql)) {
                $sql_content = file_get_contents($file_sql);
                
                // Bersihkan komentar SQL (-- dan /* ... */) agar parsing lebih akurat
                $sql_content = preg_replace('/^--.*$/m', '', $sql_content);
                $sql_content = preg_replace('/^\/\*.*\*\/$/m', '', $sql_content);

                $newTables = getTableDefinitions($sql_content);
                $updates_count = 0;

                foreach ($newTables as $tableName => $tableBody) {
                    // 1. Cek apakah Tabel Ada di Database
                    $checkTable = $db->query("SHOW TABLES LIKE '$tableName'");
                    
                    if ($checkTable->num_rows == 0) {
                        // KASUS A: Tabel Belum Ada -> Buat Tabel Baru Full
                        // Kita gunakan query CREATE TABLE asli dari file SQL
                        // Namun kita perlu membangun ulang querynya
                        $createSQL = "CREATE TABLE `$tableName` ($tableBody) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                        
                        if ($db->query($createSQL)) {
                            $logs[] = "<div class='alert alert-success'>[TABLE] Tabel baru <b>$tableName</b> berhasil dibuat.</div>";
                            $updates_count++;
                        } else {
                            $logs[] = "<div class='alert alert-danger'>[ERROR] Gagal membuat tabel $tableName: " . $db->error . "</div>";
                        }
                    } else {
                        // KASUS B: Tabel Sudah Ada -> Cek Kolom (ALTER TABLE)
                        
                        // Ambil daftar kolom yang ada di database sekarang
                        $existingColumns = [];
                        $res = $db->query("SHOW COLUMNS FROM `$tableName`");
                        while($row = $res->fetch_assoc()) {
                            $existingColumns[] = strtolower($row['Field']);
                        }

                        // Pecah body SQL menjadi baris-baris definisi
                        // Regex ini memisahkan berdasarkan koma, TAPI mengabaikan koma di dalam kurung (...)
                        // Contoh: enum('a','b') tidak akan terpecah
                        $lines = preg_split("/,(?![^()]*\))/", $tableBody); 

                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;

                            // Lewati baris yang mendefinisikan key/index (PRIMARY KEY, KEY, CONSTRAINT, UNIQUE)
                            // Fokus kita hanya menyamakan Kolom Data agar aman
                            if (preg_match('/^(PRIMARY KEY|KEY|UNIQUE|CONSTRAINT|FULLTEXT)/i', $line)) {
                                continue;
                            }

                            // Ambil nama kolom dari baris SQL (biasanya diawali backtick `nama_kolom`)
                            if (preg_match('/^[`]?(\w+)[`]?/', $line, $colMatch)) {
                                $colName = $colMatch[1];
                                
                                // Jika kolom dari file SQL TIDAK ADA di database, Tambahkan!
                                if (!in_array(strtolower($colName), $existingColumns)) {
                                    $definition = parseColumnLine($line);
                                    $alterQuery = "ALTER TABLE `$tableName` ADD $definition";
                                    
                                    if ($db->query($alterQuery)) {
                                        $logs[] = "<div class='alert alert-warning'>[COLUMN] Menambahkan kolom <b>$colName</b> ke tabel <b>$tableName</b>.</div>";
                                        $updates_count++;
                                    } else {
                                        $logs[] = "<div class='alert alert-danger'>[ERROR] Gagal alter tabel $tableName (kolom $colName): " . $db->error . "</div>";
                                    }
                                }
                            }
                        }
                    }
                }
                
                if ($updates_count == 0) {
                    $logs[] = "<div class='alert alert-info'>Struktur database sudah sinkron. Tidak ada perubahan yang diperlukan.</div>";
                } else {
                    $logs[] = "<div class='alert alert-success mt-3'><b>Selesai! Total pembaruan struktur: $updates_count</b></div>";
                }

            } else {
                $logs[] = "<div class='alert alert-danger'>File SQL <b>$file_sql</b> tidak ditemukan.</div>";
            }
            
        } else {
            $logs[] = "<div class='alert alert-danger'>Koneksi database gagal. Variabel \$koneksi tidak ditemukan.</div>";
        }
    } else {
        $logs[] = "<div class='alert alert-danger'>File <b>koneksi.php</b> tidak ditemukan.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safe DB Structure Sync</title>
    <!-- Bootstrap CSS untuk tampilan yang lebih baik -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding-top: 50px; padding-bottom: 50px; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; }
        .card-header { background-color: #fff; border-bottom: 1px solid #eee; padding: 20px; }
        .log-area { background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-top: 20px; max-height: 500px; overflow-y: auto; }
        .alert { padding: 10px 15px; margin-bottom: 10px; font-size: 14px; border-left: 4px solid transparent; }
        .alert-success { border-left-color: #198754; background-color: #d1e7dd; color: #0f5132; }
        .alert-warning { border-left-color: #ffc107; background-color: #fff3cd; color: #664d03; }
        .alert-danger { border-left-color: #dc3545; background-color: #f8d7da; color: #842029; }
        .alert-info { border-left-color: #0dcaf0; background-color: #cff4fc; color: #055160; }
        .btn-sync { width: 100%; padding: 12px; font-weight: bold; font-size: 1.1rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="mb-0 text-primary">Sinkronisasi Struktur Database (Aman)</h3>
                    <p class="text-muted mb-0 mt-2">Menyamakan struktur database dengan file SQL tanpa menghapus data.</p>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert alert-info border-0 shadow-sm mb-4">
                        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Cara Kerja:</h5>
                        <ul class="mb-0 ps-3">
                            <li>Script ini membaca file <code><?php echo $file_sql; ?></code>.</li>
                            <li>Jika ada <b>tabel baru</b> di file SQL yang belum ada di database, tabel akan <b>dibuat</b>.</li>
                            <li>Jika ada <b>kolom baru</b> di file SQL yang belum ada di tabel database, kolom akan <b>ditambahkan</b>.</li>
                            <li><b>Data yang sudah ada (INSERT/UPDATE) TIDAK akan disentuh atau dihapus.</b></li>
                        </ul>
                    </div>

                    <form method="post">
                        <div class="d-grid gap-2">
                            <button type="submit" name="btn_sync" class="btn btn-primary btn-sync shadow-sm">
                                🚀 Mulai Sinkronisasi Struktur
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($logs)): ?>
                        <div class="log-area shadow-sm">
                            <h5 class="mb-3 border-bottom pb-2">Log Proses:</h5>
                            <?php foreach ($logs as $log) { echo $log; } ?>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="card-footer text-center text-muted py-3">
                    <small>Pastikan file <code>koneksi.php</code> dan <code><?php echo $file_sql; ?></code> berada di folder yang sama.</small>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>