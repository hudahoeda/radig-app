<?php
// Apply the additive changes from the developer's DEEP SYNC AGUSTUS schema.
// Run once (or safely rerun): php scripts/migrate_august_2026.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require dirname(__DIR__) . '/koneksi.php';

$changes = [
    'mata_pelajaran' => [
        'parent_mapel_id' => 'INT DEFAULT NULL',
        'is_tambahan' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'agama_khusus' => 'VARCHAR(50) DEFAULT NULL',
    ],
    'rapor' => [
        'id_kelas_historis' => 'INT DEFAULT NULL',
        'id_walikelas_historis' => 'INT DEFAULT NULL',
    ],
    'tujuan_pembelajaran' => [
        'kktp' => 'INT DEFAULT 75',
    ],
];

// Inspect every target before making changes. Existing columns are preserved.
$pending = [];
foreach ($changes as $table => $columns) {
    $existing = [];
    $result = mysqli_query($koneksi, "SHOW COLUMNS FROM `$table`");
    while ($column = mysqli_fetch_assoc($result)) {
        $existing[$column['Field']] = true;
    }
    $additions = [];
    foreach ($columns as $name => $definition) {
        if (isset($existing[$name])) {
            echo "Already exists: $table.$name", PHP_EOL;
        } else {
            $additions[] = "ADD COLUMN `$name` $definition";
        }
    }
    if ($additions) {
        $pending[$table] = $additions;
    }
}

// MySQL DDL commits implicitly. Take a database backup before running this file.
foreach ($pending as $table => $additions) {
    mysqli_query($koneksi, "ALTER TABLE `$table` " . implode(', ', $additions));
    echo "Updated: $table (", count($additions), " columns)", PHP_EOL;
}
echo "August 2026 schema migration complete.", PHP_EOL;
