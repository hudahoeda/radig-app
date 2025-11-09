<?php
include 'header.php';
include 'koneksi.php';

// Validasi peran
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil data aktif
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta_aktif)['id_tahun_ajaran'] ?? 0;
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil data kelas
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);
$id_kelas = $kelas['id_kelas'] ?? 0;

$siswa_list = [];
if ($id_kelas > 0) {
    // Ambil daftar siswa dan status rapor mereka
    $query_siswa = "
        SELECT 
            s.id_siswa, s.nisn, s.nama_lengkap, 
            r.status AS status_rapor 
        FROM siswa s
        LEFT JOIN rapor r ON s.id_siswa = r.id_siswa 
            AND r.id_tahun_ajaran = ? AND r.semester = ?
        WHERE s.id_kelas = ? AND s.status_siswa = 'Aktif' 
        ORDER BY s.nama_lengkap ASC
    ";
    $stmt_siswa = mysqli_prepare($koneksi, $query_siswa);
    mysqli_stmt_bind_param($stmt_siswa, "iii", $id_tahun_ajaran_aktif, $semester_aktif, $id_kelas);
    mysqli_stmt_execute($stmt_siswa);
    $result_siswa = mysqli_stmt_get_result($stmt_siswa);
    while($row = mysqli_fetch_assoc($result_siswa)){
        $siswa_list[] = $row;
    }
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Cetak Rapor & Leger</h1>
                <p class="lead mb-0 opacity-75">Kelas: <?php echo htmlspecialchars($kelas['nama_kelas'] ?? 'Anda tidak menjadi wali kelas'); ?></p>
            </div>
             <div class="btn-group mt-3 mt-sm-0">
                <!-- [MODIFIKASI] Tombol ini sekarang memicu Modal -->
                <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modalPilihLeger">
                    <i class="bi bi-table me-2"></i>Cetak Leger Kelas
                </button>
                <a href="rapor_identitas_sekolah.php" class="btn btn-outline-light" target="_blank">
                    <i class="bi bi-building me-2"></i>Cetak Halaman Muka
                </a>
            </div>
        </div>
    </div>

    <?php if ($id_kelas > 0): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <h5 class="fw-bold"><span class="badge bg-primary me-2">Langkah 1</span>Manajemen Status</h5>
                    <p class="text-muted">Gunakan tombol di bawah ini untuk memfinalisasi (mengunci) rapor sebelum cetak, atau membatalkannya (membuka kunci) untuk diedit kembali.</p>
                    
                    <!-- [MODIFIKASI] Tombol Finalisasi sekarang menggunakan SweetAlert untuk konfirmasi -->
                    <button type="button" class="btn btn-primary" id="btn-finalisasi-semua">
                        <i class="bi bi-check2-circle me-2"></i>Finalisasi Semua
                    </button>
                    
                    <!-- [MODIFIKASI] Tombol Batal Finalisasi sekarang menggunakan SweetAlert untuk konfirmasi -->
                    <button type="button" class="btn btn-warning ms-2" id="btn-batalkan-finalisasi">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Batalkan Finalisasi
                    </button>
                    
                    <!-- Form tersembunyi untuk submit -->
                    <form id="form-finalisasi" action="walikelas_aksi.php?aksi=finalisasi_semua" method="POST" class="d-none"></form>
                    <form id="form-batal" action="walikelas_aksi.php?aksi=batalkan_finalisasi_semua" method="POST" class="d-none"></form>
                </div>

                <div class="col-lg-7">
                    <h5 class="fw-bold"><span class="badge bg-primary me-2">Langkah 2</span>Cetak Massal</h5>
                    <p class="text-muted">Pilih siswa pada kolom checkbox yang sesuai di tabel, lalu klik tombol cetak di bawah ini.</p>
                     <div class="btn-group w-100">
                        <button type="button" class="btn btn-outline-secondary" onclick="prosesCetakMassal('sampul')"><i class="bi bi-book-half me-2"></i>Cetak Sampul Terpilih</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="prosesCetakMassal('identitas')"><i class="bi bi-person-badge me-2"></i>Cetak Identitas Terpilih</button>
                        <button type="button" class="btn btn-success" onclick="prosesCetakMassal('rapor')"><i class="bi bi-file-earmark-pdf-fill me-2"></i>Cetak Rapor Terpilih</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
             <h5 class="mb-0"><i class="bi bi-people-fill me-2" style="color: var(--primary-color);"></i>Daftar Siswa & Opsi Cetak</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-center table-light">
                        <tr>
                            <th class="text-start ps-3">Nama Siswa</th>
                            <th>Status Rapor</th>
                            <th>Cetak Sampul<br><input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'sampul')"></th>
                            <th>Cetak Identitas<br><input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'identitas')"></th>
                            <th>Cetak Rapor<br><input type="checkbox" class="form-check-input" onclick="toggleAll(this, 'rapor')"></th>
                            <th>Aksi Individu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_list)): ?>
                            <?php foreach ($siswa_list as $siswa):
                                $status_rapor = $siswa['status_rapor'] ?? 'Draft';
                                $badge_class = 'bg-warning text-dark';
                                $icon_class = 'bi-pencil-square';
                                if ($status_rapor == 'Final') { $badge_class = 'bg-success'; $icon_class = 'bi-check-circle-fill'; }
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                    <small class="text-muted">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $badge_class; ?>"><i class="bi <?php echo $icon_class; ?> me-1"></i><?php echo $status_rapor; ?></span>
                                </td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="check_sampul[]" value="<?php echo $siswa['id_siswa']; ?>"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="check_identitas[]" value="<?php echo $siswa['id_siswa']; ?>"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input" name="check_rapor[]" value="<?php echo $siswa['id_siswa']; ?>"></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="rapor_cover.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" data-bs-toggle="tooltip" title="Cetak Sampul">
                                            <i class="bi bi-book-half"></i>
                                        </a>
                                        <a href="rapor_identitas_siswa.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" data-bs-toggle="tooltip" title="Cetak Identitas">
                                            <i class="bi bi-person-badge"></i>
                                        </a>
                                        
                                        <!-- === INI ADALAH TOMBOL YANG DIKEMBALIKAN === -->
                                        <a href="rapor_pts_pdf.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-info" target="_blank" data-bs-toggle="tooltip" title="Cetak Rapor PTS">
                                            <i class="bi bi-calendar-event"></i>
                                        </a>
                                        <!-- === AKHIR TOMBOL YANG DIKEMBALIKAN === -->
                                        
                                        <a href="rapor_pdf.php?id_siswa=<?php echo $siswa['id_siswa']; ?>" class="btn btn-sm btn-outline-primary" target="_blank" data-bs-toggle="tooltip" title="Cetak Rapor Lengkap">
                                            <i class="bi bi-file-earmark-pdf-fill"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center p-4 text-muted">Belum ada siswa aktif di kelas ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ====================================================== -->
