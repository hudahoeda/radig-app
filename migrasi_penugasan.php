<?php
include 'koneksi.php'; // Sesuaikan jika path koneksi berbeda
session_start();

// Keamanan sederhana: Hanya admin yang bisa menjalankan ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak. Anda harus login sebagai Admin.");
}

echo "<h1>Proses Migrasi Data Penugasan Guru</h1>";
echo "<p>Script ini akan mengisi data kelas pada penugasan guru yang lama. <strong>Jalankan script ini HANYA SEKALI.</strong></p>";

// 1. Ambil tahun ajaran aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

if ($id_tahun_ajaran_aktif == 0) {
    die("<strong>Error:</strong> Tidak ada tahun ajaran aktif yang ditemukan. Proses dibatalkan.");
}
echo "<p>Tahun Ajaran Aktif ID: <strong>$id_tahun_ajaran_aktif</strong></p><hr>";

// 2. Ambil semua kelas di tahun ajaran aktif
$query_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif");
$daftar_kelas_aktif = mysqli_fetch_all($query_kelas, MYSQLI_ASSOC);

if (empty($daftar_kelas_aktif)) {
    die("<strong>Error:</strong> Tidak ada data kelas di tahun ajaran aktif. Proses dibatalkan.");
}

// 3. Ambil data penugasan lama (yang kolom id_kelas nya masih NULL)
$query_lama = mysqli_query($koneksi, "SELECT id_guru_mengajar, id_guru, id_mapel FROM guru_mengajar WHERE id_kelas IS NULL AND id_tahun_ajaran = $id_tahun_ajaran_aktif");

$total_migrasi = 0;
if (mysqli_num_rows($query_lama) == 0) {
    echo "<h3>Tidak ada data lama yang perlu dimigrasi. Selesai!</h3>";
} else {
    echo "<h3>Memulai migrasi...</h3>";
    
    // Siapkan statement untuk insert data baru
    $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO guru_mengajar (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");

    while ($data_lama = mysqli_fetch_assoc($query_lama)) {
        $id_guru = $data_lama['id_guru'];
        $id_mapel = $data_lama['id_mapel'];
        $id_guru_mengajar_lama = $data_lama['id_guru_mengajar'];

        echo "Memproses penugasan untuk Guru ID: $id_guru, Mapel ID: $id_mapel...<br>";
        
        // Untuk setiap data lama, duplikasi untuk setiap kelas yang ada
        foreach ($daftar_kelas_aktif as $kelas) {
            $id_kelas_baru = $kelas['id_kelas'];

            // Cek dulu apakah data duplikat sudah ada
            $q_cek = "SELECT id_guru_mengajar FROM guru_mengajar WHERE id_guru=$id_guru AND id_mapel=$id_mapel AND id_kelas=$id_kelas_baru AND id_tahun_ajaran=$id_tahun_ajaran_aktif";
            $res_cek = mysqli_query($koneksi, $q_cek);
            
            if (mysqli_num_rows($res_cek) == 0) {
                 // Bind & execute
                mysqli_stmt_bind_param($stmt_insert, "iiii", $id_guru, $id_mapel, $id_kelas_baru, $id_tahun_ajaran_aktif);
                if(mysqli_stmt_execute($stmt_insert)) {
                    echo "<span style='color:green;'>&nbsp;&nbsp;&nbsp;-> Berhasil ditugaskan ke kelas " . $kelas['nama_kelas'] . "</span><br>";
                    $total_migrasi++;
                } else {
                    echo "<span style='color:red;'>&nbsp;&nbsp;&nbsp;-> Gagal ditugaskan ke kelas " . $kelas['nama_kelas'] . "</span><br>";
                }
            } else {
                 echo "<span style='color:orange;'>&nbsp;&nbsp;&nbsp;-> Penugasan ke kelas " . $kelas['nama_kelas'] . " sudah ada, dilewati.</span><br>";
            }
        }
        
        // Hapus data lama yang sudah diproses
        mysqli_query($koneksi, "DELETE FROM guru_mengajar WHERE id_guru_mengajar = $id_guru_mengajar_lama");
        echo "Data lama (ID: $id_guru_mengajar_lama) telah dihapus.<br><br>";
    }
    
    echo "<hr><h3>Migrasi Selesai!</h3>";
    echo "<p>Total <strong>$total_migrasi</strong> penugasan baru telah dibuat berdasarkan data lama.</p>";
}

echo "<p><strong>LANGKAH SELANJUTNYA:</strong> Anda sekarang bisa menjalankan kueri SQL di Langkah 4 untuk finalisasi struktur tabel.</p>";

?>