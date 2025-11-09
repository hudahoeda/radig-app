</main>
<?php
// --- AMBIL NAMA SEKOLAH DARI DATABASE ---
$nama_sekolah_footer = 'Aplikasi Rapor Digital Anda'; // Default name
if (isset($koneksi)) { // Check if $koneksi is available
    $query_sekolah_footer = mysqli_query($koneksi, "SELECT nama_sekolah FROM sekolah WHERE id_sekolah = 1 LIMIT 1");
    if ($query_sekolah_footer && mysqli_num_rows($query_sekolah_footer) > 0) {
        $data_sekolah_footer = mysqli_fetch_assoc($query_sekolah_footer);
        $nama_sekolah_footer = $data_sekolah_footer['nama_sekolah'];
    }
}
?>
        <footer class="app-footer">
            <div class="d-flex justify-content-between align-items-center flex-column flex-sm-row">
                <span class="text-muted text-center text-sm-start mb-2 mb-sm-0">
                    &copy; <?php echo date("Y"); ?> Aplikasi Rapor Digital - <?php echo htmlspecialchars($nama_sekolah_footer); ?>
                    <!-- [BARU] Menampilkan Versi Aplikasi dari koneksi.php -->
                    <?php if(isset($APP_VERSION)) echo '<span class="badge bg-secondary ms-1">' . htmlspecialchars($APP_VERSION) . '</span>'; ?>
                </span>
                <div class="text-center text-sm-end">
                    <a href="https://multischool.sch.id/index.php" class="ms-sm-3" target="_blank">Web Sekolah</a>
                    <a href="https://multischool.sch.id" class="ms-3" target="_blank">Portal Digital</a>
                </div>
            </div>
        </footer>

    </div>
</div>

<script>
$(document).ready(function () {
    // --- PERBAIKAN: Target kedua tombol toggle ---
    $('#sidebarCollapse, #sidebarCollapseDesktop').on('click', function () {
        $('#sidebar').toggleClass('active');
        // Optional: Save state
        // localStorage.setItem('sidebarState', $('#sidebar').hasClass('active') ? 'closed' : 'open');
    });
    // --- AKHIR PERBAIKAN ---

     // Optional: Restore sidebar state on page load
    // if (localStorage.getItem('sidebarState') === 'closed') {
    //     $('#sidebar').addClass('active');
    // }

    // Inisialisasi tooltip Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php
// Kode notifikasi SweetAlert
if (isset($_SESSION['pesan'])) {
    echo "<script>
        if (typeof Swal !== 'undefined') {
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    let config = " . $_SESSION['pesan'] . ";
                    Swal.fire(config);
                } catch (e) {
                    console.error('Error parsing SweetAlert config:', e);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi masalah saat menampilkan notifikasi.' });
                }
            });
        } else { console.error('SweetAlert2 is not loaded.'); }
    </script>";
    unset($_SESSION['pesan']);
}
if (isset($_SESSION['pesan_error'])) {
     echo "<script>
        if (typeof Swal !== 'undefined') {
             document.addEventListener('DOMContentLoaded', function() {
                 try {
                     let config = " . $_SESSION['pesan_error'] . ";
                     Swal.fire(config);
                 } catch (e) {
                     console.error('Error parsing SweetAlert error config:', e);
                     Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan.' });
                 }
             });
         } else { console.error('SweetAlert2 is not loaded.'); }
     </script>";
    unset($_SESSION['pesan_error']);
}
// Close DB connection if needed
// if (isset($koneksi) && $koneksi instanceof mysqli && $koneksi->ping()) {
//    mysqli_close($koneksi);
// }
?>

</body>
</html>