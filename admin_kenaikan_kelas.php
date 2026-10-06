<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Akses Ditolak',
            text: 'Hanya Admin yang dapat mengakses halaman ini.',
            confirmButtonColor: '#d33'
        }).then(() => {
            window.location = 'dashboard.php';
        });
    </script>";
    include 'footer.php';
    exit;
}

// Ambil info tahun ajaran aktif dan berikutnya
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta_aktif);
if (!$ta_aktif) {
    echo "<div class='container-fluid mt-4'><div class='alert alert-danger shadow-sm border-0'>
            <h4 class='alert-heading'><i class='bi bi-exclamation-octagon-fill me-2'></i>Sistem Belum Siap</h4>
            <p>Tidak ada Tahun Ajaran yang berstatus 'Aktif'. Silakan atur di halaman Pengaturan terlebih dahulu.</p>
          </div></div>";
    include 'footer.php'; exit;
}
// Cari T.A berikutnya (yang statusnya tidak aktif dan tahunnya > tahun aktif)
$q_ta_berikutnya = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Tidak Aktif' AND tahun_ajaran > '{$ta_aktif['tahun_ajaran']}' ORDER BY tahun_ajaran ASC LIMIT 1");
$ta_berikutnya = mysqli_fetch_assoc($q_ta_berikutnya);

// Ambil semua kelas di tahun ajaran aktif
$kelas_aktif_result = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = {$ta_aktif['id_tahun_ajaran']} ORDER BY nama_kelas ASC");

// Ambil semua kelas di tahun ajaran berikutnya
$kelas_baru_result = $ta_berikutnya ? mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = {$ta_berikutnya['id_tahun_ajaran']} ORDER BY nama_kelas ASC") : false;

// Ambil ID kelas yang dipilih dari URL (jika ada)
$id_kelas_pilihan = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
$siswa_di_kelas = [];
$nama_kelas_pilihan = ''; // Simpan nama kelas yang dipilih
if ($id_kelas_pilihan > 0) {
    // Ambil nama kelas pilihan
    $q_nama_kelas = mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas = $id_kelas_pilihan");
    if($n_kls = mysqli_fetch_assoc($q_nama_kelas)) $nama_kelas_pilihan = $n_kls['nama_kelas'];

    // Ambil siswa di kelas pilihan
    $siswa_query = mysqli_query($koneksi, "SELECT DISTINCT s.id_siswa, s.nama_lengkap FROM siswa s LEFT JOIN rapor r ON s.id_siswa = r.id_siswa WHERE (s.id_kelas = $id_kelas_pilihan OR r.id_kelas = $id_kelas_pilihan) AND s.status_siswa = 'Aktif' ORDER BY s.nama_lengkap ASC");
    while($row = mysqli_fetch_assoc($siswa_query)){
        $siswa_di_kelas[] = $row;
    }
}

// Logika untuk menentukan tingkat akhir
$q_sekolah = mysqli_query($koneksi, "SELECT jenjang FROM sekolah LIMIT 1");
$jenjang_sekolah = mysqli_fetch_assoc($q_sekolah)['jenjang'] ?? 'SMP'; // Default SMP

// Cek apakah kelas yang dipilih adalah kelas tingkat akhir
$is_kelas_akhir = false;
if (!empty($nama_kelas_pilihan)) {
    $nama_kelas_upper = strtoupper($nama_kelas_pilihan);
    $jenjang_upper = strtoupper($jenjang_sekolah);

    if ($jenjang_upper == 'SD' || $jenjang_upper == 'MI') {
        if ((strpos($nama_kelas_upper, 'VI') !== false && strpos($nama_kelas_upper, 'VII') === false && strpos($nama_kelas_upper, 'VIII') === false) || strpos($nama_kelas_upper, '6') !== false) {
            $is_kelas_akhir = true;
        }
    } elseif ($jenjang_upper == 'SMA' || $jenjang_upper == 'SMK' || $jenjang_upper == 'MA') {
        if (strpos($nama_kelas_upper, 'XII') !== false || strpos($nama_kelas_upper, '12') !== false) {
            $is_kelas_akhir = true;
        }
    } else {
        // Default SMP
        if (strpos($nama_kelas_upper, 'IX') !== false || strpos($nama_kelas_upper, '9') !== false) {
            $is_kelas_akhir = true;
        }
    }
}
?>