<!-- [BARU] Modal untuk Pilihan Cetak Leger -->
<!-- ====================================================== -->
<div class="modal fade" id="modalPilihLeger" tabindex="-1" aria-labelledby="modalPilihLegerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPilihLegerLabel"><i class="bi bi-table me-2"></i>Pilih Format Leger</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Silakan pilih format leger yang ingin Anda unduh untuk kelas <strong><?php echo htmlspecialchars($kelas['nama_kelas'] ?? ''); ?></strong>.</p>
                <div class="d-grid gap-2">
                    <a href="leger_pdf.php?id_kelas=<?php echo $id_kelas; ?>" class="btn btn-danger btn-lg" target="_blank">
                        <i class="bi bi-file-earmark-pdf-fill me-2"></i> Unduh sebagai PDF
                    </a>
                    <a href="leger_excel.php?id_kelas=<?php echo $id_kelas; ?>" class="btn btn-success btn-lg" target="_blank">
                        <i class="bi bi-file-earmark-excel-fill me-2"></i> Unduh sebagai Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ====================================================== -->
<!-- [AKHIR MODAL] -->
<!-- ====================================================== -->

<script>
// Inisialisasi Tooltip Bootstrap
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Fungsi untuk memilih semua checkbox dalam satu kolom
function toggleAll(source, type) {
    let checkboxes = document.getElementsByName('check_' + type + '[]');
    for(let i = 0; i < checkboxes.length; i++) {
        if (!checkboxes[i].disabled) {
            checkboxes[i].checked = source.checked;
        }
    }
}

