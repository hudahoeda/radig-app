<?php
// ==============================================================================
// FILE: pengaturan_backup_tampil.php
// DESKRIPSI: Manajemen Database (Backup, Deep Sync, Migrasi, Restore, & Riwayat)
// TEMA: TEAL MONSTER EDITION (Modern & Aggressive)
// ==============================================================================

// Tampilkan Error (Untuk Debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Batas memori & waktu eksekusi untuk SQL besar
ini_set('memory_limit', '1024M');
set_time_limit(600); 

include 'header.php';
include 'koneksi.php';

// Validasi role admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// ==================================================================================
// [FITUR 1] LOGIKA SINKRONISASI STRUKTUR TINGKAT LANJUT (DEEP SYNC)
// ==================================================================================
$sync_script = ""; 

if (isset($_POST['btn_sync_structure'])) {
    if (isset($koneksi)) $db = $koneksi;
    elseif (isset($conn)) $db = $conn;

    if (isset($db) && isset($_FILES['sql_file_sync']) && $_FILES['sql_file_sync']['error'] == 0) {
        $file_tmp = $_FILES['sql_file_sync']['tmp_name'];
        
        $report_created_tables = [];
        $report_added_columns = [];
        $report_modified_columns = [];
        $report_added_indices = [];
        $report_errors = [];
        $report_stats = ['tables_checked' => 0, 'columns_checked' => 0];

        $handle = fopen($file_tmp, "r");
        if ($handle) {
            $in_table = false;
            $table_buffer = "";
            $current_table_name = "";

            while (($line = fgets($handle)) !== false) {
                $trim_line = trim($line);
                if (empty($trim_line) || strpos($trim_line, '--') === 0 || strpos($trim_line, '/*') === 0) continue;

                if (stripos($trim_line, 'CREATE TABLE') === 0) {
                    $in_table = true;
                    $table_buffer = "";
                    if (preg_match('/CREATE TABLE\s+[`]?(\w+)[`]?/', $trim_line, $matches)) {
                        $current_table_name = $matches[1];
                    }
                }

                if ($in_table) {
                    $table_buffer .= $line;
                    if (preg_match('/\)\s*ENGINE=/i', $trim_line) || strpos($trim_line, ';') !== false) {
                        $in_table = false;
                        if (!empty($current_table_name)) {
                            $resTable = $db->query("SHOW TABLES LIKE '$current_table_name'");
                            if ($resTable->num_rows == 0) {
                                if ($db->query($table_buffer)) { $report_created_tables[] = $current_table_name; } 
                                else { $report_errors[] = "Gagal membuat tabel <b>$current_table_name</b>: " . $db->error; }
                            } else {
                                $report_stats['tables_checked']++;
                                $current_cols = [];
                                $resCols = $db->query("SHOW FULL COLUMNS FROM `$current_table_name`");
                                if ($resCols) { while($c = $resCols->fetch_assoc()) { $current_cols[strtolower($c['Field'])] = $c; } }
                                $current_indices = [];
                                $resIdx = $db->query("SHOW INDEX FROM `$current_table_name`");
                                if ($resIdx) { while($idx = $resIdx->fetch_assoc()) { $current_indices[] = $idx['Key_name']; } }

                                $lines = explode("\n", $table_buffer);
                                foreach ($lines as $b_line) {
                                    $b_line = trim($b_line);
                                    if (empty($b_line) || stripos($b_line, 'CREATE TABLE') === 0 || stripos($b_line, ') ENGINE') === 0) continue;
                                    $b_line_clean = rtrim($b_line, ',');

                                    if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY|INDEX|CONSTRAINT)\s+[`]?(\w+)?[`]?\s?\((.*)\)/i', $b_line_clean, $idxMatch)) {
                                        $idxName = !empty($idxMatch[2]) ? $idxMatch[2] : 'PRIMARY';
                                        if (!in_array($idxName, $current_indices)) {
                                            if ($db->query("ALTER TABLE `$current_table_name` ADD $b_line_clean")) {
                                                $report_added_indices[] = "Tabel <b>$current_table_name</b>: + Index <b>$idxName</b>";
                                            }
                                        }
                                        continue;
                                    }

                                    if (preg_match('/^[`]?(\w+)[`]?\s+(.*)$/', $b_line_clean, $colMatch)) {
                                        $colName = $colMatch[1]; $colDef = $colMatch[2]; $colNameLower = strtolower($colName);
                                        if (!isset($current_cols[$colNameLower])) {
                                            if ($db->query("ALTER TABLE `$current_table_name` ADD `$colName` $colDef")) {
                                                $report_added_columns[] = "Tabel <b>$current_table_name</b>: + Kolom <b>$colName</b>";
                                            }
                                        } else {
                                            $db_type = $current_cols[$colNameLower]['Type'];
                                            $db_null = ($current_cols[$colNameLower]['Null'] == 'YES') ? 'NULL' : 'NOT NULL';
                                            if (stripos($colDef, $db_type) === false || stripos($colDef, $db_null) === false) {
                                                if ($db->query("ALTER TABLE `$current_table_name` MODIFY `$colName` $colDef")) {
                                                    $report_modified_columns[] = "Tabel <b>$current_table_name</b>: Modifikasi <b>$colName</b>";
                                                }
                                            }
                                            $report_stats['columns_checked']++;
                                        }
                                    }
                                }
                            }
                            $current_table_name = ""; $table_buffer = "";
                        }
                    }
                }
            }
            fclose($handle);
        }

        $html_report = "<div style='text-align:left; font-size:0.85rem; max-height:50vh; overflow-y:auto; padding:10px;'>";
        $hasAnyChange = (!empty($report_created_tables) || !empty($report_added_columns) || !empty($report_modified_columns) || !empty($report_added_indices));
        if ($hasAnyChange) {
            $html_report .= "<div class='alert alert-success border-0 small mb-2'><h6><b>Perubahan Berhasil:</b></h6><ul class='mb-0'>";
            foreach($report_created_tables as $t) $html_report .= "<li>Tabel Baru: <b>$t</b></li>";
            foreach($report_added_columns as $c) $html_report .= "<li>Kolom Baru: $c</li>";
            foreach($report_modified_columns as $m) $html_report .= "<li>Update Struktur: $m</li>";
            foreach($report_added_indices as $i) $html_report .= "<li>Index Baru: $i</li>";
            $html_report .= "</ul></div>";
        } else if (empty($report_errors)) {
            $html_report .= "<div class='alert alert-info border-0 text-center mb-2'><h6><b>Struktur Identik</b></h6><p class='mb-0 small'>Tidak ada perubahan struktur yang diperlukan.</p></div>";
        }
        if (!empty($report_errors)) {
            $html_report .= "<div class='alert alert-danger border-0 small mb-2'><h6><b>Gagal/Error:</b></h6><ul class='mb-0'>";
            foreach($report_errors as $err) $html_report .= "<li>$err</li>";
            $html_report .= "</ul></div>";
        }
        $html_report .= "<div class='p-2 bg-light rounded border-start border-4 border-teal small'><b>Ringkasan:</b> Verifikasi {$report_stats['tables_checked']} tabel & {$report_stats['columns_checked']} kolom selesai.</div></div>";

        $sync_script = "<script>document.addEventListener('DOMContentLoaded', function() { 
            Swal.fire({ title: 'Sinkronisasi Selesai', html: `$html_report`, icon: 'success', width: '600px', confirmButtonColor: '#0d9488' }); 
        });</script>";
    }
}

