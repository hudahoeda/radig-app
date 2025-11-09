<?php
include 'koneksi.php';
include 'header.php'; // Menggunakan header.php Anda

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak memiliki wewenang.'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// --- [PAGINASI & SEARCH LOGIC] ---
$limit = 12; // Jumlah item per halaman (kartu)

// Ambil parameter Guru
$search_guru = isset($_GET['search_guru']) ? mysqli_real_escape_string($koneksi, $_GET['search_guru']) : '';
$page_guru = isset($_GET['page_guru']) ? (int)$_GET['page_guru'] : 1;
$offset_guru = ($page_guru - 1) * $limit;

// Ambil parameter Siswa
$search_siswa = isset($_GET['search_siswa']) ? mysqli_real_escape_string($koneksi, $_GET['search_siswa']) : '';
$page_siswa = isset($_GET['page_siswa']) ? (int)$_GET['page_siswa'] : 1;
$offset_siswa = ($page_siswa - 1) * $limit;

// --- [LOGIKA TAB BARU] ---
// Tentukan sub-tab aktif (ini yang paling penting)
$active_tab = $_GET['tab'] ?? 'guru'; // Default ke sub-tab 'guru'

// Tentukan main-tab aktif berdasarkan sub-tab
$active_main_tab = 'pengguna'; // Default
if (in_array($active_tab, ['import_guru', 'import_mengajar', 'import_siswa'])) {
    $active_main_tab = 'import';
}

// Logika untuk memastikan tab & paginasi/search sinkron
if (isset($_GET['search_guru']) || isset($_GET['page_guru'])) {
    $active_main_tab = 'pengguna';
    $active_tab = 'guru';
}
if (isset($_GET['search_siswa']) || isset($_GET['page_siswa'])) {
    $active_main_tab = 'pengguna';
    $active_tab = 'siswa';
}
// --- [AKHIR LOGIKA TAB BARU] ---
?>

<style>
    /* Gaya CSS dari respons saya sebelumnya */
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }

    .user-card-container {
        padding: 1.5rem 0; /* Disederhanakan */
        padding-bottom: 100px; /* Ruang untuk action bar */
    }
    .user-card {
        transition: all 0.2s ease-in-out;
        border: 1px solid var(--bs-border-color-translucent);
        border-left-width: 4px;
        position: relative; /* Untuk checkbox */
    }
    .user-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .user-card.selected {
        border-color: var(--primary-color);
        background-color: #f0f9ff;
    }
    .user-card .form-check-input {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 1.5em;
        height: 1.5em;
        cursor: pointer;
    }
    .user-card-img {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .status-dot {
        height: 10px;
        width: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .status-online {
        background-color: var(--bs-success);
    }
    .status-offline {
        background-color: var(--bs-secondary);
    }
    .search-bar {
        max-width: 400px;
    }
    .import-step {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }
    .import-step .step-number {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        margin-right: 1rem;
    }
    .import-step .step-content h5 {
        font-weight: 600;
        color: var(--primary-color);
    }
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 0.5rem;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }
    .drop-zone:hover, .drop-zone.drag-over {
        border-color: var(--primary-color);
        background-color: #f8f9fa;
    }
    .drop-zone .drop-zone-prompt {
        color: #6c757d;
    }
    .file-details {
        background-color: #e9f5ff;
        border: 1px solid #b8d9f7;
        border-radius: 0.5rem;
        padding: 1rem;
    }
    .bulk-action-bar {
        position: fixed;
        bottom: -100px; /* Mulai dari luar layar */
        left: 0;
        right: 0;
        background-color: #212529;
        color: white;
        padding: 1rem 1.5rem;
        box-shadow: 0 -4px 15px rgba(0,0,0,0.2);
        z-index: 100;
        transition: bottom 0.3s ease-in-out;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .bulk-action-bar.show {
        bottom: 0; /* Muncul ke layar */
    }
    /* Penyesuaian untuk sidebar Anda */
    #content .bulk-action-bar {
        left: 260px; /* Default */
    }
    #sidebar.active + #content .bulk-action-bar {
        left: 0;
    }
    @media (max-width: 768px) {
        #content .bulk-action-bar { left: 0; }
    }

    /* --- [GAYA TAB BARU] --- */
    /* Main tabs */
    .nav-tabs-main {
        border-bottom: 2px solid var(--border-color);
    }
    .nav-tabs-main .nav-link {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--bs-secondary-color);
        border: none;
        border-bottom: 4px solid transparent;
        padding: 1rem 1.5rem;
    }
    .nav-tabs-main .nav-link.active {
        color: var(--primary-color);
        border-color: var(--primary-color);
        background-color: transparent;
    }
    
    /* Sub tabs */
    .nav-pills-sub {
        background-color: #f8f9fa;
        padding: 0.5rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid var(--border-color);
    }
    .nav-pills-sub .nav-link {
        font-weight: 500;
        color: var(--text-dark);
        border-radius: 0.375rem;
    }
    .nav-pills-sub .nav-link.active {
        background-color: var(--primary-color);
        color: white;
        box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.3);
    }
    
    /* Container untuk main tab content */
    .card-body > .tab-content > .tab-pane {
        padding: 1.5rem 0 0 0;
    }
    /* Hapus padding default card-body agar tab utama menempel */
    .card-body.card-body-tabbed {
        padding: 0 1.5rem 1.5rem 1.5rem;
    }
    /* --- [AKHIR GAYA TAB BARU] --- */