// ==========================================================
// ### FUNGSI CETAK MASSAL (SESUAI LOGIKA ASLI ANDA) ###
// ==========================================================
function prosesCetakMassal(tipeCetak) {
    let listSiswaId = [];
    // Mengambil checkbox yang sesuai dengan tipe yang diklik (cth: check_sampul[], check_rapor[], dll)
    let checkboxes = document.getElementsByName('check_' + tipeCetak + '[]');
    
    for(let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) { 
            listSiswaId.push(checkboxes[i].value); 
        }
    }
    
    if (listSiswaId.length === 0) {
        Swal.fire('Peringatan', 'Silakan pilih minimal satu siswa pada kolom yang sesuai untuk dicetak.', 'warning');
        return;
    }
    
    let ids = listSiswaId.join(',');
    
    // Menggunakan satu file target `rapor_cetak_massal.php` dan mengirimkan tipenya
    let url = `rapor_cetak_massal.php?tipe=${tipeCetak}&ids=${ids}`;
    
    window.open(url, '_blank');
}

// [BARU] Logika SweetAlert untuk tombol Finalisasi dan Batal
document.addEventListener('DOMContentLoaded', function() {
    const btnFinalisasi = document.getElementById('btn-finalisasi-semua');
    if(btnFinalisasi) {
        btnFinalisasi.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Finalisasi Rapor?',
                html: "Anda yakin ingin memfinalisasi rapor untuk <strong>SEMUA siswa</strong> di kelas ini?<br><br><strong>Proses ini akan:</strong><br>1. Menghitung nilai akhir & deskripsi.<br>2. Mengunci rapor (status menjadi 'Final').",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Finalisasi Sekarang!',
                cancelButtonText: 'Batal',
                // [BARU] Tampilkan loading saat dikonfirmasi
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    document.getElementById('form-finalisasi').submit();
                }
            });
        });
    }

    const btnBatal = document.getElementById('btn-batalkan-finalisasi');
    if(btnBatal) {
        btnBatal.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Batalkan Finalisasi?',
                text: "PERHATIAN! Ini akan mengubah status SEMUA rapor kembali menjadi 'Draft' dan mengizinkan pengeditan ulang. Lanjutkan?",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Tidak'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('form-batal').submit();
                }
            });
        });
    }
});
</script>

<?php
// [PERBAIKAN] Blok Notifikasi SweetAlert2 yang Cerdas
if (isset($_SESSION['pesan'])) {
    $pesan_json = $_SESSION['pesan'];
    // Cek apakah pesan adalah JSON (dari aksi yang lebih baru)
    if (strpos($pesan_json, '{') === 0) {
        $pesan_data = json_decode($pesan_json, true);
        echo "<script>Swal.fire({icon: '".($pesan_data['icon'] ?? 'info')."', title: '".($pesan_data['title'] ?? 'Pemberitahuan')."', text: '".($pesan_data['text'] ?? '')."'});</script>";
    } else {
        // Pesan adalah teks biasa (dari aksi lama)
        $pesan_teks = addslashes($pesan_json);
        $pesan_judul = 'Pemberitahuan';
        $pesan_ikon = 'info'; // Default

        $pesan_lower = strtolower($pesan_teks);

        if (str_contains($pesan_lower, 'berhasil') || str_contains($pesan_lower, 'sukses') || str_contains($pesan_lower, 'disimpan') || str_contains($pesan_lower, 'diperbarui') || str_contains($pesan_lower, 'dihapus') || str_contains($pesan_lower, 'finalisasi')) {
            $pesan_judul = 'Berhasil!';
            $pesan_ikon = 'success';
        } elseif (str_contains($pesan_lower, 'gagal') || str_contains($pesan_lower, 'error') || str_contains($pesan_lower, 'ditolak') || str_contains($pesan_lower, 'salah')) {
            $pesan_judul = 'Gagal!';
            $pesan_ikon = 'error';
        }

        echo "<script>Swal.fire({
            icon: '{$pesan_ikon}',
            title: '{$pesan_judul}',
            text: '{$pesan_teks}'
        });</script>";
    }
    unset($_SESSION['pesan']);
}

// [PERBAIKAN] Blok Notifikasi Error Lama (dijadikan satu dengan yang di atas)
// Hapus blok 'pesan_error' yang lama karena sudah ditangani oleh logika cerdas di atas
if (isset($_SESSION['pesan_error'])) {
     $pesan_teks_error = addslashes($_SESSION['pesan_error']);
     echo "<script>Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '{$pesan_teks_error}'
        });</script>";
    unset($_SESSION['pesan_error']);
}
include 'footer.php';
?>