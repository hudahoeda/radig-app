<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas atau Admin
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal::fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];

// [PERBAIKAN] Ambil KKM dari database, bukan hardcoded
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm_db = mysqli_fetch_assoc($q_kkm);
$kkm = $kkm_db ? (int)$kkm_db['nilai_pengaturan'] : 75; // Fallback ke 75 jika DB error

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil data kelas
$stmt_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($stmt_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran_aktif);
mysqli_stmt_execute($stmt_kelas);
$result_kelas = mysqli_stmt_get_result($stmt_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);

if (!$kelas) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Anda tidak ditugaskan sebagai wali kelas pada tahun ajaran aktif saat ini.</div></div>";
    include 'footer.php';
    exit;
}
$id_kelas = $kelas['id_kelas'];
$action = $_POST['action'] ?? 'show_list';

// ======================================================================
// FUNGSI INI TETAP DIPERLUKAN UNTUK MEMBUAT PRATINJAU
// ======================================================================
function hitungDataRaporSiswa($koneksi, $id_siswa, $id_kelas, $semester_aktif, $kkm, $daftar_mapel) {
    $data_rapor_siswa = [];
    
    // Query 1: Mengambil Sumatif yang terkait Tujuan Pembelajaran (TP)
    $stmt_sumatif_tp = mysqli_prepare($koneksi, "
        SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian, 
               GROUP_CONCAT(tp.deskripsi_tp SEPARATOR '|||') as deskripsi_tps
        FROM penilaian_detail_nilai pdn
        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
        JOIN penilaian_tp ptp ON p.id_penilaian = ptp.id_penilaian
        JOIN tujuan_pembelajaran tp ON ptp.id_tp = tp.id_tp
        WHERE p.subjenis_penilaian = 'Sumatif TP' AND pdn.id_siswa = ? AND p.id_mapel = ? 
        AND p.id_kelas = ? AND p.semester = ?
        GROUP BY p.id_penilaian, pdn.nilai, p.bobot_penilaian
    ");
    
    // Query 2: Mengambil Sumatif Akhir Semester (SAS) atau Akhir Tahun (SAT)
    $stmt_sumatif_akhir = mysqli_prepare($koneksi, "
        SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian
        FROM penilaian_detail_nilai pdn
        JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
        WHERE p.subjenis_penilaian IN ('Sumatif Akhir Semester', 'Sumatif Akhir Tahun')
        AND p.jenis_penilaian = 'Sumatif' AND pdn.id_siswa = ? AND p.id_mapel = ?
        AND p.id_kelas = ? AND p.semester = ?
    ");
        
    foreach ($daftar_mapel as $mapel) {
        $id_mapel = $mapel['id_mapel'];
        
        $skor_per_tp = []; 
        $komponen_nilai = [];
        $total_nilai_x_bobot = 0; 
        $total_bobot = 0;

        // Proses Data dari Query 1 (Sumatif TP)
        mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_tp);
        $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
        while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
            $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
            foreach($tps_individu as $desc_tp) {
                if (!isset($skor_per_tp[$desc_tp])) {
                    $skor_per_tp[$desc_tp] = [];
                }
                $skor_per_tp[$desc_tp][] = $d_nilai['nilai'];
            }
            $komponen_nilai[] = [
                'nama' => $d_nilai['nama_penilaian'], 'jenis' => $d_nilai['subjenis_penilaian'],
                'nilai' => $d_nilai['nilai'], 'bobot' => $d_nilai['bobot_penilaian'],
                'deskripsi_tp' => str_replace('|||', '<br>- ', $d_nilai['deskripsi_tps'])
            ];
            $total_nilai_x_bobot += $d_nilai['nilai'] * $d_nilai['bobot_penilaian'];
            $total_bobot += $d_nilai['bobot_penilaian'];
        }

        // Proses Data dari Query 2 (Sumatif Akhir)
        mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_akhir);
        $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
        while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
            $komponen_nilai[] = [
                'nama' => $d_nilai_akhir['nama_penilaian'], 'jenis' => $d_nilai_akhir['subjenis_penilaian'],
                'nilai' => $d_nilai_akhir['nilai'], 'bobot' => $d_nilai_akhir['bobot_penilaian'],
                'deskripsi_tp' => 'Mencakup keseluruhan materi semester.'
            ];
            $total_nilai_x_bobot += $d_nilai_akhir['nilai'] * $d_nilai_akhir['bobot_penilaian'];
            $total_bobot += $d_nilai_akhir['bobot_penilaian'];
        }

        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;
        
        $rumus_perhitungan = "Belum ada data untuk dihitung.";
        if ($total_bobot > 0) {
            $pembilang_parts = []; $penyebut_parts = [];
            foreach ($komponen_nilai as $komponen) {
                $pembilang_parts[] = "({$komponen['nilai']} x {$komponen['bobot']})";
                $penyebut_parts[] = $komponen['bobot'];
            }
            $rumus_pembilang = implode(' + ', $pembilang_parts);
            $rumus_penyebut = implode(' + ', $penyebut_parts);
            $rumus_perhitungan = "( {$rumus_pembilang} ) / ( {$rumus_penyebut} ) = {$total_nilai_x_bobot} / {$total_bobot} ≈ {$nilai_akhir}";
        }

        // == BLOK PEMBUATAN DESKRIPSI SESUAI PANDUAN PPA 2025 ==
        $deskripsi_final = '';
        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
            $tp_dikuasai = []; 
            $tp_perlu_peningkatan = [];
            
            // Langkah 1 & 2: Identifikasi dan kelompokkan ketercapaian setiap TP berdasarkan rata-rata nilainya.
            foreach ($skor_per_tp as $deskripsi => $skor_array) {
                $rata_rata_tp = array_sum($skor_array) / count($skor_array);
                $deskripsi_bersih = lcfirst(trim(str_replace(['Peserta didik dapat', 'peserta didik mampu', 'mampu'], '', $deskripsi)));
                
                if ($rata_rata_tp >= $kkm) {
                    $tp_dikuasai[] = $deskripsi_bersih;
                } else {
                    $tp_perlu_peningkatan[] = $deskripsi_bersih;
                }
            }

            // Langkah 3: Susun kalimat deskripsi naratif.
            $deskripsi_draf = "";
            if (!empty($tp_dikuasai)) { 
                $deskripsi_draf .= "Menunjukkan penguasaan yang baik dalam " . implode(', ', array_unique($tp_dikuasai)) . ". "; 
            }
            if (!empty($tp_perlu_peningkatan)) { 
                $deskripsi_draf .= "Perlu penguatan dalam " . implode(', ', array_unique($tp_perlu_peningkatan)) . "."; 
            }
            $deskripsi_final = (empty(trim($deskripsi_draf))) ? 'Capaian kompetensi sudah baik pada seluruh materi.' : ucfirst(trim($deskripsi_draf));
        
        } elseif ($nilai_akhir !== null) {
            $deskripsi_final = 'Capaian kompetensi secara umum sudah menunjukkan ketuntasan yang baik.';
        }

        $data_rapor_siswa[$id_mapel] = [
            'nilai_akhir' => $nilai_akhir, 
            'deskripsi' => $deskripsi_final,
            'komponen_nilai' => $komponen_nilai,
            'rumus_perhitungan' => $rumus_perhitungan
        ];
    }
    // Tutup statement
    mysqli_stmt_close($stmt_sumatif_tp);
    mysqli_stmt_close($stmt_sumatif_akhir);

    return $data_rapor_siswa;
}

