<?php
include 'koneksi.php';
include 'header.php';

// ===========================================================
// VALIDASI AKSES
// ===========================================================
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Guru dan Admin.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// ===========================================================
// AMBIL DATA DARI URL & SESSION
// ===========================================================
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
$id_mapel = isset($_GET['id_mapel']) ? (int)$_GET['id_mapel'] : 0;
$id_guru = (int)$_SESSION['id_guru'];

if ($id_kelas == 0 || $id_mapel == 0) {
    echo "<script>Swal.fire('Error','Informasi Kelas atau Mata Pelajaran tidak lengkap.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// ===========================================================
// DATA KELAS & MAPEL UTAMA
// ===========================================================
$q_kelas = mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas = $id_kelas");
$nama_kelas = mysqli_fetch_assoc($q_kelas)['nama_kelas'] ?? 'N/A';

$q_mapel = mysqli_query($koneksi, "SELECT nama_mapel FROM mata_pelajaran WHERE id_mapel = $id_mapel");
$nama_mapel = mysqli_fetch_assoc($q_mapel)['nama_mapel'] ?? 'N/A';

// ===========================================================
// PENGATURAN (TAHUN AJARAN & SEMESTER)
// ===========================================================
// Ambil semester aktif
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil tahun ajaran aktif (Penting untuk filter kelas paralel agar tidak muncul kelas tahun lalu)
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

// ===========================================================
// AMBIL DAFTAR TP (TUJUAN PEMBELAJARAN)
// ===========================================================
$query_tp = mysqli_prepare($koneksi, "
    SELECT tp.id_tp, tp.deskripsi_tp 
    FROM tujuan_pembelajaran tp 
    JOIN tp_kelas tk ON tp.id_tp = tk.id_tp 
    WHERE tp.id_mapel = ? 
    AND tp.id_guru_pembuat = ? 
    AND tp.semester = ? 
    AND tk.id_kelas = ? 
    ORDER BY tp.deskripsi_tp ASC
");
mysqli_stmt_bind_param($query_tp, "iiii", $id_mapel, $id_guru, $semester_aktif, $id_kelas);
mysqli_stmt_execute($query_tp);
$result_tp = mysqli_stmt_get_result($query_tp);
$daftar_tp = [];
while ($tp = mysqli_fetch_assoc($result_tp)) {
    $daftar_tp[] = $tp;
}

// ===========================================================
// FITUR BARU: AMBIL KELAS PARALEL (DUPLIKASI)
// ===========================================================
// Mencari kelas lain yang diajar guru ini, mapel ini, tahun ajaran ini, selain kelas yang sedang dipilih
$query_kelas_lain = "
    SELECT k.id_kelas, k.nama_kelas 
    FROM guru_mengajar gm
    JOIN kelas k ON gm.id_kelas = k.id_kelas
    WHERE gm.id_guru = ? 
    AND gm.id_mapel = ? 
    AND gm.id_tahun_ajaran = ?
    AND k.id_kelas != ? 
    ORDER BY k.nama_kelas ASC
";
$stmt_kls = mysqli_prepare($koneksi, $query_kelas_lain);
mysqli_stmt_bind_param($stmt_kls, "iiii", $id_guru, $id_mapel, $id_tahun_ajaran, $id_kelas);
mysqli_stmt_execute($stmt_kls);
$res_kelas_lain = mysqli_stmt_get_result($stmt_kls);
?>

<style>
    /* Style CSS Asli */
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
    /* Style Tambahan untuk Card Duplikasi */
    .card-duplikasi {
        border-left: 5px solid #17a2b8; /* Warna Info/Biru Muda */
        background-color: #f0fcff;
    }
    .card-duplikasi .form-check-input:checked {
        background-color: #17a2b8;
        border-color: #17a2b8;
    }
</style>

<div class="container-fluid">
    <!-- Header Halaman -->
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Buat Penilaian</h1>
                <p class="lead mb-0 opacity-75">
                    Kelas: <strong><?php echo htmlspecialchars($nama_kelas); ?></strong> | 
                    Mapel: <strong><?php echo htmlspecialchars($nama_mapel); ?></strong>
                </p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="penilaian_tampil.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Card Form Utama -->
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="penilaian_aksi.php?aksi=tambah_penilaian" method="POST" id="form-penilaian-batch">
                <!-- Input Hidden ID Kelas & ID Mapel -->
                <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
                <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">

                <!-- Container Baris Penilaian (Diisi via JS) -->
                <div id="container-penilaian">
                    <!-- Baris pertama akan dimuat otomatis oleh JS -->
                </div>

                <!-- Tombol Tambah Baris -->
                <button type="button" class="btn btn-primary mt-3" id="btn-tambah-row">
                    <i class="bi bi-plus-circle-fill me-2"></i>Tambah Baris Penilaian
                </button>

                <!-- ==================================================== -->
                <!-- FITUR DUPLIKASI KELAS PARALEL -->
                <!-- ==================================================== -->
                <?php if (mysqli_num_rows($res_kelas_lain) > 0): ?>
                <div class="card card-duplikasi mt-5 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-info fw-bold">
                            <i class="bi bi-copy me-2"></i>Duplikat Penilaian ke Kelas Paralel
                        </h5>
                        <p class="text-muted small mb-3">
                            Hemat waktu Anda! Centang kelas di bawah ini untuk membuat penilaian yang sama persis (Nama, Tanggal, Jenis, TP) ke kelas lain yang Anda ajar untuk mapel ini.
                        </p>
                        
                        <!-- Checkbox Pilih Semua -->
                        <div class="form-check mb-2 pb-2 border-bottom">
                            <input class="form-check-input" type="checkbox" id="check_all_kelas">
                            <label class="form-check-label fw-bold text-dark" for="check_all_kelas">
                                Pilih Semua Kelas Paralel
                            </label>
                        </div>

                        <!-- Daftar Checkbox Kelas -->
                        <div class="row g-3 mt-1">
                            <?php while($kls = mysqli_fetch_assoc($res_kelas_lain)): ?>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check p-2 border rounded bg-white h-100 d-flex align-items-center shadow-sm">
                                        <input class="form-check-input kelas-target ms-1" type="checkbox" name="target_kelas[]" value="<?php echo $kls['id_kelas']; ?>" id="kls_<?php echo $kls['id_kelas']; ?>">
                                        <label class="form-check-label ms-2 w-100" style="cursor:pointer;" for="kls_<?php echo $kls['id_kelas']; ?>">
                                            <?php echo htmlspecialchars($kls['nama_kelas']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <!-- ==================================================== -->

                <hr class="my-4">
                
                <!-- Tombol Aksi Akhir -->
                <div class="d-flex justify-content-end">
                    <a href="penilaian_tampil.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-secondary me-2">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                        <i class="bi bi-floppy-fill me-2"></i>Simpan Semua Penilaian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================================================== -->
<!-- TEMPLATE JAVASCRIPT (HIDDEN) -->
<!-- ==================================================== -->
<template id="template-penilaian-row">
    <div class="penilaian-row p-4 mb-3 shadow-sm">
        <!-- Tombol Hapus Baris -->
        <button type="button" class="btn btn-danger btn-sm btn-hapus-row" title="Hapus baris ini">
            <i class="bi bi-trash-fill"></i>
        </button>
        
        <div class="row">
            <!-- Nama Penilaian -->
            <div class="col-md-7 mb-3">
                <label for="nama_penilaian_0" class="form-label fw-bold">Nama Penilaian <span class="row-counter">1</span></label>
                <input type="text" class="form-control" id="nama_penilaian_0" name="penilaian[0][nama_penilaian]" placeholder="Contoh: Sumatif Bab 1, Formatif Diskusi" required>
            </div>
            <!-- Tanggal -->
            <div class="col-md-5 mb-3">
                <label for="tanggal_penilaian_0" class="form-label fw-bold">Tanggal</label>
                <input type="date" class="form-control" id="tanggal_penilaian_0" name="penilaian[0][tanggal_penilaian]" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        
        <div class="row">
            <!-- Jenis Penilaian -->
            <div class="col-md-12 mb-3">
                <label for="jenis_penilaian_0" class="form-label fw-bold">Jenis Penilaian</label>
                <select class="form-select jenis-penilaian-select" id="jenis_penilaian_0" name="penilaian[0][jenis_penilaian]" required>
                    <option value="Formatif">Formatif (Untuk Analisis Guru)</option>
                    <option value="Sumatif" selected>Sumatif (Untuk Perhitungan Rapor)</option>
                </select>
            </div>
        </div>

        <!-- Opsi Khusus Sumatif (Hidden by default) -->
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
        
        <!-- Daftar Tujuan Pembelajaran -->
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
    
    // Logic "Pilih Semua Kelas"
    const checkAll = document.getElementById('check_all_kelas');
    if(checkAll) {
        checkAll.addEventListener('change', function() {
            const targets = document.querySelectorAll('.kelas-target');
            targets.forEach(cb => cb.checked = this.checked);
        });
    }

    let rowIndex = 0;

    function addPenilaianRow() {
        // Clone template
        const clone = template.content.cloneNode(true);
        const newRow = clone.querySelector('.penilaian-row');
        
        // Update Counter Visual
        newRow.querySelector('.row-counter').textContent = rowIndex + 1;
        
        // Update Name Attributes & IDs agar unik array-nya
        // 1. Nama Penilaian
        const textInput = newRow.querySelector('input[type="text"]');
        textInput.id = `nama_penilaian_${rowIndex}`;
        textInput.name = `penilaian[${rowIndex}][nama_penilaian]`;
        newRow.querySelector(`label[for="nama_penilaian_0"]`).htmlFor = textInput.id;

        // 2. Tanggal
        const dateInput = newRow.querySelector('input[type="date"]');
        dateInput.id = `tanggal_penilaian_${rowIndex}`;
        dateInput.name = `penilaian[${rowIndex}][tanggal_penilaian]`;
        newRow.querySelector(`label[for="tanggal_penilaian_0"]`).htmlFor = dateInput.id;

        // 3. Jenis Penilaian
        const jenisSelect = newRow.querySelector('.jenis-penilaian-select');
        jenisSelect.id = `jenis_penilaian_${rowIndex}`;
        jenisSelect.name = `penilaian[${rowIndex}][jenis_penilaian]`;
        newRow.querySelector(`label[for="jenis_penilaian_0"]`).htmlFor = jenisSelect.id;

        // 4. Subjenis & Bobot (dalam div opsi-sumatif)
        const opsiSumatif = newRow.querySelector('.opsi-sumatif');
        
        const subjenisSelect = newRow.querySelector('.subjenis-penilaian-select');
        subjenisSelect.id = `subjenis_penilaian_${rowIndex}`;
        subjenisSelect.name = `penilaian[${rowIndex}][subjenis_penilaian]`;
        newRow.querySelector(`label[for="subjenis_penilaian_0"]`).htmlFor = subjenisSelect.id;

        const bobotInput = newRow.querySelector('input[type="number"]');
        bobotInput.id = `bobot_penilaian_${rowIndex}`;
        bobotInput.name = `penilaian[${rowIndex}][bobot_penilaian]`;
        newRow.querySelector(`label[for="bobot_penilaian_0"]`).htmlFor = bobotInput.id;

        // 5. Checkbox TP
        const containerTp = newRow.querySelector('.container-tp');
        newRow.querySelectorAll('.tp-checkbox').forEach(cb => {
            const val = cb.value;
            cb.id = `tp_${rowIndex}_${val}`;
            cb.name = `penilaian[${rowIndex}][id_tp][]`; // Array multidimensi
            cb.nextElementSibling.htmlFor = cb.id;
        });

        // Event Listener: Tampilkan/Sembunyikan Opsi Sumatif
        function toggleSections() {
            const isSumatif = jenisSelect.value === 'Sumatif';
            opsiSumatif.style.display = isSumatif ? 'block' : 'none';
            subjenisSelect.required = isSumatif;
            bobotInput.required = isSumatif;
            
            // Cek Subjenis untuk TP
            const subjenis = subjenisSelect.value;
            const isSumatifAkhir = (subjenis === 'Sumatif Akhir Semester' || subjenis === 'Sumatif Akhir Tahun');
            // Jika Sumatif Akhir, sembunyikan pilihan TP
            containerTp.style.display = (isSumatif && isSumatifAkhir) ? 'none' : 'block';
        }

        jenisSelect.addEventListener('change', toggleSections);
        subjenisSelect.addEventListener('change', toggleSections);
        toggleSections(); // Panggil saat inisialisasi

        // Event Listener: Hapus Baris
        newRow.querySelector('.btn-hapus-row').addEventListener('click', function() {
            if (container.querySelectorAll('.penilaian-row').length > 1) {
                newRow.remove();
                updateCounters();
            } else {
                Swal.fire('Info', 'Minimal harus ada satu baris penilaian.', 'info');
            }
        });

        // Masukkan ke container
        container.appendChild(newRow);
        rowIndex++;
        updateCounters();
    }

    function updateCounters() {
        container.querySelectorAll('.penilaian-row').forEach((row, index) => {
            row.querySelector('.row-counter').textContent = index + 1;
        });
    }

    // Validasi Form Sebelum Submit
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
            
            // Kapan TP wajib dipilih?
            // 1. Jika Formatif
            // 2. Jika Sumatif Lingkup Materi (Sumatif TP)
            const isTPRequired = jenis === 'Formatif' || (jenis === 'Sumatif' && subjenis === 'Sumatif TP');

            // Cek apakah TP tersedia di database?
            // Menggunakan PHP output dalam JS
            if (isTPRequired && <?php echo empty($daftar_tp) ? 'true' : 'false'; ?>) {
                 errorMessages.push(`Baris ${index + 1}: Tidak ada TP yang tersedia untuk mapel/kelas ini.`);
                 isValid = false;
                 return; // Lanjut ke baris berikutnya
            }

            // Cek apakah user mencentang minimal 1 TP
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

    // Tambahkan 1 baris saat halaman dimuat
    addPenilaianRow();

    // Event tombol tambah
    btnTambah.addEventListener('click', addPenilaianRow);
});
</script>

<?php include 'footer.php'; ?>