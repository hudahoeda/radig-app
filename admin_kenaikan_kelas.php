<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Hanya Admin yang dapat mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil info tahun ajaran aktif dan berikutnya
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta_aktif);
if (!$ta_aktif) {
    echo "<div class='container-fluid'><div class='alert alert-danger'>Error: Tidak ada Tahun Ajaran yang berstatus 'Aktif'. Silakan atur di halaman Pengaturan.</div></div>";
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
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .table th, .table td { vertical-align: middle; }
    /* Tambahkan style untuk table-responsive jika tabel siswa panjang */
    .table-siswa-container { max-height: 50vh; overflow-y: auto; }
    .footer-actions { background-color: #f8f9fa; border-top: 1px solid #dee2e6; padding: 1rem 1.5rem; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Proses Kenaikan Kelas & Kelulusan</h1>
        <p class="lead mb-0 opacity-75">
            Tahun Ajaran Asal: <strong><?php echo htmlspecialchars($ta_aktif['tahun_ajaran']); ?></strong>
            <?php if ($ta_berikutnya): ?>
                 | Tujuan ke: <strong><?php echo htmlspecialchars($ta_berikutnya['tahun_ajaran']); ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <!-- Peringatan jika T.A berikutnya atau kelasnya belum siap -->
    <?php if (!$ta_berikutnya || !$kelas_baru_result || mysqli_num_rows($kelas_baru_result) == 0): ?>
        <div class="alert alert-danger shadow-sm">
            <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Aksi Diperlukan!</h4>
            <p>Sistem tidak menemukan data Tahun Ajaran berikutnya yang valid atau belum ada data Kelas yang dibuat untuk Tahun Ajaran tersebut.</p>
            <hr>
            <p class="mb-0">Silakan lakukan langkah berikut di halaman <a href="pengaturan_tampil.php" class="alert-link">Pengaturan</a>:</p>
            <ol>
                <li>Tambahkan Tahun Ajaran baru (misal: <?php echo substr($ta_aktif['tahun_ajaran'], 0, 5) . (intval(substr($ta_aktif['tahun_ajaran'], 5, 4)) + 1); ?>/<?php echo (intval(substr($ta_aktif['tahun_ajaran'], 0, 4)) + 2); ?>).</li>
                <li>Buat data kelas-kelas baru untuk Tahun Ajaran yang baru ditambahkan tersebut di halaman <a href="kelas_tampil.php" class="alert-link">Kelas & Siswa</a>.</li>
            </ol>
        </div>
    <?php else: ?>
        <!-- Form Pemilihan Kelas Asal -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-door-open-fill me-2" style="color: var(--primary-color);"></i>Langkah 1: Pilih Kelas Asal (T.A <?php echo htmlspecialchars($ta_aktif['tahun_ajaran']); ?>)</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="formPilihKelas">
                    <div class="input-group">
                        <select name="id_kelas" class="form-select form-select-lg" onchange="document.getElementById('formPilihKelas').submit()">
                            <option value="">-- Pilih Kelas untuk Menampilkan Siswa --</option>
                            <?php mysqli_data_seek($kelas_aktif_result, 0); while($kls = mysqli_fetch_assoc($kelas_aktif_result)): ?>
                                <option value="<?php echo $kls['id_kelas']; ?>" <?php if($id_kelas_pilihan == $kls['id_kelas']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($kls['nama_kelas']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search me-2"></i>Tampilkan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Siswa dan Form Aksi (Hanya tampil jika kelas sudah dipilih) -->
        <?php if ($id_kelas_pilihan > 0): ?>
        <form action="admin_aksi.php?aksi=proses_kenaikan_siswa" method="POST" onsubmit="return konfirmasiProses();">
            <input type="hidden" name="id_kelas_lama" value="<?php echo $id_kelas_pilihan; ?>">

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
                     <h5 class="mb-0 py-1"><i class="bi bi-people-fill me-2 text-success"></i>Langkah 2: Pilih Siswa dari Kelas <?php echo htmlspecialchars($nama_kelas_pilihan); ?></h5>
                    <?php if(!empty($siswa_di_kelas)): // Tampilkan tombol hanya jika ada siswa ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-1" id="pilihSemua"><i class="bi bi-check2-square me-1"></i> Pilih / Lepas Semua</button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-siswa-container"> 
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top"> 
                                <tr>
                                    <th class="text-center" width="5%"><i class="bi bi-check2-square"></i></th>
                                    <th>Nama Siswa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($siswa_di_kelas)): ?>
                                    <tr><td colspan="2" class="text-center text-muted py-5">Tidak ada siswa aktif di kelas ini.</td></tr>
                                <?php else: ?>
                                    <?php foreach($siswa_di_kelas as $siswa): ?>
                                    <tr>
                                        <td class="text-center"><input class="form-check-input siswa-checkbox" type="checkbox" name="id_siswa[]" value="<?php echo $siswa['id_siswa']; ?>"></td>
                                        <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Aksi (Hanya Tampil Jika Ada Siswa) -->
                <?php if(!empty($siswa_di_kelas)): ?>
                <div class="card-footer footer-actions">
                     <h5 class="mb-3"><i class="bi bi-arrow-right-circle-fill me-2 text-primary"></i>Langkah 3: Tentukan Tindakan & Kelas Tujuan</h5>
                    <div class="row align-items-end g-3">
                        <div class="col-md-5 col-lg-4">
                            <label for="select-tindakan" class="form-label fw-bold">Tindakan untuk Siswa Terpilih:</label>
                             <select name="tindakan" class="form-select form-select-lg" id="select-tindakan" required>
                                <option value="" disabled selected>-- Pilih Tindakan --</option>
                                <?php if ($is_kelas_akhir): ?>
                                    <option value="luluskan">✅ Luluskan Siswa</option>
                                    <option value="tinggal">❌ Tinggal di Kelas Ini (Pilih Kelas Tujuan T.A Baru)</option> {/* Tinggal di level yang sama */}
                                <?php else: ?>
                                    <option value="naik">⬆️ Naikkan ke Kelas Berikutnya (Pilih Kelas Tujuan T.A Baru)</option>
                                    <option value="tinggal">❌ Tinggal di Kelas Ini (Pilih Kelas Tujuan T.A Baru)</option> {/* Tinggal di level yang sama */}
                                <?php endif; ?>
                            </select>
                        </div>
                         <div class="col-md-5 col-lg-6">
                            <label for="select-tujuan" class="form-label fw-bold">Kelas Tujuan (T.A <?php echo htmlspecialchars($ta_berikutnya['tahun_ajaran']); ?>):</label>
                            <select name="id_kelas_baru" class="form-select form-select-lg" id="select-tujuan" disabled>
                                <option value="">-- Pilih Kelas Tujuan --</option>
                                <?php mysqli_data_seek($kelas_baru_result, 0); while($kb = mysqli_fetch_assoc($kelas_baru_result)): ?>
                                    <option value="<?php echo $kb['id_kelas']; ?>"><?php echo htmlspecialchars($kb['nama_kelas']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text" id="help-tujuan" style="display: none;">Pilih kelas di tahun ajaran baru. Jika 'Tinggal Kelas', pilih kelas dengan tingkat yang sama.</div>
                        </div>
                        <div class="col-md-2 col-lg-2 d-grid">
                            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle-fill me-2"></i> Proses</button>
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
                helpTujuan.style.display = 'block'; // Tampilkan helper text
            } else {
                selectTujuan.disabled = true;
                selectTujuan.required = false;
                selectTujuan.value = ''; // Reset pilihan
                 helpTujuan.style.display = 'none'; // Sembunyikan helper text
            }
        });
         // Panggil sekali saat load untuk inisialisasi state
         selectTindakan.dispatchEvent(new Event('change'));
    }
});

