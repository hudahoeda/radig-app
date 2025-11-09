<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Admin.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Mengambil data dari tabel sekolah
$query_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
if (mysqli_num_rows($query_sekolah) == 0) {
    mysqli_query($koneksi, "INSERT INTO sekolah (id_sekolah, nama_sekolah) VALUES (1, 'NAMA SEKOLAH ANDA')");
    $query_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
}
$sekolah = mysqli_fetch_assoc($query_sekolah);

// Mengambil data dari tabel pengaturan
$query_pengaturan = mysqli_query($koneksi, "SELECT * FROM pengaturan");
$pengaturan = [];
while($row = mysqli_fetch_assoc($query_pengaturan)){
    $pengaturan[$row['nama_pengaturan']] = $row['nilai_pengaturan'];
}
// Variabel baru untuk watermark
$watermark_sekarang = $pengaturan['watermark_file'] ?? null;

// Mengambil semua data tahun ajaran
$query_ta = mysqli_query($koneksi, "SELECT * FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
$daftar_tahun_ajaran = mysqli_fetch_all($query_ta, MYSQLI_ASSOC);
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .nav-tabs-form .nav-link { font-weight: 500; color: var(--bs-secondary-color); border: none; border-bottom: 3px solid transparent; padding: 0.75rem 1rem; }
    .nav-tabs-form .nav-link.active { color: var(--primary-color); border-color: var(--primary-color); font-weight: 600; }
    .info-card { background-color: var(--bs-info-bg-subtle); border-left: 5px solid var(--bs-info-border-subtle); }
    .tab-content .form-label { font-weight: 600; }
    /* Style tambahan untuk preview watermark */
    .watermark-preview { border: 2px dashed #ccc; padding: 20px; background-image: url('data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); min-height: 200px; }

    /* [MODIFIKASI] Style untuk Pilihan Warna */
    .color-option {
        display: inline-block;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        cursor: pointer;
        margin-right: 5px;
        transition: all 0.2s ease; /* Diubah dari 'transform' */
        vertical-align: middle; 
    }

    /* [PERBAIKAN] Sembunyikan radio button asli dengan benar, bukan display:none */
    .form-check-input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        border: 0;
        clip: rect(0, 0, 0, 0);
    }
    
    /* [PERBAIKAN] Selector :checked kini menargetkan label di sebelahnya */
    .form-check-input[type="radio"]:checked + .form-check-label .color-option {
        transform: scale(1.2);
        /* Outline ganda agar terlihat di atas warna terang/gelap */
        box-shadow: 0 0 0 3px #fff, 0 0 0 6px var(--primary-color);
    }
    
    /* [TAMBAHAN] Efek hover agar lebih interaktif */
    .form-check-label:hover .color-option {
         transform: scale(1.1);
    }
    
    .form-check-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        gap: 8px; /* Jarak antara radio dan teks */
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Pengaturan Aplikasi</h1>
        <p class="lead mb-0 opacity-75">Kelola informasi sekolah, pejabat, dan pengaturan teknis rapor.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8 d-flex flex-column">
            <div class="card shadow-sm flex-grow-1">
                <div class="card-header bg-light p-0 border-bottom-0">
                    <ul class="nav nav-tabs nav-tabs-form nav-fill" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-sekolah-pane" type="button"><i class="bi bi-bank2 me-2"></i>Identitas Sekolah</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pejabat-pane" type="button"><i class="bi bi-person-badge-fill me-2"></i>Pejabat</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rapor-ta-pane" type="button"><i class="bi bi-gear-fill me-2"></i>T.A & Tanggal</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mod-rapor-pane" type="button"><i class="bi bi-palette-fill me-2"></i>Modifikasi Rapor</button></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content p-2" id="myTabContent">
                        <div class="tab-pane fade show active" id="tab-sekolah-pane" role="tabpanel">
                            <form action="pengaturan_aksi.php?aksi=update_sekolah" method="POST">
                                <h5 class="mb-3"><i class="bi bi-building me-2 text-primary"></i>Data Pokok Sekolah</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="nama_sekolah" class="form-label">Nama Sekolah</label>
                                        <input type="text" class="form-control" id="nama_sekolah" name="nama_sekolah" value="<?php echo htmlspecialchars($sekolah['nama_sekolah'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="jenjang" class="form-label">Jenjang</label>
                                        <input type="text" class="form-control" id="jenjang" name="jenjang" value="SMP" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="npsn" class="form-label">NPSN</label>
                                        <input type="text" class="form-control" id="npsn" name="npsn" value="<?php echo htmlspecialchars($sekolah['npsn'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="nss" class="form-label">NSS</label>
                                        <input type="text" class="form-control" id="nss" name="nss" value="<?php echo htmlspecialchars($sekolah['nss'] ?? ''); ?>">
                                    </div>
                                </div>
                                <h5 class="mt-4 mb-3"><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Alamat & Kontak</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="jalan" class="form-label">Jalan</label>
                                        <input type="text" class="form-control" id="jalan" name="jalan" value="<?php echo htmlspecialchars($sekolah['jalan'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="desa_kelurahan" class="form-label">Desa / Kelurahan</label>
                                        <input type="text" class="form-control" id="desa_kelurahan" name="desa_kelurahan" value="<?php echo htmlspecialchars($sekolah['desa_kelurahan'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="kecamatan" class="form-label">Kecamatan</label>
                                        <input type="text" class="form-control" id="kecamatan" name="kecamatan" value="<?php echo htmlspecialchars($sekolah['kecamatan'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="kabupaten_kota" class="form-label">Kabupaten / Kota</label>
                                        <input type="text" class="form-control" id="kabupaten_kota" name="kabupaten_kota" value="<?php echo htmlspecialchars($sekolah['kabupaten_kota'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="provinsi" class="form-label">Provinsi</label>
                                        <input type="text" class="form-control" id="provinsi" name="provinsi" value="<?php echo htmlspecialchars($sekolah['provinsi'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="telepon" class="form-label">Nomor Telepon</label>
                                        <input type="text" class="form-control" id="telepon" name="telepon" value="<?php echo htmlspecialchars($sekolah['telepon'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($sekolah['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($sekolah['website'] ?? ''); ?>" placeholder="https://sekolah.sch.id">
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill me-2"></i>Simpan Identitas Sekolah</button>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="tab-pejabat-pane" role="tabpanel">
                             <form action="pengaturan_aksi.php?aksi=update_pejabat" method="POST">
                                <h5 class="mb-3"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Pejabat Penandatangan</h5>
                                <div class="mb-3">
                                    <label for="nama_kepsek" class="form-label">Nama Kepala Sekolah</label>
                                    <input type="text" class="form-control" id="nama_kepsek" name="nama_kepsek" value="<?php echo htmlspecialchars($sekolah['nama_kepsek'] ?? ''); ?>">
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="jabatan_kepsek" class="form-label">Jabatan / Pangkat</label>
                                        <input type="text" class="form-control" id="jabatan_kepsek" name="jabatan_kepsek" value="<?php echo htmlspecialchars($sekolah['jabatan_kepsek'] ?? ''); ?>" placeholder="Contoh: Pembina Tk. I, IV/b">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nip_kepsek" class="form-label">NIP Kepala Sekolah</label>
                                        <input type="text" class="form-control" id="nip_kepsek" name="nip_kepsek" value="<?php echo htmlspecialchars($sekolah['nip_kepsek'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill me-2"></i>Simpan Info Pejabat</button>
                                </div>
                             </form>
                        </div>
                        
                        <div class="tab-pane fade" id="tab-rapor-ta-pane" role="tabpanel">
                             <form action="pengaturan_aksi.php?aksi=update_pengaturan" method="POST">
                                <!-- Input tersembunyi untuk Fase D -->
                                <input type="hidden" name="pengaturan[fase_aktif]" value="D">
                                
                                <h5 class="mb-3"><i class="bi bi-calendar-check-fill me-2 text-primary"></i>Pengaturan Semester & Tanggal Rapor</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="semester_aktif" class="form-label">Semester Aktif</label>
                                        <select class="form-select" id="semester_aktif" name="pengaturan[semester_aktif]">
                                            <option value="1" <?php if(($pengaturan['semester_aktif'] ?? '1') == '1') echo 'selected'; ?>>Ganjil</option>
                                            <option value="2" <?php if(($pengaturan['semester_aktif'] ?? '1') == '2') echo 'selected'; ?>>Genap</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="kkm" class="form-label">Batas Nilai Terendah</label>
                                        <input type="number" class="form-control" id="kkm" name="pengaturan[kkm]" value="<?php echo htmlspecialchars($pengaturan['kkm'] ?? '75'); ?>" min="0" max="100">
                                        <div class="form-text">Nilai ini digunakan sebagai batas tuntas di rapor.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tanggal_rapor_pts" class="form-label">Tanggal Rapor Tengah Semester</label>
                                        <input type="date" class="form-control" id="tanggal_rapor_pts" name="pengaturan[tanggal_rapor_pts]" value="<?php echo htmlspecialchars($pengaturan['tanggal_rapor_pts'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tanggal_rapor" class="form-label">Tanggal Rapor Akhir Semester</label>
                                        <input type="date" class="form-control" id="tanggal_rapor" name="pengaturan[tanggal_rapor]" value="<?php echo htmlspecialchars($pengaturan['tanggal_rapor'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill me-2"></i>Simpan Pengaturan</button>
                                </div>
                             </form>
                             <hr class="my-4">
                             <h5 class="mb-3"><i class="bi bi-calendar3-week-fill me-2 text-primary"></i>Manajemen Tahun Ajaran</h5>
                             <table class="table table-hover align-middle">
                                 <thead class="table-light">
                                     <tr>
                                         <th>Tahun Ajaran</th>
                                         <th class="text-center">Status</th>
                                         <th class="text-center">Aksi</th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     <?php foreach ($daftar_tahun_ajaran as $ta): ?>
                                     <tr>
                                         <td class="fw-bold"><?php echo htmlspecialchars($ta['tahun_ajaran']); ?></td>
                                         <td class="text-center">
                                             <?php if ($ta['status'] == 'Aktif'): ?><span class="badge bg-success">Aktif</span><?php else: ?><span class="badge bg-secondary">Tidak Aktif</span><?php endif; ?>
                                         </td>
                                         <td class="text-center">
                                             <?php if ($ta['status'] == 'Tidak Aktif'): ?><a href="pengaturan_aksi.php?aksi=aktifkan_ta&id=<?php echo $ta['id_tahun_ajaran']; ?>" class="btn btn-sm btn-outline-success">Aktifkan</a><?php else: ?><button class="btn btn-sm btn-success" disabled>Aktif</button><?php endif; ?>
                                         </td>
                                     </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                             <h6 class="mt-4">Tambah Tahun Ajaran Baru</h6>
                             <form action="pengaturan_aksi.php?aksi=tambah_ta" method="POST" class="row g-2">
                                 <div class="col-8">
                                     <input type="text" class="form-control" name="tahun_ajaran" placeholder="Contoh: 2026/2027" required>
                                 </div>
                                 <div class="col-4">
                                     <button type="submit" class="btn btn-primary w-100">Tambah</button>
                                 </div>
                             </form>
                        </div>

                        <div class="tab-pane fade" id="tab-mod-rapor-pane" role="tabpanel">
                            <!-- Form 1: Ukuran Kertas & Warna -->
                            <form action="pengaturan_aksi.php?aksi=update_pengaturan" method="POST">
                                <h5 class="mb-3"><i class="bi bi-file-earmark-pdf-fill me-2 text-primary"></i>Ukuran Kertas Rapor</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="rapor_ukuran_kertas" class="form-label">Ukuran Kertas</label>
                                        <select class="form-select" id="rapor_ukuran_kertas" name="pengaturan[rapor_ukuran_kertas]">
                                            <option value="A4" <?php if(($pengaturan['rapor_ukuran_kertas'] ?? 'A4') == 'A4') echo 'selected'; ?>>A4 (210 x 297 mm)</option>
                                            <option value="F4" <?php if(($pengaturan['rapor_ukuran_kertas'] ?? 'A4') == 'F4') echo 'selected'; ?>>F4 (215 x 330 mm)</option>
                                        </select>
                                        <div class="form-text">Berlaku untuk Rapor Akhir Semester dan Rapor PTS.</div>
                                    </div>
                                </div>
                                <hr class="my-4">
                                <h5 class="mb-3"><i class="bi bi-palette-fill me-2 text-primary"></i>Skema Warna Rapor (Hemat Tinta)</h5>
                                <div class="form-text mb-3">Pilih warna dominan untuk kop dan judul tabel pada PDF rapor.</div>
                                
                                <?php $warna_terpilih = $pengaturan['rapor_skema_warna'] ?? 'bw'; // Default ke Hitam Putih ?>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pengaturan[rapor_skema_warna]" value="bw" id="warna_bw" <?php if($warna_terpilih == 'bw') echo 'checked'; ?>>
                                        <label class="form-check-label" for="warna_bw">
                                            <span class="color-option" style="background-color: #444444;"></span>
                                            Hitam Putih (Default)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_blue" id="warna_light_blue" <?php if($warna_terpilih == 'light_blue') echo 'checked'; ?>>
                                        <label class="form-check-label" for="warna_light_blue">
                                            <span class="color-option" style="background-color: #E3F2FD;"></span>
                                            Biru Muda
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_green" id="warna_light_green" <?php if($warna_terpilih == 'light_green') echo 'checked'; ?>>
                                        <label class="form-check-label" for="warna_light_green">
                                            <span class="color-option" style="background-color: #E8F5E9;"></span>
                                            Hijau Muda
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_teal" id="warna_light_teal" <?php if($warna_terpilih == 'light_teal') echo 'checked'; ?>>
                                        <label class="form-check-label" for="warna_light_teal">
                                            <span class="color-option" style="background-color: #E0F2F1;"></span>
                                            Teal Muda
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_purple" id="warna_light_purple" <?php if($warna_terpilih == 'light_purple') echo 'checked'; ?>>
                                        <label class="form-check-label" for="warna_light_purple">
                                            <span class="color-option" style="background-color: #EDE7F6;"></span>
                                            Ungu Muda
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_red" id="warna_light_red" <?php if($warna_terpilih == 'light_red') echo 'checked'; ?>>
                                        <label class="form-check-label" for="warna_light_red">
                                            <span class="color-option" style="background-color: #FFEBEE;"></span>
                                            Merah Muda
                                        </label>
                                    </div>
                                </div>

                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill me-2"></i>Simpan Modifikasi Rapor</button>
                                </div>
                            </form>

                            <hr class="my-4" style="border-style: dashed;">

                            <form action="pengaturan_aksi.php?aksi=update_watermark" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="watermark_lama" value="<?php echo htmlspecialchars($watermark_sekarang); ?>">
                                <h5 class="mb-3"><i class="bi bi-patch-check-fill me-2 text-primary"></i>Pengaturan Watermark Rapor</h5>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-7">
                                        <div class="form-group">
                                            <label for="watermark_baru" class="form-label">Unggah Watermark Baru (PNG Transparan)</label>
                                            <input type="file" class="form-control" id="watermark_baru" name="watermark_baru" accept="image/png">
                                            <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah. Rekomendasi format PNG transparan.</small>
                                        </div>
                                        <?php if (!empty($watermark_sekarang)): ?>
                                        <div class="form-group form-check mt-3">
                                            <input type="checkbox" class="form-check-input" id="hapus_watermark" name="hapus_watermark" value="1">
                                            <label class="form-check-label text-danger" for="hapus_watermark">Hapus Watermark Saat Ini</label>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-5 text-center">
                                        <label class="form-label">Preview Saat Ini:</label>
                                        <div class="mt-2 watermark-preview d-flex align-items-center justify-content-center">
                                            <?php if (!empty($watermark_sekarang) && file_exists('uploads/' . $watermark_sekarang)): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($watermark_sekarang); ?>" style="max-width: 150px; opacity: 0.5;">
                                            <?php else: ?>
                                                <p class="text-muted mb-0">Tidak ada watermark</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" name="simpan_watermark" class="btn btn-primary"><i class="bi bi-floppy-fill me-2"></i>Simpan Pengaturan Watermark</button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm info-card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-info-circle-fill me-2"></i>Informasi Penting</h5>
                    <p class="small">Perubahan pada data di halaman ini akan mempengaruhi seluruh bagian aplikasi, termasuk kop surat dan data pada rapor yang dicetak.</p>
                </div>
            </div>
            <div class="card shadow-sm">
                 <form action="pengaturan_aksi.php?aksi=update_logo" method="POST" enctype="multipart/form-data">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-image-fill me-2"></i>Logo Sekolah</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (!empty($sekolah['logo_sekolah']) && file_exists('uploads/' . $sekolah['logo_sekolah'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($sekolah['logo_sekolah']); ?>" alt="Logo Saat Ini" class="img-fluid rounded mb-2" style="max-height: 120px;">
                            <?php else: ?>
                                <i class="bi bi-image-alt display-4 text-muted"></i>
                                <p class="text-muted mt-2">Belum ada logo</p>
                            <?php endif; ?>
                        </div>
                        <label for="logo_sekolah" class="form-label">Ganti Logo (PNG/JPG, Maks 1MB)</label>
                        <input class="form-control" type="file" id="logo_sekolah" name="logo_sekolah" accept="image/png, image/jpeg">
                        <div class="form-text">Kosongkan jika tidak ingin mengubah logo.</div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Unggah Logo</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-database-down me-2"></i>Backup & Restore</h5>
                </div>
                <div class="card-body text-center">
                    <p class="small text-muted">
                        Kelola dan pulihkan cadangan data aplikasi Anda.
                    </p>
                    <div class="d-grid">
                        <a href="pengaturan_backup_tampil.php" class="btn btn-primary">
                            <i class="bi bi-gear-wide-connected me-2"></i>Buka Halaman Backup
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Script untuk menampilkan nama file di input file bootstrap
    document.querySelectorAll('input[type="file"]').forEach(function(input) {
        input.addEventListener('change', function(e) {
            var fileName = e.target.files[0] ? e.target.files[0].name : 'Pilih file...';
            // Cek jika ada label .custom-file-label (untuk watermark lama)
            var label = e.target.nextElementSibling;
            if (label && label.classList.contains('custom-file-label')) {
                label.innerText = fileName;
            }
        });
    });
</script>

<?php include 'footer.php'; ?>