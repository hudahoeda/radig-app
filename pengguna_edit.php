<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_guru = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_guru == 0) {
    echo "<script>Swal.fire('Error','ID Pengguna tidak valid.','error').then(() => window.location = 'pengguna_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Menggunakan prepared statement untuk keamanan
$stmt = mysqli_prepare($koneksi, "SELECT * FROM guru WHERE id_guru=?");
mysqli_stmt_bind_param($stmt, "i", $id_guru);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<script>Swal.fire('Error','Pengguna tidak ditemukan.','error').then(() => window.location = 'pengguna_tampil.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

// Ambil data penugasan yang lebih detail (mapel dan kelas)
$penugasan_diajar = [];
if ($id_tahun_ajaran_aktif > 0) {
    $stmt_mengajar = mysqli_prepare($koneksi, "SELECT id_mapel, id_kelas FROM guru_mengajar WHERE id_guru = ? AND id_tahun_ajaran = ?");
    mysqli_stmt_bind_param($stmt_mengajar, "ii", $id_guru, $id_tahun_ajaran_aktif);
    mysqli_stmt_execute($stmt_mengajar);
    $result_mengajar = mysqli_stmt_get_result($stmt_mengajar);
    while ($row = mysqli_fetch_assoc($result_mengajar)) {
        $penugasan_diajar[$row['id_mapel']][$row['id_kelas']] = true;
    }
}

// Ambil daftar kelas untuk tahun ajaran aktif
$daftar_kelas = [];
if ($id_tahun_ajaran_aktif > 0) {
    $query_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif ORDER BY nama_kelas ASC");
    $daftar_kelas = mysqli_fetch_all($query_kelas, MYSQLI_ASSOC);
}

// Ambil daftar mapel
$daftar_mapel = [];
$query_mapel = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel");
$daftar_mapel = mysqli_fetch_all($query_mapel, MYSQLI_ASSOC);

// --- [BARU] Ambil penugasan dari TAHUN AJARAN TIDAK AKTIF ---
$penugasan_tidak_aktif = [];
$stmt_tidak_aktif = mysqli_prepare($koneksi, "
    SELECT gm.id_guru_mengajar, mp.nama_mapel, k.nama_kelas, ta.tahun_ajaran
    FROM guru_mengajar gm
    JOIN mata_pelajaran mp ON gm.id_mapel = mp.id_mapel
    JOIN kelas k ON gm.id_kelas = k.id_kelas
    JOIN tahun_ajaran ta ON gm.id_tahun_ajaran = ta.id_tahun_ajaran
    WHERE gm.id_guru = ? AND ta.status = 'Tidak Aktif'
    ORDER BY ta.tahun_ajaran DESC, k.nama_kelas ASC
");
mysqli_stmt_bind_param($stmt_tidak_aktif, "i", $id_guru);
mysqli_stmt_execute($stmt_tidak_aktif);
$result_tidak_aktif = mysqli_stmt_get_result($stmt_tidak_aktif);
while ($row = mysqli_fetch_assoc($result_tidak_aktif)) {
    $penugasan_tidak_aktif[] = $row;
}
// --- [AKHIR BARU] ---

?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25); }
    .nav-tabs .nav-link.active { color: var(--primary-color); border-color: var(--primary-color) var(--primary-color) #fff; font-weight: 600; }

    /* --- [ROMBAKAN UI/UX] --- */
    #penugasan-tab-pane {
        background-color: #f8f9fa; /* Latar belakang tab yang sedikit berbeda */
    }
    .accordion-button:not(.collapsed) {
        color: var(--primary-color);
        background-color: #e0f2f1;
        font-weight: 600;
    }
    .accordion-button:focus {
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
    }
    .class-toggle-list {
        display: flex;
        flex-wrap: wrap; /* [UPGRADE] Membuat item wrap ke baris baru */
        gap: 0.75rem; /* [UPGRADE] Jarak antar item */
        padding: 1rem;
        background-color: #fff;
        border: 1px solid var(--border-color);
        border-top: none;
        border-radius: 0 0 0.375rem 0.375rem;
    }
    .class-toggle-list .form-check {
        margin: 0; /* Reset margin default .form-check */
    }
    .class-toggle-list .form-check-label {
        padding: 0.5rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 0.375rem;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
        /* [UPGRADE] Hapus 'width: 100%' agar item bisa horizontal */
        display: flex; /* [UPGRADE] Agar icon dan teks sejajar */
        align-items: center;
        min-width: 90px; /* Lebar minimum tombol kelas */
        justify-content: center;
    }
    .class-toggle-list .form-check-input:checked + .form-check-label {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        font-weight: 600;
    }
    .class-toggle-list .form-check-input:disabled + .form-check-label {
        background-color: #e9ecef;
        color: #adb5bd;
        border-color: #dee2e6;
        cursor: not-allowed;
        /* [UPGRADE] Hapus line-through, ganti dengan opacity dan icon */
        opacity: 0.7; 
    }
    .class-toggle-list .form-check-input:disabled + .form-check-label .bi-person-check-fill {
        color: #6c757d; /* Warna icon untuk guru lain */
    }
    .class-toggle-list .form-check-input:checked + .form-check-label .bi-check-circle-fill {
        color: white; /* Warna icon centang saat dicek */
    }
    .class-toggle-list .form-check-input {
        display: none; /* Sembunyikan checkbox aslinya */
    }
    .mapel-badge {
        background-color: var(--secondary-color);
    }
    /* [UPGRADE] Style untuk Bootstrap Tooltip */
    .tooltip-inner {
        background-color: #343a40;
        color: white;
        font-weight: 600;
        border-radius: 0.375rem;
    }
    .tooltip.bs-tooltip-top .tooltip-arrow::before {
        border-top-color: #343a40;
    }
    /* --- [AKHIR ROMBAKAN UI/UX] --- */

    /* [BARU] Style untuk penugasan tidak aktif */
    .inactive-assignment-list {
        list-style-type: none;
        padding-left: 0;
    }
    .inactive-assignment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
        background-color: #f8f9fa;
    }
    .inactive-assignment-item .details {
        font-size: 0.9rem;
    }
    .inactive-assignment-item .badge {
        font-size: 0.75rem;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Pengguna</h1>
                <p class="lead mb-0 opacity-75">Mengubah detail untuk: <strong><?php echo htmlspecialchars($data['nama_guru']); ?></strong></p>
            </div>
            <div class="mt-3 mt-sm-0">
                <a href="pengguna_tampil.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <form action="pengguna_aksi.php?aksi=update" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="id_guru" value="<?php echo $data['id_guru']; ?>">
            
            <div class="card-header bg-light">
                <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="akun-tab" data-bs-toggle="tab" data-bs-target="#akun-tab-pane" type="button" role="tab" aria-controls="akun-tab-pane" aria-selected="true"><i class="bi bi-person-badge-fill me-2"></i>Informasi Akun</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="penugasan-tab" data-bs-toggle="tab" data-bs-target="#penugasan-tab-pane" type="button" role="tab" aria-controls="penugasan-tab-pane" aria-selected="false"><i class="bi bi-briefcase-fill me-2"></i>Penugasan Mengajar</button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade show active" id="akun-tab-pane" role="tabpanel" aria-labelledby="akun-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama_guru" class="form-label fw-bold">Nama Lengkap</label>
                                <input type="text" class="form-control" id="nama_guru" name="nama_guru" value="<?php echo htmlspecialchars($data['nama_guru']); ?>" required>
                                <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nip" class="form-label fw-bold">NIP <span class="text-muted">(Opsional)</span></label>
                                <input type="text" class="form-control" id="nip" name="nip" value="<?php echo htmlspecialchars($data['nip']); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($data['username']); ?>" required>
                                <div class="invalid-feedback">Username wajib diisi.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label fw-bold">Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button>
                                </div>
                                <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label fw-bold">Role Sistem</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="guru" <?php if ($data['role'] == 'guru') echo 'selected'; ?>>Guru</option>
                                <option value="admin" <?php if ($data['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="tab-pane fade p-3" id="penugasan-tab-pane" role="tabpanel" aria-labelledby="penugasan-tab" tabindex="0">
                        <h5 class="mb-3">Pilih Penugasan Mengajar (Tahun Ajaran Aktif)</h5>
                        
                        <?php if (empty($daftar_kelas)) : ?>
                            <div class="alert alert-warning">Belum ada data kelas untuk tahun ajaran aktif. Silakan tambahkan terlebih dahulu di menu Kelas.</div>
                        <?php else : ?>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="search-mapel" placeholder="Cari mata pelajaran..." onkeyup="filterMapel()">
                                </div>
                            </div>

                            <div class="accordion" id="accordionPenugasan">
                                <?php
                                foreach ($daftar_mapel as $mapel) {
                                    $id_mapel = $mapel['id_mapel'];
                                    $kelas_diajar_count = 0;
                                    $list_kelas_html = '';

                                    foreach ($daftar_kelas as $kelas) {
                                        $id_kelas = $kelas['id_kelas'];
                                        $checkbox_id = "mapel_{$id_mapel}_kelas_{$id_kelas}";
                                        
                                        // --- [LOGIKA UI/UX BARU] ---
                                        $checked = isset($penugasan_diajar[$id_mapel][$id_kelas]) ? 'checked' : '';
                                        $disabled = '';
                                        $icon_html = '';
                                        $title = ''; // Judul untuk tooltip

                                        // Cek apakah slot ini sudah diambil guru lain
                                        $stmt_cek_lain = mysqli_prepare($koneksi, "SELECT g.nama_guru FROM guru_mengajar gm JOIN guru g ON gm.id_guru = g.id_guru WHERE gm.id_mapel = ? AND gm.id_kelas = ? AND gm.id_tahun_ajaran = ? AND gm.id_guru != ?");
                                        mysqli_stmt_bind_param($stmt_cek_lain, "iiii", $id_mapel, $id_kelas, $id_tahun_ajaran_aktif, $id_guru);
                                        mysqli_stmt_execute($stmt_cek_lain);
                                        $result_cek_lain = mysqli_stmt_get_result($stmt_cek_lain);
                                        $guru_lain = mysqli_fetch_assoc($result_cek_lain);
                                        
                                        if ($checked) {
                                            // 1. Ditugaskan ke GURU INI
                                            $icon_html = "<i class='bi bi-check-circle-fill me-2'></i>";
                                            $title = "Saat ini diampu oleh " . htmlspecialchars($data['nama_guru']); // $data['nama_guru'] adalah guru yang sedang diedit
                                            $kelas_diajar_count++;
                                        
                                        } elseif ($guru_lain) {
                                            // 2. Ditugaskan ke GURU LAIN (sesuai permintaan user)
                                            $disabled = 'disabled';
                                            $icon_html = "<i class='bi bi-person-check-fill me-2'></i>"; // Icon "orang"
                                            $title = "Sudah diampu oleh: " . htmlspecialchars($guru_lain['nama_guru']);
                                        
                                        } else {
                                            // 3. Kelas kosong, bisa dipilih
                                            $icon_html = ""; // Kosong, siap dipilih
                                            $title = "Tugaskan ke kelas " . htmlspecialchars($kelas['nama_kelas']);
                                        }
                                        // --- [AKHIR LOGIKA UI/UX BARU] ---

                                        $list_kelas_html .= "
                                            <div class='form-check' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='{$title}'>
                                                <input class='form-check-input' type='checkbox' name='penugasan[{$id_mapel}][]' value='{$id_kelas}' id='{$checkbox_id}' {$checked} {$disabled}>
                                                <label class='form-check-label' for='{$checkbox_id}'>
                                                    {$icon_html}
                                                    " . htmlspecialchars($kelas['nama_kelas']) . "
                                                </label>
                                            </div>
                                        ";
                                    }
                                    ?>
                                    <div class="accordion-item mapel-item" data-nama-mapel="<?php echo strtolower(htmlspecialchars($mapel['nama_mapel'])); ?>">
                                        <h2 class="accordion-header" id="heading-<?php echo $id_mapel; ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $id_mapel; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $id_mapel; ?>">
                                                <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                                                <?php if ($kelas_diajar_count > 0): ?>
                                                    <span class="badge mapel-badge ms-auto me-2"><?php echo $kelas_diajar_count; ?> Kelas</span>
                                                <?php endif; ?>
                                            </button>
                                        </h2>
                                        <div id="collapse-<?php echo $id_mapel; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $id_mapel; ?>" data-bs-parent="#accordionPenugasan">
                                            <div class="class-toggle-list">
                                                <?php echo $list_kelas_html; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <div id="no-mapel-results" class="text-center p-4" style="display: none;">
                                <i class="bi bi-search fs-2 text-muted"></i>
                                <h5 class="mt-2">Mata Pelajaran Tidak Ditemukan</h5>
                                <p class="text-muted">Tidak ada mapel yang cocok dengan kata kunci pencarian Anda.</p>
                            </div>
                        <?php endif; ?>

                        <!-- [BARU] Tampilkan Penugasan Tidak Aktif -->
                        <?php if (!empty($penugasan_tidak_aktif)): ?>
                            <hr class="my-4">
                            <h5 class="mb-3 text-danger"><i class="bi bi-archive-fill me-2"></i>Penugasan di Tahun Ajaran Tidak Aktif</h5>
                            <p class="text-muted">Daftar ini menunjukkan penugasan guru di tahun ajaran yang sudah lewat. Penugasan ini tidak dapat di-edit, hanya dapat dihapus jika terjadi kesalahan.</p>
                            <ul class="inactive-assignment-list">
                                <?php foreach ($penugasan_tidak_aktif as $penugasan): ?>
                                    <li class="inactive-assignment-item">
                                        <div class="details">
                                            <strong><?php echo htmlspecialchars($penugasan['nama_mapel']); ?></strong>
                                            <span class="text-muted mx-2">|</span>
                                            <span><?php echo htmlspecialchars($penugasan['nama_kelas']); ?></span>
                                        </div>
                                        <div>
                                            <span class="badge text-bg-secondary me-3"><?php echo htmlspecialchars($penugasan['tahun_ajaran']); ?></span>
                                            <a href="#" onclick="hapusPenugasan(<?php echo $penugasan['id_guru_mengajar']; ?>, '<?php echo htmlspecialchars(addslashes($penugasan['nama_mapel']) . ' di ' . addslashes($penugasan['nama_kelas'])); ?>')" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Hapus Penugasan Ini">
                                                <i class="bi bi-trash-fill"></i> Hapus
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <!-- [AKHIR BARU] -->

                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="pengguna_tampil.php" class="btn btn-secondary me-2"><i class="bi bi-x-lg me-2"></i>Batal</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i>Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Script Javascript (validasi form, toggle password, toggle tab)
document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi Validasi Bootstrap
    (function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation'); Array.prototype.slice.call(forms).forEach(function (form) { form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false); }); })();
    
    // Toggle Password
    const togglePassword = document.querySelector('#togglePassword');
    if (togglePassword) {
        const password = document.querySelector('#password');
        const eyeIcon = togglePassword.querySelector('i');
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            eyeIcon.classList.toggle('bi-eye');
            eyeIcon.classList.toggle('bi-eye-slash');
        });
    }

    // Toggle Tab Penugasan berdasarkan Role
    const roleSelect = document.querySelector('#role');
    const penugasanTabButton = document.querySelector('#penugasan-tab');
    function checkRoleAndToggleTab() {
        if (roleSelect.value === 'admin') {
            penugasanTabButton.classList.add('disabled');
            penugasanTabButton.setAttribute('aria-disabled', 'true');
        } else {
            penugasanTabButton.classList.remove('disabled');
            penugasanTabButton.removeAttribute('aria-disabled');
        }
    }
    roleSelect.addEventListener('change', checkRoleAndToggleTab);
    checkRoleAndToggleTab();

    // --- [BARU] Inisialisasi Bootstrap Tooltip ---
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Opsional: Update tooltip saat tab berganti (jika ada masalah rendering)
    const penugasanTab = document.querySelector('#penugasan-tab');
    if(penugasanTab) {
        penugasanTab.addEventListener('shown.bs.tab', function () {
            tooltipList.forEach(tooltip => tooltip.update());
        });
    }
});

// [BARU] Fungsi filter/search untuk mapel (Tidak berubah)
function filterMapel() {
    let input = document.getElementById('search-mapel');
    let filter = input.value.toLowerCase();
    let items = document.querySelectorAll('.mapel-item');
    let noResults = document.getElementById('no-mapel-results');
    let found = false;

    items.forEach(function(item) {
        let namaMapel = item.getAttribute('data-nama-mapel');
        if (namaMapel.includes(filter)) {
            item.style.display = '';
            found = true;
        } else {
            item.style.display = 'none';
        }
    });

    noResults.style.display = found ? 'none' : 'block';
}

// --- [BARU] Fungsi Hapus Penugasan Tidak Aktif ---
function hapusPenugasan(id_guru_mengajar, nama_penugasan) {
    Swal.fire({
        title: 'Anda yakin?',
        html: "Anda akan menghapus penugasan:<br><b>" + nama_penugasan + "</b><br>Tindakan ini permanen.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect ke pengguna_aksi.php dengan aksi baru
            window.location.href = 'pengguna_aksi.php?aksi=hapus_penugasan&id_gm=' + id_guru_mengajar + '&id_guru_redirect=<?php echo $id_guru; ?>';
        }
    })
}
</script>

<?php include 'footer.php'; ?>