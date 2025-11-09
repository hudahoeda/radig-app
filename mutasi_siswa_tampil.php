<?php
include 'header.php';
include 'koneksi.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak', text: 'Anda tidak memiliki izin.'}).then(() => window.location.href = 'dashboard.php');</script>";
    include 'footer.php';
    exit();
}

// Query 1: Mengambil siswa AKTIF
$query_siswa_aktif = "
    SELECT 
        s.id_siswa, s.nis, s.nisn, s.nama_lengkap, s.status_siswa, s.foto_siswa, k.nama_kelas 
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.status_siswa = 'Aktif'
    ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC
";
$result_siswa_aktif = mysqli_query($koneksi, $query_siswa_aktif);

// Query 2: Mengambil siswa NON-AKTIF (Mutasi)
$query_siswa_mutasi = "
    SELECT 
        s.id_siswa, s.nis, s.nisn, s.nama_lengkap, s.status_siswa, s.foto_siswa, k.nama_kelas 
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.status_siswa != 'Aktif'
    ORDER BY s.status_siswa ASC, s.nama_lengkap ASC
";
$result_siswa_mutasi = mysqli_query($koneksi, $query_siswa_mutasi);


// Query untuk mengisi dropdown filter kelas (hanya untuk siswa aktif)
$query_kelas = "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = (SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif') ORDER BY nama_kelas ASC";
$result_kelas = mysqli_query($koneksi, $query_kelas);
?>

<!-- ====================================================== -->
<!-- Menggunakan BUNDEL CSS (Core + Bootstrap 5) - (Tetap) -->
<!-- ====================================================== -->
<link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css">
<!-- CATATAN: Pastikan header.php sudah memuat 'bootstrap.min.css' -->

