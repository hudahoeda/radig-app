<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas
if ($_SESSION['role'] !== 'guru') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Wali Kelas yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil info tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];

// Ambil data kelas yang diampu
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);

if (!$kelas) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Anda tidak terdaftar sebagai wali kelas pada tahun ajaran aktif.</div></div>";
    include 'footer.php';
    exit;
}
$id_kelas = $kelas['id_kelas'];

// Ambil semua siswa di kelas ini
$q_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas ORDER BY nama_lengkap ASC");
$daftar_siswa = mysqli_fetch_all($q_siswa, MYSQLI_ASSOC);

// Ambil semua ekstrakurikuler
$q_ekskul_list = mysqli_query($koneksi, "SELECT id_ekskul, nama_ekskul FROM ekstrakurikuler WHERE id_tahun_ajaran = $id_tahun_ajaran ORDER BY nama_ekskul ASC");
$daftar_ekskul = mysqli_fetch_all($q_ekskul_list, MYSQLI_ASSOC);

// ==================================================================================
// Query Status Locked (Sama seperti sebelumnya)
// ==================================================================================
$query_status = "
    SELECT 
        ep.id_siswa, 
        ep.id_ekskul,
        (SELECT COUNT(*) FROM ekskul_penilaian pn 
         WHERE pn.id_peserta_ekskul = ep.id_peserta_ekskul 
         AND pn.nilai IS NOT NULL AND pn.nilai != '') as jum_nilai,
        (SELECT COUNT(*) FROM ekskul_kehadiran kh 
         WHERE kh.id_peserta_ekskul = ep.id_peserta_ekskul 
         AND kh.total_pertemuan > 0) as jum_hadir,
        (
            SELECT GROUP_CONCAT(CONCAT('<li>', t.deskripsi_tujuan, ': <b>', pn.nilai, '</b></li>') SEPARATOR '') 
            FROM ekskul_penilaian pn 
            JOIN ekskul_tujuan t ON pn.id_tujuan_ekskul = t.id_tujuan_ekskul
            WHERE pn.id_peserta_ekskul = ep.id_peserta_ekskul AND pn.nilai IS NOT NULL
        ) as detail_nilai
    FROM ekskul_peserta ep 
    JOIN siswa s ON ep.id_siswa = s.id_siswa 
    WHERE s.id_kelas = $id_kelas
";

