<?php
session_start();
include 'koneksi.php';

// Pastikan ada user yang login
if (!isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: Tidak ada sesi login.']);
    exit;
}

// Ambil data user yang sedang login
$current_user_id = 0;
$current_user_role = $_SESSION['role'];
if ($current_user_role == 'guru') {
    $current_user_id = (int)$_SESSION['id_guru'];
} elseif ($current_user_role == 'siswa') {
    $current_user_id = (int)$_SESSION['id_siswa'];
}

// Router untuk menentukan aksi berdasarkan parameter 'action'
$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'get_contacts':
        get_contacts($koneksi, $current_user_id, $current_user_role);
        break;
    case 'get_messages':
        get_messages($koneksi, $current_user_id, $current_user_role);
        break;
    case 'send_message':
        send_message($koneksi, $current_user_id, $current_user_role);
        break;
    case 'check_new':
        check_new_messages($koneksi, $current_user_id, $current_user_role);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid.']);
}

mysqli_close($koneksi);

// Fungsi untuk membuat ID percakapan yang konsisten
function create_conversation_id($user1_id, $user1_role, $user2_id, $user2_role) {
    $part1 = $user1_role . '_' . $user1_id;
    $part2 = $user2_role . '_' . $user2_id;
    // Urutkan berdasarkan abjad agar ID selalu sama
    if (strcmp($part1, $part2) > 0) {
        return $part2 . '-' . $part1;
    }
    return $part1 . '-' . $part2;
}

// Fungsi untuk mengambil daftar kontak
function get_contacts($koneksi, $user_id, $user_role) {
    $contacts = [];
    if ($user_role == 'guru') {
        // Guru mengambil daftar siswa bimbingannya
        $query = "
            SELECT 
                s.id_siswa as contact_id, 
                'siswa' as contact_role, 
                s.nama_lengkap as contact_name, 
                s.foto_siswa as contact_photo,
                (SELECT COUNT(*) FROM chat_messages WHERE id_pengirim = s.id_siswa AND role_pengirim = 'siswa' AND id_penerima = ? AND role_penerima = 'guru' AND status_baca = 0) as unread_count
            FROM siswa s
            WHERE s.id_guru_wali = ?
            ORDER BY s.nama_lengkap ASC
        ";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
    } else { // Siswa
        // Siswa hanya mengambil Guru Walinya
        $query = "
            SELECT 
                g.id_guru as contact_id, 
                'guru' as contact_role, 
                g.nama_guru as contact_name,
                g.foto_guru as contact_photo,
                (SELECT COUNT(*) FROM chat_messages WHERE id_pengirim = g.id_guru AND role_pengirim = 'guru' AND id_penerima = ? AND role_penerima = 'siswa' AND status_baca = 0) as unread_count
            FROM siswa s
            JOIN guru g ON s.id_guru_wali = g.id_guru
            WHERE s.id_siswa = ?
        ";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        if ($user_role == 'guru') {
             $row['contact_photo'] = !empty($row['contact_photo']) ? 'uploads/foto_siswa/' . $row['contact_photo'] : 'uploads/guruc.png';
        } else {
             $row['contact_photo'] = !empty($row['contact_photo']) ? 'uploads/guru_photos/' . $row['contact_photo'] : 'uploads/guruc.png';
        }
        $contacts[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'success', 'contacts' => $contacts]);
}

// Fungsi untuk mengambil pesan dari sebuah percakapan
function get_messages($koneksi, $user_id, $user_role) {
    $contact_id = (int)($_POST['contact_id'] ?? 0);
    $contact_role = $_POST['contact_role'] ?? '';

    if (empty($contact_id) || empty($contact_role)) {
        echo json_encode(['status' => 'error', 'message' => 'ID Kontak tidak valid.']);
        return;
    }

    $conversation_id = create_conversation_id($user_id, $user_role, $contact_id, $contact_role);

    // Ambil pesan
    $query_msg = "SELECT * FROM chat_messages WHERE percakapan_id = ? ORDER BY waktu_kirim ASC";
    $stmt_msg = mysqli_prepare($koneksi, $query_msg);
    mysqli_stmt_bind_param($stmt_msg, "s", $conversation_id);
    mysqli_stmt_execute($stmt_msg);
    $result = mysqli_stmt_get_result($stmt_msg);
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_msg);

    // Tandai pesan sebagai sudah dibaca
    $query_update = "UPDATE chat_messages SET status_baca = 1 WHERE percakapan_id = ? AND id_penerima = ? AND role_penerima = ?";
    $stmt_update = mysqli_prepare($koneksi, $query_update);
    mysqli_stmt_bind_param($stmt_update, "sis", $conversation_id, $user_id, $user_role);
    mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);

    echo json_encode(['status' => 'success', 'messages' => $messages]);
}

// Fungsi untuk mengirim pesan
function send_message($koneksi, $user_id, $user_role) {
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $receiver_role = $_POST['receiver_role'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if (empty($receiver_id) || empty($receiver_role) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
        return;
    }

    $conversation_id = create_conversation_id($user_id, $user_role, $receiver_id, $receiver_role);
    
    $query = "INSERT INTO chat_messages (percakapan_id, id_pengirim, role_pengirim, id_penerima, role_penerima, isi_pesan) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($koneksi, $query);
    
    // --- [PERBAIKAN] ---
    // Tipe data yang benar adalah "sisiss" (string, integer, string, integer, string, string)
    mysqli_stmt_bind_param($stmt, "sisiss", $conversation_id, $user_id, $user_role, $receiver_id, $receiver_role, $message);
    // --- [SELESAI PERBAIKAN] ---
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Pesan terkirim.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim pesan ke database.']);
    }
    mysqli_stmt_close($stmt);
}

// Fungsi untuk mengecek pesan baru (untuk notifikasi)
function check_new_messages($koneksi, $user_id, $user_role) {
    $query = "SELECT COUNT(id_pesan) as total_unread FROM chat_messages WHERE id_penerima = ? AND role_penerima = ? AND status_baca = 0";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $user_role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    echo json_encode(['status' => 'success', 'unread_count' => $data['total_unread']]);
}
?>