<style>
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .page-header h1 { font-weight: 800; letter-spacing: -0.5px; }
    .table th, .table td { vertical-align: middle; }
    /* Tambahkan style untuk table-responsive jika tabel siswa panjang */
    .table-siswa-container { max-height: 50vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.5rem; }
    .footer-actions { background-color: white; border-top: 1px solid #edf2f7; padding: 1.5rem; border-radius: 0 0 1rem 1rem; }
    .card { border: none; border-radius: 1rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .card-header { background-color: #fff; padding: 1.5rem; border-bottom: 1px solid #edf2f7; border-radius: 1rem 1rem 0 0 !important; }
    
    .step-badge {
        background-color: var(--primary-color); color: white;
        width: 30px; height: 30px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: bold; margin-right: 10px;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white">
        <h1 class="mb-1">Proses Kenaikan Kelas</h1>
        <p class="lead mb-0 opacity-90">
            <span class="badge bg-white text-primary me-2"><?php echo htmlspecialchars($ta_aktif['tahun_ajaran']); ?></span>
            <i class="bi bi-arrow-right mx-2"></i>
            <?php if ($ta_berikutnya): ?>
                <span class="badge bg-success bg-opacity-75 text-white"><?php echo htmlspecialchars($ta_berikutnya['tahun_ajaran']); ?></span>
            <?php else: ?>
                <span class="badge bg-danger">?</span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Peringatan jika T.A berikutnya atau kelasnya belum siap -->
    <?php if (!$ta_berikutnya || !$kelas_baru_result || mysqli_num_rows($kelas_baru_result) == 0): ?>
        <div class="alert alert-warning shadow-sm border-0 rounded-3 p-4">
            <div class="d-flex">
                <div class="me-3">
                    <i class="bi bi-exclamation-triangle-fill fs-1 text-warning"></i>
                </div>
                <div>
                    <h4 class="alert-heading fw-bold">Konfigurasi Belum Lengkap</h4>
                    <p>Sistem tidak menemukan data Tahun Ajaran berikutnya atau Kelas untuk tahun tersebut belum dibuat.</p>
                    <hr>
                    <p class="mb-2 fw-bold">Solusi:</p>
                    <ol class="mb-0">
                        <li>Tambahkan Tahun Ajaran baru (misal: <?php echo substr($ta_aktif['tahun_ajaran'], 0, 5) . (intval(substr($ta_aktif['tahun_ajaran'], 5, 4)) + 1); ?>/<?php echo (intval(substr($ta_aktif['tahun_ajaran'], 0, 4)) + 2); ?>) di menu <b>Pengaturan</b>.</li>
                        <li>Buat data kelas-kelas baru untuk Tahun Ajaran tersebut di menu <b>Kelas & Siswa</b>.</li>
                    </ol>
                    <div class="mt-3">
                        <a href="pengaturan_tampil.php" class="btn btn-warning text-dark fw-bold"><i class="bi bi-gear-fill me-2"></i>Ke Pengaturan</a>
                        <a href="kelas_tampil.php" class="btn btn-outline-dark ms-2"><i class="bi bi-door-open-fill me-2"></i>Ke Manajemen Kelas</a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Form Pemilihan Kelas Asal -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0 d-flex align-items-center text-dark fw-bold">
                    <span class="step-badge">1</span> Pilih Kelas Asal (T.A <?php echo htmlspecialchars($ta_aktif['tahun_ajaran']); ?>)
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="GET" action="" id="formPilihKelas">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <select name="id_kelas" class="form-select form-select-lg bg-light" onchange="document.getElementById('formPilihKelas').submit()">
                                <option value="">-- Pilih Kelas untuk Menampilkan Siswa --</option>
                                <?php mysqli_data_seek($kelas_aktif_result, 0); while($kls = mysqli_fetch_assoc($kelas_aktif_result)): ?>
                                    <option value="<?php echo $kls['id_kelas']; ?>" <?php if($id_kelas_pilihan == $kls['id_kelas']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($kls['nama_kelas']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mt-3 mt-md-0 d-grid">
                            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-search me-2"></i>Tampilkan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Siswa dan Form Aksi (Hanya tampil jika kelas sudah dipilih) -->
        <?php if ($id_kelas_pilihan > 0): ?>
        <!-- PERUBAHAN: Form Aksi Massal dengan Status Individual -->
        <form action="admin_aksi.php?aksi=proses_kenaikan_siswa_massal" method="POST" id="formProsesKenaikan">
            <input type="hidden" name="id_kelas_lama" value="<?php echo $id_kelas_pilihan; ?>">

            <div class="card shadow-sm">
                <!-- Bulk Action Toolbar -->
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-3 py-3" style="border-bottom: 2px solid var(--primary-color);">
                     <div class="d-flex align-items-center">
                        <span class="step-badge">2</span>
                        <h6 class="mb-0 text-dark fw-bold me-3">Atur Status Massal:</h6>
                     </div>
                     <div class="d-flex flex-wrap gap-2 flex-grow-1">
                        <select id="bulk-tindakan" class="form-select form-select-sm shadow-sm" style="width: auto; min-width: 150px;">
                            <option value="">-- Tindakan --</option>
                            <?php if ($is_kelas_akhir): ?>
                                <option value="luluskan">🎓 Luluskan</option>
                                <option value="tinggal">🔁 Tinggal Kelas</option>
                            <?php else: ?>
                                <option value="naik">📈 Naik Kelas</option>
                                <option value="tinggal">🔁 Tinggal Kelas</option>
                            <?php endif; ?>
                        </select>
                        <select id="bulk-tujuan" class="form-select form-select-sm shadow-sm" style="width: auto; min-width: 150px;" disabled>
                            <option value="">-- Kelas Tujuan --</option>
                            <?php mysqli_data_seek($kelas_baru_result, 0); while($kb = mysqli_fetch_assoc($kelas_baru_result)): ?>
                                <option value="<?php echo $kb['id_kelas']; ?>"><?php echo htmlspecialchars($kb['nama_kelas']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm fw-bold" onclick="terapkanMassal()">
                            <i class="bi bi-magic me-1"></i> Terapkan ke Semua
                        </button>
                     </div>
                </div>

                <!-- Table Container -->
                <div class="card-body p-0">
                    <div class="table-siswa-container m-0 border-0" style="max-height: 60vh;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top shadow-sm" style="z-index: 5;">
                                <tr>
                                    <th width="50px" class="text-center">No</th>
                                    <th>Nama Siswa</th>
                                    <th width="200px">Status Tindakan</th>
                                    <th width="220px">Kelas Tujuan (T.A Baru)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($siswa_di_kelas)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-person-x fs-1 opacity-50 d-block mb-2"></i>Tidak ada siswa aktif di kelas ini.</td></tr>
                                <?php else: ?>
                                    <?php $no=1; foreach($siswa_di_kelas as $siswa): ?>
                                    <tr>
                                        <td class="text-center text-muted fw-bold"><?php echo $no++; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 40px; height: 40px; font-weight: bold; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($siswa['nama_lengkap'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></div>
                                                    <input type="hidden" name="id_siswa[]" value="<?php echo $siswa['id_siswa']; ?>">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <select name="tindakan[<?php echo $siswa['id_siswa']; ?>]" class="form-select form-select-sm select-tindakan-row border-primary" onchange="updateTujuanRow(this, <?php echo $siswa['id_siswa']; ?>)" required>
                                                <option value="" disabled selected>Pilih...</option>
                                                <?php if ($is_kelas_akhir): ?>
                                                    <option value="luluskan">🎓 Luluskan</option>
                                                    <option value="tinggal">🔁 Tinggal Kelas</option>
                                                <?php else: ?>
                                                    <option value="naik">📈 Naik Kelas</option>
                                                    <option value="tinggal">🔁 Tinggal Kelas</option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="id_kelas_baru[<?php echo $siswa['id_siswa']; ?>]" id="tujuan_<?php echo $siswa['id_siswa']; ?>" class="form-select form-select-sm select-tujuan-row bg-light" disabled>
                                                <option value="">- Otomatis -</option>
                                                <?php mysqli_data_seek($kelas_baru_result, 0); while($kb = mysqli_fetch_assoc($kelas_baru_result)): ?>
                                                    <option value="<?php echo $kb['id_kelas']; ?>"><?php echo htmlspecialchars($kb['nama_kelas']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Aksi (Hanya Tampil Jika Ada Siswa) -->
                <?php if(!empty($siswa_di_kelas)): ?>
                <div class="footer-actions bg-white shadow-sm border-top">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <span class="step-badge">3</span>
                            <div>
                                <h6 class="mb-0 text-dark fw-bold">Simpan Perubahan</h6>
                                <small class="text-muted">Pastikan status semua siswa sudah benar sebelum memproses.</small>
                            </div>
                        </div>
                        <button type="button" onclick="konfirmasiProses()" class="btn btn-success btn-lg shadow fw-bold px-5 rounded-pill">
                            <i class="bi bi-save2-fill me-2"></i> Proses Semua Siswa
                        </button>
                    </div>
                </div>
                 <?php endif; ?>
            </div>
        </form>
        <?php endif; // End if ($id_kelas_pilihan > 0) ?>
    <?php endif; // End if (!$ta_berikutnya...) ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bulkTindakan = document.getElementById('bulk-tindakan');
    const bulkTujuan = document.getElementById('bulk-tujuan');

    // Fungsi Enable/Disable Kelas Tujuan pada Bulk Bar
    if(bulkTindakan && bulkTujuan) {
        bulkTindakan.addEventListener('change', function() {
            const tindakan = this.value;
            if (tindakan === 'naik' || tindakan === 'tinggal') {
                bulkTujuan.disabled = false;
                bulkTujuan.classList.add('border-primary');
            } else {
                bulkTujuan.disabled = true;
                bulkTujuan.value = '';
                bulkTujuan.classList.remove('border-primary');
            }
        });
    }
});

// Fungsi update row-level ketika tindakan individual berubah
function updateTujuanRow(selectObj, idSiswa) {
    const tindakan = selectObj.value;
    const selectTujuan = document.getElementById('tujuan_' + idSiswa);

    if (tindakan === 'naik' || tindakan === 'tinggal') {
        selectTujuan.disabled = false;
        selectTujuan.required = true;
        selectTujuan.classList.remove('bg-light');
        selectTujuan.classList.add('border-primary');
        // Kosongkan label otomatis jika belum ada pilihan
        if(selectTujuan.options[0].value === "") {
            selectTujuan.options[0].text = "-- Pilih Kelas --";
        }
    } else {
        selectTujuan.disabled = true;
        selectTujuan.required = false;
        selectTujuan.value = '';
        selectTujuan.classList.add('bg-light');
        selectTujuan.classList.remove('border-primary');
        selectTujuan.options[0].text = "- Otomatis -";
    }

    // Warnai background dropdown tindakan
    if(tindakan === 'naik') selectObj.style.backgroundColor = '#e8f5e9'; // green light
    else if(tindakan === 'tinggal') selectObj.style.backgroundColor = '#fff3cd'; // yellow light
    else if(tindakan === 'luluskan') selectObj.style.backgroundColor = '#d1e7dd'; // teal light
    else selectObj.style.backgroundColor = '';
}

// Fungsi Terapkan Ke Semua (Bulk Apply)
function terapkanMassal() {
    const bulkTindakan = document.getElementById('bulk-tindakan').value;
    const bulkTujuan = document.getElementById('bulk-tujuan').value;

    if(!bulkTindakan) {
        Swal.fire({ icon: 'warning', title: 'Pilih Tindakan', text: 'Pilih tindakan massal terlebih dahulu di menu atas.' });
        return;
    }
    if((bulkTindakan === 'naik' || bulkTindakan === 'tinggal') && !bulkTujuan) {
        Swal.fire({ icon: 'warning', title: 'Pilih Kelas Tujuan', text: 'Pilih kelas tujuan untuk tindakan ini.' });
        return;
    }

    // Terapkan ke semua row
    const actionSelects = document.querySelectorAll('.select-tindakan-row');
    actionSelects.forEach(select => {
        select.value = bulkTindakan;
        // Panggil event onchange secara manual agar logika disable/enable berjalan
        select.dispatchEvent(new Event('change'));
    });

    const targetSelects = document.querySelectorAll('.select-tujuan-row');
    targetSelects.forEach(select => {
        if(!select.disabled) {
            select.value = bulkTujuan;
        }
    });

    // Beri feedback visual kecil
    const Toast = Swal.mixin({
      toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true
    });
    Toast.fire({ icon: 'success', title: 'Diterapkan ke semua baris!' });
}

// Fungsi Konfirmasi Proses Submit
function konfirmasiProses() {
    const form = document.getElementById('formProsesKenaikan');

    // Cek kelengkapan form dengan HTML5 reportValidity
    if (!form.reportValidity()) {
        return; // Hentikan jika ada row yang required tapi kosong
    }

    // Bangun Pesan Konfirmasi HTML
    let htmlContent = `
        <div class="text-start bg-light p-3 rounded border">
            <div class="mb-2">Anda akan memproses status untuk seluruh siswa di kelas ini secara bersamaan.</div>
            <div class="mt-2 text-danger small fw-bold">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Pastikan semua pilihan di masing-masing baris sudah benar!
            </div>
        </div>
    `;

    Swal.fire({
        title: 'Proses Semua Siswa?',
        html: htmlContent,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-save2-fill me-1"></i> Ya, Simpan Semua!',
        cancelButtonText: 'Cek Kembali',
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sedang Memproses...',
                text: 'Memindahkan data siswa, mohon tunggu.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            form.submit();
        }
    });
}
</script>

<?php
// Tampilkan pesan sukses/error dari session jika ada (Format Modern)
if (isset($_SESSION['pesan'])) {
    $pesan = $_SESSION['pesan'];
    $data_json = json_decode($pesan, true);

    if (json_last_error() == JSON_ERROR_NONE && is_array($data_json)) {
        // Jika JSON valid
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '" . addslashes($data_json['icon']) . "',
                    title: '" . addslashes($data_json['title']) . "',
                    html: '" . addslashes($data_json['html']) . "',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false
                });
            });
        </script>";
    } else {
        // Jika Plain Text
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success', 
                    title: 'Berhasil!', 
                    html: '" . addslashes($pesan) . "',
                    timer: 2000,
                    timerProgressBar: true
                });
            });
        </script>";
    }
    unset($_SESSION['pesan']); // Hapus pesan setelah ditampilkan
} elseif (isset($_SESSION['error'])) { 
    // Handle session error
    $error_pesan = $_SESSION['error'];
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error', 
                title: 'Gagal!', 
                html: '" . addslashes($error_pesan) . "'
            });
        });
    </script>";
    unset($_SESSION['error']);
}

include 'footer.php';
?>