</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Pengguna & Siswa</h1>
                <p class="lead mb-0 opacity-75">Kelola akun guru, admin, dan siswa di sistem.</p>
            </div>
            <div class="d-flex mt-3 mt-sm-0">
                <!-- Tautan ke pengguna_tambah.php Anda -->
                <a href="pengguna_tambah.php" class="btn btn-light"><i class="bi bi-person-plus-fill me-2"></i>Tambah Guru/Admin</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <!-- [NAVIGASI TAB UTAMA BARU] -->
        <div class="card-header bg-light p-0 border-bottom-0">
            <ul class="nav nav-tabs nav-tabs-main nav-fill" id="mainTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php if($active_main_tab == 'pengguna') echo 'active'; ?>" id="main-tab-pengguna" data-bs-toggle="tab" data-bs-target="#pengguna-main-pane" type="button" role="tab"><i class="bi bi-people-fill me-2"></i>PENGGUNA</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php if($active_main_tab == 'import') echo 'active'; ?>" id="main-tab-import" data-bs-toggle="tab" data-bs-target="#import-main-pane" type="button" role="tab"><i class="bi bi-upload me-2"></i>IMPORT</button>
                </li>
            </ul>
        </div>
        <!-- [AKHIR NAVIGASI TAB UTAMA BARU] -->

        <div class="card-body card-body-tabbed">
            <!-- [CONTENT TAB UTAMA BARU] -->
            <div class="tab-content" id="mainTabContent">

                <!-- [PANE TAB UTAMA: PENGGUNA] -->
                <div class="tab-pane fade <?php if($active_main_tab == 'pengguna') echo 'show active'; ?>" id="pengguna-main-pane" role="tabpanel">
                    
                    <!-- Sub-Tab Navigasi (Pengguna) -->
                    <ul class="nav nav-pills nav-pills-sub nav-fill" id="penggunaSubTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php if($active_tab == 'guru') echo 'active'; ?>" id="sub-tab-guru" data-bs-toggle="tab" data-bs-target="#guru-admin-pane" type="button" role="tab"><i class="bi bi-person-vcard me-2"></i>Guru & Admin</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php if($active_tab == 'siswa') echo 'active'; ?>" id="sub-tab-siswa" data-bs-toggle="tab" data-bs-target="#siswa-pane" type="button" role="tab"><i class="bi bi-person-rolodex me-2"></i>Siswa</button>
                        </li>
                    </ul>

                    <!-- Sub-Tab Content (Pengguna) -->
                    <div class="tab-content" id="penggunaSubTabContent">
                        
                        <!-- [SUB-PANE: GURU & ADMIN] -->
                        <div class="tab-pane fade <?php if($active_tab == 'guru') echo 'show active'; ?> user-card-container" id="guru-admin-pane" role="tabpanel">
                            
                            <!-- Search Bar Guru -->
                            <form method="GET" action="pengguna_tampil.php" class="mb-4">
                                <input type="hidden" name="tab" value="guru">
                                <div class="input-group search-bar">
                                    <input type="text" id="searchGuru" name="search_guru" class="form-control" placeholder="Cari nama atau NIP guru/admin..." value="<?php echo htmlspecialchars($search_guru); ?>">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                                </div>
                            </form>

                            <!-- Form Bulk Delete Guru -->
                            <form id="form-bulk-delete-guru" action="pengguna_aksi.php?aksi=hapus_banyak" method="POST">
                                <div id="guru-list" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                    <?php
                                    // Query Guru (Sama seperti sebelumnya)
                                    $where_guru = ''; $params_guru = []; $types_guru = '';
                                    if (!empty($search_guru)) {
                                        $where_guru = " WHERE (nama_guru LIKE ? OR nip LIKE ?)";
                                        $search_guru_param = "%" . $search_guru . "%";
                                        $params_guru[] = $search_guru_param; $params_guru[] = $search_guru_param;
                                        $types_guru = 'ss';
                                    }
                                    $query_count_guru = "SELECT COUNT(id_guru) as total FROM guru" . $where_guru;
                                    $stmt_count_guru = mysqli_prepare($koneksi, $query_count_guru);
                                    if ($types_guru) { mysqli_stmt_bind_param($stmt_count_guru, $types_guru, ...$params_guru); }
                                    mysqli_stmt_execute($stmt_count_guru);
                                    $total_guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count_guru))['total'];
                                    $total_pages_guru = ceil($total_guru / $limit);
                                    $query_guru = "SELECT id_guru, nama_guru, nip, username, role, foto_guru, terakhir_login FROM guru" . $where_guru . " ORDER BY nama_guru ASC LIMIT ? OFFSET ?";
                                    $params_guru[] = $limit; $params_guru[] = $offset_guru; $types_guru .= 'ii';
                                    $stmt_guru = mysqli_prepare($koneksi, $query_guru);
                                    mysqli_stmt_bind_param($stmt_guru, $types_guru, ...$params_guru);
                                    mysqli_stmt_execute($stmt_guru);
                                    $result_guru = mysqli_stmt_get_result($stmt_guru);
                                    
                                    if ($total_guru > 0) {
                                        while ($data = mysqli_fetch_assoc($result_guru)) {
                                            $foto_guru = $data['foto_guru'] ?? null;
                                            $foto_path = 'uploads/guru_photos/' . $foto_guru;
                                            $foto_default = 'uploads/guruc.png'; 
                                            $gambar_tampil = (!empty($foto_guru) && file_exists($foto_path)) ? $foto_path : $foto_default;
                                            $is_self = ($_SESSION['id_guru'] == $data['id_guru']);
                                    ?>
                                    <div class="col user-card-col">
                                        <div class="card h-100 user-card">
                                            <div class="card-body">
                                                <?php if (!$is_self): ?>
                                                <input class="form-check-input bulk-checkbox-guru" type="checkbox" name="user_ids[]" value="<?php echo $data['id_guru']; ?>" title="Pilih pengguna ini">
                                                <?php endif; ?>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo htmlspecialchars($gambar_tampil); ?>" class="user-card-img" alt="Foto <?php echo htmlspecialchars($data['nama_guru']); ?>">
                                                    <div class="ms-3 text-start">
                                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($data['nama_guru']); ?></h5>
                                                        <p class="card-text text-muted mb-1">NIP. <?php echo htmlspecialchars($data['nip'] ?? '-'); ?></p>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="text-start small">
                                                    <p class="mb-1"><strong><i class="bi bi-person-vcard me-2"></i>Role:</strong> <?php if($data['role'] == 'admin'): ?><span class="badge text-bg-primary">Admin</span><?php else: ?><span class="badge text-bg-secondary">Guru</span><?php endif; ?></p>
                                                    <p class="mb-1"><strong><i class="bi bi-person me-2"></i>Username:</strong> <?php echo htmlspecialchars($data['username']); ?></p>
                                                    <p class="mb-2"><strong><i class="bi bi-clock-history me-2"></i>Aktivitas:</strong> 
                                                    <?php
                                                        if ($data['terakhir_login']) {
                                                            $last_login = new DateTime($data['terakhir_login']); $now = new DateTime();
                                                            $interval = $now->getTimestamp() - $last_login->getTimestamp();
                                                            $is_online = $interval < 300; // 5 menit
                                                            echo '<span class="status-dot ' . ($is_online ? 'status-online' : 'status-offline') . '"></span>';
                                                            echo 'Login ' . $last_login->format('d/m/Y, H:i');
                                                        } else { echo '<span class="status-dot status-offline"></span> Belum pernah login'; }
                                                    ?>
                                                    </p>
                                                </div>
                                                <div class="mt-3 border-top pt-3">
                                                    <a href="pengguna_edit.php?id=<?php echo $data['id_guru']; ?>" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Edit Pengguna"><i class="bi bi-pencil-fill me-1"></i> Edit</a>
                                                    <?php if (!$is_self) : ?>
                                                    <a href="admin_aksi.php?aksi=login_sebagai_guru&id_target=<?php echo $data['id_guru']; ?>" class="btn btn-outline-warning btn-sm" data-bs-toggle="tooltip" title="Login sebagai <?php echo htmlspecialchars($data['nama_guru']); ?>"><i class="bi bi-person-fill-gear"></i></a>
                                                    <a href="#" onclick="hapusGuru(<?php echo $data['id_guru']; ?>)" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Hapus Pengguna"><i class="bi bi-trash-fill me-1"></i> Hapus</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php } } else { ?>
                                    <div class="col-12">
                                        <div id="no-results-guru" class="text-center py-5">
                                            <i class="bi bi-search fs-1 text-muted"></i> <h4 class="mt-3">Guru / Admin tidak ditemukan</h4>
                                            <p class="text-muted">Tidak ada pengguna yang cocok dengan kata kunci pencarian Anda.</p>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </form>
                            
                            <!-- Paginasi Guru -->
                            <?php if ($total_pages_guru > 1): ?>
                            <nav aria-label="Paginasi Guru" class="mt-4 d-flex justify-content-center">
                                <ul class="pagination">
                                    <li class="page-item <?php if($page_guru <= 1) echo 'disabled'; ?>"><a class="page-link" href="?tab=guru&search_guru=<?php echo urlencode($search_guru); ?>&page_guru=<?php echo $page_guru - 1; ?>">Prev</a></li>
                                    <?php 
                                    $start_page = max(1, $page_guru - 2); $end_page = min($total_pages_guru, $page_guru + 2);
                                    if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="?tab=guru&search_guru='.urlencode($search_guru).'&page_guru=1">1</a></li>'; if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } }
                                    for ($i = $start_page; $i <= $end_page; $i++): ?><li class="page-item <?php if($page_guru == $i) echo 'active'; ?>"><a class="page-link" href="?tab=guru&search_guru=<?php echo urlencode($search_guru); ?>&page_guru=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; 
                                    if ($end_page < $total_pages_guru) { if ($end_page < $total_pages_guru - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } echo '<li class="page-item"><a class="page-link" href="?tab=guru&search_guru='.urlencode($search_guru).'&page_guru='.$total_pages_guru.'">'.$total_pages_guru.'</a></li>'; }
                                    ?>
                                    <li class="page-item <?php if($page_guru >= $total_pages_guru) echo 'disabled'; ?>"><a class="page-link" href="?tab=guru&search_guru=<?php echo urlencode($search_guru); ?>&page_guru=<?php echo $page_guru + 1; ?>">Next</a></li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                        
                        <!-- [SUB-PANE: SISWA] -->
                        <div class="tab-pane fade <?php if($active_tab == 'siswa') echo 'show active'; ?> user-card-container" id="siswa-pane" role="tabpanel">
                            
                            <!-- Search Bar Siswa -->
                            <form method="GET" action="pengguna_tampil.php" class="mb-4">
                                <input type="hidden" name="tab" value="siswa">
                                <div class="input-group search-bar">
                                    <input type="text" id="searchSiswa" name="search_siswa" class="form-control" placeholder="Cari nama atau NISN siswa..." value="<?php echo htmlspecialchars($search_siswa); ?>">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                                </div>
                            </form>

                            <!-- Form Bulk Delete Siswa -->
                            <form id="form-bulk-delete-siswa" action="siswa_aksi.php?aksi=hapus_banyak" method="POST">
                                <div id="siswa-list" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                    <?php
                                    // Query Siswa (Sama seperti sebelumnya)
                                    $where_siswa = ''; $params_siswa = []; $types_siswa = '';
                                    if (!empty($search_siswa)) {
                                        $where_siswa = " WHERE (s.nama_lengkap LIKE ? OR s.nisn LIKE ?)";
                                        $search_siswa_param = "%" . $search_siswa . "%";
                                        $params_siswa[] = $search_siswa_param; $params_siswa[] = $search_siswa_param;
                                        $types_siswa = 'ss';
                                    }
                                    $query_count_siswa = "SELECT COUNT(s.id_siswa) as total FROM siswa s" . $where_siswa;
                                    $stmt_count_siswa = mysqli_prepare($koneksi, $query_count_siswa);
                                    if ($types_siswa) { mysqli_stmt_bind_param($stmt_count_siswa, $types_siswa, ...$params_siswa); }
                                    mysqli_stmt_execute($stmt_count_siswa);
                                    $total_siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count_siswa))['total'];
                                    $total_pages_siswa = ceil($total_siswa / $limit);
                                    $query_siswa = "SELECT s.id_siswa, s.nama_lengkap, s.nisn, s.nis, s.username, s.foto_siswa, s.status_siswa, (SELECT k.nama_kelas FROM kelas k WHERE k.id_kelas = s.id_kelas) as nama_kelas FROM siswa s " . $where_siswa . " ORDER BY s.nama_lengkap ASC LIMIT ? OFFSET ?";
                                    $params_siswa[] = $limit; $params_siswa[] = $offset_siswa; $types_siswa .= 'ii';
                                    $stmt_siswa = mysqli_prepare($koneksi, $query_siswa);
                                    mysqli_stmt_bind_param($stmt_siswa, $types_siswa, ...$params_siswa);
                                    mysqli_stmt_execute($stmt_siswa);
                                    $result_siswa = mysqli_stmt_get_result($stmt_siswa);
                                    
                                    if ($total_siswa > 0) {
                                        while ($data = mysqli_fetch_assoc($result_siswa)) {
                                            $foto_siswa = $data['foto_siswa'] ?? null;
                                            $foto_path = 'uploads/foto_siswa/' . $foto_siswa;
                                            $foto_default = 'uploads/siswac.png'; 
                                            $gambar_tampil = (!empty($foto_siswa) && file_exists($foto_path)) ? $foto_path : $foto_default;
                                    ?>
                                    <div class="col user-card-col">
                                        <div class="card h-100 user-card">
                                            <div class="card-body">
                                                <input class="form-check-input bulk-checkbox-siswa" type="checkbox" name="siswa_ids[]" value="<?php echo $data['id_siswa']; ?>" title="Pilih siswa ini">
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo htmlspecialchars($gambar_tampil); ?>" class="user-card-img" alt="Foto <?php echo htmlspecialchars($data['nama_lengkap']); ?>">
                                                    <div class="ms-3 text-start">
                                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($data['nama_lengkap']); ?></h5>
                                                        <p class="card-text text-muted mb-1">NISN. <?php echo htmlspecialchars($data['nisn'] ?? '-'); ?></p>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="text-start small">
                                                    <p class="mb-1"><strong><i class="bi bi-person-badge me-2"></i>Kelas:</strong> <span class="badge text-bg-info"><?php echo htmlspecialchars($data['nama_kelas'] ?? 'Belum ada kelas'); ?></span></p>
                                                    <p class="mb-1"><strong><i class="bi bi-info-circle me-2"></i>Status:</strong> <?php $status_badge = ($data['status_siswa'] != 'Aktif') ? 'text-bg-secondary' : 'text-bg-success'; ?><span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($data['status_siswa']); ?></span></p>
                                                    <p class="mb-1"><strong><i class="bi bi-person me-2"></i>Username:</strong> <?php echo htmlspecialchars($data['username']); ?></p>
                                                </div>
                                                <div class="mt-3 border-top pt-3">
                                                    <a href="siswa_edit.php?id=<?php echo $data['id_siswa']; ?>" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Edit Siswa"><i class="bi bi-pencil-fill me-1"></i> Edit</a>
                                                    <a href="admin_aksi.php?aksi=login_sebagai_siswa&id_target=<?php echo $data['id_siswa']; ?>" class="btn btn-outline-warning btn-sm" data-bs-toggle="tooltip" title="Login sebagai <?php echo htmlspecialchars($data['nama_lengkap']); ?>"><i class="bi bi-person-fill-gear"></i></a>
                                                    <a href="#" onclick="hapusSiswa(<?php echo $data['id_siswa']; ?>)" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip" title="Hapus Siswa"><i class="bi bi-trash-fill me-1"></i> Hapus</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php } } else { ?>
                                    <div class="col-12">
                                        <div id="no-results-siswa" class="text-center py-5">
                                            <i class="bi bi-search fs-1 text-muted"></i> <h4 class="mt-3">Siswa tidak ditemukan</h4>
                                            <p class="text-muted">Tidak ada siswa yang cocok dengan kata kunci pencarian Anda.</p>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </form>
                            
                            <!-- Paginasi Siswa -->
                            <?php if ($total_pages_siswa > 1): ?>
                            <nav aria-label="Paginasi Siswa" class="mt-4 d-flex justify-content-center">
                                <ul class="pagination">
                                    <li class="page-item <?php if($page_siswa <= 1) echo 'disabled'; ?>"><a class="page-link" href="?tab=siswa&search_siswa=<?php echo urlencode($search_siswa); ?>&page_siswa=<?php echo $page_siswa - 1; ?>">Prev</a></li>
                                    <?php 
                                    $start_page = max(1, $page_siswa - 2); $end_page = min($total_pages_siswa, $page_siswa + 2);
                                    if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="?tab=siswa&search_siswa='.urlencode($search_siswa).'&page_siswa=1">1</a></li>'; if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } }
                                    for ($i = $start_page; $i <= $end_page; $i++): ?><li class="page-item <?php if($page_siswa == $i) echo 'active'; ?>"><a class="page-link" href="?tab=siswa&search_siswa=<?php echo urlencode($search_siswa); ?>&page_siswa=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; 
                                    if ($end_page < $total_pages_siswa) { if ($end_page < $total_pages_siswa - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } echo '<li class="page-item"><a class="page-link" href="?tab=siswa&search_siswa='.urlencode($search_siswa).'&page_siswa='.$total_pages_siswa.'">'.$total_pages_siswa.'</a></li>'; }
                                    ?>
                                    <li class="page-item <?php if($page_siswa >= $total_pages_siswa) echo 'disabled'; ?>"><a class="page-link" href="?tab=siswa&search_siswa=<?php echo urlencode($search_siswa); ?>&page_siswa=<?php echo $page_siswa + 1; ?>">Next</a></li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- [PANE TAB UTAMA: IMPORT] -->
                <div class="tab-pane fade <?php if($active_main_tab == 'import') echo 'show active'; ?>" id="import-main-pane" role="tabpanel">

                    <!-- Sub-Tab Navigasi (Import) -->
                    <ul class="nav nav-pills nav-pills-sub nav-fill" id="importSubTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php if($active_tab == 'import_guru') echo 'active'; ?>" id="sub-tab-import-guru" data-bs-toggle="tab" data-bs-target="#import-guru-pane" type="button" role="tab">Import Guru (Simpel)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php if($active_tab == 'import_mengajar') echo 'active'; ?>" id="sub-tab-import-mengajar" data-bs-toggle="tab" data-bs-target="#import-guru-mengajar-pane" type="button" role="tab">Import Guru & Mengajar</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php if($active_tab == 'import_siswa') echo 'active'; ?>" id="sub-tab-import-siswa" data-bs-toggle="tab" data-bs-target="#import-siswa-pane" type="button" role="tab">Import Siswa</button>
                        </li>
                    </ul>

                    <!-- Sub-Tab Content (Import) -->
                    <div class="tab-content" id="importSubTabContent">
                        
                        <!-- [SUB-PANE: IMPORT GURU SIMPEL] -->
                        <div class="tab-pane fade <?php if($active_tab == 'import_guru') echo 'show active'; ?> p-4" id="import-guru-pane" role="tabpanel">
                            <div class="import-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Download Template (Simpel)</h5>
                                    <p class="text-muted mb-0">Hanya untuk menambah data guru (NIP, Nama, Username, Role) tanpa penugasan mengajar.</p>
                                    <a href="template_download.php?tipe=guru" class="btn btn-sm btn-success mt-2"><i class="bi bi-file-earmark-arrow-down-fill me-2"></i>Download Template Guru (Simpel)</a>
                                </div>
                            </div>
                            <hr>
                            <div class="import-step">
                                <div class="step-number">2</div>
                                <div class="step-content w-100">
                                    <h5>Unggah File Template</h5>
                                    <form action="pengguna_aksi.php?aksi=import" method="POST" enctype="multipart/form-data" id="form-import-guru">
                                        <label for="file-input-guru" class="drop-zone" id="drop-zone-guru">
                                            <div class="drop-zone-prompt"><i class="bi bi-cloud-arrow-up-fill fs-1 text-muted"></i><p class="mt-2"><b>Seret file ke sini</b> atau klik untuk memilih</p><small class="text-muted">Hanya file .xlsx yang diizinkan</small></div>
                                            <div class="file-details" id="file-details-guru" style="display: none;"></div>
                                        </label>
                                        <input type="file" id="file-input-guru" name="file_pengguna" accept=".xlsx" style="display: none;" required>
                                        <div class="d-grid mt-3"><button type="submit" class="btn btn-primary btn-lg" id="btn-import-guru" disabled><i class="bi bi-upload me-2"></i>Import (Simpel)</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- [SUB-PANE: IMPORT GURU & MENGAJAR] -->
                        <div class="tab-pane fade <?php if($active_tab == 'import_mengajar') echo 'show active'; ?> p-4" id="import-guru-mengajar-pane" role="tabpanel">
                            <div class="alert alert-info" role="alert"><h4 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>Impor Lengkap!</h4><p>Gunakan tab ini untuk mengimpor data guru baru sekaligus mendaftarkan penugasan mengajar (Mapel & Kelas) mereka dalam satu file.</p></div>
                            <div class="import-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Download Template (Lengkap)</h5>
                                    <p class="text-muted mb-0">Template ini berisi kolom untuk data guru dan penugasan mengajar, beserta lembar data Mapel & Kelas.</p>
                                    <a href="template_download.php?tipe=guru_mengajar" class="btn btn-sm btn-success mt-2"><i class="bi bi-file-earmark-arrow-down-fill me-2"></i>Download Template Guru & Mengajar</a>
                                </div>
                            </div>
                            <hr>
                            <div class="import-step">
                                <div class="step-number">2</div>
                                <div class="step-content w-100">
                                    <h5>Unggah File Template (Lengkap)</h5>
                                    <form action="pengguna_aksi.php?aksi=import_mengajar" method="POST" enctype="multipart/form-data" id="form-import-guru-mengajar">
                                        <label for="file-input-guru-mengajar" class="drop-zone" id="drop-zone-guru-mengajar">
                                            <div class="drop-zone-prompt"><i class="bi bi-cloud-arrow-up-fill fs-1 text-muted"></i><p class="mt-2"><b>Seret file ke sini</b> atau klik untuk memilih</p><small class="text-muted">Hanya file .xlsx yang diizinkan</small></div>
                                            <div class="file-details" id="file-details-guru-mengajar" style="display: none;"></div>
                                        </label>
                                        <input type="file" id="file-input-guru-mengajar" name="file_guru_mengajar" accept=".xlsx" style="display: none;" required>
                                        <div class="d-grid mt-3"><button type="submit" class="btn btn-primary btn-lg" id="btn-import-guru-mengajar" disabled><i class="bi bi-upload me-2"></i>Import Guru & Mengajar</Tsubmit></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- [SUB-PANE: IMPORT SISWA] -->
                        <div class="tab-pane fade <?php if($active_tab == 'import_siswa') echo 'show active'; ?> p-4" id="import-siswa-pane" role="tabpanel">
                             <div class="import-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Download Template</h5>
                                    <p class="text-muted mb-0">Unduh template Excel yang sudah disediakan untuk data siswa lengkap.</p>
                                    <a href="template_download.php?tipe=siswa" class="btn btn-sm btn-success mt-2"><i class="bi bi-file-earmark-arrow-down-fill me-2"></i>Download Template Siswa</a>
                                </div>
                            </div>
                            <hr>
                            <div class="import-step">
                                <div class="step-number">2</div>
                                <div class="step-content w-100">
                                    <h5>Unggah File Template</h5>
                                    <form action="siswa_aksi.php?aksi=import_lengkap" method="POST" enctype="multipart/form-data" id="form-import-siswa">
                                        <label for="file-input-siswa" class="drop-zone" id="drop-zone-siswa">
                                            <div class="drop-zone-prompt"><i class="bi bi-cloud-arrow-up-fill fs-1 text-muted"></i><p class="mt-2"><b>Seret file ke sini</b> atau klik untuk memilih</p><small class="text-muted">Hanya file .xlsx yang diizinkan</small></div>
                                            <div class="file-details" id="file-details-siswa" style="display: none;"></div>
                                        </label>
                                        <input type="file" id="file-input-siswa" name="file_siswa_lengkap" accept=".xlsx" style="display: none;" required>
                                        <div class="d-grid mt-3"><button type="submit" class="btn btn-primary btn-lg" id="btn-import-siswa" disabled><i class="bi bi-upload me-2"></i>Import dari Template</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
            <!-- [AKHIR CONTENT TAB UTAMA BARU] -->
        </div>
    </div>
</div>

<!-- Bulk Action Bar GURU -->
<div class="bulk-action-bar" id="bulk-action-bar-guru">
    <div><span class="fw-bold fs-5" id="selected-count-guru">0</span> Guru/Admin Dipilih</div>
    <button type="button" class="btn btn-danger" id="btn-bulk-delete-guru"><i class="bi bi-trash-fill me-2"></i>Hapus Pilihan</button>
</div>

<!-- Bulk Action Bar SISWA -->
<div class="bulk-action-bar" id="bulk-action-bar-siswa">
    <div><span class="fw-bold fs-5" id="selected-count-siswa">0</span> Siswa Dipilih</div>
    <button type="button" class="btn btn-danger" id="btn-bulk-delete-siswa"><i class="bi bi-trash-fill me-2"></i>Hapus Pilihan</button>
</div>

<!-- [PERBAIKAN LOKASI SWEETALERT] -->
<?php
// Ditempatkan SEBELUM footer.php agar tidak konflik
if (isset($_SESSION['pesan'])) {
    $pesan = $_SESSION['pesan'];
    $data_json = json_decode($pesan, true);

    if (json_last_error() == JSON_ERROR_NONE && is_array($data_json)) {
        echo "<script>Swal.fire({
            icon: '" . addslashes($data_json['icon']) . "',
            title: '" . addslashes($data_json['title']) . "',
            html: '" . addslashes($data_json['html']) . "'
        });</script>";
    } else {
        echo "<script>Swal.fire({
            icon: 'success', 
            title: 'Berhasil!', 
            html: '" . addslashes($pesan) . "'
        });</script>";
    }
    unset($_SESSION['pesan']);

} elseif (isset($_SESSION['error'])) { 
    $error_pesan = $_SESSION['error'];
    $data_json = json_decode($error_pesan, true);

    if (json_last_error() == JSON_ERROR_NONE && is_array($data_json)) {
        echo "<script>Swal.fire({
            icon: '" . addslashes($data_json['icon']) . "',
            title: '" . addslashes($data_json['title']) . "',
            html: '" . addslashes($data_json['html']) . "'
        });</script>";
    } else {
        echo "<script>Swal.fire({
            icon: 'error', 
            title: 'Gagal!', 
            html: '" . addslashes($error_pesan) . "'
        });</script>";
    }
    unset($_SESSION['error']);

} elseif (isset($_SESSION['pesan_error'])) { // Dari siswa_aksi.php
    echo "<script>Swal.fire({
        icon: 'error', 
        title: 'Gagal!', 
        html: '" . addslashes($_SESSION['pesan_error']) . "'
    });</script>";
    unset($_SESSION['pesan_error']);
}
?>
<!-- [AKHIR PERBAIKAN LOKASI SWEETALERT] -->


