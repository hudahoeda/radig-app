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
    $siswa_query = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas_pilihan AND status_siswa = 'Aktif' ORDER BY nama_lengkap ASC");
    while($row = mysqli_fetch_assoc($siswa_query)){
        $siswa_di_kelas[] = $row;
    }
}

// Logika untuk menentukan tingkat akhir
$q_sekolah = mysqli_query($koneksi, "SELECT jenjang FROM sekolah LIMIT 1");
$jenjang_sekolah = mysqli_fetch_assoc($q_sekolah)['jenjang'] ?? 'SMP'; // Default SMP
// Pola tingkat akhir (SD: 6, VI; SMP: 9, IX)
$tingkat_akhir_patterns = ($jenjang_sekolah == 'SD') ? ['6', 'VI'] : (($jenjang_sekolah == 'SMP') ? ['9', 'IX'] : []); // Kosong jika bukan SD/SMP

// Cek apakah kelas yang dipilih adalah kelas tingkat akhir
$is_kelas_akhir = false;
if (!empty($nama_kelas_pilihan) && !empty($tingkat_akhir_patterns)) {
    foreach ($tingkat_akhir_patterns as $pattern) {
        // Cek jika nama kelas diawali dengan angka/romawi tingkat akhir (case insensitive)
        if (stripos($nama_kelas_pilihan, $pattern) === 0) {
            $is_kelas_akhir = true;
            break;
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
        <!-- PERUBAHAN: Hapus onsubmit, tambahkan ID form, ubah action button type -->
        <form action="admin_aksi.php?aksi=proses_kenaikan_siswa" method="POST" id="formProsesKenaikan">
            <input type="hidden" name="id_kelas_lama" value="<?php echo $id_kelas_pilihan; ?>">

            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                     <h5 class="mb-0 text-dark fw-bold d-flex align-items-center">
                        <span class="step-badge">2</span> Pilih Siswa dari <?php echo htmlspecialchars($nama_kelas_pilihan); ?>
                     </h5>
                    <?php if(!empty($siswa_di_kelas)): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="pilihSemua">
                        <i class="bi bi-check2-all me-1"></i> Pilih Semua
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-siswa-container m-3"> 
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top"> 
                                <tr>
                                    <th class="text-center" width="60px"><i class="bi bi-check-lg"></i></th>
                                    <th>Nama Siswa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($siswa_di_kelas)): ?>
                                    <tr><td colspan="2" class="text-center text-muted py-5"><i class="bi bi-person-x fs-1 opacity-50 d-block mb-2"></i>Tidak ada siswa aktif di kelas ini.</td></tr>
                                <?php else: ?>
                                    <?php foreach($siswa_di_kelas as $siswa): ?>
                                    <tr>
                                        <td class="text-center">
                                            <div class="form-check d-flex justify-content-center">
                                                <input class="form-check-input siswa-checkbox" type="checkbox" name="id_siswa[]" value="<?php echo $siswa['id_siswa']; ?>" style="transform: scale(1.3);">
                                            </div>
                                        </td>
                                        <td class="fw-medium text-dark"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Aksi (Hanya Tampil Jika Ada Siswa) -->
                <?php if(!empty($siswa_di_kelas)): ?>
                <div class="footer-actions bg-light">
                    <h5 class="mb-3 text-dark fw-bold d-flex align-items-center">
                        <span class="step-badge">3</span> Konfirmasi Tindakan
                    </h5>
                    <div class="row align-items-end g-3">
                        <div class="col-md-5 col-lg-4">
                            <label for="select-tindakan" class="form-label text-muted fw-bold small text-uppercase">Tindakan</label>
                             <select name="tindakan" class="form-select form-select-lg shadow-sm border-primary" id="select-tindakan" required>
                                <option value="" disabled selected>-- Pilih Tindakan --</option>
                                <?php if ($is_kelas_akhir): ?>
                                    <option value="luluskan">🎓 Luluskan Siswa</option>
                                    <option value="tinggal">🔁 Tinggal Kelas (Di Tingkat Ini)</option>
                                <?php else: ?>
                                    <option value="naik">📈 Naik ke Kelas Berikutnya</option>
                                    <option value="tinggal">🔁 Tinggal Kelas (Di Tingkat Ini)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                         <div class="col-md-5 col-lg-6">
                            <label for="select-tujuan" class="form-label text-muted fw-bold small text-uppercase">Kelas Tujuan (T.A <?php echo htmlspecialchars($ta_berikutnya['tahun_ajaran']); ?>)</label>
                            <select name="id_kelas_baru" class="form-select form-select-lg shadow-sm" id="select-tujuan" disabled>
                                <option value="">-- Pilih Kelas Tujuan --</option>
                                <?php mysqli_data_seek($kelas_baru_result, 0); while($kb = mysqli_fetch_assoc($kelas_baru_result)): ?>
                                    <option value="<?php echo $kb['id_kelas']; ?>"><?php echo htmlspecialchars($kb['nama_kelas']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text text-primary" id="help-tujuan" style="display: none;"><i class="bi bi-info-circle me-1"></i>Pilih kelas tujuan untuk Tahun Ajaran baru.</div>
                        </div>
                        <div class="col-md-2 col-lg-2 d-grid">
                            <!-- Button type changed to button to trigger JS -->
                            <button type="button" onclick="konfirmasiProses()" class="btn btn-success btn-lg shadow fw-bold"><i class="bi bi-check-lg me-2"></i>Proses</button>
                        </div>
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
    const pilihSemuaBtn = document.getElementById('pilihSemua');
    const checkboxes = document.querySelectorAll('.siswa-checkbox');
    const selectTindakan = document.getElementById('select-tindakan');
    const selectTujuan = document.getElementById('select-tujuan');
    const helpTujuan = document.getElementById('help-tujuan');

    // Fungsi Pilih/Lepas Semua
    if(pilihSemuaBtn && checkboxes.length > 0) {
        pilihSemuaBtn.addEventListener('click', function() {
            // Cek apakah semua sudah terpilih
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            // Lakukan aksi kebalikan
            checkboxes.forEach(cb => cb.checked = !allChecked);
            
            // Ubah teks tombol
            if(!allChecked) {
                 this.innerHTML = '<i class="bi bi-x-lg me-1"></i> Batal Pilih';
                 this.classList.replace('btn-outline-primary', 'btn-outline-danger');
            } else {
                 this.innerHTML = '<i class="bi bi-check2-all me-1"></i> Pilih Semua';
                 this.classList.replace('btn-outline-danger', 'btn-outline-primary');
            }
        });
    }

    // Fungsi Enable/Disable Kelas Tujuan
    if(selectTindakan && selectTujuan) {
        selectTindakan.addEventListener('change', function() {
            const tindakan = this.value;
            // Aktifkan jika 'naik' atau 'tinggal', Nonaktifkan jika 'luluskan' atau kosong
            if (tindakan === 'naik' || tindakan === 'tinggal') {
                selectTujuan.disabled = false;
                selectTujuan.required = true;
                selectTujuan.classList.add('border-primary');
                helpTujuan.style.display = 'block'; 
            } else {
                selectTujuan.disabled = true;
                selectTujuan.required = false;
                selectTujuan.value = ''; // Reset pilihan
                selectTujuan.classList.remove('border-primary');
                helpTujuan.style.display = 'none'; 
            }
        });
         // Panggil sekali saat load untuk inisialisasi state
         if(selectTindakan.value) selectTindakan.dispatchEvent(new Event('change'));
    }
});

// Fungsi Konfirmasi dengan SweetAlert2
function konfirmasiProses() {
    const form = document.getElementById('formProsesKenaikan');
    const checkboxes = document.querySelectorAll('.siswa-checkbox:checked');
    const selectTindakan = document.getElementById('select-tindakan');
    const selectTujuan = document.getElementById('select-tujuan');
    const tindakan = selectTindakan.value;

    // Validasi input
    if (checkboxes.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Belum ada siswa',
            text: 'Silakan pilih minimal satu siswa dari daftar.',
            confirmButtonColor: '#f39c12'
        });
        return;
    }
    if (tindakan === '') {
         Swal.fire({
            icon: 'warning',
            title: 'Tindakan Kosong',
            text: 'Silakan pilih tindakan yang akan dilakukan.',
            confirmButtonColor: '#f39c12'
        });
         return;
    }
     if ((tindakan === 'naik' || tindakan === 'tinggal') && selectTujuan.value === '') {
         Swal.fire({
            icon: 'warning',
            title: 'Kelas Tujuan Kosong',
            text: 'Silakan pilih kelas tujuan untuk tahun ajaran baru.',
            confirmButtonColor: '#f39c12'
        });
         return;
    }

    // Bangun Pesan Konfirmasi HTML
    let actionText = selectTindakan.options[selectTindakan.selectedIndex].text;
    let targetClassText = (tindakan === 'naik' || tindakan === 'tinggal') ? selectTujuan.options[selectTujuan.selectedIndex].text : '-';
    
    let htmlContent = `
        <div class="text-start bg-light p-3 rounded border">
            <div class="mb-2">Anda akan memproses: <b>${checkboxes.length} Siswa</b></div>
            <div class="mb-2">Tindakan: <b>${actionText}</b></div>
            ${(tindakan !== 'luluskan') ? `<div class="mb-2">Kelas Tujuan: <b>${targetClassText}</b></div>` : ''}
        </div>
        <div class="mt-3 text-danger small fst-italic">
            <i class="bi bi-exclamation-circle me-1"></i> Data siswa akan dipindahkan ke Tahun Ajaran Baru. Pastikan data sudah benar.
        </div>
    `;

    Swal.fire({
        title: 'Konfirmasi Proses',
        html: htmlContent,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#d33',
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Ya, Proses Sekarang!',
        cancelButtonText: 'Batal',
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sedang Memproses...',
                text: 'Mohon tunggu sebentar.',
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