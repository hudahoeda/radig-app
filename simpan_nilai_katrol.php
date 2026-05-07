<?php
session_start();
include 'koneksi.php';

// Cek akses guru
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_siswa = (int)$_POST['id_siswa'];
    $id_mapel = (int)$_POST['id_mapel'];
    $id_kelas = (int)$_POST['id_kelas'];
    $nilai_katrol = $_POST['nilai_katrol']; // Bisa angka atau string kosong

    // Ambil tahun ajaran aktif
    $q_ta = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif'");
    $semester = mysqli_fetch_assoc($q_ta)['nilai_pengaturan'];
    
    $q_thn = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran = mysqli_fetch_assoc($q_thn)['id_tahun_ajaran'];

    // 1. Cek apakah Siswa sudah punya ID Rapor untuk semester ini?
    $cek_rapor = mysqli_query($koneksi, "SELECT id_rapor FROM rapor WHERE id_siswa = $id_siswa AND id_kelas = $id_kelas AND id_tahun_ajaran = $id_tahun_ajaran AND semester = $semester");
    
    if (mysqli_num_rows($cek_rapor) > 0) {
        $d_rapor = mysqli_fetch_assoc($cek_rapor);
        $id_rapor = $d_rapor['id_rapor'];
    } else {
        // Jika belum ada rapor, buatkan draft rapor baru otomatis
        $ins_rapor = mysqli_query($koneksi, "INSERT INTO rapor (id_siswa, id_kelas, id_tahun_ajaran, semester, status) VALUES ($id_siswa, $id_kelas, $id_tahun_ajaran, $semester, 'Draft')");
        if ($ins_rapor) {
            $id_rapor = mysqli_insert_id($koneksi);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal membuat data rapor awal']);
            exit;
        }
    }

    // 2. Simpan Nilai Katrol ke rapor_detail_akademik
    // Cek dulu apakah sudah ada record mapel ini di rapor detail
    $cek_detail = mysqli_query($koneksi, "SELECT id_rapor_detail FROM rapor_detail_akademik WHERE id_rapor = $id_rapor AND id_mapel = $id_mapel");

    // Siapkan nilai untuk database (NULL jika kosong, Angka jika diisi)
    $val_db = ($nilai_katrol === '' || $nilai_katrol === null) ? "NULL" : (int)$nilai_katrol;

    if (mysqli_num_rows($cek_detail) > 0) {
        // Update
        $upd = mysqli_query($koneksi, "UPDATE rapor_detail_akademik SET nilai_katrol = $val_db WHERE id_rapor = $id_rapor AND id_mapel = $id_mapel");
    } else {
        // Insert baru (jika belum ada nilai akhir sama sekali, kita buat row baru)
        $upd = mysqli_query($koneksi, "INSERT INTO rapor_detail_akademik (id_rapor, id_mapel, nilai_katrol) VALUES ($id_rapor, $id_mapel, $val_db)");
    }

    if ($upd) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
    }
}
?>