<!-- Footer (untuk memanggil jQuery, dll) -->
<?php include 'footer.php'; ?>

<script>
$(document).ready(function(){
    // Inisialisasi Tooltip Bootstrap (jika footer Anda tidak memilikinya)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Fungsi Hapus GURU
function hapusGuru(id) {
    Swal.fire({
        title: 'Anda yakin?', 
        text: "Data guru/admin ini akan dihapus secara permanen!", 
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', 
        cancelButtonColor: '#3085d6', confirmButtonText: 'Ya, hapus!', cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'pengguna_aksi.php?aksi=hapus&id=' + id;
        }
    })
}

// Fungsi Hapus SISWA
function hapusSiswa(id) {
    Swal.fire({
        title: 'Anda yakin?', 
        text: "Data siswa ini akan dihapus permanen, termasuk semua nilai terkait!", 
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', 
        cancelButtonColor: '#3085d6', confirmButtonText: 'Ya, hapus!', cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'siswa_aksi.php?aksi=hapus&id=' + id;
        }
    })
}

// Skrip untuk Drag and Drop
function setupImportTab(tabPrefix, inputFileElementName) {
    const dropZone = $(`#drop-zone-${tabPrefix}`);
    const fileInput = $(`#file-input-${tabPrefix}`);
    const fileDetails = $(`#file-details-${tabPrefix}`);
    const importBtn = $(`#btn-import-${tabPrefix}`);

    const handleFile = (file) => {
        if (file && file.name.endsWith('.xlsx')) {
            const fileSize = (file.size / 1024).toFixed(2) + ' KB';
            fileDetails.html(`<div class="d-flex align-items-center"><i class="bi bi-file-earmark-excel-fill fs-2 text-success me-3"></i><div><div class="fw-bold">${file.name}</div><div class="small text-muted">${fileSize}</div></div></div>`);
            dropZone.find('.drop-zone-prompt').hide();
            fileDetails.show();
            importBtn.prop('disabled', false);
        } else {
            Swal.fire('Format Salah', 'Harap unggah file dengan format .xlsx', 'error');
            fileInput.val('');
            dropZone.find('.drop-zone-prompt').show();
            fileDetails.hide();
            importBtn.prop('disabled', true);
        }
    };
    fileInput.on('change', () => { if (fileInput[0].files.length > 0) { handleFile(fileInput[0].files[0]); } });
    dropZone.on('dragover', (e) => { e.preventDefault(); dropZone.addClass('drag-over'); });
    dropZone.on('dragleave', () => { dropZone.removeClass('drag-over'); });
    dropZone.on('drop', (e) => {
        e.preventDefault(); dropZone.removeClass('drag-over');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            const dataTransfer = new DataTransfer(); dataTransfer.items.add(files[0]);
            fileInput[0].files = dataTransfer.files;
            handleFile(files[0]);
        }
    });
}
setupImportTab('guru', 'file_pengguna');
setupImportTab('guru-mengajar', 'file_guru_mengajar'); 
setupImportTab('siswa', 'file_siswa_lengkap');

