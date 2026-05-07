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
// Variabel untuk watermark & KOP
$watermark_sekarang = $pengaturan['watermark_file'] ?? null;
// Cek kedua key untuk kompatibilitas
$kop_sekolah_sekarang = !empty($pengaturan['kop_sekolah']) ? $pengaturan['kop_sekolah'] : ($pengaturan['file_kop_sekolah'] ?? null);

// [FITUR BARU] Variabel Tanpa KOP
$cetak_tanpa_kop = $pengaturan['cetak_tanpa_kop'] ?? '0';
$margin_atas_tanpa_kop = $pengaturan['margin_atas_tanpa_kop'] ?? '0';

// Mengambil semua data tahun ajaran
$query_ta = mysqli_query($koneksi, "SELECT * FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
$daftar_tahun_ajaran = mysqli_fetch_all($query_ta, MYSQLI_ASSOC);
?>

<style>
    /* Styling Header Halaman */
    .page-header { 
        background: linear-gradient(135deg, var(--primary-color, #0d6efd), var(--secondary-color, #6c757d)); 
        padding: 2.5rem 2rem; 
        border-radius: 0.75rem; 
        color: white; 
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .page-header h1 { font-weight: 700; margin-bottom: 0.5rem; }
    .page-header p { font-size: 1.1rem; opacity: 0.9; margin-bottom: 0; }

    /* Styling Tabs Navigasi */
    .nav-tabs-custom { border-bottom: 2px solid #dee2e6; }
    .nav-tabs-custom .nav-link { 
        font-weight: 600; 
        color: #495057; 
        border: none; 
        border-bottom: 3px solid transparent; 
        padding: 1rem 1.5rem;
        transition: all 0.3s ease;
    }
    .nav-tabs-custom .nav-link:hover { color: var(--primary-color, #0d6efd); background-color: rgba(var(--bs-primary-rgb), 0.05); }
    .nav-tabs-custom .nav-link.active { 
        color: var(--primary-color, #0d6efd); 
        border-bottom-color: var(--primary-color, #0d6efd); 
        background-color: transparent;
    }
    .nav-icon { margin-right: 8px; font-size: 1.1em; }

    /* Styling Card & Forms */
    .card-settings { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 0.75rem; }
    .form-section-title { 
        font-size: 1.1rem; 
        font-weight: 700; 
        color: #343a40; 
        margin-bottom: 1.25rem; 
        padding-bottom: 0.5rem; 
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
    }
    .form-section-title i { color: var(--primary-color, #0d6efd); margin-right: 10px; font-size: 1.2rem; }
    .form-label { font-weight: 600; color: #495057; font-size: 0.95rem; }
    .form-control, .form-select { padding: 0.6rem 1rem; border-radius: 0.5rem; }
    .form-text { font-size: 0.85rem; color: #6c757d; }

    /* Styling Pilihan Warna (Color Swatches) */
    .color-picker-container { display: flex; flex-wrap: wrap; gap: 15px; }
    .color-option-wrapper { position: relative; }
    .color-option-input { position: absolute; opacity: 0; width: 0; height: 0; }
    .color-option-label { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        cursor: pointer; 
        padding: 10px; 
        border: 2px solid #dee2e6; 
        border-radius: 10px; 
        width: 100px;
        transition: all 0.2s;
    }
    .color-circle { 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        margin-bottom: 8px; 
        border: 2px solid rgba(0,0,0,0.1); 
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .color-name { font-size: 0.85rem; font-weight: 600; color: #495057; }
    
    /* Efek Selected pada Color Picker */
    .color-option-input:checked + .color-option-label {
        border-color: var(--primary-color, #0d6efd);
        background-color: rgba(var(--bs-primary-rgb), 0.05);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(var(--bs-primary-rgb), 0.2);
    }
    .color-option-input:checked + .color-option-label .color-name { color: var(--primary-color, #0d6efd); }

    /* Styling Preview Image Box */
    .img-preview-container { 
        border: 2px dashed #ced4da; 
        border-radius: 0.75rem; 
        background-color: #f8f9fa; 
        min-height: 180px; 
        display: flex; 
        flex-direction: column;
        align-items: center; 
        justify-content: center; 
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s;
    }
    .img-preview-container:hover { border-color: #adb5bd; background-color: #e9ecef; }
    .img-preview { max-width: 100%; max-height: 150px; object-fit: contain; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; }
    .preview-placeholder { color: #adb5bd; font-style: italic; }
    .preview-placeholder i { font-size: 2rem; margin-bottom: 0.5rem; display: block; }

    /* Modern Switch Toggle */
    .modern-switch { padding-left: 0; display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1px solid #dee2e6; padding: 1rem 1.5rem; border-radius: 0.75rem; }
    .form-check-input.switch-lg { width: 3rem; height: 1.5rem; margin-left: 1rem; cursor: pointer; }
    .switch-label-content h6 { margin-bottom: 0.25rem; font-weight: 700; color: #343a40; }
    .switch-label-content small { display: block; line-height: 1.3; color: #6c757d; }

    /* Side Info Card */
    .side-card { background-color: #fff; border: none; border-radius: 0.75rem; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 1.5rem; overflow: hidden; }
    .side-card-header { background-color: #f8f9fa; padding: 1rem 1.5rem; border-bottom: 1px solid #eee; font-weight: 700; color: #495057; display: flex; align-items: center; }
    .side-card-header i { margin-right: 10px; color: var(--primary-color, #0d6efd); }
    .side-card-body { padding: 1.5rem; }
</style>

<div class="container-fluid">
    <!-- Tampilkan Pesan SweetAlert dari Session -->
    <?php if (isset($_SESSION['pesan'])): ?>
        <script>
            const pesan = <?php echo $_SESSION['pesan']; ?>;
            Swal.fire(pesan.title, pesan.text, pesan.icon);
        </script>
        <?php unset($_SESSION['pesan']); ?>
    <?php endif; ?>

    <div class="page-header">
        <h1>Pengaturan Aplikasi</h1>
        <p>Pusat kendali data sekolah, pejabat, tahun ajaran, dan kustomisasi laporan hasil belajar.</p>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Form Utama -->
        <div class="col-lg-8">
            <div class="card card-settings h-100">
                <div class="card-header bg-white p-0">
                    <ul class="nav nav-tabs nav-tabs-custom nav-fill" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-sekolah" data-bs-toggle="tab" data-bs-target="#content-sekolah" type="button" role="tab"><i class="bi bi-building nav-icon"></i>Identitas Sekolah</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-pejabat" data-bs-toggle="tab" data-bs-target="#content-pejabat" type="button" role="tab"><i class="bi bi-person-badge nav-icon"></i>Pejabat</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-ta" data-bs-toggle="tab" data-bs-target="#content-ta" type="button" role="tab"><i class="bi bi-calendar3 nav-icon"></i>T.A & Tanggal</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-rapor" data-bs-toggle="tab" data-bs-target="#content-rapor" type="button" role="tab"><i class="bi bi-palette nav-icon"></i>Modifikasi Rapor</button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <div class="tab-content" id="settingsTabContent">
                        
                        <!-- TAB 1: IDENTITAS SEKOLAH -->
                        <div class="tab-pane fade show active" id="content-sekolah" role="tabpanel">
                            <form action="pengaturan_aksi.php?aksi=update_sekolah" method="POST">
                                <div class="form-section-title"><i class="bi bi-info-circle"></i>Data Pokok</div>
                                <div class="row g-3 mb-4">
                                    <div class="col-12">
                                        <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nama_sekolah" name="nama_sekolah" value="<?php echo htmlspecialchars($sekolah['nama_sekolah'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="jenjang" class="form-label">Jenjang</label>
                                        <input type="text" class="form-control bg-light" id="jenjang" name="jenjang" value="SMP" readonly>
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

                                <div class="form-section-title"><i class="bi bi-geo-alt"></i>Alamat & Kontak</div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="jalan" class="form-label">Alamat Jalan</label>
                                        <input type="text" class="form-control" id="jalan" name="jalan" value="<?php echo htmlspecialchars($sekolah['jalan'] ?? ''); ?>" placeholder="Contoh: Jl. Merdeka No. 10">
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
                                        <label for="telepon" class="form-label">No. Telepon</label>
                                        <input type="text" class="form-control" id="telepon" name="telepon" value="<?php echo htmlspecialchars($sekolah['telepon'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="email" class="form-label">Email Sekolah</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($sekolah['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($sekolah['website'] ?? ''); ?>" placeholder="https://...">
                                    </div>
                                </div>
                                <div class="text-end mt-4 pt-2 border-top">
                                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: PEJABAT -->
                        <div class="tab-pane fade" id="content-pejabat" role="tabpanel">
                            <form action="pengaturan_aksi.php?aksi=update_pejabat" method="POST">
                                <div class="form-section-title"><i class="bi bi-pen"></i>Pejabat Penandatangan Rapor</div>
                                <div class="alert alert-light border-start border-primary border-4 text-muted mb-4">
                                    <small><i class="bi bi-info-circle-fill me-1"></i> Data ini akan muncul di bagian tanda tangan pada halaman terakhir rapor.</small>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="nama_kepsek" class="form-label">Nama Kepala Sekolah <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-lg" id="nama_kepsek" name="nama_kepsek" value="<?php echo htmlspecialchars($sekolah['nama_kepsek'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nip_kepsek" class="form-label">NIP Kepala Sekolah</label>
                                        <input type="text" class="form-control" id="nip_kepsek" name="nip_kepala_sekolah" value="<?php echo htmlspecialchars($sekolah['nip_kepsek'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="jabatan_kepsek" class="form-label">Pangkat / Golongan</label>
                                        <input type="text" class="form-control" id="jabatan_kepsek" name="jabatan_kepsek" value="<?php echo htmlspecialchars($sekolah['jabatan_kepsek'] ?? ''); ?>" placeholder="Contoh: Pembina Tk. I, IV/b">
                                    </div>
                                </div>
                                <div class="text-end mt-4 pt-2 border-top">
                                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan Data Pejabat</button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 3: TA & TANGGAL -->
                        <div class="tab-pane fade" id="content-ta" role="tabpanel">
                            <form action="pengaturan_aksi.php?aksi=update_pengaturan" method="POST">
                                <input type="hidden" name="pengaturan[fase_aktif]" value="D">
                                
                                <div class="form-section-title"><i class="bi bi-sliders"></i>Parameter Akademik</div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="semester_aktif" class="form-label">Semester Aktif Saat Ini</label>
                                        <select class="form-select" id="semester_aktif" name="pengaturan[semester_aktif]">
                                            <option value="1" <?php if(($pengaturan['semester_aktif'] ?? '1') == '1') echo 'selected'; ?>>Semester 1 (Ganjil)</option>
                                            <option value="2" <?php if(($pengaturan['semester_aktif'] ?? '1') == '2') echo 'selected'; ?>>Semester 2 (Genap)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="kkm" class="form-label">KKM / Batas Tuntas (0-100)</label>
                                        <input type="number" class="form-control" id="kkm" name="pengaturan[kkm]" value="<?php echo htmlspecialchars($pengaturan['kkm'] ?? '75'); ?>" min="0" max="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tanggal_rapor_pts" class="form-label">Tanggal Rapor Tengah Semester (PTS)</label>
                                        <input type="date" class="form-control" id="tanggal_rapor_pts" name="pengaturan[tanggal_rapor_pts]" value="<?php echo htmlspecialchars($pengaturan['tanggal_rapor_pts'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tanggal_rapor" class="form-label">Tanggal Rapor Akhir Semester (PAS/PAT)</label>
                                        <input type="date" class="form-control" id="tanggal_rapor" name="pengaturan[tanggal_rapor]" value="<?php echo htmlspecialchars($pengaturan['tanggal_rapor'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="text-end mb-4">
                                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan Parameter</button>
                                </div>
                            </form>

                            <div class="form-section-title mt-5"><i class="bi bi-calendar-range"></i>Manajemen Tahun Ajaran</div>
                            <div class="row g-4">
                                <div class="col-md-7">
                                    <div class="table-responsive border rounded">
                                        <table class="table table-hover table-striped align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3">Tahun Ajaran</th>
                                                    <th class="text-center">Status</th>
                                                    <th class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($daftar_tahun_ajaran as $ta): ?>
                                                <tr>
                                                    <td class="ps-3 fw-bold"><?php echo htmlspecialchars($ta['tahun_ajaran']); ?></td>
                                                    <td class="text-center">
                                                        <?php if ($ta['status'] == 'Aktif'): ?>
                                                            <span class="badge bg-success rounded-pill px-3">Aktif</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary rounded-pill px-3">Nonaktif</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($ta['status'] == 'Tidak Aktif'): ?>
                                                            <a href="pengaturan_aksi.php?aksi=aktifkan_ta&id=<?php echo $ta['id_tahun_ajaran']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Aktifkan</a>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-primary rounded-pill px-3" disabled><i class="bi bi-check-lg"></i></button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">Tambah Tahun Ajaran Baru</h6>
                                            <form action="pengaturan_aksi.php?aksi=tambah_ta" method="POST">
                                                <div class="mb-3">
                                                    <label class="form-label">Format: YYYY/YYYY</label>
                                                    <input type="text" class="form-control" name="tahun_ajaran" placeholder="Contoh: 2026/2027" required>
                                                </div>
                                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-lg me-2"></i>Tambahkan</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: MODIFIKASI RAPOR (FITUR UTAMA) -->
                        <div class="tab-pane fade" id="content-rapor" role="tabpanel">
                            
                            <!-- 1. Tampilan Dasar (Kertas & Warna) -->
                            <form action="pengaturan_aksi.php?aksi=update_pengaturan" method="POST">
                                <div class="form-section-title"><i class="bi bi-layout-text-window-reverse"></i>Layout & Warna</div>
                                <div class="row g-4 mb-4">
                                    <div class="col-md-4">
                                        <label for="rapor_ukuran_kertas" class="form-label">Ukuran Kertas PDF</label>
                                        <select class="form-select form-select-lg" id="rapor_ukuran_kertas" name="pengaturan[rapor_ukuran_kertas]">
                                            <option value="A4" <?php if(($pengaturan['rapor_ukuran_kertas'] ?? 'A4') == 'A4') echo 'selected'; ?>>A4 (210 x 297 mm)</option>
                                            <option value="F4" <?php if(($pengaturan['rapor_ukuran_kertas'] ?? 'A4') == 'F4') echo 'selected'; ?>>F4 (215 x 330 mm)</option>
                                        </select>
                                        <div class="form-text mt-2">Ukuran kertas ini berlaku untuk cetak Rapor PTS dan Rapor Akhir.</div>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label class="form-label mb-3">Skema Warna Header Tabel (Tema)</label>
                                        <?php $warna_terpilih = $pengaturan['rapor_skema_warna'] ?? 'bw'; ?>
                                        <div class="color-picker-container">
                                            
                                            <!-- Pilihan 1: Hitam Putih -->
                                            <div class="color-option-wrapper">
                                                <input class="color-option-input" type="radio" name="pengaturan[rapor_skema_warna]" value="bw" id="warna_bw" <?php if($warna_terpilih == 'bw') echo 'checked'; ?>>
                                                <label class="color-option-label" for="warna_bw">
                                                    <div class="color-circle" style="background-color: #333;"></div>
                                                    <span class="color-name">Hitam</span>
                                                </label>
                                            </div>

                                            <!-- Pilihan 2: Biru -->
                                            <div class="color-option-wrapper">
                                                <input class="color-option-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_blue" id="warna_blue" <?php if($warna_terpilih == 'light_blue') echo 'checked'; ?>>
                                                <label class="color-option-label" for="warna_blue">
                                                    <div class="color-circle" style="background-color: #0D47A1;"></div>
                                                    <span class="color-name">Biru</span>
                                                </label>
                                            </div>

                                            <!-- Pilihan 3: Hijau -->
                                            <div class="color-option-wrapper">
                                                <input class="color-option-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_green" id="warna_green" <?php if($warna_terpilih == 'light_green') echo 'checked'; ?>>
                                                <label class="color-option-label" for="warna_green">
                                                    <div class="color-circle" style="background-color: #1B5E20;"></div>
                                                    <span class="color-name">Hijau</span>
                                                </label>
                                            </div>

                                            <!-- Pilihan 4: Teal -->
                                            <div class="color-option-wrapper">
                                                <input class="color-option-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_teal" id="warna_teal" <?php if($warna_terpilih == 'light_teal') echo 'checked'; ?>>
                                                <label class="color-option-label" for="warna_teal">
                                                    <div class="color-circle" style="background-color: #004D40;"></div>
                                                    <span class="color-name">Teal</span>
                                                </label>
                                            </div>

                                            <!-- Pilihan 5: Ungu -->
                                            <div class="color-option-wrapper">
                                                <input class="color-option-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_purple" id="warna_purple" <?php if($warna_terpilih == 'light_purple') echo 'checked'; ?>>
                                                <label class="color-option-label" for="warna_purple">
                                                    <div class="color-circle" style="background-color: #311B92;"></div>
                                                    <span class="color-name">Ungu</span>
                                                </label>
                                            </div>

                                            <!-- Pilihan 6: Merah -->
                                            <div class="color-option-wrapper">
                                                <input class="color-option-input" type="radio" name="pengaturan[rapor_skema_warna]" value="light_red" id="warna_red" <?php if($warna_terpilih == 'light_red') echo 'checked'; ?>>
                                                <label class="color-option-label" for="warna_red">
                                                    <div class="color-circle" style="background-color: #B71C1C;"></div>
                                                    <span class="color-name">Merah</span>
                                                </label>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="text-end pb-4 mb-4 border-bottom">
                                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan Layout</button>
                                </div>
                            </form>

                            <!-- 2. Pengaturan KOP Surat (DI SINI SAYA TAMBAHKAN FITURNYA) -->
                            <!-- Menggunakan aksi 'simpan_kop' yang sudah diperbarui di backend -->
                            <form action="pengaturan_aksi.php?aksi=simpan_kop" method="POST" enctype="multipart/form-data">
                                <div class="form-section-title"><i class="bi bi-image"></i>Kustomisasi KOP Surat</div>
                                
                                <!-- === FITUR BARU: TOGGLE TANPA KOP === -->
                                <div class="modern-switch mb-3 bg-light border-0">
                                    <div class="switch-label-content">
                                        <h6><i class="bi bi-printer-fill me-2 text-dark"></i>Mode Tanpa KOP (Pre-printed)</h6>
                                        <small class="text-muted">Aktifkan jika kertas Anda sudah ada cetakan KOP-nya. Sistem tidak akan mencetak KOP digital.</small>
                                    </div>
                                    <div class="form-check form-switch form-switch-lg">
                                        <input class="form-check-input switch-lg" type="checkbox" role="switch" id="toggleTanpaKop" name="cetak_tanpa_kop" value="1" <?php if($cetak_tanpa_kop == '1') echo 'checked'; ?>>
                                    </div>
                                </div>

                                <!-- Input Margin Atas (Muncul jika Tanpa KOP Aktif) -->
                                <div id="areaMarginKop" class="bg-white border rounded p-4 mb-4 <?php echo ($cetak_tanpa_kop == '1') ? '' : 'd-none'; ?>">
                                    <label class="form-label text-primary fw-bold">Jarak Kosong dari Atas (Margin Top)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" name="margin_atas_tanpa_kop" class="form-control" value="<?php echo $margin_atas_tanpa_kop; ?>" placeholder="Contoh: 3.5">
                                        <span class="input-group-text bg-light fw-bold">cm</span>
                                    </div>
                                    <div class="form-text text-danger fst-italic mt-1">
                                        <i class="bi bi-ruler me-1"></i> Ukur jarak dari ujung atas kertas hingga baris pertama konten rapor dimulai.
                                    </div>
                                </div>
                                <!-- ==================================== -->

                                <!-- === AREA PENGATURAN GAMBAR KOP (YANG LAMA) === -->
                                <!-- Ini akan disembunyikan via JS jika Mode Tanpa KOP aktif -->
                                <div id="wrapper-pengaturan-gambar">
                                    <div class="modern-switch mb-4">
                                        <div class="switch-label-content">
                                            <h6>Gunakan Gambar KOP Kustom</h6>
                                            <small>Aktifkan untuk mengganti KOP teks standar dengan gambar scan/screenshot KOP sekolah Anda.</small>
                                        </div>
                                        <div class="form-check form-switch form-switch-lg">
                                            <input type="hidden" name="rapor_tampil_kop" value="0">
                                            <?php $tampil_kop = $pengaturan['rapor_tampil_kop'] ?? '0'; ?>
                                            <input class="form-check-input switch-lg" type="checkbox" role="switch" id="rapor_tampil_kop" name="rapor_tampil_kop" value="1" <?php if($tampil_kop == '1') echo 'checked'; ?>>
                                        </div>
                                    </div>

                                    <div id="area-upload-kop" class="bg-light border rounded p-4 mb-4 <?php echo ($tampil_kop == '1') ? '' : 'd-none'; ?>">
                                        <div class="row g-4">
                                            <div class="col-md-7">
                                                <label for="file_kop_sekolah" class="form-label mb-2">Upload File Gambar KOP</label>
                                                <!-- Pastikan name input file sesuai: file_kop (sesuai backend baru) -->
                                                <input type="file" class="form-control" id="file_kop_sekolah" name="file_kop" accept="image/png, image/jpeg, image/jpg">
                                                
                                                <div class="mt-3 p-3 bg-white border rounded">
                                                    <h6 class="text-primary fw-bold mb-2"><i class="bi bi-lightbulb"></i> Tips Hasil Terbaik:</h6>
                                                    <ul class="mb-0 small text-muted ps-3">
                                                        <li>Gunakan format <b>PNG</b> atau <b>JPG</b> kualitas tinggi.</li>
                                                        <li>Disarankan lebar gambar minimal <b>2000px</b> agar hasil cetak PDF tidak pecah/buram.</li>
                                                        <li>Crop gambar hanya pada bagian KOP (Logo + Teks + Garis Bawah).</li>
                                                        <li>Sistem akan otomatis menyesuaikan lebar gambar dengan lebar kertas (A4/F4).</li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label text-center w-100">Preview Gambar KOP Saat Ini</label>
                                                <div class="img-preview-container bg-white">
                                                    <?php if (!empty($kop_sekolah_sekarang) && file_exists('uploads/' . $kop_sekolah_sekarang)): ?>
                                                        <img src="uploads/<?php echo htmlspecialchars($kop_sekolah_sekarang); ?>" class="img-preview" alt="KOP Sekolah">
                                                        <div class="mt-2 text-success small"><i class="bi bi-check-circle-fill"></i> KOP Aktif</div>
                                                    <?php else: ?>
                                                        <div class="preview-placeholder">
                                                            <i class="bi bi-image"></i>
                                                            <span>Belum ada gambar diupload</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end pb-4 mb-4 border-bottom">
                                    <button type="submit" class="btn btn-success px-4"><i class="bi bi-upload me-2"></i>Simpan Pengaturan KOP</button>
                                </div>
                            </form>

                            <!-- 3. Watermark -->
                            <form action="pengaturan_aksi.php?aksi=simpan_watermark" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="watermark_lama" value="<?php echo htmlspecialchars($watermark_sekarang); ?>">
                                <div class="form-section-title"><i class="bi bi-droplet"></i>Watermark Halaman</div>
                                
                                <div class="row g-4 align-items-center">
                                    <div class="col-md-7">
                                        <div class="mb-3">
                                            <label for="file_watermark" class="form-label">Upload Logo Transparan (PNG)</label>
                                            <input type="file" class="form-control" id="file_watermark" name="file_watermark" accept="image/png">
                                            <div class="form-text">Gambar akan muncul samar di tengah halaman rapor. Wajib format PNG background transparan.</div>
                                        </div>
                                        
                                        <?php if (!empty($watermark_sekarang)): ?>
                                        <div class="d-flex gap-2">
                                             <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="hapus_watermark" name="hapus_watermark" value="1">
                                                <label class="form-check-label text-danger fw-bold" for="hapus_watermark">Hapus Watermark Saat Ini</label>
                                             </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label text-center w-100">Preview Watermark</label>
                                        <div class="img-preview-container">
                                            <?php if (!empty($watermark_sekarang) && file_exists('uploads/' . $watermark_sekarang)): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($watermark_sekarang); ?>" class="img-preview" style="opacity: 0.5;" alt="Watermark">
                                            <?php else: ?>
                                                <div class="preview-placeholder">
                                                    <i class="bi bi-file-earmark-x"></i>
                                                    <span>Tidak ada watermark</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Simpan Watermark</button>
                                </div>
                            </form>

                        </div> <!-- End Tab 4 -->

                    </div> <!-- End Tab Content -->
                </div> <!-- End Card Body -->
            </div>
        </div>
        
        <!-- Kolom Kanan: Sidebar Info (Sesuai kode asli) -->
        <div class="col-lg-4">
            <!-- Card Logo Sekolah -->
            <div class="side-card">
                <div class="side-card-header">
                    <i class="bi bi-image-fill"></i> Logo Sekolah
                </div>
                <div class="side-card-body text-center">
                    <form action="pengaturan_aksi.php?aksi=update_logo" method="POST" enctype="multipart/form-data">
                        <div class="mb-3 img-preview-container bg-light" style="min-height: 160px;">
                            <?php if (!empty($sekolah['logo_sekolah']) && file_exists('uploads/' . $sekolah['logo_sekolah'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($sekolah['logo_sekolah']); ?>" alt="Logo Sekolah" class="img-preview">
                            <?php else: ?>
                                <div class="preview-placeholder">
                                    <i class="bi bi-image-alt"></i>
                                    <span>Logo belum diatur</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label for="logo_sekolah" class="form-label text-start w-100 small">Ganti Logo (PNG/JPG, Maks 1MB)</label>
                        <input class="form-control form-control-sm mb-3" type="file" id="logo_sekolah" name="logo_sekolah" accept="image/png, image/jpeg">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-2"></i>Upload Logo Baru</button>
                    </form>
                </div>
            </div>

            <!-- Card Backup -->
            <div class="side-card">
                <div class="side-card-header">
                    <i class="bi bi-database-fill-gear"></i> Keamanan Data
                </div>
                <div class="side-card-body">
                    <p class="small text-muted mb-3">
                        Lakukan pencadangan (backup) data secara berkala untuk menghindari kehilangan data akibat kesalahan sistem atau human error.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="pengaturan_backup_tampil.php" class="btn btn-outline-primary">
                            <i class="bi bi-shield-check me-2"></i>Menu Backup & Restore
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card Info -->
            <div class="side-card">
                <div class="side-card-header bg-info bg-opacity-10 text-info-emphasis border-info border-opacity-25">
                    <i class="bi bi-info-circle-fill"></i> Informasi Sistem
                </div>
                <div class="side-card-body">
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Versi Aplikasi: <strong>V2.0.1 Rev</strong></li>
                        <li class="mb-2"><i class="bi bi-hdd-network text-primary me-2"></i>Status PHP: <strong><?php echo phpversion(); ?></strong></li>
                        <li><i class="bi bi-database text-warning me-2"></i>Database: <strong>MySQL</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Script Interaksi UI
    document.addEventListener('DOMContentLoaded', function() {
        
        // 1. LOGIKA TOGGLE TANPA KOP (FITUR BARU)
        const toggleTanpaKop = document.getElementById('toggleTanpaKop');
        const areaMargin = document.getElementById('areaMarginKop');
        const wrapperGambar = document.getElementById('wrapper-pengaturan-gambar');

        if(toggleTanpaKop && areaMargin && wrapperGambar) {
            function updateTanpaKop() {
                if (toggleTanpaKop.checked) {
                    // Jika Tanpa KOP ON: Tampilkan Margin, Sembunyikan Pengaturan Gambar
                    areaMargin.classList.remove('d-none');
                    wrapperGambar.classList.add('d-none');
                } else {
                    // Jika Tanpa KOP OFF: Sembunyikan Margin, Tampilkan Pengaturan Gambar
                    areaMargin.classList.add('d-none');
                    wrapperGambar.classList.remove('d-none');
                }
            }
            // Jalankan saat load dan saat berubah
            updateTanpaKop();
            toggleTanpaKop.addEventListener('change', updateTanpaKop);
        }

        // 2. Toggle Area Upload KOP (FITUR LAMA)
        const toggleKop = document.getElementById('rapor_tampil_kop');
        const areaUpload = document.getElementById('area-upload-kop');
        
        if(toggleKop && areaUpload) {
            toggleKop.addEventListener('change', function() {
                if (this.checked) {
                    areaUpload.classList.remove('d-none');
                    areaUpload.style.opacity = 0;
                    setTimeout(() => {
                        areaUpload.style.transition = 'opacity 0.3s';
                        areaUpload.style.opacity = 1;
                    }, 10);
                } else {
                    areaUpload.classList.add('d-none');
                }
            });
        }

        // 3. Preview Nama File saat di-select
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const file = this.files[0];
                if (file) {
                    if (file.size > 2 * 1024 * 1024) { // 2MB
                        alert('Ukuran file terlalu besar! Maksimal 2MB.');
                        this.value = ''; 
                    }
                }
            });
        });

    });
</script>

<?php include 'footer.php'; ?>