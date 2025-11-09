<?php
session_start();
include 'koneksi.php';

// Ambil data yang dikirim dari form modal
$mapping_data = $_POST['mapping'] ?? null;
$id_ta_terpilih = $_POST['id_ta_terpilih'] ?? null;
$id_ta_redirect = $id_ta_terpilih ? "id_ta=" . $id_ta_terpilih : ""; // Untuk redirect

// URL dasar untuk redirect
$redirect_url = "admin_penetapan_guru_wali.php?" . $id_ta_redirect;

// Pastikan ada data yang diproses dan tahun ajaran dipilih
if ($mapping_data === null || $id_ta_terpilih === null) {
    $error_msg = urlencode('Gagal! Tidak ada data yang dikirim atau tahun ajaran tidak terdeteksi.');
    header("Location: " . $redirect_url . "&paste_error=1&msg=" . $error_msg);
    exit;
}

// Mulai transaksi database
mysqli_begin_transaction($koneksi);

try {
    // Siapkan query UPDATE.
    // Kita JOIN dengan tabel 'kelas' untuk memastikan kita HANYA mengupdate siswa
    // yang terdaftar di tahun ajaran yang sedang dipilih.
    $query = "
        UPDATE siswa s
        JOIN kelas k ON s.id_kelas = k.id_kelas
        SET s.id_guru_wali = ?
        WHERE s.nisn = ? AND k.id_tahun_ajaran = ?
    ";
    
    $stmt = mysqli_prepare($koneksi, $query);
    
    if (!$stmt) {
        throw new Exception("Gagal menyiapkan statement query: " . mysqli_error($koneksi));
    }

    $processed_count = 0;
    $total_data = count($mapping_data);

    foreach ($mapping_data as $data) {
        $id_guru = $data['id_guru'];
        $nisn = $data['nisn'];

        // Bind parameter ke statement
        mysqli_stmt_bind_param($stmt, "isi", $id_guru, $nisn, $id_ta_terpilih);
        
        // Eksekusi statement
        if (!mysqli_stmt_execute($stmt)) {
            // Jika satu saja gagal, lemparkan error
            throw new Exception("Gagal mengupdate NISN: " . $nisn . ". Error: " . mysqli_stmt_error($stmt));
        }

        // Hitung baris yang terpengaruh (affected)
        // Jika nisn tidak ada di tahun ajaran itu, affected_rows akan 0, tapi itu bukan error
        $processed_count += mysqli_stmt_affected_rows($stmt);
    }

    // Jika semua berhasil, commit transaksi
    mysqli_commit($koneksi);

    // Tambahkan parameter sukses ke URL redirect
    $redirect_url .= "&paste_success=1&count=" . $processed_count . "&total=" . $total_data;


} catch (Exception $e) {
    // Jika terjadi error, rollback semua perubahan
    mysqli_rollback($koneksi);

    // Tambahkan parameter error ke URL redirect
    $error_msg = urlencode("Update Gagal Total! " . $e->getMessage());
    $redirect_url .= "&paste_error=1&msg=" . $error_msg;

} finally {
    // Selalu tutup statement jika sudah dibuat
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
}

// Tutup koneksi
mysqli_close($koneksi);

// Redirect kembali ke halaman admin dengan parameter yang sesuai
header("Location: " . $redirect_url);
exit;

?>