// Logika untuk Bulk Delete GURU
$(document).ready(function() {
    const $actionBarGuru = $('#bulk-action-bar-guru');
    const $countSpanGuru = $('#selected-count-guru');
    const $checkboxesGuru = $('.bulk-checkbox-guru');
    function updateActionBarGuru() {
        const count = $checkboxesGuru.filter(':checked').length;
        $countSpanGuru.text(count);
        $actionBarGuru.toggleClass('show', count > 0);
    }
    $checkboxesGuru.on('change', function() {
        $(this).closest('.user-card').toggleClass('selected', $(this).is(':checked'));
        updateActionBarGuru();
    });
    $('#btn-bulk-delete-guru').on('click', function() {
        const count = $checkboxesGuru.filter(':checked').length;
        Swal.fire({
            title: `Anda yakin?`, text: `Anda akan menghapus ${count} guru/admin secara permanen.`,
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', 
            cancelButtonColor: '#3085d6', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => { if (result.isConfirmed) { $('#form-bulk-delete-guru').submit(); } });
    });
    updateActionBarGuru();
});

// Logika untuk Bulk Delete SISWA
$(document).ready(function() {
    const $actionBarSiswa = $('#bulk-action-bar-siswa');
    const $countSpanSiswa = $('#selected-count-siswa');
    const $checkboxesSiswa = $('.bulk-checkbox-siswa');
    function updateActionBarSiswa() {
        const count = $checkboxesSiswa.filter(':checked').length;
        $countSpanSiswa.text(count);
        $actionBarSiswa.toggleClass('show', count > 0);
    }
    $checkboxesSiswa.on('change', function() {
        $(this).closest('.user-card').toggleClass('selected', $(this).is(':checked'));
        updateActionBarSiswa();
    });
    $('#btn-bulk-delete-siswa').on('click', function() {
        const count = $checkboxesSiswa.filter(':checked').length;
        Swal.fire({
            title: `Anda yakin?`, text: `Anda akan menghapus ${count} siswa secara permanen.`,
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', 
            cancelButtonColor: '#3085d6', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => { if (result.isConfirmed) { $('#form-bulk-delete-siswa').submit(); } });
    });
    updateActionBarSiswa();
});

// Penyesuaian posisi action bar saat sidebar di-toggle (Sesuai header.php Anda)
$(document).ready(function () {
    const initialLeftPos = $('#sidebar').hasClass('active') ? '0' : '260px';
    $('#bulk-action-bar-guru').css('left', initialLeftPos);
    $('#bulk-action-bar-siswa').css('left', initialLeftPos);
    $('#sidebarCollapse').on('click', function () {
        setTimeout(function() {
            const leftPos = $('#sidebar').hasClass('active') ? '0' : '260px';
            $('#bulk-action-bar-guru').css('left', leftPos);
            $('#bulk-action-bar-siswa').css('left', leftPos);
        }, 300); // Sesuaikan dengan durasi transisi Anda
    });
});

// [LOGIKA TAB BARU]
// Menangani URL dan Bulk Action Bar saat berganti tab
$('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
    const targetTab = $(e.target).data('bs-target');

    // Cek apakah ini sub-tab
    if ($(e.target).closest('.nav-pills-sub').length > 0) {
        let subTabId = targetTab.replace('#', '').replace('-pane', '');
        // Simpan sub-tab di URL
        if(history.pushState) {
            let newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + subTabId;
            window.history.pushState({path:newUrl}, '', newUrl);
        }
    }

    // Logika untuk menampilkan/menyembunyikan bulk action bar
    if (targetTab === '#siswa-pane') {
        $('#bulk-action-bar-guru').removeClass('show');
        if ($('.bulk-checkbox-siswa:checked').length > 0) {
            $('#bulk-action-bar-siswa').addClass('show');
        }
    } else if (targetTab === '#guru-admin-pane') {
        $('#bulk-action-bar-siswa').removeClass('show');
         if ($('.bulk-checkbox-guru:checked').length > 0) {
            $('#bulk-action-bar-guru').addClass('show');
        }
    } else {
        // Sembunyikan kedua bar jika di tab import atau main tab
        $('#bulk-action-bar-guru').removeClass('show');
        $('#bulk-action-bar-siswa').removeClass('show');
    }
});

</script>