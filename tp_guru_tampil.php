<?php
include 'koneksi.php';
include 'header.php';

// Validasi peran, hanya guru yang bisa mengakses
if ($_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk peran Guru.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_guru_login = $_SESSION['id_guru'];

// === OPTIMASI PERFORMA (LANGKAH 1): Ambil semua data TP dalam satu query ===
$query_tp = "SELECT tp.id_tp, tp.id_mapel, tp.semester, tp.kode_tp, tp.deskripsi_tp, m.nama_mapel 
             FROM tujuan_pembelajaran tp 
             JOIN mata_pelajaran m ON tp.id_mapel = m.id_mapel
             WHERE tp.id_guru_pembuat = ? 
             AND tp.id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status='Aktif')
             ORDER BY m.nama_mapel, tp.semester, tp.kode_tp";
$stmt = mysqli_prepare($koneksi, $query_tp);
mysqli_stmt_bind_param($stmt, "i", $id_guru_login);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$tp_data = [];
$tp_ids = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tp_data[] = $row;
    $tp_ids[] = $row['id_tp']; // Kumpulkan semua ID TP
}

// === OPTIMASI PERFORMA (LANGKAH 2): Ambil semua penugasan kelas dalam satu query ===
$kelas_per_tp = [];
if (!empty($tp_ids)) {
    $id_list = implode(',', $tp_ids); // Buat daftar ID untuk klausa IN
    $query_kelas = "SELECT tk.id_tp, k.nama_kelas 
                    FROM tp_kelas tk 
                    JOIN kelas k ON tk.id_kelas = k.id_kelas 
                    WHERE tk.id_tp IN ($id_list) 
                    ORDER BY k.nama_kelas";
    $result_kelas = mysqli_query($koneksi, $query_kelas);
    while ($row = mysqli_fetch_assoc($result_kelas)) {
        $kelas_per_tp[$row['id_tp']][] = $row['nama_kelas'];
    }
}