// Data backup
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
$backup_files = glob($backup_dir . '*.sql');
rsort($backup_files);
?>

<!-- =================================================================================
     2. TAMPILAN MODERN (TEAL THEME - DATABASE EDITION)
================================================================================== -->
<style>
    :root {
        --teal-primary: #0d9488;
        --teal-dark: #065f46;
        --teal-light: #ccfbf1;
        --accent-orange: #f97316;
    }

    body { background-color: #f0fdfa; font-family: 'Inter', sans-serif; }

    /* 1. HERO SECTION */
    .hero-db-teal {
        background: var(--teal-dark);
        border-radius: 30px;
        padding: 3rem 2.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 15px 40px -15px rgba(6, 95, 70, 0.9);
        margin-bottom: 2.5rem;
        border-top: 8px solid var(--teal-primary);
    }
    .hero-db-teal::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.05) 1px, transparent 0);
        background-size: 24px 24px;
    }

    /* 2. TOOL CARDS */
    .db-tool-card {
        background: white;
        border-radius: 20px;
        border: 1px solid var(--teal-light);
        box-shadow: 0 10px 25px -10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .db-tool-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px -10px rgba(13, 148, 136, 0.25);
        border-color: var(--teal-primary);
    }
    .db-icon-box {
        width: 70px; height: 70px;
        border-radius: 18px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        transition: transform 0.3s;
    }
    .db-tool-card:hover .db-icon-box { transform: scale(1.1) rotate(5deg); }

    .btn-db {
        border-radius: 50px;
        font-weight: 800;
        padding: 10px 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* 3. TABLE STYLING */
    .table-custom-teal {
        width: 100%; border-collapse: separate; border-spacing: 0 8px;
    }
    .table-custom-teal thead th {
        border: none; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;
        padding: 1rem;
    }
    .table-custom-teal tbody tr {
        background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        transition: transform 0.2s;
    }
    .table-custom-teal tbody tr:hover { transform: scale(1.005); background: #fdfdfd; }
    .table-custom-teal td {
        padding: 1.2rem 1rem; vertical-align: middle; border: none;
    }
    .table-custom-teal td:first-child { border-top-left-radius: 15px; border-bottom-left-radius: 15px; }
    .table-custom-teal td:last-child { border-top-right-radius: 15px; border-bottom-right-radius: 15px; }

    .file-name-teal { color: var(--teal-dark); font-weight: 700; font-size: 0.95rem; }
    
    .btn-action-db {
        width: 36px; height: 36px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        transition: all 0.2s; border: none;
    }
    .btn-dl { background: var(--teal-light); color: var(--teal-primary); }
    .btn-del { background: #fee2e2; color: #ef4444; }
    .btn-action-db:hover { transform: scale(1.1); filter: brightness(0.9); }
</style>

<div class="container-fluid px-4 py-4">
    <?php echo $sync_script; ?>

    <!-- 1. HERO SECTION -->
    <div class="hero-db-teal shadow">
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-md-8">
                <h1 class="fw-bolder fs-1 mb-2">DATABASE CENTER</h1>
                <p class="mb-0 fs-5 opacity-90 fw-medium">Manajemen pemeliharaan, cadangan, dan sinkronisasi struktur data aplikasi.</p>
            </div>
            <div class="col-md-4 text-md-end mt-4 mt-md-0">
                <a href="pengaturan_tampil.php" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-arrow-left me-2"></i>KEMBALI KE PENGATURAN
                </a>
            </div>
        </div>
    </div>

    <!-- 2. TOOLBOX GRID -->
    <div class="row g-4 mb-5">
        
        <!-- BACKUP -->
        <div class="col-md-6 col-lg-3">
            <div class="card db-tool-card p-4">
                <div class="db-icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cloud-arrow-down-fill"></i>
                </div>
                <div class="text-center">
                    <h5 class="fw-bolder text-dark mb-2">BACKUP DATA</h5>
                    <p class="small text-muted mb-4">Simpan kondisi database saat ini ke folder cadangan server.</p>
                    <a href="pengaturan_aksi.php?aksi=buat_backup" class="btn btn-success btn-db w-100">
                        <i class="bi bi-download me-2"></i>BACKUP SEKARANG
                    </a>
                </div>
            </div>
        </div>

        <!-- SYNC (DEEP SYNC) -->
        <div class="col-md-6 col-lg-3">
            <div class="card db-tool-card p-4" style="background: #fffcf0;">
                <form method="POST" enctype="multipart/form-data" id="form-sync">
                    <input type="hidden" name="btn_sync_structure" value="1">
                    <div class="db-icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div class="text-center">
                        <h5 class="fw-bolder text-warning-emphasis mb-2">DEEP SYNC</h5>
                        <p class="small text-muted mb-3">Update struktur (tabel/kolom) tanpa menghapus data.</p>
                        <input type="file" name="sql_file_sync" class="form-control form-control-sm mb-2 rounded-3 border-warning border-opacity-25" accept=".sql" required>
                        <button type="submit" class="btn btn-warning btn-db w-100 text-white">SINKRONKAN</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MIGRASI -->
        <div class="col-md-6 col-lg-3">
            <div class="card db-tool-card p-4">
                <form action="pengaturan_aksi.php" method="POST" enctype="multipart/form-data" id="form-migrasi">
                    <input type="hidden" name="aksi" value="migrasi_via_file">
                    <div class="db-icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-database-fill-up"></i>
                    </div>
                    <div class="text-center">
                        <h5 class="fw-bolder text-primary mb-2">MIGRASI DATA</h5>
                        <p class="small text-muted mb-3">Pembersihan data total sebelum diisi data dari file SQL.</p>
                        <input type="file" name="sql_file_migrasi" class="form-control form-control-sm mb-2 rounded-3" accept=".sql" required>
                        <button type="submit" class="btn btn-primary btn-db w-100">JALANKAN MIGRASI</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RESTORE TOTAL -->
        <div class="col-md-6 col-lg-3">
            <div class="card db-tool-card p-4">
                <form action="pengaturan_aksi.php" method="POST" enctype="multipart/form-data" id="form-restore">
                    <input type="hidden" name="aksi" value="lakukan_restore_total">
                    <div class="db-icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div class="text-center">
                        <h5 class="fw-bolder text-danger mb-2">RESTORE TOTAL</h5>
                        <p class="small text-muted mb-3">Ganti total seluruh sistem. <b class="text-danger">Sangat Berisiko!</b></p>
                        <input type="file" name="sql_file_restore" class="form-control form-control-sm mb-2 rounded-3 border-danger border-opacity-25" accept=".sql" required>
                        <button type="submit" class="btn btn-danger btn-db w-100">RESTORE SISTEM</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- 3. RIWAYAT BACKUP -->
    <div class="row">
        <div class="col-12">
            <div class="card db-tool-card border-0">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bolder text-teal-dark"><i class="bi bi-clock-history me-2 text-teal-primary"></i>RIWAYAT CADANGAN SERVER</h4>
                    <span class="badge bg-teal-light text-teal-primary px-3 py-2 rounded-pill fw-bold border border-teal-primary">FOLDER: /backups</span>
                </div>
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="table-responsive">
                        <table class="table-custom-teal">
                            <thead>
                                <tr>
                                    <th width="45%">NAMA FILE CADANGAN</th>
                                    <th width="20%">TANGGAL PEMBUATAN</th>
                                    <th width="15%">UKURAN</th>
                                    <th width="20%" class="text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($backup_files)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <i class="bi bi-folder-x fs-1 opacity-25 d-block mb-3"></i>
                                            <span class="text-muted fw-bold">Belum ada file cadangan yang tersimpan.</span>
                                        </td>
                                    </tr>
                                <?php else: foreach($backup_files as $file): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-teal-light text-teal-primary p-2 rounded-3 me-3">
                                                    <i class="bi bi-file-earmark-code-fill fs-4"></i>
                                                </div>
                                                <span class="file-name-teal"><?php echo basename($file); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo date("d M Y", filemtime($file)); ?></div>
                                            <small class="text-muted"><?php echo date("H:i", filemtime($file)); ?> WIB</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border px-2 py-1">
                                                <?php echo round(filesize($file) / 1024, 2); ?> KB
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="<?php echo $file; ?>" class="btn-action-db btn-dl" data-bs-toggle="tooltip" title="Download ke Komputer" download>
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <button onclick="hapusBackup('<?php echo urlencode(basename($file)); ?>')" class="btn-action-db btn-del" data-bs-toggle="tooltip" title="Hapus dari Server">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });

    // 1. Konfirmasi Sinkronisasi Struktur
    const formSync = document.getElementById('form-sync');
    if(formSync) {
        formSync.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Lakukan Deep Sync?',
                html: "Sistem akan mendeteksi penambahan tabel, kolom, index, serta perubahan tipe data.<br><br><span class='badge bg-success'>DATA LAMA AMAN</span>",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d9488',
                confirmButtonText: 'SINKRONKAN!'
            }).then((result) => { if(result.isConfirmed) { Swal.showLoading(); formSync.submit(); } });
        });
    }

    // 2. Konfirmasi Migrasi
    const formMigrasi = document.getElementById('form-migrasi');
    if(formMigrasi) {
        formMigrasi.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Mulai Migrasi Data?',
                html: "Seluruh data saat ini akan <b class='text-danger'>DIHAPUS</b> dan diganti data baru.<br>Pastikan Anda memiliki backup!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0d9488',
                confirmButtonText: 'YA, MIGRASI!'
            }).then((result) => { if(result.isConfirmed) { Swal.showLoading(); formMigrasi.submit(); } });
        });
    }

    // 3. Konfirmasi Restore Total
    const formRestore = document.getElementById('form-restore');
    if(formRestore) {
        formRestore.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'PERINGATAN RESTORE!',
                html: "Tindakan ini akan <b>MENGHAPUS TOTAL</b> struktur dan isi database.<br>Sistem diganti sepenuhnya sesuai file SQL.",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'RESTORE SEKARANG!'
            }).then((result) => { if(result.isConfirmed) { Swal.showLoading(); formRestore.submit(); } });
        });
    }
});

function hapusBackup(file) {
    Swal.fire({
        title: 'Hapus Backup?',
        text: "File cadangan ini akan dihapus permanen dari folder server.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'HAPUS'
    }).then((result) => { if(result.isConfirmed) window.location.href = 'pengaturan_aksi.php?aksi=hapus_backup&file=' + file; });
}
</script>

<?php include 'footer.php'; ?>