// Fungsi Konfirmasi Sebelum Submit
function konfirmasiProses() {
    const checkboxes = document.querySelectorAll('.siswa-checkbox:checked');
    const selectTindakan = document.getElementById('select-tindakan');
    const selectTujuan = document.getElementById('select-tujuan');
    const tindakan = selectTindakan.value;

    if (checkboxes.length === 0) {
        Swal.fire('Peringatan', 'Silakan pilih minimal satu siswa untuk diproses.', 'warning');
        return false; // Mencegah submit
    }
    if (tindakan === '') {
         Swal.fire('Peringatan', 'Silakan pilih tindakan yang akan dilakukan.', 'warning');
         return false;
    }
     if ((tindakan === 'naik' || tindakan === 'tinggal') && selectTujuan.value === '') {
         Swal.fire('Peringatan', 'Silakan pilih kelas tujuan untuk tindakan ini.', 'warning');
         return false;
    }

    // Konfirmasi akhir
    let pesanKonfirmasi = `Anda yakin ingin memproses ${checkboxes.length} siswa yang dipilih dengan tindakan "${selectTindakan.options[selectTindakan.selectedIndex].text}"?`;
    if (tindakan === 'naik' || tindakan === 'tinggal') {
        pesanKonfirmasi += ` ke kelas "${selectTujuan.options[selectTujuan.selectedIndex].text}"`;
    }
     pesanKonfirmasi += "\nAksi ini akan mengubah data siswa dan TIDAK DAPAT DIURUNGKAN.";

    return confirm(pesanKonfirmasi); // Menggunakan confirm() bawaan browser
}
</script>

<?php
// Tampilkan pesan sukses/error dari session jika ada
if (isset($_SESSION['pesan'])) {
    // Gunakan addslashes untuk menangani kutip dalam pesan JSON
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire(" . $_SESSION['pesan'] . ");
        });
    </script>";
    unset($_SESSION['pesan']); // Hapus pesan setelah ditampilkan
}
include 'footer.php';
?>
