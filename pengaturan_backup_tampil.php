<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Logika untuk membaca file backup yang ada
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}
$backup_files = glob($backup_dir . '*.sql');
rsort($backup_files);
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    
    /* Style untuk setiap jenis card */
    .upload-box-success { border: 2px dashed var(--bs-success-border-subtle); background-color: var(--bs-success-bg-subtle); }
    .upload-box-primary { border: 2px dashed var(--bs-primary-border-subtle); background-color: var(--bs-primary-bg-subtle); }
    .upload-box-danger { border: 2px dashed var(--bs-danger-border-subtle); background-color: var(--bs-danger-bg-subtle); }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Backup & Restore Database</h1>
                <p class="lead mb-0 opacity-75">Buat cadangan, migrasikan data lama, atau pulihkan data aplikasi.</p>
            </div>
            <a href="pengaturan_tampil.php" class="btn btn-outline-light mt-3 mt-sm-0"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
        </div>
    </div>

    <!-- Layout 3 Kolom untuk 3 Aksi Utama -->
    <div class="row g-4">
        
        <!-- ============================================= -->
        <!-- === OPSI 1: BUAT BACKUP (Aman)           === -->
        <!-- ============================================= -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 upload-box-success">
                <div class="card-body text-center p-4 d-flex flex-column justify-content-center">
                    <h5><i class="bi bi-plus-circle-fill text-success me-2"></i>Buat Cadangan Baru</h5>
                    <p class="text-muted">Buat file cadangan (backup) dari data Anda di server ini. Sangat disarankan sebelum melakukan update.</p>
                    <a href="pengaturan_aksi.php?aksi=buat_backup" class="btn btn-lg btn-success mt-auto"><i class="bi bi-database-down me-2"></i>Buat Backup Sekarang</a>
                </div>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- === OPSI 2: MIGRASI DARI BACKUP LAMA (Alat Update) === -->
        <!-- ================================================= -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 upload-box-primary">
                <form action="pengaturan_aksi.php" method="POST" enctype="multipart/form-data" id="form-migrasi">
                    <input type="hidden" name="aksi" value="migrasi_via_file">
                    <div class="card-body text-center p-4 d-flex flex-column">
                        <h5 class="text-primary-emphasis"><i class="bi bi-stars me-2"></i>Migrasi dari aplikasi Lama</h5>
                        <p class="text-primary-emphasis small">
                            <strong>Gunakan ini untuk pengguna baru.</strong> Fitur ini akan mengimpor data (siswa, nilai, dll) dari file backup aplikasi lama ke aplikasi baru Anda.
                        </p>
                        <div class="alert alert-primary p-2 small">
                            Database di server ini akan dikosongkan dulu, lalu diisi data dari file backup Anda, dan strukturnya akan otomatis diperbarui.
                        </div>
                        <div class="mb-3 mt-auto">
                            <label for="sql_file_migrasi" class="form-label fw-bold">Pilih File .sql (dari Aplikasi Lama)</label>
                            <input class="form-control" type="file" id="sql_file_migrasi" name="sql_file_migrasi" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-lg btn-primary"><i class="bi bi-cloud-upload me-2"></i>Mulai Proses Migrasi</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================= -->
        <!-- === OPSI 3: RESTORE TOTAL (Kloning / Bahaya) === -->
        <!-- ============================================= -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 upload-box-danger">
                <form action="pengaturan_aksi.php" method="POST" enctype="multipart/form-data" id="form-restore-total">
                    <input type="hidden" name="aksi" value="lakukan_restore_total">
                    <div class="card-body text-center p-4 d-flex flex-column">
                        <h5 class="text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Restore / Kloning Total</h5>
                        <p class="text-danger small">
                            <strong>PERINGATAN:</strong> Gunakan ini hanya untuk memulihkan data dari file backup yang dibuat dengan <strong>versi aplikasi yang SAMA</strong>.
                        </p>
                        <div class="alert alert-danger p-2 small">
                            Ini akan MENGHAPUS TOTAL database server dan menggantinya dengan isi file.
                        </div>
                        <div class="mb-3 mt-auto">
                            <label for="sql_file_restore" class="form-label fw-bold">Pilih File .sql (dari Aplikasi Baru)</label>
                            <input class="form-control" type="file" id="sql_file_restore" name="sql_file_restore" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-lg btn-danger"><i class="bi bi-upload me-2"></i>Mulai Restore Total</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Arsip File Backup -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-archive-fill me-2"></i>Arsip File Backup (Cadangan Server)</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle-fill me-2"></i>Info:</strong> Ini adalah daftar file backup yang dibuat dari database *server* ini.
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama File</th>
                            <th>Tanggal Dibuat</th>
                            <th class="text-end">Ukuran File</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backup_files)): ?>
                            <tr><td colspan="4" class="text-center text-muted p-5">Belum ada file backup yang dibuat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($backup_files as $file): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars(basename($file)); ?></td>
                                <td><?php echo date("d F Y, H:i:s", filemtime($file)); ?></td>
                                <td class="text-end"><?php echo round(filesize($file) / 1024, 2); ?> KB</td>
                                <td class="text-center">
                                    <a href="<?php echo $file; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Download" download><i class="bi bi-download"></i></a>
                                    <a href="#" onclick="hapusBackup('<?php echo urlencode(basename($file)); ?>')" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Hapus"><i class="bi bi-trash-fill"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });

    // =============================================
    // === JAVASCRIPT BARU UNTUK FORM MIGRASI     ===
    // =============================================
    const formMigrasi = document.getElementById('form-migrasi');
    if(formMigrasi) {
        formMigrasi.addEventListener('submit', function (e) {
            e.preventDefault();
            const sqlFile = document.getElementById('sql_file_migrasi').files;
            
            if(sqlFile.length === 0) {
                Swal.fire('File Belum Dipilih', 'Anda harus memilih file .sql (dari aplikasi lama) terlebih dahulu.', 'error');
                return;
            }

            const fileName = sqlFile[0].name;

            Swal.fire({
                title: 'Konfirmasi Migrasi Data?',
                html: `Anda akan mengimpor data dari file <strong>${fileName}</strong> ke aplikasi baru.<br><br>Database di server akan <strong>DIKOSONGKAN</strong> terlebih dahulu, lalu diisi dengan data lama Anda, dan strukturnya akan diperbarui.<br><br><b class='text-danger'>Yakin ingin melanjutkan?</b>`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Mulai Migrasi!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses Migrasi...',
                        html: 'Ini mungkin memakan waktu beberapa menit. Jangan tutup halaman ini.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    e.target.submit();
                }
            });
        });
    }

    // =============================================
    // === JAVASCRIPT LAMA UNTUK RESTORE TOTAL    ===
    // =============================================
    const formRestore = document.getElementById('form-restore-total');
    if(formRestore) {
        formRestore.addEventListener('submit', function (e) {
            e.preventDefault();
            // PERBAIKAN: Menggunakan ID yang benar
            const sqlFile = document.getElementById('sql_file_restore').files;
            
            if(sqlFile.length === 0) {
                Swal.fire('File Belum Dipilih', 'Anda harus memilih file .sql terlebih dahulu.', 'error');
                return;
            }

            const fileName = sqlFile[0].name;

            Swal.fire({
                title: 'ANDA SANGAT YAKIN?',
                html: `Anda akan <strong>MENGHAPUS TOTAL</strong> database di server ini dan menimpanya dengan file <strong>${fileName}</strong>.<br><br><b class='text-danger'>TINDAKAN INI TIDAK BISA DIBATALKAN!</b>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus dan Timpa!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses Restore...',
                        html: 'Ini mungkin memakan waktu beberapa menit. Jangan tutup halaman ini.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    e.target.submit();
                }
            });
        });
    }
});

// Fungsi hapus backup (tidak berubah)
function hapusBackup(namaFile) {
    Swal.fire({
        title: 'Anda yakin?', text: `File backup "${decodeURIComponent(namaFile)}" akan dihapus permanen!`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'pengaturan_aksi.php?aksi=hapus_backup&file=' + namaFile;
        }
    });
}
</script>

<?php include 'footer.php'; ?>