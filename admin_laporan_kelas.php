<?php
include 'header.php';
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran_aktif = $d_ta['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_aktif = $d_ta['tahun_ajaran'] ?? 'Tidak Ditemukan';

$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    
    /* [BARU] Style untuk tabel yang lebih rapi */
    .table-monitoring th {
        vertical-align: middle;
        text-align: center;
        background-color: #f8f9fa; /* Header tabel abu-abu muda */
    }
    .table-monitoring th:first-child {
        text-align: left; /* Kolom pertama rata kiri */
    }
    .table-monitoring td {
        vertical-align: middle;
    }
    
    .table-monitoring .kelas-wali {
        line-height: 1.4;
    }
    .table-monitoring .kelas-wali strong {
        font-size: 1.1rem;
        color: var(--text-dark);
    }
    .table-monitoring .kelas-wali small {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    .table-monitoring .progress-container {
        min-width: 200px; /* Agar progress bar tidak terlalu sempit */
    }
    
    .table-monitoring .action-buttons {
        min-width: 250px; /* Agar tombol aksi tidak terpotong */
        text-align: right;
    }
    
    .progress { height: 1.25rem; font-size: 0.8rem; font-weight: 600; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Pusat Laporan & Cetak</h1>
        <p class="lead mb-0 opacity-75">
            Monitor kelengkapan dan cetak dokumen final untuk Tahun Ajaran <?php echo $tahun_ajaran_aktif; ?> - Semester <?php echo $semester_aktif; ?>.
        </p>
    </div>

    <!-- [ROMBAK] Menggunakan satu Card dengan Tabel -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-collection-fill me-2" style="color: var(--primary-color);"></i>Progres Rapor per Kelas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-monitoring mb-0">
                    <thead>
                        <tr>
                            <th>Kelas & Wali Kelas</th>
                            <th>Jumlah Siswa</th>
                            <th>Progres Finalisasi Rapor</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $query_kelas = "SELECT k.id_kelas, k.nama_kelas, g.nama_guru 
                                    FROM kelas k 
                                    LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
                                    WHERE k.id_tahun_ajaran = $id_tahun_ajaran_aktif 
                                    ORDER BY k.nama_kelas ASC";
                    $result_kelas = mysqli_query($koneksi, $query_kelas);

                    if ($result_kelas && mysqli_num_rows($result_kelas) > 0) {
                        while($kelas = mysqli_fetch_assoc($result_kelas)):
                            $id_kelas = $kelas['id_kelas'];
                            
                            // Hitung progres untuk setiap kelas
                            $q_siswa = mysqli_query($koneksi, "SELECT COUNT(id_siswa) as total FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif'");
                            $jumlah_siswa = mysqli_fetch_assoc($q_siswa)['total'] ?? 0;

                            $q_rapor_final = mysqli_query($koneksi, "SELECT COUNT(id_rapor) as total FROM rapor WHERE id_kelas = $id_kelas AND status = 'Final' AND semester = $semester_aktif AND id_tahun_ajaran = $id_tahun_ajaran_aktif");
                            $jumlah_rapor_final = mysqli_fetch_assoc($q_rapor_final)['total'] ?? 0;

                            $persentase = ($jumlah_siswa > 0) ? round(($jumlah_rapor_final / $jumlah_siswa) * 100) : 0;
                            $progress_color = 'bg-warning';
                            if ($persentase == 100) $progress_color = 'bg-success';
                            elseif ($persentase > 0) $progress_color = 'bg-info';
                    ?>
                        <tr>
                            <!-- Kolom Kelas & Wali -->
                            <td class="kelas-wali">
                                <strong><?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong><br>
                                <small>Wali: <?php echo htmlspecialchars($kelas['nama_guru'] ?? 'Belum Ditentukan'); ?></small>
                            </td>
                            
                            <!-- Kolom Jumlah Siswa -->
                            <td class="text-center fw-bold"><?php echo $jumlah_siswa; ?> Siswa</td>
                            
                            <!-- Kolom Progres -->
                            <td class="progress-container">
                                <small class="text-muted d-block"><?php echo $jumlah_rapor_final; ?> dari <?php echo $jumlah_siswa; ?> siswa telah final</small>
                                <div class="progress" role="progressbar">
                                    <div class="progress-bar progress-bar-striped <?php echo $progress_color; ?>" style="width: <?php echo $persentase; ?>%">
                                        <?php echo $persentase; ?>%
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom Aksi (Dirapikan) -->
                            <td class="action-buttons">
                                <div class="btn-group" role="group">
                                    <a href="admin_laporan_siswa.php?id_kelas=<?php echo $id_kelas; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-search me-1"></i>Lihat Detail
                                    </a>
                                    <!-- [ROMBAK] Tombol Leger menjadi Dropdown -->
                                    <div class="btn-group" role="group">
                                        <button type.button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-table me-1"></i> Leger
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="leger_pdf.php?id_kelas=<?php echo $id_kelas; ?>" target="_blank"><i class="bi bi-file-earmark-pdf-fill me-2"></i>Cetak PDF</a></li>
                                            <li><a class="dropdown-item" href="leger_excel.php?id_kelas=<?php echo $id_kelas; ?>" target="_blank"><i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>Ekspor Excel</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    } else {
                        echo '<tr><td colspan="4"><div class="text-center py-5 text-muted"><i class="bi bi-door-closed fs-1"></i><h4 class="mt-3">Tidak Ada Kelas</h4><p>Belum ada data kelas yang dibuat untuk tahun ajaran aktif.</p></div></td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Akhir Rombak -->
    
</div>

<?php 
// [ROMBAK] Notifikasi diubah menjadi SweetAlert2 Cerdas
if (isset($_SESSION['pesan']) || isset($_SESSION['pesan_error'])) {
    $pesan = $_SESSION['pesan'] ?? $_SESSION['pesan_error'];
    $pesan_teks = addslashes($pesan);
    $pesan_judul = isset($_SESSION['pesan']) ? 'Berhasil!' : 'Gagal!';
    $pesan_ikon = isset($_SESSION['pesan']) ? 'success' : 'error';
    
    echo "<script>
        Swal.fire({
            icon: '{$pesan_ikon}',
            title: '{$pesan_judul}',
            text: '{$pesan_teks}'
        });
    </script>";
    
    unset($_SESSION['pesan']);
    unset($_SESSION['pesan_error']);
}
include 'footer.php'; 
?>