<?php
include 'header.php';
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang untuk mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

// --- LANGKAH 1: DETEKSI OTOMATIS JENJANG SEKOLAH ---
$query_sekolah = mysqli_query($koneksi, "SELECT jenjang FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($query_sekolah);
$jenjang_sekolah = $sekolah['jenjang'] ?? 'SMP'; 

// [MODIFIKASI] Kita ingin halaman ini SELALU menampilkan Mapel
$tampilkan_mode_mapel = true; 
?>

<style>
    /* Style umum */
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    #search-input { max-width: 400px; }

    /* Style untuk Kartu Mata Pelajaran */
    .mapel-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: 0; border-radius: 0.75rem;
        overflow: hidden;
    }
    .mapel-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.12);
    }
    .mapel-card-header {
        color: white; padding: 1.5rem; position: relative; overflow: hidden;
    }
    .mapel-card-header .mapel-icon {
        font-size: 4rem; position: absolute; right: -1rem; bottom: -1.5rem;
        opacity: 0.2; transform: rotate(-15deg);
    }
    .mapel-card-header h4 { font-weight: 700; margin-bottom: 0.25rem; font-size: 1.1rem; }
    .mapel-card-header p { margin: 0; opacity: 0.9; font-size: 0.85rem; }
    
    .mapel-card .list-group-item { border-color: rgba(0,0,0,0.05); padding: 0.75rem 1.25rem; }
    .mapel-card .guru-list { padding-left: 1.2rem; font-size: 0.85rem; }
    .mapel-card .guru-list li { margin-bottom: 0.15rem; }
    
    .badge-kelas {
        font-size: 0.75rem; margin-right: 4px; margin-bottom: 4px; 
        display: inline-block; font-weight: normal;
    }
    .badge-kelompok {
        font-size: 0.7rem; letter-spacing: 0.5px; text-transform: uppercase;
        background: rgba(255,255,255,0.2); backdrop-filter: blur(5px);
        border: 1px solid rgba(255,255,255,0.3);
    }
    /* Tombol Toggle Agama */
    .btn-toggle-agama {
        width: 32px; height: 32px; padding: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; transition: all 0.2s;
        z-index: 10; position: relative;
    }
    .btn-toggle-agama:hover {
        transform: scale(1.1);
        background-color: rgba(255,255,255,0.3) !important;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Manajemen Mata Pelajaran</h1>
                <p class="lead mb-0 opacity-75">Master Data Mapel untuk <?php echo $jenjang_sekolah; ?>.</p>
            </div>
            <a href="mapel_tambah.php" class="btn btn-outline-light mt-3 mt-sm-0">
                <i class="bi bi-plus-circle-fill me-2"></i>Tambah Mata Pelajaran
            </a>
        </div>
    </div>

    <div class="mb-4 d-flex justify-content-between flex-wrap gap-2">
        <div class="input-group" style="max-width: 400px;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="search-input" class="form-control border-start-0 ps-0" placeholder="Cari mapel, kode, guru, atau kelompok...">
        </div>
        
        <div>
            <a href="mapel_urutkan.php" class="btn btn-primary">
                <i class="bi bi-list-ol me-2"></i>Atur Urutan Mapel
            </a>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="card-list">
        <?php 
        $query = mysqli_query($koneksi, "
            SELECT 
                mp.id_mapel, mp.nama_mapel, mp.kode_mapel, mp.kelompok,
                (SELECT COUNT(tp.id_tp) FROM tujuan_pembelajaran tp WHERE tp.id_mapel = mp.id_mapel) as jumlah_tp,
                (SELECT COUNT(DISTINCT gm.id_guru) FROM guru_mengajar gm WHERE gm.id_mapel = mp.id_mapel) as jumlah_guru,
                (SELECT GROUP_CONCAT(DISTINCT g.nama_guru SEPARATOR '</li><li>') FROM guru_mengajar gm JOIN guru g ON gm.id_guru = g.id_guru WHERE gm.id_mapel = mp.id_mapel LIMIT 3) as guru_pengampu,
                (SELECT GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas ASC SEPARATOR ', ') FROM guru_mengajar gm JOIN kelas k ON gm.id_kelas = k.id_kelas WHERE gm.id_mapel = mp.id_mapel) as kelas_diajarkan
            FROM mata_pelajaran mp ORDER BY mp.urutan ASC, mp.nama_mapel ASC
        ");
        
        $colors = ['#0d6efd', '#6f42c1', '#d63384', '#fd7e14', '#198754', '#0dcaf0', '#6610f2'];
        $color_index = 0;

        if ($query && mysqli_num_rows($query) > 0) {
            while ($data = mysqli_fetch_assoc($query)) {
                // Deteksi Agama berdasarkan Nama ATAU Kelompok
                $kelompok = $data['kelompok'] ?? 'Umum'; // Default Umum jika null
                // Kita anggap Agama jika kelompoknya mengandung kata 'Agama'
                $is_agama = (stripos($kelompok, 'Agama') !== false); 
                $is_mulok = (stripos($kelompok, 'Mulok') !== false || stripos($kelompok, 'Muatan Lokal') !== false);
                
                // Tentukan warna header
                if ($is_agama) {
                    $bg_color = '#157347'; // Hijau tua (Agama)
                    $icon_class = 'bi-moon-stars-fill';
                } elseif ($is_mulok) {
                    $bg_color = '#d63384'; // Pink/Merah (Mulok)
                    $icon_class = 'bi-flower1';
                } else {
                    $bg_color = $colors[$color_index % count($colors)];
                    $color_index++;
                    $icon_class = 'bi-book-half';
                }
        ?>
            <div class="col searchable-card">
                <div class="card shadow-sm mapel-card h-100">
                    <div class="mapel-card-header" style="background-color: <?php echo $bg_color; ?>;">
                        <i class="bi <?php echo $icon_class; ?> mapel-icon"></i>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <!-- Badge Kelompok -->
                            <span class="badge badge-kelompok searchable-kelompok">
                                <?php echo htmlspecialchars($kelompok); ?>
                            </span>
                            
                            <!-- [BARU] Tombol Toggle Agama -->
                            <button onclick="toggleStatusAgama(<?php echo $data['id_mapel']; ?>, '<?php echo addslashes($data['nama_mapel']); ?>', <?php echo $is_agama ? 'true' : 'false'; ?>)" 
                                    class="btn btn-toggle-agama <?php echo $is_agama ? 'btn-light text-warning' : 'btn-outline-light text-white'; ?>" 
                                    data-bs-toggle="tooltip" 
                                    title="<?php echo $is_agama ? 'Klik untuk ubah ke Mapel Umum' : 'Klik untuk tandai sebagai Agama'; ?>">
                                <i class="bi <?php echo $is_agama ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                            </button>
                        </div>
                        
                        <h4 class="searchable-name"><?php echo htmlspecialchars($data['nama_mapel']); ?></h4>
                        <p class="searchable-code badge bg-white bg-opacity-25 fw-normal">Kode: <?php echo htmlspecialchars($data['kode_mapel']); ?></p>
                    </div>
                    
                    <div class="card-body p-0">
                        <!-- Stats Bar -->
                        <div class="d-flex border-bottom bg-light">
                            <div class="flex-fill text-center p-2 border-end">
                                <h5 class="mb-0 fw-bold text-primary"><?php echo $data['jumlah_guru']; ?></h5>
                                <small class="text-muted" style="font-size: 0.7rem;">GURU</small>
                            </div>
                            <div class="flex-fill text-center p-2">
                                <h5 class="mb-0 fw-bold text-primary"><?php echo $data['jumlah_tp']; ?></h5>
                                <small class="text-muted" style="font-size: 0.7rem;">TUJUAN PEMB.</small>
                            </div>
                        </div>

                        <!-- Info Kelas (Flexibilitas) -->
                        <div class="p-3 border-bottom">
                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Distribusi Kelas Aktif</small>
                            <div class="mt-1 searchable-kelas">
                                <?php if (!empty($data['kelas_diajarkan'])): ?>
                                    <?php 
                                        $kelasList = explode(', ', $data['kelas_diajarkan']);
                                        $max_show = 8;
                                        $count = 0;
                                        foreach($kelasList as $kelas) {
                                            if($count < $max_show) {
                                                echo '<span class="badge bg-secondary badge-kelas">' . htmlspecialchars($kelas) . '</span>';
                                            }
                                            $count++;
                                        }
                                        if(count($kelasList) > $max_show) {
                                            echo '<span class="badge bg-light text-dark border badge-kelas">+' . (count($kelasList) - $max_show) . ' Lainnya</span>';
                                        }
                                    ?>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger badge-kelas">Belum ada kelas</span>
                                    <small class="d-block text-muted fst-italic" style="font-size: 0.75rem;">Mapel ini belum diatur ke kelas manapun.</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Info Guru -->
                        <div class="p-3">
                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Guru Pengampu</small>
                            <ol class="guru-list mb-0 searchable-guru mt-1">
                                <?php if (!empty($data['guru_pengampu'])) {
                                    echo '<li>' . $data['guru_pengampu'] . '</li>'; 
                                    if ($data['jumlah_guru'] > 3) { echo '<li class="text-muted small fst-italic">... dan ' . ($data['jumlah_guru'] - 3) . ' guru lainnya</li>'; }
                                } else { echo '<li class="text-muted fst-italic" style="list-style: none;">Belum ditentukan</li>'; } ?>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-white p-3">
                        <a href="tp_tampil.php?id_mapel=<?php echo $data['id_mapel']; ?>" class="btn btn-primary btn-sm d-block w-100 mb-2 fw-bold">
                            <i class="bi bi-card-list me-2"></i>Kelola Tujuan Pemb.
                        </a>
                        <div class="d-flex justify-content-between gap-2">
                            <a href="mapel_edit.php?id=<?php echo $data['id_mapel']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                                <i class="bi bi-pencil-fill me-1"></i> Edit
                            </a>
                            <a href="mapel_aksi.php?aksi=hapus&id=<?php echo $data['id_mapel']; ?>" class="btn btn-outline-danger btn-sm flex-fill btn-hapus">
                                <i class="bi bi-trash-fill me-1"></i> Hapus
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        <?php } } else { ?>
            <div class="col-12 text-center py-5">
                <div class="text-muted opacity-50 mb-3">
                    <i class="bi bi-journal-x" style="font-size: 4rem;"></i>
                </div>
                <h5>Belum ada mata pelajaran</h5>
                <p class="text-muted">Silakan tambahkan mata pelajaran master data terlebih dahulu.</p>
                <?php if (!$query) { echo "<p class='text-danger small'>Error Database: Pastikan Anda sudah menambahkan kolom 'kelompok' di tabel mata_pelajaran.</p>"; } ?>
            </div>
        <?php } ?>
    </div>

    <div id="no-results" class="text-center py-5" style="display: none;">
        <i class="bi bi-search fs-1 text-muted opacity-50"></i>
        <h4 class="mt-3">Data tidak ditemukan</h4>
        <p class="text-muted">Coba kata kunci lain (Nama, Kode, Kelompok, Guru, atau Kelas).</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function(){
    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Skrip Pencarian Universal
    $("#search-input").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        var found = false;
        $("#card-list .searchable-card").filter(function() {
            var cardText = $(this).find('.searchable-name').text().toLowerCase() +
                           $(this).find('.searchable-code').text().toLowerCase() +
                           $(this).find('.searchable-guru').text().toLowerCase() +
                           $(this).find('.searchable-kelompok').text().toLowerCase() + 
                           $(this).find('.searchable-kelas').text().toLowerCase();
            
            var isVisible = cardText.indexOf(value) > -1;
            $(this).toggle(isVisible);
            if(isVisible) {
                found = true;
            }
        });
        $("#no-results").toggle(!found);
    });

    // SKRIP KONFIRMASI HAPUS
    $(document).on('click', '.btn-hapus', function(e) {
        e.preventDefault(); 
        var href = $(this).attr('href'); 

        Swal.fire({
            title: 'Hapus Mata Pelajaran?',
            html: "Data mapel dan TP akan dihapus. <br><br><strong>Peringatan:</strong> Data nilai siswa mungkin terpengaruh!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = href;
            }
        });
    });
});