$q_peserta = mysqli_query($koneksi, $query_status);
$peserta_data = [];
while ($p = mysqli_fetch_assoc($q_peserta)) {
    $peserta_data[$p['id_siswa']][$p['id_ekskul']] = [
        'terdaftar' => true,
        'locked' => ($p['jum_nilai'] > 0 || $p['jum_hadir'] > 0), 
        'detail_nilai' => $p['detail_nilai'] ?? 'Belum ada detail nilai.',
        'jum_hadir' => $p['jum_hadir']
    ];
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }
    .page-header h1 { font-weight: 700; }

    .table-responsive {
        max-height: 70vh; 
    }
    .table-ekskul thead th {
        position: -webkit-sticky;
        position: sticky;
        top: 0;
        z-index: 3;
        background-color: #f8f9fa;
    }
    .table-ekskul .sticky-col {
        position: -webkit-sticky;
        position: sticky;
        left: 0;
        z-index: 2;
        background-color: #fff;
    }
    .table-hover > tbody > tr:hover > * {
        background-color: #e0f2f1 !important;
    }
    .table-ekskul .sticky-col-header {
        z-index: 4 !important;
    }
    .table-ekskul, .table-ekskul th, .table-ekskul td {
        border-left: none;
        border-right: none;
    }
    .table-ekskul td, .table-ekskul th {
        padding: 1rem 0.75rem;
        white-space: nowrap; /* Mencegah tombol turun baris */
    }
    .form-check-input {
        transform: scale(1.2);
        cursor: pointer;
    }
    .locked-checkbox {
        background-color: #e9ecef;
        opacity: 0.6;
        cursor: not-allowed;
    }
    /* Style untuk tombol aksi kecil */
    .btn-action-mini {
        width: 24px;
        height: 24px;
        padding: 0;
        line-height: 24px;
        text-align: center;
        border-radius: 50%;
        font-size: 0.7rem;
        display: inline-block;
        margin-left: 3px;
        vertical-align: middle;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Kelola Ekstrakurikuler Siswa</h1>
        <p class="lead mb-0 opacity-75">Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></p>
    </div>

    <div class="alert alert-info shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Info Sistem:</strong> 
        <ul class="mb-0 ps-3">
            <li>Kotak centang <b>terkunci (abu-abu)</b> menandakan siswa sudah memiliki nilai/presensi.</li>
            <li>Gunakan tombol <span class="badge bg-danger rounded-circle"><i class="bi bi-trash"></i></span> untuk <b>menghapus paksa</b> siswa dari ekskul (Nilai akan ikut terhapus).</li>
        </ul>
    </div>

    <form action="walikelas_aksi.php?aksi=simpan_pendaftaran_ekskul" method="POST">
        <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="bi bi-person-check-fill me-2" style="color: var(--primary-color);"></i>
                    Daftar Keikutsertaan Siswa
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-ekskul align-middle">
                        <thead class="text-center">
                            <tr>
                                <th class="sticky-col sticky-col-header text-start">Nama Siswa</th>
                                <?php foreach ($daftar_ekskul as $ekskul): ?>
                                    <th>
                                        <?php echo htmlspecialchars($ekskul['nama_ekskul']); ?><br>
                                        <input class="form-check-input mt-1" type="checkbox" title="Pilih Semua di Kolom Ini" data-ekskul-id="<?php echo $ekskul['id_ekskul']; ?>">
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar_siswa as $siswa):
                                $id_siswa = $siswa['id_siswa'];
                            ?>
                            <tr>
                                <td class="sticky-col fw-bold">
                                    <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                                </td>
                                <?php foreach ($daftar_ekskul as $ekskul):
                                    $id_ekskul = $ekskul['id_ekskul'];
                                    
                                    // Cek status data
                                    $data_status = $peserta_data[$id_siswa][$id_ekskul] ?? null;
                                    $is_checked = isset($data_status['terdaftar']);
                                    $is_locked = isset($data_status['locked']) && $data_status['locked'];
                                    
                                    $checked_attr = $is_checked ? 'checked' : '';
                                    $disabled_attr = $is_locked ? 'disabled' : '';
                                    $class_locked = $is_locked ? 'locked-checkbox' : '';
                                ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <!-- Checkbox -->
                                        <div class="form-check m-0">
                                            <?php if ($is_locked): ?>
                                                <input type="hidden" name="ekskul[<?php echo $id_siswa; ?>][]" value="<?php echo $id_ekskul; ?>">
                                            <?php endif; ?>

                                            <input class="form-check-input <?php echo $class_locked; ?>"
                                                   type="checkbox"
                                                   name="ekskul[<?php echo $id_siswa; ?>][]"
                                                   value="<?php echo $id_ekskul; ?>"
                                                   data-member-of-ekskul="<?php echo $id_ekskul; ?>"
                                                   <?php echo $checked_attr; ?>
                                                   <?php echo $disabled_attr; ?>>
                                        </div>
                                        
                                        <!-- Tombol Aksi Tambahan untuk Data Terkunci -->
                                        <?php if ($is_locked): ?>
                                            <!-- Info Nilai -->
                                            <button type="button" class="btn btn-info text-white btn-action-mini" 
                                                    onclick="showDetailNilai('<?php echo addslashes($siswa['nama_lengkap']); ?>', '<?php echo addslashes($ekskul['nama_ekskul']); ?>', `<?php echo $data_status['detail_nilai']; ?>`, <?php echo $data_status['jum_hadir']; ?>)"
                                                    title="Lihat Detail Nilai">
                                                <i class="bi bi-eye-fill"></i>
                                            </button>

                                            <!-- [BARU] Tombol Hapus Paksa -->
                                            <a href="walikelas_aksi.php?aksi=hapus_peserta_ekskul&id_siswa=<?php echo $id_siswa; ?>&id_ekskul=<?php echo $id_ekskul; ?>" 
                                               class="btn btn-danger btn-action-mini"
                                               onclick="konfirmasiHapus(event, this.href, '<?php echo addslashes($siswa['nama_lengkap']); ?>', '<?php echo addslashes($ekskul['nama_ekskul']); ?>')"
                                               title="Hapus Peserta & Nilai (Reset)">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-4 d-flex justify-content-end">
            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Pendaftaran</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const headerCheckboxes = document.querySelectorAll('thead input[type="checkbox"][data-ekskul-id]');
    headerCheckboxes.forEach(headerCheckbox => {
        headerCheckbox.addEventListener('change', function() {
            const ekskulId = this.getAttribute('data-ekskul-id');
            const isChecked = this.checked;
            const memberCheckboxes = document.querySelectorAll(`tbody input[type="checkbox"][data-member-of-ekskul="${ekskulId}"]:not(:disabled)`);
            memberCheckboxes.forEach(memberCheckbox => {
                memberCheckbox.checked = isChecked;
            });
        });
    });
});

// Fungsi Popup Detail Nilai
function showDetailNilai(namaSiswa, namaEkskul, detailNilaiHtml, jumHadir) {
    let htmlContent = `
        <div class="text-start">
            <p>Siswa ini sudah memiliki data penilaian/kehadiran.</p>
            <hr class="my-2">
            <p class="mb-1"><strong>Kehadiran:</strong> ${jumHadir > 0 ? jumHadir + ' pertemuan' : 'Belum ada data'}</p>
            <p class="mb-1"><strong>Rincian Nilai:</strong></p>
            <ul class="mb-0 ps-3">${detailNilaiHtml || '<li>Belum ada rincian nilai</li>'}</ul>
        </div>
    `;
    Swal.fire({
        title: `Data: ${namaEkskul}`,
        html: htmlContent,
        icon: 'info',
        confirmButtonText: 'Tutup'
    });
}

// [BARU] Fungsi Konfirmasi Hapus
function konfirmasiHapus(e, url, namaSiswa, namaEkskul) {
    e.preventDefault(); // Mencegah link langsung jalan
    
    Swal.fire({
        title: 'Hapus Peserta & Nilai?',
        html: `Anda akan menghapus <b>${namaSiswa}</b> dari ekskul <b>${namaEkskul}</b>.<br><br><span class="text-danger fw-bold">PERINGATAN: Seluruh Data Nilai dan Kehadiran siswa ini di ekskul tersebut akan DIHAPUS PERMANEN!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus Permanen!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>

<?php
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire('Berhasil!','" . addslashes($_SESSION['pesan']) . "','success');</script>";
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>