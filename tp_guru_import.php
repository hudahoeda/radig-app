<?php
include 'koneksi.php';
include 'header.php';

// Pastikan hanya guru yang bisa mengakses
if ($_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }
    
    .step-timeline {
        position: relative;
        padding-left: 50px;
        border-left: 3px solid var(--border-color);
    }
    .step-item {
        position: relative;
        margin-bottom: 2.5rem;
    }
    .step-item:last-child {
        margin-bottom: 0;
    }
    .step-number {
        position: absolute;
        left: -69px; /* Posisi relatif terhadap garis */
        top: -5px;
        width: 40px;
        height: 40px;
        background-color: var(--background-light);
        border: 3px solid var(--secondary-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        color: var(--secondary-color);
    }
    .step-item h4 {
        color: var(--primary-color);
        font-weight: 600;
    }

    /* Styling untuk Drag and Drop Zone */
    .drop-zone {
        border: 2px dashed var(--border-color);
        border-radius: 0.5rem;
        padding: 3rem;
        text-align: center;
        color: var(--text-muted);
        transition: all 0.2s ease-in-out;
        cursor: pointer;
    }
    .drop-zone.dragover {
        border-color: var(--primary-color);
        background-color: #e0f2f1; /* Light Teal */
    }
    .drop-zone .drop-zone-icon {
        font-size: 3rem;
        color: var(--primary-color);
    }
    .drop-zone-prompt {
        font-weight: 500;
    }
    #file-name {
        font-weight: 600;
        color: var(--secondary-color);
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Import Tujuan Pembelajaran</h1>
                <p class="lead mb-0 opacity-75">Unggah data TP secara massal dari file Excel.</p>
            </div>
            <a href="tp_guru_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i> Kembali ke Daftar TP</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="step-timeline">

                <div class="step-item">
                    <div class="step-number">1</div>
                    <h4>Unduh Template</h4>
                    <p class="text-muted">Mulailah dengan mengunduh template Excel yang telah kami sediakan. Pastikan untuk tidak mengubah nama kolom yang ada di dalamnya.</p>
                    <a href="tp_guru_import_template.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-arrow-down-fill me-2"></i>Download Template Excel
                    </a>
                </div>

                <div class="step-item">
                    <div class="step-number">2</div>
                    <h4>Isi Data Sesuai Petunjuk</h4>
                    <p class="text-muted">Buka file template menggunakan aplikasi spreadsheet (seperti Microsoft Excel atau Google Sheets), lalu isi data Tujuan Pembelajaran Anda pada baris-baris yang tersedia.</p>
                </div>

                <div class="step-item">
                    <div class="step-number">3</div>
                    <h4>Unggah File Anda</h4>
                    <p class="text-muted">Setelah selesai mengisi, simpan file Anda. Kemudian, unggah file tersebut di area di bawah ini.</p>
                    
                    <form action="tp_guru_import_aksi.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="drop-zone" id="dropZone">
                            <i class="bi bi-cloud-arrow-up drop-zone-icon"></i>
                            <p class="drop-zone-prompt mt-3 mb-1">Seret file Excel ke sini, atau <strong>klik untuk memilih file</strong></p>
                            <small class="text-muted">Hanya file .xlsx yang diterima</small>
                            <p class="mt-2" id="file-name"></p>
                        </div>
                        <input class="d-none" type="file" id="file_import" name="file_import" accept=".xlsx">
                        
                        <button type="submit" class="btn btn-primary mt-4 btn-lg" id="submitBtn" disabled>
                            <i class="bi bi-upload me-2"></i>Unggah dan Proses
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('file_import');
    const fileNameDisplay = document.getElementById('file-name');
    const submitBtn = document.getElementById('submitBtn');
    const uploadForm = document.getElementById('uploadForm');

    // Memicu klik pada input file saat drop zone diklik
    dropZone.addEventListener('click', () => fileInput.click());

    // Menangani saat file diseret ke atas drop zone
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    // Menangani saat file meninggalkan area drop zone
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    // Menangani saat file di-drop
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length) {
            handleFile(files[0]);
        }
    });

    // Menangani saat file dipilih melalui dialog file
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            handleFile(fileInput.files[0]);
        }
    });

    // Fungsi untuk memvalidasi dan menampilkan nama file
    function handleFile(file) {
        // Validasi tipe file
        if (file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            fileInput.files = new DataTransfer().files; // Reset file input jika ada
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;

            fileNameDisplay.textContent = `File terpilih: ${file.name}`;
            submitBtn.disabled = false; // Aktifkan tombol submit
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Format File Salah',
                text: 'Harap unggah file dengan format .xlsx (Excel).',
            });
            fileNameDisplay.textContent = '';
            submitBtn.disabled = true; // Non-aktifkan tombol submit
        }
    }

    // Mencegah submit form jika tidak ada file
    uploadForm.addEventListener('submit', (e) => {
        if (fileInput.files.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Tidak Ada File',
                text: 'Silakan pilih file Excel yang akan diunggah.',
            });
        }
    });
});
</script>

<?php include 'footer.php'; ?>