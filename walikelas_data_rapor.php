<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas atau Admin
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

// Ambil info jenjang sekolah
$q_sekolah = mysqli_query($koneksi, "SELECT jenjang FROM sekolah LIMIT 1");
$jenjang = mysqli_fetch_assoc($q_sekolah)['jenjang'] ?? 'SMP';

// Ambil data kelas yang diampu oleh Wali Kelas ini
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas, fase FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);
$id_kelas = $kelas['id_kelas'] ?? 0;
$fase_kelas = $kelas['fase'] ?? '';

// Ambil semua siswa di kelas ini (Termasuk riwayat dari tabel rapor jika melihat tahun lalu)
$q_siswa = $id_kelas ? mysqli_query($koneksi, "
    SELECT DISTINCT s.id_siswa, s.nama_lengkap
    FROM siswa s
    LEFT JOIN rapor r ON s.id_siswa = r.id_siswa
    WHERE (s.id_kelas = $id_kelas) OR (r.id_kelas = $id_kelas AND r.id_tahun_ajaran = $id_tahun_ajaran)
    ORDER BY s.nama_lengkap ASC
") : false;

// Pre-fetch data yang dibutuhkan untuk tampilan awal
// 1. Data Rapor (Absensi & Catatan yang sudah ada) - DITAMBAHKAN KEPUTUSAN AKHIR
$data_rapor = [];
if ($id_kelas) {
    $q_rapor = mysqli_query($koneksi, "SELECT id_rapor, id_siswa, sakit, izin, tanpa_keterangan, catatan_wali_kelas, keputusan_akhir, keterangan_keputusan FROM rapor WHERE id_kelas = $id_kelas AND semester = $semester_aktif AND id_tahun_ajaran = $id_tahun_ajaran");
    while ($r = mysqli_fetch_assoc($q_rapor)) {
        $data_rapor[$r['id_siswa']] = $r;
    }
}

// 2. Data Ekstrakurikuler Siswa untuk ditampilkan
$data_ekskul_siswa = [];
if ($id_kelas) {
    $query_ekskul = mysqli_prepare($koneksi, "
        SELECT 
            s.id_siswa, e.nama_ekskul,
            (SELECT CONCAT(k.jumlah_hadir, ' / ', k.total_pertemuan) 
             FROM ekskul_kehadiran k 
             WHERE k.id_peserta_ekskul = ep.id_peserta_ekskul AND k.semester = ?) as kehadiran,
            GROUP_CONCAT(CONCAT(t.deskripsi_tujuan, ':', p.nilai) SEPARATOR ';') as penilaian
        FROM siswa s
        JOIN ekskul_peserta ep ON s.id_siswa = ep.id_siswa
        JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
        LEFT JOIN ekskul_penilaian p ON ep.id_peserta_ekskul = p.id_peserta_ekskul
        LEFT JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul AND t.semester = ?
        WHERE s.id_kelas = ? AND e.id_tahun_ajaran = ?
        GROUP BY s.id_siswa, e.id_ekskul
    ");
    mysqli_stmt_bind_param($query_ekskul, "iiii", $semester_aktif, $semester_aktif, $id_kelas, $id_tahun_ajaran);
    mysqli_stmt_execute($query_ekskul);
    $result_ekskul = mysqli_stmt_get_result($query_ekskul);
    while ($ekskul = mysqli_fetch_assoc($result_ekskul)) {
        $data_ekskul_siswa[$ekskul['id_siswa']][] = $ekskul;
    }
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }

    .page-header h1 {
        font-weight: 700;
    }

    .accordion-button:not(.collapsed) {
        background-color: #e0f2f1;
        color: var(--secondary-color);
        box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .125);
    }

    .accordion-button:focus {
        box-shadow: 0 0 0 0.25rem rgba(38, 166, 154, 0.25);
    }

    .info-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        display: block;
    }

    .ekskul-summary-card {
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--secondary-color);
    }

    .guidance-card {
        background-color: var(--bs-info-bg-subtle);
        border-left: 5px solid var(--bs-info);
    }

    .guidance-card ul {
        padding-left: 1.2rem;
    }

    .guidance-card li {
        margin-bottom: 0.5rem;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Input Data Rapor</h1>
        <p class="lead mb-0 opacity-75">
            Kelas: <?php echo htmlspecialchars($kelas['nama_kelas'] ?? 'Anda tidak menjadi wali kelas di tahun ajaran aktif'); ?>
            (Fase <?php echo htmlspecialchars($fase_kelas); ?>)
        </p>
    </div>

    <?php if ($id_kelas && $q_siswa && mysqli_num_rows($q_siswa) > 0) : ?>
        <div class="d-flex justify-content-end mb-3 gap-2">
            <a href="walikelas_absensi_template.php" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i> Download Template Excel
            </a>
            <button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportAbsensi">
                <i class="bi bi-upload me-1"></i> Import Absensi & Catatan
            </button>
        </div>

        <!-- Modal Import -->
        <div class="modal fade" id="modalImportAbsensi" tabindex="-1" aria-labelledby="modalImportAbsensiLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="walikelas_absensi_import_aksi.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalImportAbsensiLabel">Import Absensi & Catatan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Upload file Excel (<code>.xlsx</code>) yang telah Anda isi menggunakan template yang diunduh.</p>
                            <div class="mb-3">
                                <label for="file_import" class="form-label">File Excel</label>
                                <input class="form-control" type="file" id="file_import" name="file_import" accept=".xlsx" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Import Data</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <form action="walikelas_aksi.php?aksi=simpan_data" method="POST">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="card guidance-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title text-info-emphasis"><i class="bi bi-info-circle-fill me-2"></i>Panduan Pengisian Catatan Wali Kelas</h5>
                            <?php if (strtoupper($fase_kelas) == 'A'): ?>
                                <p class="card-text">Untuk siswa <strong>Fase A</strong>, fokuskan catatan pada perkembangan <strong>kemampuan fondasi</strong> mereka. Contoh poin yang bisa disampaikan:</p>
                                <ul>
                                    <li><strong>Keterampilan Sosial & Bahasa:</strong> Bagaimana ananda berinteraksi dengan teman? Apakah sudah bisa mengomunikasikan kebutuhannya?</li>
                                    <li><strong>Kematangan Emosi:</strong> Bagaimana ananda mengelola emosi saat menghadapi kesulitan atau saat bermain bersama teman?</li>
                                    <li><strong>Kemandirian:</strong> Sejauh mana ananda sudah mandiri dalam merawat diri dan peralatannya di sekolah?</li>
                                    <li><strong>Pemaknaan Belajar:</strong> Apakah ananda menunjukkan antusiasme dan rasa ingin tahu dalam kegiatan belajar?</li>
                                    <li>Sertakan juga saran konkret untuk orang tua agar bisa berkolaborasi mendukung perkembangan anak.</li>
                                </ul>
                            <?php else: ?>
                                <p class="card-text">Untuk siswa <strong>Fase B, C, dan D</strong>, fokuskan catatan pada hal-hal berikut:</p>
                                <ul>
                                    <li><strong>Perkembangan Akademik Menonjol:</strong> Sebutkan kekuatan spesifik siswa pada beberapa mata pelajaran atau keterampilan yang paling menonjol.</li>
                                    <li><strong>Aspek yang Perlu Ditingkatkan:</strong> Berikan masukan konstruktif tentang area yang masih perlu dikembangkan, baik akademik maupun non-akademik.</li>
                                    <li><strong>Perkembangan Karakter (Profil Lulusan):</strong> Ceritakan perkembangan karakter siswa, misalnya dalam hal gotong royong, kemandirian, atau kreativitas selama pembelajaran.</li>
                                    <li><strong>Saran Tindak Lanjut:</strong> Berikan saran yang bisa dilakukan siswa dan orang tua untuk meningkatkan prestasi dan potensi di semester berikutnya.</li>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-people-fill me-2 text-secondary"></i>Daftar Siswa</h5>
                        <button type="button" class="btn btn-warning shadow-sm fw-bold" id="generate-all-btn">
                            <i class="bi bi-stars me-1"></i> Generate Semua Catatan Otomatis
                        </button>
                    </div>

                    <div class="accordion" id="accordionSiswa">
                        <?php $no = 1;
                        mysqli_data_seek($q_siswa, 0);
                        while ($siswa = mysqli_fetch_assoc($q_siswa)) :
                            $id_siswa = $siswa['id_siswa'];
                            $rapor_siswa = $data_rapor[$id_siswa] ?? null;
                            $ekskul_siswa = $data_ekskul_siswa[$id_siswa] ?? [];
                        ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-<?php echo $id_siswa; ?>">
                                    <button class="accordion-button <?php echo $no > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $id_siswa; ?>">
                                        <span class="badge bg-primary me-3"><?php echo $no; ?></span>
                                        <span class="fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></span>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo $id_siswa; ?>" class="accordion-collapse collapse <?php echo $no == 1 ? 'show' : ''; ?>" data-bs-parent="#accordionSiswa">
                                    <div class="accordion-body bg-light">
                                        <div class="row g-4">
                                            <div class="col-lg-6">
                                                <div class="mb-4">
                                                    <span class="info-label"><i class="bi bi-calendar-check me-2"></i>Absensi (Jumlah Hari)</span>
                                                    <div class="row g-3">
                                                        <div class="col">
                                                            <div class="input-group"><span class="input-group-text">Sakit</span><input type="number" min="0" name="absensi[<?php echo $id_siswa; ?>][sakit]" class="form-control" value="<?php echo $rapor_siswa['sakit'] ?? 0; ?>"></div>
                                                        </div>
                                                        <div class="col">
                                                            <div class="input-group"><span class="input-group-text">Izin</span><input type="number" min="0" name="absensi[<?php echo $id_siswa; ?>][izin]" class="form-control" value="<?php echo $rapor_siswa['izin'] ?? 0; ?>"></div>
                                                        </div>
                                                        <div class="col">
                                                            <div class="input-group"><span class="input-group-text">Alpha</span><input type="number" min="0" name="absensi[<?php echo $id_siswa; ?>][tanpa_keterangan]" class="form-control" value="<?php echo $rapor_siswa['tanpa_keterangan'] ?? 0; ?>"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-4">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="info-label mb-0"><i class="bi bi-chat-left-text me-2"></i>Catatan Wali Kelas</span>
                                                        <button type="button" class="btn btn-info btn-sm generate-note-btn"
                                                            data-siswa-id="<?php echo $id_siswa; ?>">
                                                            <i class="bi bi-magic me-1"></i> Buat Otomatis
                                                        </button>
                                                    </div>
                                                    <textarea name="catatan[<?php echo $id_siswa; ?>]"
                                                        id="catatan-<?php echo $id_siswa; ?>"
                                                        class="form-control"
                                                        rows="6"
                                                        placeholder="Klik tombol 'Buat Otomatis' atau isi manual..."><?php echo htmlspecialchars($rapor_siswa['catatan_wali_kelas'] ?? ''); ?></textarea>
                                                </div>

                                                <!-- FITUR NAIK KELAS / LULUS HANYA MUNCUL DI SEMESTER 2 -->
                                                <?php if ($semester_aktif == 2): ?>
                                                <div class="p-3 border rounded bg-white shadow-sm">
                                                    <span class="info-label text-primary"><i class="bi bi-award-fill me-2"></i>Keputusan Akhir Semester 2</span>
                                                    <div class="row g-3 mt-1">
                                                        <div class="col-md-6">
                                                            <label class="form-label small text-muted">Status</label>
                                                            <select name="keputusan[<?php echo $id_siswa; ?>]" class="form-select status-keputusan" data-siswa="<?php echo $id_siswa; ?>">
                                                                <option value="-" <?php echo (($rapor_siswa['keputusan_akhir'] ?? '-') == '-') ? 'selected' : ''; ?>>- Belum Ditentukan -</option>
                                                                
                                                                <?php 
                                                                // Deteksi otomatis Kelas Akhir berdasarkan Jenjang
                                                                $jenjang_upper = strtoupper($jenjang);
                                                                $is_kelas_akhir = false;
                                                                $nama_kelas_upper = strtoupper($kelas['nama_kelas']);

                                                                if ($jenjang_upper == 'SD' || $jenjang_upper == 'MI') {
                                                                    // Cari 'VI' tapi pastikan bukan 'VII' (7) atau 'VIII' (8)
                                                                    // Atau angka '6'
                                                                    if ((strpos($nama_kelas_upper, 'VI') !== false && strpos($nama_kelas_upper, 'VII') === false && strpos($nama_kelas_upper, 'VIII') === false) || strpos($nama_kelas_upper, '6') !== false) {
                                                                        $is_kelas_akhir = true;
                                                                    }
                                                                } elseif ($jenjang_upper == 'SMA' || $jenjang_upper == 'SMK' || $jenjang_upper == 'MA') {
                                                                    if (strpos($nama_kelas_upper, 'XII') !== false || strpos($nama_kelas_upper, '12') !== false) {
                                                                        $is_kelas_akhir = true;
                                                                    }
                                                                } else {
                                                                    // Default SMP / MTs
                                                                    if (strpos($nama_kelas_upper, 'IX') !== false || strpos($nama_kelas_upper, '9') !== false) {
                                                                        $is_kelas_akhir = true;
                                                                    }
                                                                }

                                                                if ($is_kelas_akhir):
                                                                ?>
                                                                    <option value="Lulus" <?php echo (($rapor_siswa['keputusan_akhir'] ?? '') == 'Lulus') ? 'selected' : ''; ?>>Lulus</option>
                                                                    <option value="Tidak Lulus" <?php echo (($rapor_siswa['keputusan_akhir'] ?? '') == 'Tidak Lulus') ? 'selected' : ''; ?>>Tidak Lulus</option>
                                                                <?php else: ?>
                                                                    <option value="Naik Kelas" <?php echo (($rapor_siswa['keputusan_akhir'] ?? '') == 'Naik Kelas') ? 'selected' : ''; ?>>Naik Kelas</option>
                                                                    <option value="Tinggal Kelas" <?php echo (($rapor_siswa['keputusan_akhir'] ?? '') == 'Tinggal Kelas') ? 'selected' : ''; ?>>Tinggal Kelas</option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 keterangan-container" id="keterangan-<?php echo $id_siswa; ?>" style="<?php echo (($rapor_siswa['keputusan_akhir'] ?? '') == 'Naik Kelas') ? '' : 'display: none;'; ?>">
                                                            <label class="form-label small text-muted">Tujuan Kelas</label>
                                                            <input type="text" name="keterangan_keputusan[<?php echo $id_siswa; ?>]" class="form-control" value="<?php echo htmlspecialchars($rapor_siswa['keterangan_keputusan'] ?? ''); ?>" placeholder="Contoh: VIII-A">
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <!-- SELESAI FITUR NAIK KELAS / LULUS -->

                                            </div>

                                            <div class="col-lg-6">
                                                <span class="info-label"><i class="bi bi-bicycle me-2"></i>Rangkuman Ekstrakurikuler</span>
                                                <?php if (!empty($ekskul_siswa)): ?>
                                                    <div class="d-flex flex-column" style="gap: 1rem;">
                                                        <?php foreach ($ekskul_siswa as $ekskul): ?>
                                                            <div class="p-3 rounded ekskul-summary-card">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($ekskul['nama_ekskul']); ?></h6>
                                                                    <span class="badge bg-secondary">Kehadiran: <?php echo htmlspecialchars($ekskul['kehadiran'] ?? 'N/A'); ?></span>
                                                                </div>
                                                                <div class="ps-2 small text-muted">
                                                                    <?php
                                                                    if (!empty($ekskul['penilaian'])) {
                                                                        $penilaian_list = explode(';', $ekskul['penilaian']);
                                                                        foreach ($penilaian_list as $item) {
                                                                            list($tujuan, $nilai) = array_pad(explode(':', $item, 2), 2, '');
                                                                            if (!empty($tujuan)) {
                                                                                echo '<div>' . htmlspecialchars($tujuan) . ': <strong>' . htmlspecialchars($nilai) . '</strong></div>';
                                                                            }
                                                                        }
                                                                    } else {
                                                                        echo "<em>Belum ada penilaian capaian.</em>";
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-light text-center">Siswa tidak terdaftar pada ekstrakurikuler apapun semester ini.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php $no++;
                        endwhile; ?>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-end">
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Semua Perubahan</button>
            </div>
        </form>
    <?php elseif (!$id_kelas): ?>
        <div class="card shadow-sm text-center py-5">
            <div class="card-body"><i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                <h3 class="mt-3">Akses Ditolak</h3>
                <p class="text-muted">Anda tidak terdaftar sebagai wali kelas pada tahun ajaran aktif ini.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm text-center py-5">
            <div class="card-body"><i class="bi bi-people-fill fs-1 text-muted"></i>
                <h3 class="mt-3">Kelas Kosong</h3>
                <p class="text-muted">Belum ada data siswa yang ditambahkan ke kelas ini.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function() {
        // Logika untuk menampilkan/menyembunyikan form Keterangan (Tujuan Kelas)
        $('.status-keputusan').on('change', function() {
            var siswaId = $(this).data('siswa');
            var status = $(this).val();
            if(status === 'Naik Kelas') {
                $('#keterangan-' + siswaId).slideDown();
            } else {
                $('#keterangan-' + siswaId).slideUp();
                // Opsional: bersihkan value jika ditutup
                // $('#keterangan-' + siswaId + ' input').val(''); 
            }
        });

        // Validasi input absensi
        $('input[name^="absensi"]').on('change', function(e) {
            e.preventDefault();

            let regex = /^(?:0|[1-9]\d*)(?:\.\d+)?$/;
            const val = $(this).val();
            if (val === '' || !regex.test(val)) {
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-tooltip">Harap masukkan angka valid untuk absensi.</div>');
                $(this).focus();
                return;
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        $('#accordionSiswa').on('hide.bs.collapse', function(e) {
            if ($(e.target).find('.is-invalid').length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Masih Ada Kesalahan',
                    text: 'Perbaiki input yang tidak valid sebelum menutup panel ini.',
                    confirmButtonText: 'Oke'
                });
            }
        }).on('show.bs.collapse', function(e) {
            const opened = $(this).find('.accordion-collapse.show');
            if (opened.length > 0 && opened.find('.is-invalid').length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Panel Sebelumnya Belum Valid',
                    text: 'Perbaiki data pada panel sebelumnya sebelum membuka siswa lain.',
                    confirmButtonText: 'Oke'
                });
            }
        });

        $('.generate-note-btn').on('click', function(e) {
            e.preventDefault();

            var button = $(this);
            var siswaId = button.data('siswa-id');

            // Ambil nilai absensi terbaru dari input form
            var sakit = $('input[name="absensi[' + siswaId + '][sakit]"]').val();
            var izin = $('input[name="absensi[' + siswaId + '][izin]"]').val();
            var alpha = $('input[name="absensi[' + siswaId + '][tanpa_keterangan]"]').val();

            var targetTextarea = $('#catatan-' + siswaId);

            // Simpan teks asli tombol & nonaktifkan
            var originalButtonText = button.html();
            button.html('<i class="spinner-border spinner-border-sm"></i> Memproses...').prop('disabled', true);

            $.ajax({
                url: 'ajax_generate_catatan.php',
                type: 'POST',
                data: {
                    id_siswa: siswaId,
                    sakit: sakit,
                    izin: izin,
                    alpha: alpha
                },
                success: function(response) {
                    // Masukkan hasil ke textarea
                    targetTextarea.val($('<div/>').html(response).text()); // Membersihkan tag HTML jika ada
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Catatan berhasil dibuat!',
                        showConfirmButton: false,
                        timer: 2000
                    });
                },
                error: function() {
                    Swal.fire('Error', 'Gagal membuat catatan. Silakan coba lagi.', 'error');
                },
                complete: function() {
                    // Kembalikan tombol ke keadaan semula
                    button.html(originalButtonText).prop('disabled', false);
                }
            });
        });

        // Fitur Generate Semua Catatan Otomatis
        $('#generate-all-btn').on('click', function(e) {
            e.preventDefault();

            var buttons = $('.generate-note-btn');
            if (buttons.length === 0) return;

            Swal.fire({
                title: 'Generate Semua Catatan?',
                text: "Fitur ini akan memproses catatan untuk " + buttons.length + " siswa satu per satu. Proses ini mungkin memakan waktu beberapa saat.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Generate Semua!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    var mainBtn = $('#generate-all-btn');
                    var originalMainText = mainBtn.html();
                    mainBtn.html('<i class="spinner-border spinner-border-sm me-1"></i> Sedang Memproses...').prop('disabled', true);

                    var index = 0;

                    function processNext() {
                        if (index >= buttons.length) {
                            mainBtn.html(originalMainText).prop('disabled', false);
                            Swal.fire('Selesai!', 'Semua catatan siswa berhasil di-generate secara otomatis.', 'success');
                            return;
                        }

                        var btn = $(buttons[index]);
                        var siswaId = btn.data('siswa-id');
                        var targetTextarea = $('#catatan-' + siswaId);

                        var sakit = $('input[name="absensi[' + siswaId + '][sakit]"]').val();
                        var izin = $('input[name="absensi[' + siswaId + '][izin]"]').val();
                        var alpha = $('input[name="absensi[' + siswaId + '][tanpa_keterangan]"]').val();

                        var originalBtnHtml = btn.html();
                        btn.html('<i class="spinner-border spinner-border-sm"></i>').prop('disabled', true);

                        // Tampilkan progress di tombol utama
                        mainBtn.html('<i class="spinner-border spinner-border-sm me-1"></i> Memproses ' + (index + 1) + ' dari ' + buttons.length + '...');

                        $.ajax({
                            url: 'ajax_generate_catatan.php',
                            type: 'POST',
                            data: { id_siswa: siswaId, sakit: sakit, izin: izin, alpha: alpha },
                            success: function(response) {
                                targetTextarea.val($('<div/>').html(response).text());
                            },
                            complete: function() {
                                btn.html(originalBtnHtml).prop('disabled', false);
                                index++;
                                processNext(); // Panggil secara rekursif untuk antrean berikutnya
                            }
                        });
                    }

                    processNext();
                }
            });
        });
    });
</script>

<?php
if (isset($_SESSION['pesan'])) {
    $pesan_raw = $_SESSION['pesan'];
    $pesan_data = json_decode($pesan_raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($pesan_data)) {
        echo "<script>Swal.fire({icon: '".addslashes($pesan_data['icon'] ?? 'info')."', title: '".addslashes($pesan_data['title'] ?? 'Info')."', text: '".addslashes($pesan_data['text'] ?? '')."'});</script>";
    } else {
        echo "<script>Swal.fire('Informasi', '" . addslashes($pesan_raw) . "', 'info');</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>