// [BARU] Fungsi Toggle Agama
function toggleStatusAgama(id, nama, isAgama) {
    let action = isAgama ? 'ubah ke Mapel UMUM' : 'ubah ke Mapel AGAMA';
    let newKelompok = isAgama ? 'Umum' : 'Agama';
    let confirmBtnText = isAgama ? 'Ya, Ubah ke Umum' : 'Ya, Ubah ke Agama';
    let icon = isAgama ? 'info' : 'question';

    Swal.fire({
        title: 'Ubah Kelompok Mapel?',
        text: `Anda akan mengubah "${nama}" menjadi kelompok ${newKelompok}.`,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: confirmBtnText,
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect ke mapel_aksi untuk update
            window.location.href = `mapel_aksi.php?aksi=set_kelompok&id=${id}&kelompok=${newKelompok}`;
        }
    });
}
</script>

<?php
// Notifikasi Sukses
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '" . addslashes($_SESSION['pesan']) . "',
        timer: 3000,
        showConfirmButton: false
    });</script>";
    unset($_SESSION['pesan']);
}
// Notifikasi Error
if (isset($_SESSION['pesan_error'])) {
    echo "<script>Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: '" . addslashes($_SESSION['pesan_error']) . "'
    });</script>";
    unset($_SESSION['pesan_error']);
}
include 'footer.php';
?>