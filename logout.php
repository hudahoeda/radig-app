<?php
// logout.php
session_start();
// Hapus semua variabel session
session_unset();
// Hancurkan sesi
session_destroy();
// Alihkan ke halaman login
header("location:index.php?pesan=logout");
?>