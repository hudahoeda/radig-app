<?php
include 'koneksi.php'; // Sesuaikan dengan file koneksi Anda

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_siswa = isset($_POST['id_siswa']) ? (int)$_POST['id_siswa'] : 0;
    $jenis_mutasi = isset($_POST['jenis_mutasi']) ? $_POST['jenis_mutasi'] : '';

    if ($id_siswa == 0 || $jenis_mutasi == '') {
        die("Data tidak lengkap.");
    }

    if ($jenis_mutasi == 'keluar') {
        $tanggal_keluar = $_POST['tanggal_keluar'];
        $kelas_ditinggalkan = $_POST['kelas_ditinggalkan'];
        $alasan = $_POST['alasan'];

        // Simpan ke database menggunakan prepared statement
        $stmt = mysqli_prepare($koneksi, "INSERT INTO mutasi_keluar (id_siswa, tanggal_keluar, kelas_ditinggalkan, alasan) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $id_siswa, $tanggal_keluar, $kelas_ditinggalkan, $alasan);
        
        if(mysqli_stmt_execute($stmt)){
            // Juga update status siswa di tabel utama
            $stmt_update = mysqli_prepare($koneksi, "UPDATE siswa SET status_siswa = 'Pindah' WHERE id_siswa = ?");
            mysqli_stmt_bind_param($stmt_update, "i", $id_siswa);
            mysqli_stmt_execute($stmt_update);
            
            // Arahkan ke halaman cetak
            header("Location: cetak_mutasi.php?id_siswa=$id_siswa&tipe=keluar");
            exit();
        } else {
            die("Gagal menyimpan data mutasi keluar.");
        }

    } elseif ($jenis_mutasi == 'masuk') {
        $tanggal_masuk = $_POST['tanggal_masuk'];
        $diterima_di_kelas = $_POST['diterima_di_kelas'];
        $sekolah_asal = $_POST['sekolah_asal'];
        $tahun_pelajaran = $_POST['tahun_pelajaran'];
        
        // Simpan data mutasi masuk
        $stmt = mysqli_prepare($koneksi, "INSERT INTO mutasi_masuk (id_siswa, tanggal_masuk, diterima_di_kelas, sekolah_asal, tahun_pelajaran) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $id_siswa, $tanggal_masuk, $diterima_di_kelas, $sekolah_asal, $tahun_pelajaran);

        if(mysqli_stmt_execute($stmt)) {
            // Update juga data di tabel siswa
             $stmt_update = mysqli_prepare($koneksi, "UPDATE siswa SET sekolah_asal = ?, diterima_tanggal = ? WHERE id_siswa = ?");
             mysqli_stmt_bind_param($stmt_update, "ssi", $sekolah_asal, $tanggal_masuk, $id_siswa);
             mysqli_stmt_execute($stmt_update);
            
            // Arahkan ke halaman cetak
            header("Location: cetak_mutasi.php?id_siswa=$id_siswa&tipe=masuk");
            exit();
        } else {
            die("Gagal menyimpan data mutasi masuk.");
        }
    }
}
?>