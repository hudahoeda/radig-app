<?php
include 'koneksi.php';
include 'header.php'; // Menggunakan header.php Anda

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak memiliki wewenang.'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// --- [LOGIKA TAB BARU - UPDATED] ---
// Tentukan sub-tab aktif
$active_tab = $_GET['tab'] ?? 'guru'; // Default ke sub-tab 'guru'

// Tentukan main-tab aktif berdasarkan sub-tab
$active_main_tab = 'pengguna'; // Default
if (in_array($active_tab, ['import_guru', 'import_siswa'])) {
    $active_main_tab = 'import';
}
// --- [AKHIR LOGIKA TAB BARU] ---

?>

<style>
    /* Gaya CSS dari respons saya sebelumnya */
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }

    /* Sticky Header CSS Override untuk DataTables */
    .table-container {
        max-height: 65vh;
        overflow-y: auto;
    }
    .table-container::-webkit-scrollbar { width: 6px; }
    .table-container::-webkit-scrollbar-track { background: #f1f5f9; }
    .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    .table thead th,
    table.dataTable thead th,
    table.dataTable thead th.sorting,
    table.dataTable thead th.sorting_asc,
    table.dataTable thead th.sorting_desc {
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        background-color: #f8fafc !important;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        padding: 1rem;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
        border-bottom: none !important;
    }
    .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table tbody tr:hover { background-color: #f8fafc; }

    /* Styling Dropdown Aksi 3 Titik */
    .btn-action-dots {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%; transition: all 0.2s; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569;
    }
    .btn-action-dots:hover, .btn-action-dots:focus {
        background: #e2e8f0; transform: translateY(-2px); box-shadow: 0 3px 6px rgba(0,0,0,0.05); color: #0f172a;
    }
    .dropdown-action-menu {
        border: none;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        border-radius: 12px;
        padding: 0.5rem;
    }
    .dropdown-action-menu .dropdown-item {
        border-radius: 8px;
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s;
    }
    .dropdown-action-menu .dropdown-item:hover {
        background-color: #f1f5f9;
        transform: translateX(3px);
    }
    .dropdown-action-menu .dropdown-item.text-danger:hover {
        background-color: #fef2f2;
    }

    /* DataTables Customization */
    div.dataTables_wrapper div.dataTables_filter input {
        border-radius: 20px;
        border: 1px solid #cbd5e1;
        padding: 0.4rem 1rem;
    }
    div.dataTables_wrapper div.dataTables_length select {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
    }

    /* Other utilities */
    .status-dot {
        height: 10px;
        width: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .status-online { background-color: var(--bs-success); }
    .status-offline { background-color: var(--bs-secondary); }

    .table-students img {
        width: 45px; height: 45px;
        object-fit: cover; border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    /* Import Styles */
    .import-step {
        display: flex; align-items: flex-start; margin-bottom: 1.5rem;
    }
    .import-step .step-number {
        flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%;
        background-color: var(--primary-color); color: white;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 1.2rem; margin-right: 1rem;
    }
    .import-step .step-content h5 { font-weight: 600; color: var(--primary-color); }
    .drop-zone {
        border: 2px dashed #ccc; border-radius: 0.5rem; padding: 2rem;
        text-align: center; cursor: pointer; transition: all 0.2s ease-in-out;
    }
    .drop-zone:hover, .drop-zone.drag-over {
        border-color: var(--primary-color); background-color: #f8f9fa;
    }
    .drop-zone .drop-zone-prompt { color: #6c757d; }
    .file-details {
        background-color: #e9f5ff; border: 1px solid #b8d9f7;
        border-radius: 0.5rem; padding: 1rem;
    }
    
    /* Bulk Action Bar */
    .bulk-action-bar {
        position: fixed; bottom: -100px; left: 0; right: 0;
        background-color: #212529; color: white;
        padding: 1rem 1.5rem; box-shadow: 0 -4px 15px rgba(0,0,0,0.2);
        z-index: 100; transition: bottom 0.3s ease-in-out;
        display: flex; justify-content: space-between; align-items: center;
    }
    .bulk-action-bar.show { bottom: 0; }
    
    /* Sidebar adjustment */
    #content .bulk-action-bar { left: 260px; }
    #sidebar.active + #content .bulk-action-bar { left: 0; }
    @media (max-width: 768px) { #content .bulk-action-bar { left: 0; } }

    /* Tab Styles */
    .nav-tabs-main { border-bottom: 2px solid var(--border-color); }
    .nav-tabs-main .nav-link {
        font-size: 1.1rem; font-weight: 600; color: var(--bs-secondary-color);
        border: none; border-bottom: 4px solid transparent; padding: 1rem 1.5rem;
    }
    .nav-tabs-main .nav-link.active {
        color: var(--primary-color); border-color: var(--primary-color); background-color: transparent;
    }
    
    .nav-pills-sub {
        background-color: #f8f9fa; padding: 0.5rem; border-radius: 0.5rem;
        margin-bottom: 1.5rem; border: 1px solid var(--border-color);
    }
    .nav-pills-sub .nav-link { font-weight: 500; color: var(--text-dark); border-radius: 0.375rem; }
    .nav-pills-sub .nav-link.active {
        background-color: var(--primary-color); color: white;
        box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.3);
    }
    
    .card-body > .tab-content > .tab-pane { padding: 1.5rem 0 0 0; }
    .card-body.card-body-tabbed { padding: 0 1.5rem 1.5rem 1.5rem; }
</style>

<!-- Tambahan DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

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
        <!-- [NAVIGASI TAB UTAMA] -->
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

        <div class="card-body card-body-tabbed">
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
                        <div class="tab-pane fade <?php if($active_tab == 'guru') echo 'show active'; ?>" id="guru-admin-pane" role="tabpanel">
                            
                            <!-- Toolbar: Download -->
                            <div class="d-flex justify-content-end mb-4 gap-2">
                                <!-- Tombol Download Data Guru -->
                                <a href="pengguna_aksi.php?aksi=export_guru" target="_blank" class="btn btn-success text-white shadow-sm">
                                    <i class="bi bi-file-earmark-excel-fill me-2"></i>Download Data Guru
                                </a>
                            </div>

                            <!-- Form Bulk Delete Guru -->
                            <form id="form-bulk-delete-guru" action="pengguna_aksi.php?aksi=hapus_banyak" method="POST">
                                <div class="table-container">
                                    <table id="tabelGuru" class="table table-students align-middle mb-0 w-100">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 40px;"><input type="checkbox" class="form-check-input" id="checkAllGuru" title="Pilih Semua"></th>
                                                <th class="text-center" style="width: 60px;">No</th>
                                                <th class="text-center" style="width: 70px;">Foto</th>
                                                <th>Nama / NIP</th>
                                                <th>Username & Role</th>
                                                <th>Status Aktivitas</th>
                                                <th class="text-center" style="width: 80px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        // Query Guru (Tanpa Limit agar DataTables yang meng-handle)
                                        $query_guru = mysqli_query($koneksi, "SELECT id_guru, nama_guru, nip, username, role, foto_guru, terakhir_login FROM guru ORDER BY nama_guru ASC");
                                        $no = 1;
                                        while ($data = mysqli_fetch_assoc($query_guru)) {
                                            $foto_guru = $data['foto_guru'] ?? null;
                                            $foto_path = 'uploads/guru_photos/' . $foto_guru;
                                            $foto_default = 'uploads/guruc.png'; 
                                            $gambar_tampil = (!empty($foto_guru) && file_exists($foto_path)) ? $foto_path : $foto_default;
                                            $is_self = ($_SESSION['id_guru'] == $data['id_guru']);

                                            // Cek Status
                                            if ($data['terakhir_login']) {
                                                $last_login = new DateTime($data['terakhir_login']); $now = new DateTime();
                                                $interval = $now->getTimestamp() - $last_login->getTimestamp();
                                                $is_online = $interval < 300; // 5 menit
                                                $status_text = 'Login ' . $last_login->format('d/m/Y, H:i');
                                            } else {
                                                $is_online = false;
                                                $status_text = 'Belum pernah login';
                                            }
                                        ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php if (!$is_self): ?>
                                                    <input class="form-check-input bulk-checkbox-guru" type="checkbox" name="user_ids[]" value="<?php echo $data['id_guru']; ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center fw-bold text-muted"><?php echo $no++; ?></td>
                                                <td class="text-center">
                                                    <img src="<?php echo htmlspecialchars($gambar_tampil); ?>" alt="Foto" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($data['nama_guru']); ?>">
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($data['nama_guru']); ?></div>
                                                    <div class="text-muted small">NIP. <?php echo htmlspecialchars($data['nip'] ?? '-'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($data['username']); ?></div>
                                                    <div class="mt-1">
                                                        <?php if($data['role'] == 'admin'): ?><span class="badge text-bg-primary">Admin</span><?php else: ?><span class="badge text-bg-secondary">Guru</span><?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <span class="status-dot <?php echo $is_online ? 'status-online' : 'status-offline'; ?>"></span>
                                                        <span class="text-muted"><?php echo $status_text; ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn-action-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="0,5">
                                                            <i class="bi bi-three-dots-vertical"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end dropdown-action-menu">
                                                            <li><a class="dropdown-item text-warning" href="pengguna_edit.php?id=<?php echo $data['id_guru']; ?>"><i class="bi bi-pencil-square me-2"></i> Edit Data</a></li>
                                                            <?php if (!$is_self) : ?>
                                                            <li><hr class="dropdown-divider my-1"></li>
                                                            <li><a class="dropdown-item text-primary" href="admin_aksi.php?aksi=login_sebagai_guru&id_target=<?php echo $data['id_guru']; ?>"><i class="bi bi-person-fill-gear me-2"></i> Login Sebagai</a></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="hapusGuru(<?php echo $data['id_guru']; ?>); return false;"><i class="bi bi-trash me-2"></i> Hapus</a></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                        
                        <!-- [SUB-PANE: SISWA] -->
                        <div class="tab-pane fade <?php if($active_tab == 'siswa') echo 'show active'; ?>" id="siswa-pane" role="tabpanel">
                            
                            <!-- Toolbar: Download Siswa -->
                            <div class="d-flex justify-content-end mb-4 gap-2">
                                <!-- Tombol Download Data Siswa -->
                                <a href="pengguna_aksi.php?aksi=export_siswa" target="_blank" class="btn btn-success text-white shadow-sm">
                                    <i class="bi bi-file-earmark-excel-fill me-2"></i>Download Data Siswa
                                </a>
                            </div>

                            <!-- Form Bulk Delete Siswa -->
                            <form id="form-bulk-delete-siswa" action="siswa_aksi.php?aksi=hapus_banyak" method="POST">
                                <div class="table-container">
                                    <table id="tabelSiswa" class="table table-students align-middle mb-0 w-100">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 40px;"><input type="checkbox" class="form-check-input" id="checkAllSiswa" title="Pilih Semua"></th>
                                                <th class="text-center" style="width: 60px;">No</th>
                                                <th class="text-center" style="width: 70px;">Foto</th>
                                                <th>Nama / NISN</th>
                                                <th>Kelas</th>
                                                <th>Status & Username</th>
                                                <th class="text-center" style="width: 80px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        // Query Siswa (Tanpa Limit agar DataTables yang meng-handle)
                                        $query_siswa = mysqli_query($koneksi, "SELECT s.id_siswa, s.nama_lengkap, s.nisn, s.nis, s.username, s.foto_siswa, s.status_siswa, (SELECT k.nama_kelas FROM kelas k WHERE k.id_kelas = s.id_kelas) as nama_kelas FROM siswa s ORDER BY s.nama_lengkap ASC");
                                        $no = 1;
                                        while ($data = mysqli_fetch_assoc($query_siswa)) {
                                            $foto_siswa = $data['foto_siswa'] ?? null;
                                            $foto_path = 'uploads/foto_siswa/' . $foto_siswa;
                                            $foto_default = 'uploads/siswac.png'; 
                                            $gambar_tampil = (!empty($foto_siswa) && file_exists($foto_path)) ? $foto_path : $foto_default;
                                        ?>
                                            <tr>
                                                <td class="text-center">
                                                    <input class="form-check-input bulk-checkbox-siswa" type="checkbox" name="siswa_ids[]" value="<?php echo $data['id_siswa']; ?>">
                                                </td>
                                                <td class="text-center fw-bold text-muted"><?php echo $no++; ?></td>
                                                <td class="text-center">
                                                    <img src="<?php echo htmlspecialchars($gambar_tampil); ?>" alt="Foto" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($data['nama_lengkap']); ?>">
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($data['nama_lengkap']); ?></div>
                                                    <div class="text-muted small">NISN. <?php echo htmlspecialchars($data['nisn'] ?? '-'); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge text-bg-info"><?php echo htmlspecialchars($data['nama_kelas'] ?? 'Belum ada kelas'); ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($data['username']); ?></div>
                                                    <div class="mt-1">
                                                        <?php $status_badge = ($data['status_siswa'] != 'Aktif') ? 'text-bg-secondary' : 'text-bg-success'; ?>
                                                        <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($data['status_siswa']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn-action-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="0,5">
                                                            <i class="bi bi-three-dots-vertical"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end dropdown-action-menu">
                                                            <li><a class="dropdown-item text-warning" href="siswa_edit.php?id=<?php echo $data['id_siswa']; ?>"><i class="bi bi-pencil-square me-2"></i> Edit Data</a></li>
                                                            <li><hr class="dropdown-divider my-1"></li>
                                                            <li><a class="dropdown-item text-primary" href="admin_aksi.php?aksi=login_sebagai_siswa&id_target=<?php echo $data['id_siswa']; ?>"><i class="bi bi-person-fill-gear me-2"></i> Login Sebagai</a></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="hapusSiswa(<?php echo $data['id_siswa']; ?>); return false;"><i class="bi bi-trash me-2"></i> Hapus</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>

                <!-- [PANE TAB UTAMA: IMPORT] -->
                <div class="tab-pane fade <?php if($active_main_tab == 'import') echo 'show active'; ?>" id="import-main-pane" role="tabpanel">

                    <!-- Sub-Tab Navigasi (Import) - HANYA 2 TAB -->
                    <ul class="nav nav-pills nav-pills-sub nav-fill" id="importSubTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php if($active_tab == 'import_guru') echo 'active'; ?>" id="sub-tab-import-guru" data-bs-toggle="tab" data-bs-target="#import-guru-pane" type="button" role="tab">Import Guru</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php if($active_tab == 'import_siswa') echo 'active'; ?>" id="sub-tab-import-siswa" data-bs-toggle="tab" data-bs-target="#import-siswa-pane" type="button" role="tab">Import Siswa</button>
                        </li>
                    </ul>

                    <!-- Sub-Tab Content (Import) -->
                    <div class="tab-content" id="importSubTabContent">
                        
                        <!-- [SUB-PANE: IMPORT GURU] -->
                        <div class="tab-pane fade <?php if($active_tab == 'import_guru') echo 'show active'; ?> p-4" id="import-guru-pane" role="tabpanel">
                            <div class="import-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Download Template Guru</h5>
                                    <p class="text-muted mb-0">Hanya untuk menambah data guru (NIP, Nama, Username, Role).</p>
                                    <a href="template_download.php?tipe=guru" class="btn btn-sm btn-success mt-2"><i class="bi bi-file-earmark-arrow-down-fill me-2"></i>Download Template Guru</a>
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
                                        <div class="d-grid mt-3"><button type="submit" class="btn btn-primary btn-lg" id="btn-import-guru" disabled><i class="bi bi-upload me-2"></i>Import Guru</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- [SUB-PANE: IMPORT SISWA] -->
                        <div class="tab-pane fade <?php if($active_tab == 'import_siswa') echo 'show active'; ?> p-4" id="import-siswa-pane" role="tabpanel">
                             <div class="import-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Download Template Siswa</h5>
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
                                        <div class="d-grid mt-3"><button type="submit" class="btn btn-primary btn-lg" id="btn-import-siswa" disabled><i class="bi bi-upload me-2"></i>Import Siswa</button></div>
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

<!-- Footer (untuk memanggil jQuery, dll) -->
<?php include 'footer.php'; ?>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
    // Inisialisasi Tooltip Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Inisialisasi DataTables untuk tabel Guru
    const dtGuru = $('#tabelGuru').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
        pageLength: 25,
        dom: '<"row align-items-center mb-3"<"col-md-6"l><"col-md-6"f>>rt<"row align-items-center mt-3"<"col-md-6"i><"col-md-6"p>>',
        columnDefs: [ { orderable: false, targets: [0, 2, 6] } ] // Disable sort for checkbox, foto, aksi
    });

    // Inisialisasi DataTables untuk tabel Siswa
    const dtSiswa = $('#tabelSiswa').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
        pageLength: 25,
        dom: '<"row align-items-center mb-3"<"col-md-6"l><"col-md-6"f>>rt<"row align-items-center mt-3"<"col-md-6"i><"col-md-6"p>>',
        columnDefs: [ { orderable: false, targets: [0, 2, 6] } ] // Disable sort for checkbox, foto, aksi
    });

    // Handle "Check All" functionality inside DataTables pages
    $('#checkAllGuru').on('change', function() {
        const isChecked = $(this).is(':checked');
        dtGuru.rows({page: 'current'}).nodes().to$().find('.bulk-checkbox-guru').prop('checked', isChecked);
        updateActionBarGuru();
    });
    $('#checkAllSiswa').on('change', function() {
        const isChecked = $(this).is(':checked');
        dtSiswa.rows({page: 'current'}).nodes().to$().find('.bulk-checkbox-siswa').prop('checked', isChecked);
        updateActionBarSiswa();
    });

    // Handle individual checkbox changes
    $('#tabelGuru tbody').on('change', '.bulk-checkbox-guru', function() {
        const allChecked = dtGuru.rows({page: 'current'}).nodes().to$().find('.bulk-checkbox-guru').length === dtGuru.rows({page: 'current'}).nodes().to$().find('.bulk-checkbox-guru:checked').length;
        $('#checkAllGuru').prop('checked', allChecked);
        updateActionBarGuru();
    });
    $('#tabelSiswa tbody').on('change', '.bulk-checkbox-siswa', function() {
        const allChecked = dtSiswa.rows({page: 'current'}).nodes().to$().find('.bulk-checkbox-siswa').length === dtSiswa.rows({page: 'current'}).nodes().to$().find('.bulk-checkbox-siswa:checked').length;
        $('#checkAllSiswa').prop('checked', allChecked);
        updateActionBarSiswa();
    });

    // Import Tab Setup (Drag and Drop)
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
    setupImportTab('siswa', 'file_siswa_lengkap');

    // Bulk Delete Action Bars
    const $actionBarGuru = $('#bulk-action-bar-guru');
    const $countSpanGuru = $('#selected-count-guru');
    function updateActionBarGuru() {
        const count = dtGuru.$('.bulk-checkbox-guru:checked').length;
        $countSpanGuru.text(count);
        $actionBarGuru.toggleClass('show', count > 0);
    }
    $('#btn-bulk-delete-guru').on('click', function() {
        const count = dtGuru.$('.bulk-checkbox-guru:checked').length;
        Swal.fire({
            title: `Anda yakin?`, text: `Anda akan menghapus ${count} guru/admin secara permanen.`,
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', 
            cancelButtonColor: '#3085d6', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => { if (result.isConfirmed) { $('#form-bulk-delete-guru').submit(); } });
    });

    const $actionBarSiswa = $('#bulk-action-bar-siswa');
    const $countSpanSiswa = $('#selected-count-siswa');
    function updateActionBarSiswa() {
        const count = dtSiswa.$('.bulk-checkbox-siswa:checked').length;
        $countSpanSiswa.text(count);
        $actionBarSiswa.toggleClass('show', count > 0);
    }
    $('#btn-bulk-delete-siswa').on('click', function() {
        const count = dtSiswa.$('.bulk-checkbox-siswa:checked').length;
        Swal.fire({
            title: `Anda yakin?`, text: `Anda akan menghapus ${count} siswa secara permanen.`,
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', 
            cancelButtonColor: '#3085d6', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => { if (result.isConfirmed) { $('#form-bulk-delete-siswa').submit(); } });
    });
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