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
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }

    .student-card {
        transition: all 0.2s ease-in-out;
        border: 1px solid var(--border-color);
    }
    .student-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: var(--primary-color);
    }
    .student-card .card-body {
        display: flex;
        align-items: center;
    }
    .student-avatar img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .student-avatar .icon-placeholder {
        font-size: 60px;
        color: #adb5bd;
    }
    .student-info {
        margin-left: 1rem;
        overflow: hidden;
    }
    .student-info .student-name {
        font-weight: 600;
        font-size: 1.1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .student-info .text-muted {
        font-size: 0.9rem;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Panel Guru Wali</h1>
        <p class="lead mb-0 opacity-75">Kelola dan pantau perkembangan siswa bimbingan Anda.</p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Siswa Bimbingan Saya (<?php echo count($daftar_siswa); ?> siswa)</h5>
            <div class="w-50" style="max-width: 300px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama siswa...">
            </div>
        </div>
    </div>

    <div class="row g-4" id="student-list-container">
        <?php if (!empty($daftar_siswa)): ?>
            <?php foreach ($daftar_siswa as $siswa): ?>
                <div class="col-xl-4 col-md-6 student-item">
                    <div class="card h-100 student-card">
                        <div class="card-body">
                            <div class="student-avatar">
                                <?php if (!empty($siswa['foto_siswa']) && file_exists('uploads/foto_siswa/' . $siswa['foto_siswa'])): ?>
                                    <img src="uploads/foto_siswa/<?php echo htmlspecialchars($siswa['foto_siswa']); ?>" alt="Foto Siswa">
                                <?php else: ?>
                                    <i class="bi bi-person-circle icon-placeholder"></i>
                                <?php endif; ?>
                            </div>
                            <div class="student-info">
                                <div class="student-name"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                <div class="text-muted">
                                    Kelas: <?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'N/A'); ?> | NIS: <?php echo htmlspecialchars($siswa['nis']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                            <span class="badge bg-primary rounded-pill">
                                <i class="bi bi-journal-text me-1"></i>
                                <?php echo $siswa['jumlah_catatan']; ?> Catatan
                            </span>
                            <a href="guru_wali_catatan_siswa.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-folder2-open me-1"></i> Buka Portofolio
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card card-body text-center py-5">
                    <i class="bi bi-person-x fs-1 text-muted"></i>
                    <h4 class="mt-3">Belum Ada Siswa</h4>
                    <p class="text-muted">Anda saat ini tidak ditugaskan sebagai Guru Wali untuk siswa manapun.</p>
                </div>
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
            if (nameElement) {
                let textValue = nameElement.textContent || nameElement.innerText;
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
