<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(1);
include 'header.php';
include 'koneksi.php';

// Ambil semua tahun ajaran untuk filter
$query_ta_all = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran, status FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
$daftar_ta = mysqli_fetch_all($query_ta_all, MYSQLI_ASSOC);

// Tentukan tahun ajaran yang akan ditampilkan
$id_ta_terpilih = $_GET['id_ta'] ?? null;
if ($id_ta_terpilih === null) {
    foreach ($daftar_ta as $ta) {
        if ($ta['status'] == 'Aktif') {
            $id_ta_terpilih = $ta['id_tahun_ajaran'];
            break;
        }
    }
    // Jika tidak ada yang aktif, pilih yang pertama
    if ($id_ta_terpilih === null && !empty($daftar_ta)) {
        $id_ta_terpilih = $daftar_ta[0]['id_tahun_ajaran'];
    }
}


// ===================================================================================
// DATA UNTUK TAB 1: ATUR ALOKASI
// ===================================================================================
// Ambil daftar semua guru untuk dropdown
$query_guru_list = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru WHERE role = 'guru' ORDER BY nama_guru ASC");
$daftar_guru_dropdown = [];
while ($row = mysqli_fetch_assoc($query_guru_list)) {
    $daftar_guru_dropdown[] = $row;
}