<!-- Style (Sudah ada, ditambah sedikit style untuk tab) -->
<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }

    .table-mutasi img { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
    .table-mutasi .student-name { font-weight: 600; color: var(--text-dark); }
    .profile-icon-placeholder {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #e9ecef;
        color: #6c757d;
        font-size: 1.5rem;
    }
    
    .nav-pills-primary .nav-link { color: var(--primary-color); }
    .nav-pills-primary .nav-link.active {
        background-color: var(--primary-color);
        color: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .tab-content { margin-top: 1.5rem; }
    .card-header .form-control, .card-header .form-select { min-width: 200px; }

    /* Style untuk dropdown aksi cepat */
    .dropdown-item.text-danger {
        font-weight: 500;
    }
    .dropdown-item.text-danger:hover {
        background-color: #f8d7da;
        color: #721c24;
    }

    /* Style untuk memastikan paginasi terlihat */
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1rem;
    }
    .dataTables_wrapper .dataTables_info {
        padding-top: 1rem;
    }
     .dataTables_wrapper .dataTables_length {
        padding-top: 0.5rem;
    }
    
    /* Menyembunyikan filter/searchbox default DataTables */
    .dataTables_wrapper .dataTables_filter {
        display: none;
    }
    
    /* ===================================================================
      --- CSS "KEREN" (Tetap dipertahankan) ---
      ===================================================================
    */
    .dataTables_wrapper .dataTables_paginate .page-item .page-link {
        border-radius: 0.375rem; /* Menyamakan dengan border-radius Bootstrap */
        margin: 0 3px;
        border: none;
        background-color: #f8f9fa; /* Warna dasar yang soft */
        color: var(--primary-color);
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .dataTables_wrapper .dataTables_paginate .page-item.disabled .page-link {
        background-color: #e9ecef;
        color: #adb5bd;
        box-shadow: none;
    }

    .dataTables_wrapper .dataTables_paginate .page-item .page-link:hover {
        background-color: var(--primary-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        transform: scale(1.05);
    }
    
    .dataTables_wrapper .dataTables_paginate .page-item.active .page-link:hover {
        transform: scale(1.05) translateY(-2px); /* Tetap ada hover effect */
    }
    
    /* Membuat "Show entries" dan "Showing 1 of..." lebih jelas */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_info {
        padding-top: 1rem; /* Menyamakan padding atas */
        font-weight: 500;
        color: #555;
    }
    .dataTables_wrapper .dataTables_length .form-select {
        font-weight: 500;
        border-radius: 0.375rem;
    }
</style>

<div class="container-fluid">
    <!-- Header Halaman (Tetap sama) -->
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Mutasi Siswa</h1>
                <p class="lead mb-0 opacity-75">Kelola data siswa yang masuk, keluar, pindah, atau lulus.</p>
            </div>
            <div class="d-flex mt-3 mt-sm-0">
                <a href="kelola_mutasi.php?aksi=masuk" class="btn btn-outline-light"><i class="bi bi-person-plus-fill me-2"></i>Catat Mutasi Masuk</a>
            </div>
        </div>
    </div>
    
    <!-- Navigasi Tab (Tetap sama) -->
    <ul class="nav nav-pills nav-pills-primary nav-fill" id="mutasiTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="aktif-tab" data-bs-toggle="tab" data-bs-target="#tabAktif" type="button" role="tab" aria-controls="tabAktif" aria-selected="true">
                <i class="bi bi-person-check-fill me-2"></i>Siswa Aktif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="mutasi-tab" data-bs-toggle="tab" data-bs-target="#tabMutasi" type="button" role="tab" aria-controls="tabMutasi" aria-selected="false">
                <i class="bi bi-person-x-fill me-2"></i>Siswa Non-Aktif / Mutasi
            </button>
        </li>
    </ul>

    <!-- Isi Tab -->
    <div class="tab-content" id="mutasiTabContent">
        
        <!-- --- TAB 1: SISWA AKTIF --- -->
        <div class="tab-pane fade show active" id="tabAktif" role="tabpanel" aria-labelledby="aktif-tab">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="card-title mb-0 py-2 me-3"><i class="bi bi-list-ul me-2" style="color: var(--primary-color);"></i>Daftar Siswa Aktif</h5>
                    <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 600px;">
                        <div class="flex-grow-1">
                            <input type="text" id="searchNamaAktif" class="form-control" placeholder="Cari nama siswa...">
                        </div>
                        <div class="flex-grow-1">
                            <select id="filterKelasAktif" class="form-select">
                                <option value="">Semua Kelas</option>
                                <?php mysqli_data_seek($result_kelas, 0); // Reset pointer result set ?>
                                <?php while($kelas = mysqli_fetch_assoc($result_kelas)): ?>
                                    <option value="<?php echo htmlspecialchars($kelas['nama_kelas']); ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-mutasi" id="tabelSiswaAktif" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 5%;">No.</th>
                                    <th style="width: 50px;">Foto</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>Kelas</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 15%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result_siswa_aktif) > 0): $no = 1; ?>
                                    <?php while ($siswa = mysqli_fetch_assoc($result_siswa_aktif)): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++; ?></td>
                                            <td style="width: 50px;">
                                                <?php 
                                                $foto_path = !empty($siswa['foto_siswa']) ? 'uploads/foto_siswa/' . $siswa['foto_siswa'] : '';
                                                if (!empty($foto_path) && file_exists($foto_path)): 
                                                ?>
                                                    <img src="<?php echo htmlspecialchars($foto_path); ?>" alt="Foto Siswa">
                                                <?php else: ?>
                                                    <div class="profile-icon-placeholder"><i class="bi bi-person-fill"></i></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="student-name"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                                <small class="text-muted">NIS: <?php echo htmlspecialchars($siswa['nis']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                                            <td><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></td>
                                            <td class="text-center">
                                                <span class='badge bg-success'>Aktif</span>
                                            </td>
                                            <td class="text-center">
                                                <!-- Tombol Aksi Split Button Dropdown -->
                                                <div class="btn-group">
                                                    <a href="kelola_mutasi.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Kelola Status Lengkap (Pindah/Lulus)">
                                                        <i class="bi bi-pencil-square"></i> Kelola
                                                    </a>
                                                    <button type="button" class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item text-danger btn-quick-keluar" href="#" 
                                                               data-id-siswa="<?php echo $siswa['id_siswa']; ?>" 
                                                               data-nama-siswa="<?php echo htmlspecialchars($siswa['nama_lengkap']); ?>">
                                                               <i class="bi bi-box-arrow-left me-2"></i>Keluarkan Siswa
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                                <!-- 
                                =============================================
                                --- PERBAIKAN 1: Menghapus blok 'else' ---
                                Blok 'else' yang menampilkan "Tidak ada data"
                                dihapus. DataTables akan menanganinya.
                                =============================================
                                -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- --- TAB 2: SISWA NON-AKTIF / MUTASI --- -->
        <div class="tab-pane fade" id="tabMutasi" role="tabpanel" aria-labelledby="mutasi-tab">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="card-title mb-0 py-2 me-3"><i class="bi bi-list-ul me-2" style="color: var(--primary-color);"></i>Daftar Siswa Non-Aktif (Pindah, Lulus, Keluar)</h5>
                    <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 600px;">
                        <div class="flex-grow-1">
                            <input type="text" id="searchNamaMutasi" class="form-control" placeholder="Cari nama siswa...">
                        </div>
                        <div class="flex-grow-1">
                            <select id="filterStatusMutasi" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="Pindah">Pindah</option>
                                <option value="Lulus">Lulus</option>
                                <option value="Keluar">Keluar</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-mutasi" id="tabelSiswaMutasi" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 5%;">No.</th>
                                    <th style="width: 50px;">Foto</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>Kelas Terakhir</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 10%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result_siswa_mutasi) > 0): $no = 1; ?>
                                    <?php while ($siswa = mysqli_fetch_assoc($result_siswa_mutasi)): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++; ?></td>
                                            <td style="width: 50px;">
                                                <?php 
                                                $foto_path = !empty($siswa['foto_siswa']) ? 'uploads/foto_siswa/' . $siswa['foto_siswa'] : '';
                                                if (!empty($foto_path) && file_exists($foto_path)): 
                                                ?>
                                                    <img src="<?php echo htmlspecialchars($foto_path); ?>" alt="Foto Siswa">
                                                <?php else: ?>
                                                    <div class="profile-icon-placeholder"><i class="bi bi-person-fill"></i></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="student-name"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                                <small class="text-muted">NIS: <?php echo htmlspecialchars($siswa['nis']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                                            <td><?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Tidak ada kelas'); ?></td> 
                                            <td class="text-center">
                                                <?php
                                                $status = htmlspecialchars($siswa['status_siswa']);
                                                $badge_class = 'bg-secondary';
                                                if ($status == 'Pindah') $badge_class = 'bg-warning text-dark';
                                                elseif ($status == 'Lulus') $badge_class = 'bg-info';
                                                elseif ($status == 'Keluar') $badge_class = 'bg-danger';
                                                echo "<span class='badge {$badge_class}'>{$status}</span>";
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="kelola_mutasi.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Lihat Detail & Kelola Mutasi">
                                                    <i class="bi bi-pencil-square"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                                <!-- 
                                =============================================
                                --- PERBAIKAN 2: Menghapus blok 'else' ---
                                Blok 'else' yang menampilkan "Tidak ada data"
                                dihapus. Ini adalah sumber error Anda.
                                =============================================
                                -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- 
  ===================================================================
  --- URUTAN LOADING SCRIPT (SUDAH BENAR) ---
  LANGKAH 1: Memuat footer.php (yang berisi JQUERY dan BOOTSTRAP JS).
  ===================================================================
-->
<?php include 'footer.php'; ?>

<!-- 
  ===================================================================
  LANGKAH 2: SEKARANG, kita memuat script KHUSUS untuk halaman ini.
  ===================================================================
-->

<!-- ====================================================== -->
<!-- Menggunakan BUNDEL JS (Core + Bootstrap 5) - (Tetap) -->
<!-- ====================================================== -->
<script src="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.js"></script>


<!-- 3. SKRIP CUSTOM ANDA (SEKARANG DIJAMIN BERHASIL) -->
<script>
$(document).ready(function() {
    var indoJson = "https://cdn.datatables.net/plug-ins/2.0.8/i18n/id.json";
    
    // Inisialisasi DataTables untuk TAB 1 (Siswa Aktif)
    var tableAktif = $('#tabelSiswaAktif').DataTable({
        "language": { "url": indoJson },
        "pagingType": "full_numbers", 
        "columnDefs": [ 
            // Kolom 1 (Foto) & 6 (Aksi) tidak bisa di-sort
            { "orderable": false, "targets": [1, 6] }, 
            // Kolom 0, 1, 3, 5, 6 tidak bisa di-search
            { "searchable": false, "targets": [0, 1, 3, 5, 6] } 
            // Kolom 2 (Nama) dan 4 (Kelas) BISA di-search
        ],
        "pageLength": 5, 
        "lengthMenu": [ [5, 10, 25, -1], [5, 10, 25, "Semua"] ] 
    });

    // Event listener untuk filter Tab 1
    $('#searchNamaAktif').on('keyup', function() {
        // Kolom 2 adalah 'Nama Siswa'
        tableAktif.column(2).search(this.value).draw();
    });
    $('#filterKelasAktif').on('change', function() {
        // Kolom 4 adalah 'Kelas'
        tableAktif.column(4).search(this.value).draw();
    });

    // Inisialisasi DataTables untuk TAB 2 (Siswa Mutasi)
    var tableMutasi = $('#tabelSiswaMutasi').DataTable({
        "language": { "url": indoJson },
        "pagingType": "full_numbers", 
        "columnDefs": [ 
            // Kolom 1 (Foto) & 6 (Aksi) tidak bisa di-sort
            { "orderable": false, "targets": [1, 6] },
            // Kolom 0, 1, 3, 4, 6 tidak bisa di-search
            { "searchable": false, "targets": [0, 1, 3, 4, 6] }
            // Kolom 2 (Nama) dan 5 (Status) BISA di-search
        ],
        "pageLength": 10, 
        "lengthMenu": [ [10, 25, -1], [10, 25, "Semua"] ] 
    });

    // Event listener untuk filter Tab 2
    $('#searchNamaMutasi').on('keyup', function() {
        // Kolom 2 adalah 'Nama Siswa'
        tableMutasi.column(2).search(this.value).draw();
    });
    $('#filterStatusMutasi').on('change', function() {
        // Kolom 5 adalah 'Status'
        tableMutasi.column(5).search(this.value).draw();
    });
    
    // Aksi Cepat "Keluarkan Siswa" (Tetap sama)
    $('#tabelSiswaAktif').on('click', '.btn-quick-keluar', function(e) {
        e.preventDefault();
        var idSiswa = $(this).data('id-siswa');
        var namaSiswa = $(this).data('nama-siswa');
        var trElement = $(this).closest('tr'); 

        Swal.fire({
            title: 'Keluarkan Siswa: ' + namaSiswa,
            text: 'Anda yakin ingin mengubah status siswa ini menjadi "Keluar"? Tindakan ini akan melepas siswa dari kelasnya.',
            input: 'textarea',
            inputLabel: 'Alasan Keluar (Wajib diisi)',
            inputPlaceholder: 'Contoh: Pindah sekolah, berhenti, dll...',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Keluarkan',
            cancelButtonText: 'Batal',
            inputValidator: (value) => {
                if (!value) {
                    return 'Anda harus mengisi alasan!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const alasan = result.value;
                prosesQuickKeluar(idSiswa, alasan, trElement);
            }
        });
    });

    // Fungsi AJAX untuk Aksi Cepat (Tetap sama)
    async function prosesQuickKeluar(idSiswa, alasan, trElement) {
        const formData = new FormData();
        formData.append('id_siswa', idSiswa);
        formData.append('alasan', alasan);

        try {
            const response = await fetch('mutasi_aksi.php?aksi=quick_keluar', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.status === 'success') {
                Swal.fire(
                    'Berhasil!',
                    data.message,
                    'success'
                ).then(() => {
                    location.reload(); 
                });
                
            } else {
                Swal.fire(
                    'Gagal!',
                    data.message,
                    'error'
                );
            }
        } catch (error)
        {
            Swal.fire(
                'Error!',
                'Terjadi kesalahan saat menghubungi server: ' + error.message,
                'error'
            );
        }
    }
    
    // Blok Tooltip (Sudah benar dihapus, ditangani oleh footer.php)

    // Refresh DataTables saat tab diganti (Tetap diperlukan)
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });
});
</script>