// ======================================================================
// [MODIFIKASI] BLOK LOGIKA 'save_final' DIHAPUS
// ======================================================================
// Blok 'if ($action === 'save_final' ...)' telah dihapus
// karena penyimpanan sekarang ditangani oleh 'walikelas_aksi.php?aksi=finalisasi_semua'
// ======================================================================

?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .table-preview .sticky-col {
        position: -webkit-sticky; position: sticky;
        left: 0; z-index: 2; background-color: #fff;
    }
    .table-preview thead th {
        position: -webkit-sticky; position: sticky;
        top: 0; z-index: 3;
    }
    .table-hover > tbody > tr:hover > * {
        background-color: #e0f2f1 !important;
    }
    .detail-nilai-trigger {
        cursor: pointer;
    }
    .font-monospace {
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9em;
        color: #d63384;
    }
</style>

<div class="container-fluid">
    <?php if ($action === 'show_list'): ?>
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Proses Rapor Akademik</h1>
        <p class="lead mb-0 opacity-75">Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></p>
    </div>
    <form action="walikelas_proses_rapor.php" method="POST">
        <input type="hidden" name="action" value="preview">
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
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-eye-fill me-2"></i>Buat Pratinjau Nilai</button>
            </div>
        </div>
    </form>
    
    <?php elseif ($action === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <?php
    if (empty($_POST['id_siswa'])) {
        echo "<div class='alert alert-warning mt-4'>Tidak ada siswa yang dipilih.</div>";
        echo '<a href="walikelas_proses_rapor.php" class="btn btn-secondary mt-2"><i class="bi bi-arrow-left"></i> Kembali</a>';
    } else {
        $daftar_id_siswa_preview = $_POST['id_siswa'];
        $q_siswa_preview = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_siswa IN (".implode(',', $daftar_id_siswa_preview).") ORDER BY nama_lengkap");
        $siswa_preview = mysqli_fetch_all($q_siswa_preview, MYSQLI_ASSOC);
        
        $q_mapel_relevan = mysqli_prepare($koneksi, "SELECT DISTINCT m.id_mapel, m.nama_mapel, m.urutan FROM mata_pelajaran m JOIN penilaian p ON m.id_mapel = p.id_mapel WHERE p.id_kelas = ? AND p.semester = ? ORDER BY m.urutan");
        mysqli_stmt_bind_param($q_mapel_relevan, "ii", $id_kelas, $semester_aktif);
        mysqli_stmt_execute($q_mapel_relevan);
        $daftar_mapel = mysqli_fetch_all(mysqli_stmt_get_result($q_mapel_relevan), MYSQLI_ASSOC);
    ?>
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Pratinjau Nilai Rapor</h1>
        <p class="lead mb-0 opacity-75">Klik pada nilai untuk melihat rincian. Nilai di bawah KKM (<?php echo $kkm; ?>) ditandai <span class="badge bg-danger">Merah</span>.</p>
    </div>
    
    <!-- [MODIFIKASI] Form tidak lagi dibutuhkan di sini karena tombol simpan dihapus -->
    <!-- <form action="walikelas_proses_rapor.php" method="POST"> -->
        
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle table-preview mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center sticky-col">Nama Siswa</th>
                                <?php foreach ($daftar_mapel as $mapel) { echo "<th class='text-center'>".htmlspecialchars($mapel['nama_mapel'])."</th>"; } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa_preview as $siswa):
                                $data_rapor_siswa = hitungDataRaporSiswa($koneksi, $siswa['id_siswa'], $id_kelas, $semester_aktif, $kkm, $daftar_mapel);
                            ?>
                            <tr>
                                <td class="sticky-col fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                <?php foreach ($daftar_mapel as $mapel):
                                    $data_mapel = $data_rapor_siswa[$mapel['id_mapel']];
                                    $nilai = $data_mapel['nilai_akhir'];
                                    $deskripsi = htmlspecialchars($data_mapel['deskripsi']);
                                    $rumus = htmlspecialchars($data_mapel['rumus_perhitungan']);
                                    $komponen_json = htmlspecialchars(json_encode($data_mapel['komponen_nilai']), ENT_QUOTES, 'UTF-8');

                                    $display_nilai = '-';
                                    $badge_class = 'bg-secondary';
                                    
                                    if ($nilai === null) {
                                        $display_nilai = 'N/A';
                                        $badge_class = 'bg-warning text-dark';
                                    } elseif ($nilai < $kkm) {
                                        $display_nilai = $nilai;
                                        $badge_class = 'bg-danger';
                                    } else {
                                        $display_nilai = $nilai;
                                        $badge_class = 'bg-success';
                                    }
                                ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm w-100 fw-bold fs-6 <?php echo $badge_class; ?> detail-nilai-trigger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailNilaiModal"
                                            data-nama-siswa="<?php echo htmlspecialchars($siswa['nama_lengkap']); ?>"
                                            data-nama-mapel="<?php echo htmlspecialchars($mapel['nama_mapel']); ?>"
                                            data-nilai-akhir="<?php echo $display_nilai; ?>"
                                            data-deskripsi="<?php echo $deskripsi; ?>"
                                            data-rumus="<?php echo $rumus; ?>"
                                            data-komponen='<?php echo $komponen_json; ?>'>
                                        <?php echo $display_nilai; ?>
                                    </button>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- ====================================================== -->
        <!-- [MODIFIKASI] Tombol Simpan Dihapus, diganti Info Box -->
        <!-- ====================================================== -->
        <div class="alert alert-info mt-4" role="alert">
          <h4 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Ini Halaman Pratinjau</h4>
          <p>Data yang Anda lihat di atas adalah <strong>hasil kalkulasi sementara</strong> dan <strong>belum tersimpan permanen</strong>. Halaman ini hanya untuk memverifikasi perhitungan nilai akhir dan deskripsi.</p>
          <hr>
          <p class="mb-0">Untuk menyimpan data ini secara permanen dan mengunci rapor, silakan kembali ke menu <strong>"Cetak Rapor"</strong> dan gunakan tombol <strong>"Finalisasi Semua"</strong>.</p>
        </div>
        
        <div class="mt-4">
            <a href="walikelas_proses_rapor.php" class="btn btn-secondary btn-lg"><i class="bi bi-arrow-left me-2"></i>Kembali & Ubah Pilihan Siswa</a>
        </div>
        <!-- ====================================================== -->
        <!-- [AKHIR MODIFIKASI] -->
        <!-- ====================================================== -->
    
    <div class="modal fade" id="detailNilaiModal" tabindex="-1" aria-labelledby="detailNilaiModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="detailNilaiModalLabel">Detail Nilai Rapor</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-1"><strong>Siswa:</strong> <span id="modal-nama-siswa"></span></p>
            <p><strong>Mata Pelajaran:</strong> <span id="modal-nama-mapel"></span></p>
            <hr>
            <h4>Nilai Akhir: <span class="badge bg-primary" id="modal-nilai-akhir"></span></h4>
            <div class="mt-3">
                <h6>Deskripsi Capaian Kompetensi:</h6>
                <p class="text-muted fst-italic bg-light p-3 rounded" id="modal-deskripsi"></p>
            </div>
             <div class="mt-4">
                <h6>Teknik Perhitungan Nilai Akhir (Rata-rata Berbobot):</h6>
                <p class="bg-light p-3 rounded font-monospace" id="modal-rumus"></p>
            </div>
            <div class="mt-4">
                <h6>Rincian Komponen Nilai Sumatif:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 20%;">Nama Penilaian</th>
                                <th style="width: 15%;">Jenis</th>
                                <th class="text-center" style="width: 8%;">Nilai</th>
                                <th class="text-center" style="width: 8%;">Bobot</th>
                                <th>Tujuan Pembelajaran yang Dinilai</th>
                            </tr>
                        </thead>
                        <tbody id="modal-tabel-komponen">
                            </tbody>
                    </table>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <?php } ?>
    <?php endif; ?>