// Ambil semua siswa aktif, dikelompokkan per kelas, BERDASARKAN TAHUN AJARAN TERPILIH
$query_siswa_per_kelas = mysqli_prepare($koneksi, "
    SELECT 
        k.id_kelas, k.nama_kelas,
        s.id_siswa, s.nama_lengkap,
        gw.nama_guru AS nama_guru_wali
    FROM kelas k
    JOIN siswa s ON k.id_kelas = s.id_kelas
    LEFT JOIN guru gw ON s.id_guru_wali = gw.id_guru
    WHERE k.id_tahun_ajaran = ? AND s.status_siswa = 'Aktif'
    ORDER BY k.nama_kelas, s.nama_lengkap ASC
");
mysqli_stmt_bind_param($query_siswa_per_kelas, "i", $id_ta_terpilih);
mysqli_stmt_execute($query_siswa_per_kelas);
$result_siswa_per_kelas = mysqli_stmt_get_result($query_siswa_per_kelas);
$data_kelas_siswa = [];
while ($row = mysqli_fetch_assoc($result_siswa_per_kelas)) {
    $data_kelas_siswa[$row['nama_kelas']][] = $row;
}

// ===================================================================================
// DATA UNTUK TAB 2: LIHAT ALOKASI
// ===================================================================================
// Ambil daftar guru yang sudah menjadi guru wali, BERDASARKAN TAHUN AJARAN TERPILIH
$query_guru_wali_info = mysqli_prepare($koneksi, "
    SELECT 
        g.id_guru, g.nama_guru, COUNT(s.id_siswa) as jumlah_binaan
    FROM guru g
    JOIN siswa s ON g.id_guru = s.id_guru_wali
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.status_siswa = 'Aktif' AND k.id_tahun_ajaran = ?
    GROUP BY g.id_guru, g.nama_guru
    ORDER BY g.nama_guru ASC
");
mysqli_stmt_bind_param($query_guru_wali_info, "i", $id_ta_terpilih);
mysqli_stmt_execute($query_guru_wali_info);
$result_guru_wali_info = mysqli_stmt_get_result($query_guru_wali_info);

$data_guru_wali = [];
while ($row = mysqli_fetch_assoc($result_guru_wali_info)) {
    // Ambil detail siswa untuk setiap guru wali
    $id_guru = $row['id_guru'];
    $q_detail_siswa = mysqli_prepare($koneksi, "
        SELECT s.nama_lengkap, k.nama_kelas 
        FROM siswa s 
        JOIN kelas k ON s.id_kelas = k.id_kelas
        WHERE s.id_guru_wali = ? AND s.status_siswa = 'Aktif' AND k.id_tahun_ajaran = ?
        ORDER BY k.nama_kelas, s.nama_lengkap ASC
    ");
    mysqli_stmt_bind_param($q_detail_siswa, "ii", $id_guru, $id_ta_terpilih);
    mysqli_stmt_execute($q_detail_siswa);
    $res_detail_siswa = mysqli_stmt_get_result($q_detail_siswa);
    $detail_siswa = [];
    while ($siswa_row = mysqli_fetch_assoc($res_detail_siswa)) {
        $detail_siswa[] = $siswa_row;
    }
    $row['detail_siswa'] = $detail_siswa;
    $data_guru_wali[] = $row;
}
?>

<style>
    /* Styling UI/UX Baru */
    :root {
        --primary-color: #4f46e5;
        --primary-soft: #e0e7ff;
        --secondary-color: #4338ca;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --bg-light: #f8fafc;
    }

    body {
        background-color: #f1f5f9;
    }

    /* Hero Header Card */
    .hero-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 1.25rem;
        border-left: 6px solid var(--primary-color);
        overflow: hidden;
    }

    .hero-title {
        color: var(--text-dark);
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    .hero-illustration {
        max-height: 140px;
        filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));
        animation: float-up-down 3s ease-in-out infinite;
    }

    @keyframes float-up-down {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
        100% { transform: translateY(0px); }
    }

    /* Custom Tabs */
    .custom-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1.5rem;
        gap: 1rem;
    }
    .custom-tabs .nav-link {
        color: var(--text-muted);
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        border: none;
        border-bottom: 3px solid transparent;
        border-radius: 0;
        transition: all 0.3s ease;
        background: transparent;
    }
    .custom-tabs .nav-link:hover {
        color: var(--primary-color);
    }
    .custom-tabs .nav-link.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
        background: transparent;
    }

    /* Class Accordion (Tab 1) */
    .class-accordion .accordion-item {
        border: none;
        background: #fff;
        border-radius: 1rem !important;
        margin-bottom: 1rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .class-accordion .accordion-button {
        padding: 1.25rem 1.5rem;
        font-weight: 700;
        color: var(--text-dark);
        background-color: #fff;
        box-shadow: none !important;
    }
    .class-accordion .accordion-button:not(.collapsed) {
        background-color: #fafafa;
        color: var(--primary-color);
        border-bottom: 1px solid #f1f5f9;
    }
    .class-accordion .accordion-button::after {
        background-size: 1.2rem;
    }

    /* Modern Table */
    .modern-table thead th {
        background-color: #f8fafc;
        color: var(--text-muted);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
        padding: 1rem;
    }
    .modern-table tbody td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .modern-table tbody tr:hover {
        background-color: #f8fafc;
    }

    /* Action Box */
    .action-box {
        background: #fff;
        border-radius: 1rem;
        border: 2px dashed #cbd5e1;
        transition: border-color 0.3s;
    }
    .action-box:hover {
        border-color: var(--primary-color);
    }

    /* Guru Grid Card (Tab 2) */
    .guru-card {
        border-radius: 1rem;
        border: none;
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
    }
    .guru-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.05) !important;
    }
    .guru-avatar {
        width: 55px;
        height: 55px;
        background: linear-gradient(135deg, var(--primary-soft), #fff);
        color: var(--primary-color);
        font-weight: 800;
        font-size: 1.5rem;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .student-list-container {
        max-height: 200px;
        overflow-y: auto;
    }
    /* Scrollbar minimalis */
    .student-list-container::-webkit-scrollbar { width: 6px; }
    .student-list-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .student-list-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .student-list-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div class="container-fluid py-4">

    <!-- Hero Header -->
    <div class="card border-0 shadow-sm hero-card mb-4">
        <div class="row g-0">
            <div class="col-md-8 p-4 p-lg-5 d-flex flex-column justify-content-center">
                <h1 class="hero-title mb-2">Penugasan Guru Wali</h1>
                <p class="lead text-muted mb-0">Atur dan alokasikan siswa ke Guru Wali masing-masing dengan mudah dan terstruktur.</p>
            </div>
            <div class="col-md-4 d-none d-md-flex align-items-center justify-content-center p-4">
                <!-- Ilustrasi Edukasi/Team -->
                <img src="https://cdn-icons-png.flaticon.com/512/3281/3281323.png" alt="Ilustrasi Penugasan" class="hero-illustration">
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form action="admin_penetapan_guru_wali.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-6 col-lg-5">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary-soft text-primary p-3 rounded-3 me-3 d-none d-sm-block">
                            <i class="bi bi-calendar3 fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <label for="id_ta" class="form-label fw-bold text-dark mb-1">Tahun Ajaran Aktif</label>
                            <select name="id_ta" id="id_ta" class="form-select form-select-lg border-0 bg-light fw-semibold" onchange="this.form.submit()" style="cursor: pointer; font-size: 1rem;">
                                <?php foreach($daftar_ta as $ta): ?>
                                <option value="<?php echo $ta['id_tahun_ajaran']; ?>" <?php if($id_ta_terpilih == $ta['id_tahun_ajaran']) echo 'selected'; ?>>
                                    <?php echo $ta['tahun_ajaran'] . ($ta['status'] == 'Aktif' ? ' (Tahun Berjalan)' : ''); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <!-- Custom Tabs Navigation -->
            <ul class="nav custom-tabs px-4 pt-3" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="atur-tab" data-bs-toggle="tab" data-bs-target="#atur" type="button" role="tab">
                        <i class="bi bi-person-lines-fill me-2"></i>Atur Alokasi Siswa
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lihat-tab" data-bs-toggle="tab" data-bs-target="#lihat" type="button" role="tab">
                        <i class="bi bi-grid-fill me-2"></i>Lihat Data per Guru
                    </button>
                </li>
            </ul>

            <div class="tab-content p-4" id="myTabContent">

                <!-- TAB 1: ATUR ALOKASI -->
                <div class="tab-pane fade show active" id="atur" role="tabpanel">
                    <form action="proses_penetapan_guru_wali.php" method="POST">
                        <input type="hidden" name="id_ta_redirect" value="<?php echo $id_ta_terpilih; ?>">

                        <div class="alert bg-primary-soft text-primary border-0 rounded-3 mb-4 d-flex align-items-center">
                            <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                            <div>
                                <strong>Panduan:</strong> Buka akordion kelas, centang siswa yang diinginkan, pilih guru di panel bawah, lalu klik "Tetapkan Pilihan".
                            </div>
                        </div>

                        <?php if (empty($data_kelas_siswa)): ?>
                            <div class="text-center py-5">
                                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="Kosong" width="100" class="opacity-50 mb-3">
                                <h5 class="text-muted fw-bold">Belum Ada Data Siswa</h5>
                            </div>
                        <?php else: ?>
                            <div class="accordion class-accordion" id="kelasAccordion">
                                <?php foreach ($data_kelas_siswa as $nama_kelas => $siswas): ?>
                                <div class="accordion-item shadow-sm">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo str_replace([' ', '/'], '', $nama_kelas); ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                                <span class="fs-5">Kelas <?php echo htmlspecialchars($nama_kelas); ?></span>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-3 py-2">
                                                    <i class="bi bi-people-fill me-1"></i> <?php echo count($siswas); ?> Siswa
                                                </span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse-<?php echo str_replace([' ', '/'], '', $nama_kelas); ?>" class="accordion-collapse collapse" data-bs-parent="#kelasAccordion">
                                        <div class="accordion-body p-0">
                                            <div class="table-responsive">
                                                <table class="table modern-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 5%;" class="text-center">
                                                                <input type="checkbox" class="form-check-input select-all-in-class shadow-none" style="transform: scale(1.2);">
                                                            </th>
                                                            <th style="width: 45%;">Nama Lengkap Siswa</th>
                                                            <th style="width: 50%;">Status Guru Wali Saat Ini</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($siswas as $siswa): ?>
                                                        <tr>
                                                            <td class="text-center">
                                                                <input type="checkbox" name="id_siswa[]" value="<?php echo $siswa['id_siswa']; ?>" class="form-check-input siswa-checkbox shadow-none" style="transform: scale(1.2);">
                                                            </td>
                                                            <td class="fw-semibold text-dark">
                                                                <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($siswa['nama_guru_wali']): ?>
                                                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                                                                        <i class="bi bi-person-check-fill me-1"></i> <?php echo htmlspecialchars($siswa['nama_guru_wali']); ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">
                                                                        <i class="bi bi-exclamation-circle me-1"></i> Belum Dialokasikan
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Floating/Elevated Action Box -->
                            <div class="action-box p-4 mt-4 shadow-sm">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-check-all text-primary me-2"></i>Aksi Massal</h5>
                                        <div class="form-floating">
                                            <select id="id_guru_wali_massal" name="id_guru_wali" class="form-select border-primary shadow-none" required>
                                                <option value="" disabled selected>-- Klik untuk memilih Guru Wali --</option>
                                                <option value="0" class="text-danger fw-bold">❌ [ Hapus / Lepaskan Guru Wali dari Siswa ]</option>
                                                <?php foreach ($daftar_guru_dropdown as $guru): ?>
                                                    <option value="<?php echo $guru['id_guru']; ?>"><?php echo htmlspecialchars($guru['nama_guru']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="id_guru_wali_massal">Pilih Guru untuk siswa yang dicentang</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-end mt-4 mt-md-0">
                                        <button type="submit" name="tetapkan_massal" class="btn btn-primary btn-lg w-100 rounded-3 shadow-sm py-3 fw-bold">
                                            <i class="bi bi-save2-fill me-2"></i>Tetapkan Pilihan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- TAB 2: LIHAT ALOKASI -->
                <div class="tab-pane fade" id="lihat" role="tabpanel">
                    <?php if (empty($data_guru_wali)): ?>
                        <div class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="Kosong" width="100" class="opacity-50 mb-3">
                            <h5 class="text-muted fw-bold">Belum Ada Penugasan</h5>
                            <p class="text-muted">Belum ada satupun guru yang ditetapkan sebagai Guru Wali.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($data_guru_wali as $guru): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card guru-card shadow-sm border p-3">
                                    <!-- Header Card -->
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <div class="guru-avatar me-3">
                                            <?php echo substr(htmlspecialchars($guru['nama_guru']), 0, 1); ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($guru['nama_guru']); ?></h5>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2 py-1">
                                                <i class="bi bi-people-fill me-1"></i> <?php echo $guru['jumlah_binaan']; ?> Siswa Binaan
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Body Card (List Siswa) -->
                                    <div class="student-list-container pe-2">
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($guru['detail_siswa'] as $siswa_binaan): ?>
                                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                                                <div class="text-truncate fw-medium text-secondary" style="max-width: 70%; font-size: 0.95rem;">
                                                    • <?php echo htmlspecialchars($siswa_binaan['nama_lengkap']); ?>
                                                </div>
                                                <span class="badge bg-light text-dark border px-2 py-1">
                                                    Kls <?php echo htmlspecialchars($siswa_binaan['nama_kelas']); ?>
                                                </span>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logic JS tetap dipertahankan
    const selectAllCheckboxes = document.querySelectorAll('.select-all-in-class');
    selectAllCheckboxes.forEach(function(headerCheckbox) {
        headerCheckbox.addEventListener('change', function(e) {
            const accordionBody = e.target.closest('table').querySelector('tbody');
            const studentCheckboxes = accordionBody.querySelectorAll('.siswa-checkbox');
            studentCheckboxes.forEach(function(checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>