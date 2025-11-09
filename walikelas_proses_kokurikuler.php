<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas atau Admin
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil data sesi dan data aktif dari DB
$id_wali_kelas = $_SESSION['id_guru'];
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil data kelas
$stmt_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($stmt_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($stmt_kelas);
$kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kelas));

if (!$kelas) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Anda tidak ditugaskan sebagai wali kelas.</div></div>";
    include 'footer.php';
    exit;
}
$id_kelas = $kelas['id_kelas'];
$action = $_POST['action'] ?? 'show_list';

// Fungsi bantu untuk memproses data kokurikuler (tidak berubah)
function prosesDataKokurikulerSiswaRataRata($koneksi, $id_siswa, $id_tahun_ajaran_aktif, $semester_aktif) {
    $query_asesmen = mysqli_prepare($koneksi, "
        SELECT kk.tema_kegiatan, ktd.nama_dimensi, ka.nilai_kualitatif, g.nama_guru
        FROM kokurikuler_asesmen ka
        JOIN kokurikuler_target_dimensi ktd ON ka.id_target = ktd.id_target
        JOIN kokurikuler_kegiatan kk ON ktd.id_kegiatan = kk.id_kegiatan
        LEFT JOIN guru g ON ka.id_guru_penilai = g.id_guru
        WHERE ka.id_siswa = ? AND kk.semester = ? AND kk.id_tahun_ajaran = ?
        ORDER BY kk.tema_kegiatan, ktd.nama_dimensi");
    mysqli_stmt_bind_param($query_asesmen, "iii", $id_siswa, $semester_aktif, $id_tahun_ajaran_aktif);
    mysqli_stmt_execute($query_asesmen);
    $result_asesmen = mysqli_stmt_get_result($query_asesmen);
    $data_terstruktur = [];
    while($asesmen = mysqli_fetch_assoc($result_asesmen)){
        $data_terstruktur[$asesmen['tema_kegiatan']][$asesmen['nama_dimensi']][] = ['nilai' => $asesmen['nilai_kualitatif'], 'guru' => $asesmen['nama_guru'] ?? 'N/A'];
    }
    $skala = ['Sangat Baik' => 4, 'Baik' => 3, 'Cukup' => 2, 'Kurang' => 1];
    $hasil_akhir_per_kegiatan = [];
    $nilai_akhir_untuk_deskripsi = [];
    foreach ($data_terstruktur as $tema => $dimensi_group) {
        foreach ($dimensi_group as $nama_dimensi => $penilaian) {
            $total_skor = 0;
            $jumlah_penilai = count($penilaian);
            foreach ($penilaian as $p) {
                $total_skor += $skala[$p['nilai']] ?? 0;
            }
            $rata_rata_skor = $jumlah_penilai > 0 ? $total_skor / $jumlah_penilai : 0;
            $nilai_akhir_kualitatif = 'Kurang';
            if ($rata_rata_skor > 3.5) $nilai_akhir_kualitatif = 'Sangat Baik';
            elseif ($rata_rata_skor > 2.5) $nilai_akhir_kualitatif = 'Baik';
            elseif ($rata_rata_skor > 1.5) $nilai_akhir_kualitatif = 'Cukup';
            $hasil_akhir_per_kegiatan[$tema][$nama_dimensi] = ['detail_penilaian' => $penilaian, 'nilai_akhir' => $nilai_akhir_kualitatif];
            $nilai_akhir_untuk_deskripsi[$tema][$nama_dimensi] = $nilai_akhir_kualitatif;
        }
    }
    $kamus_dimensi = ['Keimanan dan Ketakwaan terhadap Tuhan YME' => 'sikap syukurnya', 'Kewargaan' => 'rasa cinta tanah airnya', 'Penalaran Kritis' => 'kemampuan nalar kritisnya', 'Kreativitas' => 'kreativitasnya', 'Kolaborasi' => 'kemampuan berkolaborasinya', 'Kemandirian' => 'kemandiriannya', 'Kesehatan' => 'kesadaran akan kesehatannya', 'Komunikasi' => 'kemampuan komunikasinya'];
    $deskripsi_draf_arr = [];
    foreach ($nilai_akhir_untuk_deskripsi as $tema_kegiatan => $dimensi_group) {
        $kalimat_positif = [];
        foreach ($dimensi_group as $nama_dimensi => $nilai_akhir) {
            if (in_array($nilai_akhir, ['Sangat Baik', 'Baik'])) {
                if (isset($kamus_dimensi[$nama_dimensi])) {
                    $kalimat_positif[] = $kamus_dimensi[$nama_dimensi];
                }
            }
        }
        if (!empty($kalimat_positif)) {
            $deskripsi_draf_arr[] = "Dalam projek '" . htmlspecialchars($tema_kegiatan) . "', ananda menunjukkan perkembangan yang baik terutama pada " . implode(', ', $kalimat_positif) . ".";
        }
    }
    $deskripsi = !empty($deskripsi_draf_arr) ? implode(" ", $deskripsi_draf_arr) . " Potensi ini perlu terus diasah." : "Ananda telah berpartisipasi aktif dalam kegiatan projek semester ini.";
    return ['rangkuman' => $hasil_akhir_per_kegiatan, 'deskripsi' => $deskripsi];
}

// Logika penyimpanan akhir (tidak berubah)
if ($action === 'save_final' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['deskripsi'])) { $_SESSION['pesan'] = json_encode(['icon' => 'warning', 'title' => 'Gagal', 'text' => 'Tidak ada data deskripsi untuk disimpan.']); header("Location: walikelas_proses_kokurikuler.php"); exit; }
    $deskripsi_siswa = $_POST['deskripsi']; $total_berhasil = 0;
    mysqli_begin_transaction($koneksi);
    try {
        $stmt_update_rapor = mysqli_prepare($koneksi, "UPDATE rapor SET deskripsi_kokurikuler = ? WHERE id_rapor = ?");
        foreach ($deskripsi_siswa as $id_siswa => $deskripsi) {
            $q_cek_rapor = mysqli_prepare($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = ? AND id_tahun_ajaran = ? AND semester = ?");
            mysqli_stmt_bind_param($q_cek_rapor, "iii", $id_siswa, $id_tahun_ajaran_aktif, $semester_aktif); mysqli_stmt_execute($q_cek_rapor);
            $data_rapor = mysqli_fetch_assoc(mysqli_stmt_get_result($q_cek_rapor)); $id_rapor = $data_rapor ? $data_rapor['id_rapor'] : null;
            if (!$id_rapor) { $q_insert_rapor = mysqli_prepare($koneksi, "INSERT INTO rapor (id_siswa, id_kelas, id_tahun_ajaran, semester) VALUES (?, ?, ?, ?)"); mysqli_stmt_bind_param($q_insert_rapor, "iiii", $id_siswa, $id_kelas, $id_tahun_ajaran_aktif, $semester_aktif); mysqli_stmt_execute($q_insert_rapor); $id_rapor = mysqli_insert_id($koneksi); }
            mysqli_stmt_bind_param($stmt_update_rapor, "si", $deskripsi, $id_rapor); mysqli_stmt_execute($stmt_update_rapor); $total_berhasil++;
        }
        mysqli_commit($koneksi); $_SESSION['pesan'] = json_encode(['icon' => 'success', 'title' => 'Berhasil', 'text' => "Deskripsi untuk {$total_berhasil} siswa disimpan."]);
    } catch (Exception $e) { mysqli_rollback($koneksi); $_SESSION['pesan'] = json_encode(['icon' => 'error', 'title' => 'Gagal', 'text' => 'Error: ' . $e->getMessage()]); }
    header("Location: walikelas_proses_kokurikuler.php"); exit;
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .accordion-button:not(.collapsed) {
        background-color: #e0f2f1; color: var(--secondary-color);
        box-shadow: inset 0 -1px 0 rgba(0,0,0,.125);
    }
</style>

<div class="container-fluid">
    <?php if ($action === 'show_list'): ?>
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Proses Deskripsi Kokurikuler</h1>
        <p class="lead mb-0 opacity-75">Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></p>
    </div>
    <form action="walikelas_proses_kokurikuler.php" method="POST">
        <input type="hidden" name="action" value="generate_draft">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 card-title"><i class="bi bi-people-fill me-2" style="color: var(--primary-color);"></i>Pilih Siswa untuk Diproses</h5>
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" id="pilihSemua">
                    <label class="form-check-label" for="pilihSemua">Pilih Semua</label>
                </div>
            </div>
            <div class="list-group list-group-flush">
                <?php
                $q_siswa_list = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif' ORDER BY nama_lengkap");
                if (mysqli_num_rows($q_siswa_list) > 0) {
                    while($siswa = mysqli_fetch_assoc($q_siswa_list)): ?>
                        <label class="list-group-item list-group-item-action fs-6">
                            <input class="form-check-input me-3" type="checkbox" name="id_siswa[]" value="<?php echo $siswa['id_siswa']; ?>">
                            <?php echo htmlspecialchars($siswa['nama_lengkap']); ?>
                        </label>
                    <?php endwhile;
                } else {
                    echo "<div class='list-group-item text-center text-muted'>Tidak ada siswa aktif di kelas ini.</div>";
                }
                ?>
            </div>
             <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-pencil-square me-2"></i>Buat Draf Deskripsi</button>
            </div>
        </div>
    </form>
    
    <?php elseif ($action === 'generate_draft' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <?php
    if (empty($_POST['id_siswa'])) {
        echo "<div class='alert alert-warning mt-4'>Tidak ada siswa yang dipilih.</div>";
        echo '<a href="walikelas_proses_kokurikuler.php" class="btn btn-secondary mt-2"><i class="bi bi-arrow-left"></i> Kembali</a>';
    } else {
        $daftar_id_siswa_preview = $_POST['id_siswa'];
        $q_siswa_preview = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_siswa IN (".implode(',', $daftar_id_siswa_preview).") ORDER BY nama_lengkap");
        $siswa_preview = mysqli_fetch_all($q_siswa_preview, MYSQLI_ASSOC);
    ?>
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Pratinjau Deskripsi Kokurikuler</h1>
        <p class="lead mb-0 opacity-75">Periksa rangkuman nilai dan draf deskripsi untuk setiap siswa sebelum disimpan.</p>
    </div>
    
    <form action="walikelas_proses_kokurikuler.php" method="POST">
        <input type="hidden" name="action" value="save_final">
        <div class="accordion" id="accordionSiswa">
        <?php foreach ($siswa_preview as $index => $siswa):
            $id_siswa = $siswa['id_siswa'];
            echo '<input type="hidden" name="id_siswa[]" value="'.$id_siswa.'">'; // Kirim ulang id siswa untuk proses simpan
            $hasil_proses = prosesDataKokurikulerSiswaRataRata($koneksi, $id_siswa, $id_tahun_ajaran_aktif, $semester_aktif);
            $rangkuman_data = $hasil_proses['rangkuman'];
            $deskripsi_draf = $hasil_proses['deskripsi'];
            $q_rapor_exist = mysqli_query($koneksi, "SELECT deskripsi_kokurikuler FROM rapor WHERE id_siswa=$id_siswa AND id_tahun_ajaran=$id_tahun_ajaran_aktif AND semester=$semester_aktif");
            $deskripsi_tersimpan = mysqli_fetch_assoc($q_rapor_exist)['deskripsi_kokurikuler'] ?? '';
            $deskripsi_final = !empty($deskripsi_tersimpan) ? $deskripsi_tersimpan : $deskripsi_draf;
        ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $id_siswa; ?>">
                        <span class="badge bg-primary me-3"><?php echo ($index + 1); ?></span>
                        <span class="fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></span>
                    </button>
                </h2>
                <div id="collapse-<?php echo $id_siswa; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" data-bs-parent="#accordionSiswa">
                    <div class="accordion-body bg-light">
                         <div class="row g-4">
                            <div class="col-lg-5">
                                <h6 class="text-muted"><i class="bi bi-people-fill me-2"></i>Rangkuman Penilaian & Hasil Rata-Rata</h6><hr class="mt-2">
                                <?php if (count($rangkuman_data) > 0): ?>
                                    <?php foreach($rangkuman_data as $tema => $dimensi_group): ?>
                                    <div class="mb-3"><p class="fw-bold mb-1">Kegiatan: <?php echo htmlspecialchars($tema); ?></p>
                                    <?php foreach($dimensi_group as $nama_dimensi => $detail): 
                                        $badge_color = ['Sangat Baik' => 'success', 'Baik' => 'primary', 'Cukup' => 'warning text-dark', 'Kurang' => 'danger'][$detail['nilai_akhir']] ?? 'secondary';
                                    ?>
                                        <div class="p-2 border rounded mb-2 bg-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong><?php echo $nama_dimensi; ?></strong>
                                                <span class="badge bg-<?php echo $badge_color; ?>">Akhir: <?php echo $detail['nilai_akhir']; ?></span>
                                            </div>
                                            <hr class="my-1">
                                            <div class="d-flex flex-wrap" style="gap: 3px;">
                                            <small class="text-muted me-2">Penilai:</small>
                                            <?php foreach($detail['detail_penilaian'] as $p): ?>
                                                <span class="badge bg-secondary" title="<?php echo htmlspecialchars($p['guru']); ?>"><?php echo explode(' ', $p['guru'])[0] . ' ('.$p['nilai'].')'; ?></span>
                                            <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning small">Belum ada data asesmen untuk siswa ini.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-lg-7">
                                 <h6 class="text-muted"><i class="bi bi-pencil-square me-2"></i>Draf Deskripsi (Bisa Diedit)</h6><hr class="mt-2">
                                <textarea name="deskripsi[<?php echo $id_siswa; ?>]" class="form-control" rows="10"><?php echo htmlspecialchars($deskripsi_final); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="mt-4 d-flex justify-content-between">
            <a href="walikelas_proses_kokurikuler.php" class="btn btn-secondary btn-lg"><i class="bi bi-arrow-left me-2"></i>Kembali & Ubah Pilihan</a>
            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle-fill me-2"></i>Konfirmasi & Simpan Deskripsi</button>
        </div>
    </form>
    <?php } ?>
    <?php endif; ?>
</div>

<script>
if(document.getElementById('pilihSemua')) {
    document.getElementById('pilihSemua').addEventListener('change', function(e) {
        document.querySelectorAll('input[name="id_siswa[]"]').forEach(c => { c.checked = e.target.checked; });
    });
}
</script>

<?php
if(isset($_SESSION['pesan'])){ 
    $pesan_data = json_decode($_SESSION['pesan'], true);
    if ($pesan_data) {
        echo "<script>Swal.fire({icon: '".($pesan_data['icon'] ?? 'info')."', title: '".($pesan_data['title'] ?? 'Info')."', text: '".($pesan_data['text'] ?? '')."'});</script>";
    }
    unset($_SESSION['pesan']);
} 
include 'footer.php';
?>