</div>

<script>
// Skrip untuk checkbox "Pilih Semua"
if(document.getElementById('pilihSemua')) {
    document.getElementById('pilihSemua').addEventListener('change', function(e) {
        const isChecked = e.target.checked;
        document.querySelectorAll('input[name="id_siswa[]"]').forEach(checkbox => {
            checkbox.checked = isChecked;
        });
    });
}

// Skrip untuk Modal Detail Nilai
const detailModal = document.getElementById('detailNilaiModal');
if (detailModal) {
    detailModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;

        // Ekstrak data dari atribut data-*
        const namaSiswa = button.getAttribute('data-nama-siswa');
        const namaMapel = button.getAttribute('data-nama-mapel');
        const nilaiAkhir = button.getAttribute('data-nilai-akhir');
        const deskripsi = button.getAttribute('data-deskripsi');
        const rumus = button.getAttribute('data-rumus');
        const komponen = JSON.parse(button.getAttribute('data-komponen'));

        // Update konten modal
        detailModal.querySelector('.modal-title').textContent = 'Detail Nilai: ' + namaMapel;
        detailModal.querySelector('#modal-nama-siswa').textContent = namaSiswa;
        detailModal.querySelector('#modal-nama-mapel').textContent = namaMapel;
        detailModal.querySelector('#modal-nilai-akhir').textContent = nilaiAkhir;
        detailModal.querySelector('#modal-deskripsi').textContent = deskripsi;
        detailModal.querySelector('#modal-rumus').textContent = rumus;

        const modalTabelBody = detailModal.querySelector('#modal-tabel-komponen');
        modalTabelBody.innerHTML = '';
        
        // Buat baris tabel untuk setiap komponen
        if (komponen.length > 0) {
            komponen.forEach(item => {
                let row = `<tr>
                                <td>${item.nama}</td>
                                <td>${item.jenis}</td>
                                <td class="text-center fw-bold">${item.nilai}</td>
                                <td class="text-center">${item.bobot}</td>
                                <td>- ${item.deskripsi_tp}</td>
                           </tr>`;
                modalTabelBody.innerHTML += row;
            });
        } else {
            let row = `<tr><td colspan="5" class="text-center text-muted p-3">Belum ada data penilaian sumatif untuk mata pelajaran ini.</td></tr>`;
            modalTabelBody.innerHTML = row;
        }
    });
}
</script>

<?php
if (isset($_SESSION['pesan'])) {
    $pesan_data = json_decode($_SESSION['pesan'], true);
    if ($pesan_data) {
        echo "<script>Swal.fire({icon: '".($pesan_data['icon'] ?? 'info')."', title: '".($pesan_data['title'] ?? 'Info')."', text: '".($pesan_data['text'] ?? '')."'});</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>