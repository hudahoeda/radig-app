<?php
// CLI-only, repeatable schema migration for developer release 3.5.1.
// Back up the database before running: php scripts/deep_sync_351.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/migrate_august_2026.php';

$result = mysqli_query($koneksi, "SHOW COLUMNS FROM `penilaian` LIKE 'subjenis_penilaian'");
$column = mysqli_fetch_assoc($result);
if (!$column || strpos($column['Type'], 'enum(') !== 0) {
    throw new RuntimeException('Expected penilaian.subjenis_penilaian to be an ENUM.');
}
$existing = str_getcsv(substr($column['Type'], 5, -1), ',', "'", '\\');
$required = ['Sumatif TP', 'Sumatif Tengah Semester', 'Sumatif Akhir Semester', 'Sumatif Akhir Tahun'];
$missing = array_diff($required, $existing);
if ($missing) {
    // Keep any additional existing enum choices; never narrow the stored values.
    $values = array_values(array_unique(array_merge($required, $existing)));
    $quoted = array_map(function ($value) use ($koneksi) {
        return "'" . mysqli_real_escape_string($koneksi, $value) . "'";
    }, $values);
    $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
    $default = $column['Default'] === null
        ? ($column['Null'] === 'YES' ? ' DEFAULT NULL' : '')
        : " DEFAULT '" . mysqli_real_escape_string($koneksi, $column['Default']) . "'";
    mysqli_query($koneksi, "ALTER TABLE `penilaian` MODIFY `subjenis_penilaian` ENUM(" . implode(',', $quoted) . ") $null$default");
    echo "Updated: penilaian.subjenis_penilaian (added middle-semester assessment)", PHP_EOL;
} else {
    echo "Already exists: middle-semester assessment enum value", PHP_EOL;
}
echo "Release 3.5.1 Deep Sync complete.", PHP_EOL;