// Kelompokkan data TP berdasarkan Mata Pelajaran untuk ditampilkan
$tp_per_mapel = [];
foreach ($tp_data as $tp) {
    $tp_per_mapel[$tp['nama_mapel']][] = $tp;
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    
    .mapel-group-header td {
        background-color: var(--bs-secondary-bg);
        font-weight: bold;
        color: var(--primary-color);
        border-bottom: 2px solid var(--primary-color) !important;
    }
    .badge.bg-kelas {
        background-color: var(--bs-secondary-bg-subtle) !important;
        color: var(--bs-emphasis-color) !important;
        border: 1px solid var(--bs-secondary-border-subtle);
        font-weight: 500;
    }
    .badge.bg-kelas-belum {
        background-color: var(--bs-warning-bg-subtle) !important;
        color: var(--bs-warning-text-emphasis) !important;
        border: 1px solid var(--bs-warning-border-subtle);
        font-weight: 500;
    }

    /* Style untuk Modal Penugasan Tunggal */
    #modalPenugasan .modal-body-content { min-height: 200px; }
    #modalPenugasan .jenjang-group { border-bottom: 2px solid #eee; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    #modalPenugasan .jenjang-group:last-child { border-bottom: none; margin-bottom: 0; }
    #modalPenugasan .jenjang-title { font-weight: 600; font-size: 1.25rem; color: var(--primary-color); margin-bottom: 1rem; }
    #modalPenugasan .form-check-lg.form-switch { padding-left: 3.5rem; min-height: 2.5rem; }
    #modalPenugasan .form-check-lg.form-switch .form-check-input { width: 3rem; height: 1.5rem; }
    #modalPenugasan .form-check-lg.form-switch .form-check-label { font-size: 1.1rem; padding-top: 0.25rem; }
    
    /* Style untuk Modal Penugasan Massal */
    #modalPenugasanMassal .form-check-label { font-size: 1.05rem; }
    
    /* Spinner Loading */
    .modal-loading-spinner {
        display: none; /* Sembunyikan default */
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(255, 255, 255, 0.7);
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Bank Tujuan Pembelajaran (TP)</h1>
                <p class="lead mb-0 opacity-75">Kelola semua TP yang Anda buat untuk tahun ajaran aktif.</p>
            </div>
            <div class="d-flex align-items-center flex-wrap mt-3 mt-sm-0" style="gap: 0.5rem;">
                <!-- Tombol Aksi Massal -->
                <button type="button" id="btn-tugaskan-massal" class="btn btn-primary" disabled data-bs-toggle="modal" data-bs-target="#modalPenugasanMassal"><i class="bi bi-door-open-fill me-2"></i>Tugaskan Pilihan</button>
                <button type="button" id="btn-hapus-massal" class="btn btn-danger" disabled><i class="bi bi-trash-fill me-2"></i>Hapus Pilihan</button>
                <a href="tp_guru_import.php" class="btn btn-light"><i class="bi bi-file-earmark-arrow-up-fill me-2"></i>Import TP</a>
                <a href="tp_guru_tambah.php" class="btn btn-outline-light"><i class="bi bi-plus-circle-fill me-2"></i>Tambah TP Baru</a>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-collection-fill me-2" style="color: var(--primary-color);"></i>Daftar Tujuan Pembelajaran Anda</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tp_per_mapel)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-journal-x fs-1 text-muted"></i>
                    <h4 class="mt-3">Anda Belum Membuat TP</h4>
                    <p class="text-muted">Silakan mulai dengan menambahkan TP baru atau mengimpor dari file.</p>
                </div>
            <?php else: ?>
                <form action="tp_guru_aksi.php" method="post" id="form-bulk-action">
                    <!-- Aksi dinamis (hapus_massal atau tugaskan_massal) -->
                    <input type="hidden" name="aksi" id="bulk_action_input" value="">
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;" class="text-center"><input class="form-check-input" type="checkbox" id="select-all-checkbox"></th>
                                    <th style="width: 10%;" class="text-center">Semester</th>
                                    <th style="width: 10%;">Kode TP</th>
                                    <th>Deskripsi Tujuan Pembelajaran</th>
                                    <th style="width: 25%;">Ditugaskan di Kelas</th>
                                    <th class="text-center" style="width: 15%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tp_per_mapel as $nama_mapel => $tps): ?>
                                    <tr class="mapel-group-header">
                                        <td colspan="6"> 
                                            <h5 class="mb-0"><i class="bi bi-book-half me-2"></i><?php echo htmlspecialchars($nama_mapel); ?></h5>
                                        </td>
                                    </tr>
                                    <?php foreach ($tps as $tp): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input class="form-check-input tp-checkbox" type="checkbox" name="tp_ids[]" value="<?php echo $tp['id_tp']; ?>">
                                        </td>
                                        <td class="text-center fw-bold"><?php echo $tp['semester']; ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($tp['kode_tp']); ?></span></td>
                                        <td><?php echo htmlspecialchars($tp['deskripsi_tp']); ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap" style="gap: 0.25rem;">
                                                <?php
                                                if (isset($kelas_per_tp[$tp['id_tp']])) {
                                                    foreach ($kelas_per_tp[$tp['id_tp']] as $nama_kelas) {
                                                        echo '<span class="badge bg-kelas">' . htmlspecialchars($nama_kelas) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-kelas-belum">Belum ditugaskan</span>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="tp_guru_edit.php?id_tp=<?php echo $tp['id_tp']; ?>" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Edit TP"><i class="bi bi-pencil-fill"></i></a>
                                                <button type="button" class="btn btn-outline-primary btn-sm btn-tugaskan" 
                                                        data-bs-toggle="modal" data-bs-target="#modalPenugasan" 
                                                        data-id-tp="<?php echo $tp['id_tp']; ?>" 
                                                        data-deskripsi-tp="<?php echo htmlspecialchars($tp['deskripsi_tp']); ?>"
                                                        data-bs-toggle="tooltip" title="Tugaskan ke Kelas (Individu)">
                                                    <i class="bi bi-door-open-fill"></i>
                                                </button>
                                                <button type="button" onclick="konfirmasiHapus(<?php echo $tp['id_tp']; ?>)" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Hapus TP"><i class="bi bi-trash-fill"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal untuk Penugasan TP Individu -->
<div class="modal fade" id="modalPenugasan" tabindex="-1" aria-labelledby="modalPenugasanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPenugasanLabel">Tugaskan TP ke Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="tp_guru_aksi.php" method="POST">
                <input type="hidden" name="aksi" value="tugaskan_tp">
                <input type="hidden" name="id_tp" id="modal_id_tp" value="">
                
                <div class="modal-body position-relative">
                    <div id="modalLoadingSpinner" class="modal-loading-spinner spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="fst-italic text-muted">TP: "<strong id="modal_deskripsi_tp"></strong>"</p>
                    <hr>
                    <div id="modalPenugasanKonten" class="modal-body-content">
                        <!-- Konten (daftar kelas) akan dimuat di sini oleh AJAX -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-2"></i>Batal</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-floppy-fill me-2"></i>Simpan Penugasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- [BARU] Modal untuk Penugasan TP Massal -->
<div class="modal fade" id="modalPenugasanMassal" tabindex="-1" aria-labelledby="modalPenugasanMassalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPenugasanMassalLabel">Tugaskan TP Pilihan ke Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body position-relative">
                <div id="modalLoadingSpinnerMassal" class="modal-loading-spinner spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Pilih satu atau lebih kelas untuk menugaskan <strong id="jumlahTpTerpilih">0</strong> TP yang sudah Anda pilih.</p>
                <hr>
                <div id="modalPenugasanMassalKonten" class="modal-body-content">
                    <!-- Konten (daftar kelas) akan dimuat di sini oleh AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-2"></i>Batal</button>
                <button type="button" class="btn btn-success" id="btnSimpanPenugasanMassal"><i class="bi bi-floppy-fill me-2"></i>Tugaskan ke Kelas Terpilih</button>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // --- Logika untuk Modal Penugasan INDIVIDU ---
    const modalPenugasan = document.getElementById('modalPenugasan');
    if (modalPenugasan) {
        const modalSpinner = modalPenugasan.querySelector('.modal-loading-spinner');
        const modalKonten = modalPenugasan.querySelector('.modal-body-content');
        const modalIdTpField = modalPenugasan.querySelector('#modal_id_tp');
        const modalDeskripsiTpField = modalPenugasan.querySelector('#modal_deskripsi_tp');

        document.querySelectorAll('.btn-tugaskan').forEach(button => {
            button.addEventListener('click', function() {
                const idTp = this.dataset.idTp;
                const deskripsiTp = this.dataset.deskripsiTp;
                
                modalIdTpField.value = idTp;
                modalDeskripsiTpField.textContent = deskripsiTp;
                modalSpinner.style.display = 'flex';
                modalKonten.innerHTML = '';

                fetch('tp_guru_get_kelas.php?id_tp=' + idTp)
                    .then(response => response.text())
                    .then(html => {
                        modalKonten.innerHTML = html;
                        modalSpinner.style.display = 'none';
                    })
                    .catch(error => {
                        modalKonten.innerHTML = '<div class="alert alert-danger">Gagal memuat daftar kelas.</div>';
                        modalSpinner.style.display = 'none';
                    });
            });
        });
    }
    
    // --- Logika untuk Aksi MASSA L---
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const itemCheckboxes = document.querySelectorAll('.tp-checkbox');
    const bulkDeleteBtn = document.getElementById('btn-hapus-massal');
    const bulkAssignBtn = document.getElementById('btn-tugaskan-massal'); // Tombol baru
    const bulkActionForm = document.getElementById('form-bulk-action');
    const bulkActionInput = document.getElementById('bulk_action_input');

    function updateBulkActionButtons() {
        const selectedCount = document.querySelectorAll('.tp-checkbox:checked').length;
        const anyChecked = selectedCount > 0;
        
        bulkDeleteBtn.disabled = !anyChecked;
        bulkAssignBtn.disabled = !anyChecked;

        // Update jumlah di modal massal
        document.getElementById('jumlahTpTerpilih').textContent = selectedCount;
    }

    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionButtons();
        });
    }

    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            if (!this.checked) {
                if(selectAllCheckbox) selectAllCheckbox.checked = false;
            } else {
                const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                if(selectAllCheckbox) selectAllCheckbox.checked = allChecked;
            }
            updateBulkActionButtons();
        });
    });

    // Aksi untuk tombol HAPUS MASSAL
    if(bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedCount = document.querySelectorAll('.tp-checkbox:checked').length;
            Swal.fire({
                title: 'Anda Yakin?',
                html: `Anda akan menghapus <b>${selectedCount}</b> TP yang dipilih. Tindakan ini tidak dapat dibatalkan.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus Semua!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkActionInput.value = 'hapus_massal'; // Set aksi
                    bulkActionForm.submit();
                }
            });
        });
    }

    // --- [BARU] Logika untuk Modal Penugasan MASSAL ---
    const modalPenugasanMassal = document.getElementById('modalPenugasanMassal');
    if (modalPenugasanMassal) {
        const modalSpinnerMassal = modalPenugasanMassal.querySelector('.modal-loading-spinner');
        const modalKontenMassal = modalPenugasanMassal.querySelector('.modal-body-content');
        const btnSimpanMassal = document.getElementById('btnSimpanPenugasanMassal');

        // Saat modal dibuka
        modalPenugasanMassal.addEventListener('show.bs.modal', function() {
            modalSpinnerMassal.style.display = 'flex';
            modalKontenMassal.innerHTML = '';
            
            // Panggil file baru untuk daftar kelas
            fetch('tp_guru_get_kelas_list.php')
                .then(response => response.text())
                .then(html => {
                    modalKontenMassal.innerHTML = html;
                    modalSpinnerMassal.style.display = 'none';
                })
                .catch(error => {
                    modalKontenMassal.innerHTML = '<div class="alert alert-danger">Gagal memuat daftar kelas.</div>';
                    modalSpinnerMassal.style.display = 'none';
                });
        });

        // Saat tombol SIMPAN di modal massal diklik
        btnSimpanMassal.addEventListener('click', function() {
            // Ambil semua ID kelas yang dicentang di modal
            const kelasIds = Array.from(modalKontenMassal.querySelectorAll('input[name="id_kelas_massal[]"]:checked'))
                                .map(cb => cb.value);

            if (kelasIds.length === 0) {
                Swal.fire('Tidak Ada Kelas', 'Anda harus memilih minimal satu kelas.', 'warning');
                return;
            }

            // Tambahkan ID kelas yang dipilih ke form utama
            kelasIds.forEach(idKelas => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'id_kelas_tujuan[]';
                hiddenInput.value = idKelas;
                bulkActionForm.appendChild(hiddenInput);
            });

            // Set aksi dan submit form utama
            bulkActionInput.value = 'tugaskan_massal';
            bulkActionForm.submit();
        });
    }
});

// Fungsi konfirmasi hapus tunggal
function konfirmasiHapus(id) {
    Swal.fire({
        title: 'Anda Yakin?',
        html: "TP yang dihapus tidak dapat dikembalikan dan akan dilepas dari semua kelas yang menggunakannya.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus Saja!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'tp_guru_aksi.php?aksi=hapus&id_tp=' + id;
        }
    })
}
</script>

<?php
if (isset($_SESSION['pesan'])) {
    // Menggunakan format JSON dari file aksi (jika ada)
    $pesan = $_SESSION['pesan'];
    if (strpos($pesan, '{') === 0) { // Cek jika ini format JSON
        echo "<script>Swal.fire(" . $pesan . ");</script>";
    } else { // Fallback untuk string biasa
        echo "<script>Swal.fire('Berhasil', '" . addslashes($pesan) . "', 'success');</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>

