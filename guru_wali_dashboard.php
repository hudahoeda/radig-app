<?php
include 'header.php';
include 'koneksi.php';

// Pastikan yang akses adalah guru
if ($_SESSION['role'] != 'guru') {
    die("Akses tidak diizinkan. Halaman ini khusus untuk guru.");
}

$id_guru_login = $_SESSION['id_guru'];

// Ambil siswa bimbingan dari guru yang login
$query_siswa = mysqli_query($koneksi, "
    SELECT 
        s.id_siswa, s.nis, s.nama_lengkap, s.foto_siswa,
        k.nama_kelas, 
        (SELECT COUNT(*) FROM catatan_guru_wali c WHERE c.id_siswa = s.id_siswa) as jumlah_catatan
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE s.id_guru_wali = $id_guru_login AND s.status_siswa = 'Aktif'
    ORDER BY k.nama_kelas, s.nama_lengkap ASC
");

$daftar_siswa = mysqli_fetch_all($query_siswa, MYSQLI_ASSOC);
?>

<style>
    /* Premium Mobile UI Styles */
    body {
        background-color: #f4f7fe;
    }

    /* Header Melengkung (Gojek-Style) */
    .mobile-header-wrapper {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 1.5rem 4rem 1.5rem;
        border-bottom-left-radius: 2rem;
        border-bottom-right-radius: 2rem;
        color: white;
        margin-bottom: -2.5rem; /* Menarik konten bawah naik ke atas untuk efek floating */
        position: relative;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .mobile-header-wrapper h1 {
        font-weight: 700;
        font-size: 1.6rem;
        margin-bottom: 0.2rem;
    }
    .mobile-header-wrapper p {
        opacity: 0.85;
        font-size: 0.9rem;
    }

    /* Floating Search Bar */
    .floating-search-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 1rem;
        padding: 0.75rem 1.25rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        margin: 0 1rem 1.5rem 1rem;
        position: relative;
        z-index: 10;
    }
    .floating-search-card .bi-search {
        color: var(--primary-color);
        font-size: 1.2rem;
        margin-right: 10px;
    }
    .floating-search-card input {
        border: none;
        background: transparent;
        width: 100%;
        font-weight: 500;
        color: #333;
        outline: none;
    }
    .floating-search-card input:focus {
        box-shadow: none;
    }

    /* Premium List Item Card (M-Banking Style) */
    .student-list-container {
        padding: 0 1rem;
    }
    .student-premium-card {
        background: #fff;
        border-radius: 1rem;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        border: 1px solid rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        transition: transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.2s ease;
        text-decoration: none;
        color: inherit;
        position: relative;
        overflow: hidden;
    }
    .student-premium-card:active, .student-premium-card:hover {
        transform: scale(0.98);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        color: inherit;
    }

    /* Ripple Effect on click */
    .student-premium-card::after {
        content: "";
        display: block;
        position: absolute;
        width: 100%; height: 100%;
        top: 0; left: 0;
        pointer-events: none;
        background-image: radial-gradient(circle, #000 10%, transparent 10.01%);
        background-repeat: no-repeat;
        background-position: 50%;
        transform: scale(10, 10);
        opacity: 0;
        transition: transform .5s, opacity 1s;
    }
    .student-premium-card:active::after {
        transform: scale(0, 0);
        opacity: .1;
        transition: 0s;
    }

    /* Avatar */
    .student-premium-avatar {
        position: relative;
        margin-right: 1.2rem;
    }
    .student-premium-avatar img {
        width: 65px;
        height: 65px;
        object-fit: cover;
        border-radius: 1rem; /* Rounded rect insted of circle for modern look */
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        background: #e9ecef;
    }
    .student-premium-avatar .icon-placeholder {
        width: 65px;
        height: 65px;
        border-radius: 1rem;
        background: linear-gradient(135deg, #e9ecef, #dee2e6);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #6c757d;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* Notification Badge on Avatar */
    .badge-catatan {
        position: absolute;
        top: -5px;
        right: -5px;
        background: var(--danger-color, #dc3545);
        color: white;
        font-size: 0.7rem;
        font-weight: bold;
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    /* Info */
    .student-premium-info {
        flex-grow: 1;
        overflow: hidden;
    }
    .student-premium-info h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1.1rem;
        color: #2c3e50;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .student-premium-info p {
        margin: 0;
        font-size: 0.85rem;
        color: #6c757d;
        font-weight: 500;
        margin-top: 3px;
    }

    /* Arrow Icon */
    .student-premium-arrow {
        color: #adb5bd;
        font-size: 1.5rem;
        margin-left: 10px;
        transition: transform 0.2s;
    }
    .student-premium-card:hover .student-premium-arrow {
        transform: translateX(5px);
        color: var(--primary-color);
    }

    /* Summary Stats */
    .stats-container {
        display: flex;
        gap: 1rem;
        padding: 0 1rem;
        margin-bottom: 2rem;
        position: relative;
        z-index: 10;
    }
    .stat-box {
        flex: 1;
        background: #fff;
        border-radius: 1rem;
        padding: 1rem;
        text-align: center;
        box-shadow: 0 6px 20px rgba(0,0,0,0.04);
    }
    .stat-box h3 {
        margin: 0;
        font-weight: 800;
        color: var(--primary-color);
        font-size: 1.5rem;
    }
    .stat-box p {
        margin: 0;
        font-size: 0.75rem;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
</style>

<div class="mobile-header-wrapper">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Ruang Guru Wali</h1>
            <p>Bimbingan & Portofolio Siswa</p>
        </div>
        <i class="bi bi-person-rolodex fs-1 opacity-50"></i>
    </div>
</div>

<div class="floating-search-card">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Cari nama atau NIS siswa...">
</div>

<?php
// Hitung total catatan
$total_catatan = 0;
foreach($daftar_siswa as $s) {
    $total_catatan += $s['jumlah_catatan'];
}
?>

<div class="stats-container">
    <div class="stat-box">
        <h3><?php echo count($daftar_siswa); ?></h3>
        <p>Siswa</p>
    </div>
    <div class="stat-box">
        <h3><?php echo $total_catatan; ?></h3>
        <p>Total Catatan</p>
    </div>
</div>

<div class="student-list-container pb-5" id="student-list-container">
    <h6 class="text-muted fw-bold mb-3 ms-2 text-uppercase" style="font-size:0.8rem; letter-spacing:1px;">Daftar Bimbingan</h6>

    <div class="row g-2">
    <?php if (!empty($daftar_siswa)): ?>
        <?php foreach ($daftar_siswa as $siswa): ?>
            <!-- Membungkus dengan kolom agar di desktop tetap rapi (grid), di HP (1 kolom) -->
            <div class="col-12 col-md-6 col-lg-4 student-item">
                <a href="guru_wali_catatan_siswa.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="student-premium-card text-decoration-none">

                    <div class="student-premium-avatar">
                        <?php if (!empty($siswa['foto_siswa']) && file_exists('uploads/foto_siswa/' . $siswa['foto_siswa'])): ?>
                            <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto" onerror="this.onerror=null; this.outerHTML='<div class=\'icon-placeholder\'><i class=\'bi bi-person-fill\'></i></div>';">
                        <?php else: ?>
                            <div class="icon-placeholder"><i class="bi bi-person-fill"></i></div>
                        <?php endif; ?>

                        <?php if($siswa['jumlah_catatan'] > 0): ?>
                            <div class="badge-catatan"><?php echo $siswa['jumlah_catatan']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="student-premium-info">
                        <h5 class="student-name"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></h5>
                        <p><i class="bi bi-mortarboard me-1"></i> Kelas <?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?> &bull; NIS: <?php echo htmlspecialchars($siswa['nis']); ?></p>
                    </div>

                    <div class="student-premium-arrow">
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5">
            <div class="d-inline-block p-4 rounded-circle bg-white shadow-sm mb-3">
                <i class="bi bi-person-x fs-1 text-muted"></i>
            </div>
            <h5 class="fw-bold text-dark">Belum Ada Siswa</h5>
            <p class="text-muted">Anda belum ditugaskan membimbing siswa satupun.</p>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const studentContainer = document.getElementById('student-list-container');
    const studentItems = studentContainer.getElementsByClassName('student-item');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        for (let i = 0; i < studentItems.length; i++) {
            let nameElement = studentItems[i].querySelector('.student-name');
            let infoElement = studentItems[i].querySelector('p'); // Get the NIS/Kelas string
            if (nameElement && infoElement) {
                let textValue = nameElement.textContent + " " + infoElement.textContent;
                if (textValue.toLowerCase().indexOf(filter) > -1) {
                    studentItems[i].style.display = "";
                } else {
                    studentItems[i].style.display = "none";
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>
