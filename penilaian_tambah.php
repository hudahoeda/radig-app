<?php
include 'koneksi.php';
include 'header.php';

// Validasi peran
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Guru dan Admin.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID dari URL dan validasi
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
$id_mapel = isset($_GET['id_mapel']) ? (int)$_GET['id_mapel'] : 0;
$id_guru = (int)$_SESSION['id_guru']; // Asumsi id_guru ada di session untuk guru

if ($id_kelas == 0 || $id_mapel == 0) {
    echo "<script>Swal.fire('Error','Informasi Kelas atau Mata Pelajaran tidak lengkap.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil nama kelas & mapel
$q_kelas = mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas = $id_kelas");
$nama_kelas = mysqli_fetch_assoc($q_kelas)['nama_kelas'] ?? 'N/A';
$q_mapel = mysqli_query($koneksi, "SELECT nama_mapel FROM mata_pelajaran WHERE id_mapel = $id_mapel");
$nama_mapel = mysqli_fetch_assoc($q_mapel)['nama_mapel'] ?? 'N/A';

// Ambil semester aktif
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil daftar TP untuk di-clone
$query_tp = mysqli_prepare($koneksi, "SELECT tp.id_tp, tp.deskripsi_tp FROM tujuan_pembelajaran tp JOIN tp_kelas tk ON tp.id_tp = tk.id_tp WHERE tp.id_mapel = ? AND tp.id_guru_pembuat = ? AND tp.semester = ? AND tk.id_kelas = ? ORDER BY tp.deskripsi_tp ASC");
mysqli_stmt_bind_param($query_tp, "iiii", $id_mapel, $id_guru, $semester_aktif, $id_kelas);
mysqli_stmt_execute($query_tp);
$result_tp = mysqli_stmt_get_result($query_tp);
$daftar_tp = [];
while ($tp = mysqli_fetch_assoc($result_tp)) {
    $daftar_tp[] = $tp;
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
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
    }
    .tp-container {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        max-height: 200px;
        overflow-y: auto;
    }
    .penilaian-row {
        border: 1px solid #ddd;
        border-radius: 0.5rem;
        background: #fff;
        position: relative;
    }
    .penilaian-row .btn-danger {
        position: absolute;
        top: 1rem;
        right: 1rem;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Buat Penilaian</h1>
                <p class="lead mb-0 opacity-75">Kelas: <strong><?php echo htmlspecialchars($nama_kelas); ?></strong> | Mapel: <strong><?php echo htmlspecialchars($nama_mapel); ?></strong></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="penilaian_tampil.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="penilaian_aksi.php?aksi=tambah_penilaian" method="POST" id="form-penilaian-batch">
                <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
                <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">

                <div id="container-penilaian">
                    <!-- Template Row (akan di-clone) -->
                    <!-- Baris pertama dimuat oleh server -->
                </div>

                <button type="button" class="btn btn-primary mt-3" id="btn-tambah-row">
                    <i class="bi bi-plus-circle-fill me-2"></i>Tambah Baris Penilaian
                </button>

                <hr class="my-4">
                <div class="d-flex justify-content-end">
                    <a href="penilaian_tampil.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                    <button type="submit" class="btn btn-success btn-lg" id="submitBtn"><i class="bi bi-floppy-fill me-2"></i>Simpan Semua Penilaian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template HTML untuk baris penilaian baru (disembunyikan) -->
<template id="template-penilaian-row">
    <div class="penilaian-row p-4 mb-3 shadow-sm">
        <button type="button" class="btn btn-danger btn-sm btn-hapus-row"><i class="bi bi-trash-fill"></i></button>
        <div class="row">
            <div class="col-md-7 mb-3">
                <label for="nama_penilaian_0" class="form-label fw-bold">Nama Penilaian <span class="row-counter">1</span></label>
                <input type="text" class="form-control" id="nama_penilaian_0" name="penilaian[0][nama_penilaian]" placeholder="Contoh: Sumatif Bab 1, Formatif Diskusi" required>
            </div>
            <div class="col-md-5 mb-3">
                <label for="tanggal_penilaian_0" class="form-label fw-bold">Tanggal</label>
                <input type="date" class="form-control" id="tanggal_penilaian_0" name="penilaian[0][tanggal_penilaian]" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12 mb-3">
                <label for="jenis_penilaian_0" class="form-label fw-bold">Jenis Penilaian</label>
                <select class="form-select jenis-penilaian-select" id="jenis_penilaian_0" name="penilaian[0][jenis_penilaian]" required>
                    <option value="Formatif">Formatif (Untuk Analisis Guru)</option>
                    <option value="Sumatif" selected>Sumatif (Untuk Perhitungan Rapor)</option>
                </select>
            </div>
        </div>

        <div class="opsi-sumatif p-3 border rounded bg-light mt-2" style="display: none;">
            <h6 class="mb-3 fw-bold">Detail Penilaian Sumatif</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="subjenis_penilaian_0" class="form-label">Sub-Jenis Sumatif</label>
                    <select class="form-select subjenis-penilaian-select" id="subjenis_penilaian_0" name="penilaian[0][subjenis_penilaian]" required>
                        <option value="Sumatif TP" selected>Sumatif Lingkup Materi (Per TP)</option>
                        <?php if ($semester_aktif == 1): ?>
                            <option value="Sumatif Akhir Semester">Sumatif Akhir Semester (Ganjil)</option>
                        <?php else: ?>
                            <option value="Sumatif Akhir Semester">Sumatif Akhir Semester (Genap)</option>
                            <option value="Sumatif Akhir Tahun">Sumatif Akhir Tahun (Kenaikan)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="bobot_penilaian_0" class="form-label">Bobot Penilaian</label>
                    <input type="number" class="form-control" id="bobot_penilaian_0" name="penilaian[0][bobot_penilaian]" value="1" min="1" required>
                    <div class="form-text">Contoh: Sumatif per TP bobot 1, UAS bobot 2.</div>
                </div>
            </div>
        </div>
        
        <div class="container-tp mt-4">
            <label class="form-label fw-bold">Tujuan Pembelajaran (TP) yang Dinilai</label>
            <div class="border rounded p-3 tp-container">
                <?php if (!empty($daftar_tp)): ?>
                    <?php foreach ($daftar_tp as $tp): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input tp-checkbox" type="checkbox" name="penilaian[0][id_tp][]" value="<?php echo $tp['id_tp']; ?>" id="tp_0_<?php echo $tp['id_tp']; ?>">
                            <label class="form-check-label" for="tp_0_<?php echo $tp['id_tp']; ?>"><?php echo htmlspecialchars($tp['deskripsi_tp']); ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">Tidak ada Tujuan Pembelajaran yang ditugaskan untuk kelas ini.</div>
                <?php endif; ?>
            </div>
            <div class="form-text mt-2">Untuk Sumatif Akhir Semester/Tahun, tidak perlu memilih TP.</div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('container-penilaian');
    const template = document.getElementById('template-penilaian-row');
    const btnTambah = document.getElementById('btn-tambah-row');
    const form = document.getElementById('form-penilaian-batch');
    let rowIndex = 0;

    function addPenilaianRow() {
        const clone = template.content.cloneNode(true);
        const newRow = clone.querySelector('.penilaian-row');
        
        // Update IDs and names
        newRow.querySelector('.row-counter').textContent = rowIndex + 1;
        
        const textInput = newRow.querySelector('input[type="text"]');
        textInput.id = `nama_penilaian_${rowIndex}`;
        textInput.name = `penilaian[${rowIndex}][nama_penilaian]`;
        newRow.querySelector(`label[for="nama_penilaian_0"]`).htmlFor = textInput.id;

        const dateInput = newRow.querySelector('input[type="date"]');
        dateInput.id = `tanggal_penilaian_${rowIndex}`;
        dateInput.name = `penilaian[${rowIndex}][tanggal_penilaian]`;
        newRow.querySelector(`label[for="tanggal_penilaian_0"]`).htmlFor = dateInput.id;

        const jenisSelect = newRow.querySelector('.jenis-penilaian-select');
        jenisSelect.id = `jenis_penilaian_${rowIndex}`;
        jenisSelect.name = `penilaian[${rowIndex}][jenis_penilaian]`;
        newRow.querySelector(`label[for="jenis_penilaian_0"]`).htmlFor = jenisSelect.id;

        const opsiSumatif = newRow.querySelector('.opsi-sumatif');
        
        const subjenisSelect = newRow.querySelector('.subjenis-penilaian-select');
        subjenisSelect.id = `subjenis_penilaian_${rowIndex}`;
        subjenisSelect.name = `penilaian[${rowIndex}][subjenis_penilaian]`;
        newRow.querySelector(`label[for="subjenis_penilaian_0"]`).htmlFor = subjenisSelect.id;

        const bobotInput = newRow.querySelector('input[type="number"]');
        bobotInput.id = `bobot_penilaian_${rowIndex}`;
        bobotInput.name = `penilaian[${rowIndex}][bobot_penilaian]`;
        newRow.querySelector(`label[for="bobot_penilaian_0"]`).htmlFor = bobotInput.id;

        const containerTp = newRow.querySelector('.container-tp');
        
        // Update TP checkboxes
        newRow.querySelectorAll('.tp-checkbox').forEach(cb => {
            const val = cb.value;
            cb.id = `tp_${rowIndex}_${val}`;
            cb.name = `penilaian[${rowIndex}][id_tp][]`;
            cb.nextElementSibling.htmlFor = cb.id;
        });

        // Add event listeners for this row
        function toggleSections() {
            const isSumatif = jenisSelect.value === 'Sumatif';
            opsiSumatif.style.display = isSumatif ? 'block' : 'none';
            subjenisSelect.required = isSumatif;
            bobotInput.required = isSumatif;
            
            const subjenis = subjenisSelect.value;
            const isSumatifAkhir = (subjenis === 'Sumatif Akhir Semester' || subjenis === 'Sumatif Akhir Tahun');
            containerTp.style.display = (isSumatif && isSumatifAkhir) ? 'none' : 'block';
        }

        jenisSelect.addEventListener('change', toggleSections);
        subjenisSelect.addEventListener('change', toggleSections);
        toggleSections(); // Initial call

        newRow.querySelector('.btn-hapus-row').addEventListener('click', function() {
            if (container.querySelectorAll('.penilaian-row').length > 1) {
                newRow.remove();
                updateCounters();
            } else {
                Swal.fire('Info', 'Minimal harus ada satu baris penilaian.', 'info');
            }
        });

        container.appendChild(newRow);
        rowIndex++;
        updateCounters();
    }

    function updateCounters() {
        container.querySelectorAll('.penilaian-row').forEach((row, index) => {
            row.querySelector('.row-counter').textContent = index + 1;
        });
    }

    // Add submit validation
    form.addEventListener('submit', function(event) {
        let isValid = true;
        let errorMessages = [];
        
        container.querySelectorAll('.penilaian-row').forEach((row, index) => {
            const jenisSelect = row.querySelector('.jenis-penilaian-select');
            const subjenisSelect = row.querySelector('.subjenis-penilaian-select');
            const containerTp = row.querySelector('.container-tp');
            const tpCheckboxes = row.querySelectorAll('.tp-checkbox');

            const jenis = jenisSelect.value;
            const subjenis = subjenisSelect.value;
            const isTPRequired = jenis === 'Formatif' || (jenis === 'Sumatif' && subjenis === 'Sumatif TP');

            if (isTPRequired && <?php echo empty($daftar_tp) ? 'true' : 'false'; ?>) {
                 errorMessages.push(`Baris ${index + 1}: Tidak ada TP yang tersedia untuk mapel/kelas ini.`);
                 isValid = false;
                 return;
            }

            if (isTPRequired) {
                const isAnyTpSelected = Array.from(tpCheckboxes).some(cb => cb.checked);
                if (!isAnyTpSelected) {
                    errorMessages.push(`Baris ${index + 1}: Wajib memilih minimal satu TP untuk jenis penilaian ini.`);
                    isValid = false;
                }
            }
        });

        if (!isValid) {
            event.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                html: errorMessages.join('<br>'),
            });
        }
    });

    // Add first row on load
    addPenilaianRow();

    btnTambah.addEventListener('click', addPenilaianRow);
});
</script>

<?php include 'footer.php'; ?>
